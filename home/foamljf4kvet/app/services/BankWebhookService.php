<?php
declare(strict_types=1);

/**
 * BankWebhookService — replaces the inline logic in public_html/webhook/bank.php.
 * Token check + JSON parsing remain in the thin shim. This service handles tx ingestion
 * and dispatches retail order/topup fulfillment via RetailFulfillmentService.
 *
 * Idempotency:
 *   Layer 1: bank_transactions.reference UNIQUE — INSERT duplicate throws → marked duplicate, no-op.
 *   Layer 2: RetailFulfillmentService uses atomic UPDATE WHERE status=0.
 *
 * Amount mismatch (amount < price/total): we do NOT fulfill; we enqueue admin review.
 * We never log raw description verbatim if it could carry sensitive data — we only log the
 * matched mark code (Nxxxxxxx / Txxxxxxx) and an integer amount.
 */
final class BankWebhookService {

    public function process(array $payload): array {
        $txs = isset($payload['data'])
            ? (array_is_list($payload['data']) ? $payload['data'] : [$payload['data']])
            : [$payload];

        $matched = 0;
        $processed = [];
        $pdo = db();

        foreach ($txs as $tx) {
            if (!is_array($tx)) continue;
            $entry = $this->processOne($pdo, $tx);
            if (!empty($entry['matched'])) $matched++;
            $processed[] = $entry;
        }

        return ['matched' => $matched, 'processed' => $processed];
    }

    private const OVERPAY_RATIO = 3;

    private function processOne($pdo, array $tx): array {
        $ref = (string)($tx['reference'] ?? $tx['tid'] ?? $tx['transaction_id'] ?? '');
        $desc = strtoupper((string)($tx['description'] ?? $tx['content'] ?? ''));
        $amount = (int)($tx['amount'] ?? $tx['creditAmount'] ?? 0);
        $datetime = (string)($tx['transaction_datetime'] ?? 'now');

        if ($ref === '') {
            $ref = hash('sha256', $desc . $amount . $datetime);
        }

        if ($amount <= 0) {
            app_log('BankWebhook zero/negative amount ref=' . $ref . ' amount=' . $amount, 'WARN');
            return ['ref' => $ref, 'skipped' => true, 'reason' => 'invalid_amount'];
        }

        // Layer 1: UNIQUE reference idempotency.
        try {
            $pdo->prepare('INSERT INTO bank_transactions (reference,description,amount,transaction_datetime,account_number,bank_name,counter_account_name,counter_account_number) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([
                    $ref,
                    $desc,
                    $amount,
                    date('Y-m-d H:i:s', strtotime($datetime) ?: time()),
                    (string)($tx['bank_sub_acc_id'] ?? $tx['account_number'] ?? ''),
                    (string)($tx['bankName'] ?? $tx['bank_name'] ?? ''),
                    (string)($tx['counterAccountName'] ?? ''),
                    (string)($tx['counterAccountNumber'] ?? ''),
                ]);
        } catch (Throwable $e) {
            return ['ref' => $ref, 'duplicate' => true];
        }

        // Mark code extraction. Topup (Txxxxxxx) takes priority over order (Nxxxxxxx)
        // because topups also start with 'T' nowhere ambiguous; pattern is exclusive.
        $done = false;
        $kind = null;
        $code = null;
        $reason = null;

        if (preg_match('/\bT[A-Z0-9]{7}\b/', $desc, $m)) {
            $code = $m[0];
            $kind = 'topup';
            $st = $pdo->prepare('SELECT tid, price FROM topup_order WHERE status=0 AND tid=? LIMIT 1');
            $st->execute([$code]);
            $o = $st->fetch();
            if ($o) {
                $expected = (int)$o['price'];
                if ($amount >= $expected) {
                    if ($expected > 0 && $amount >= $expected * self::OVERPAY_RATIO) {
                        $this->enqueueOverpay($code, $amount, $expected, 'topup');
                    }
                    try {
                        $res = (new RetailFulfillmentService())->fulfillPaidTopup($code);
                        $done = !empty($res['success']);
                        $reason = $res['reason'] ?? null;
                    } catch (Throwable $e) {
                        app_log('BankWebhook topup fulfill ' . $code . ' ' . $e->getMessage(), 'ERROR');
                        $reason = 'fulfill_exception';
                    }
                } else {
                    $reason = 'amount_mismatch';
                    $this->enqueueMismatch($code, $amount, $expected, 'topup');
                }
            } else {
                $reason = 'no_pending_topup';
            }
        }

        if (!$done && preg_match('/\bN[A-Z0-9]{7}\b/', $desc, $m)) {
            $code = $m[0];
            $kind = 'order';
            $st = $pdo->prepare('SELECT order_id, total FROM `order` WHERE status=0 AND order_id=? LIMIT 1');
            $st->execute([$code]);
            $o = $st->fetch();
            if ($o) {
                $expected = (int)$o['total'];
                if ($amount >= $expected) {
                    if ($expected > 0 && $amount >= $expected * self::OVERPAY_RATIO) {
                        $this->enqueueOverpay($code, $amount, $expected, 'order');
                    }
                    try {
                        $res = (new RetailFulfillmentService())->fulfillPaidOrder($code);
                        $done = !empty($res['success']);
                        $reason = $res['reason'] ?? null;
                    } catch (Throwable $e) {
                        app_log('BankWebhook order fulfill ' . $code . ' ' . $e->getMessage(), 'ERROR');
                        $reason = 'fulfill_exception';
                    }
                } else {
                    $reason = 'amount_mismatch';
                    $this->enqueueMismatch($code, $amount, $expected, 'order');
                }
            } else {
                $reason = 'no_pending_order';
            }
        }

        if ($done) {
            try {
                $pdo->prepare('UPDATE bank_transactions SET `match`=1 WHERE reference=?')->execute([$ref]);
            } catch (Throwable $e) {
                app_log('BankWebhook mark match fail ' . $ref . ' ' . $e->getMessage(), 'ERROR');
            }
        }

        $entry = ['ref' => $ref, 'matched' => $done];
        if ($code !== null) $entry['code'] = $code;
        if ($kind !== null) $entry['kind'] = $kind;
        if ($reason !== null && !$done) $entry['reason'] = $reason;
        return $entry;
    }

    private function enqueueMismatch(string $code, int $amount, int $expected, string $kind): void {
        try {
            $summary = sprintf('amount_mismatch %s amount=%d expected_min=%d kind=%s', $code, $amount, $expected, $kind);
            (new AdminFailedOrderQueue())->enqueue(
                AdminFailedOrderQueue::KIND_AMOUNT_MISMATCH,
                $code,
                $summary,
                null
            );
        } catch (Throwable $e) {
            app_log('BankWebhook mismatch enqueue fail ' . $code . ' ' . $e->getMessage(), 'ERROR');
        }
    }

    private function enqueueOverpay(string $code, int $amount, int $expected, string $kind): void {
        try {
            $summary = sprintf('overpayment %s amount=%d expected=%d ratio=%.1fx kind=%s — fulfilled but flagged for review',
                $code, $amount, $expected, $amount / max(1, $expected), $kind);
            (new AdminFailedOrderQueue())->enqueue(
                AdminFailedOrderQueue::KIND_AMOUNT_MISMATCH,
                'OVERPAY-' . $code,
                $summary,
                null
            );
        } catch (Throwable $e) {
            app_log('BankWebhook overpay enqueue fail ' . $code . ' ' . $e->getMessage(), 'ERROR');
        }
    }
}
