<?php
declare(strict_types=1);

/**
 * RetailFulfillmentService — single owner of B2C order/topup fulfillment after a paid bank tx.
 *
 * Flow (fulfillPaidOrder):
 *   1. SELECT order WHERE order_id=? AND status=0 → atomically UPDATE status=2, paid_at=NOW().
 *   2. Call LegacyProviderClient::createEsim(packCode, orderId).
 *   3. On success: UPDATE order muasim=1, orderNo, transactionId. Try EsimService::getByOrder() (saves esimlist + emails).
 *   4. On provider failure: order stays paid (status=2); enqueue AdminFailedOrderQueue::KIND_PROVIDER_ERROR.
 *   5. Email failure post-success: order remains fulfilled, emailsent stays 0 for retry; we enqueue KIND_EMAIL_ERROR.
 *
 * Idempotent: status=0 guard + atomic UPDATE WHERE status=0 ensures double-call safety.
 *
 * Test mode: LegacyProviderClient short-circuits HTTP. We still write muasim=1 with TEST-* refs so we
 * can verify the wiring end-to-end without hitting upstream.
 */
final class RetailFulfillmentService {

    public function fulfillPaidOrder(string $orderId): array {
        $pdo = db();
        $st = $pdo->prepare('SELECT order_id, pack_code, status, muasim FROM `order` WHERE order_id=? LIMIT 1');
        $st->execute([$orderId]);
        $o = $st->fetch();
        if (!$o) {
            app_log('RetailFulfill order not found ' . $orderId, 'WARN');
            return ['success' => false, 'reason' => 'not_found'];
        }
        if ((int)$o['status'] !== 0) {
            // already paid or expired — idempotent no-op.
            return ['success' => true, 'reason' => 'already_processed', 'status' => (int)$o['status'], 'muasim' => (int)$o['muasim']];
        }

        // Atomic transition pending(0) → paid(2). If another worker already flipped it, bail.
        $upd = $pdo->prepare('UPDATE `order` SET status=2, paid_at=NOW(), updated_at=NOW() WHERE order_id=? AND status=0');
        $upd->execute([$orderId]);
        if ($upd->rowCount() === 0) {
            return ['success' => true, 'reason' => 'race_lost'];
        }

        $packCode = (string)$o['pack_code'];
        try {
            $resp = (new LegacyProviderClient())->createEsim($packCode, $orderId);
        } catch (Throwable $e) {
            $this->enqueueAdmin(AdminFailedOrderQueue::KIND_PROVIDER_ERROR, $orderId, 'createEsim threw: ' . $e->getMessage(), null);
            app_log('RetailFulfill createEsim threw ' . $orderId . ' ' . $e->getMessage(), 'ERROR');
            return ['success' => false, 'reason' => 'provider_exception'];
        }

        if (empty($resp['success'])) {
            $summary = trim((string)($resp['error_code'] ?? '') . ' ' . (string)($resp['error_message'] ?? ''));
            if ($summary === '') $summary = 'provider createEsim failed';
            $this->enqueueAdmin(AdminFailedOrderQueue::KIND_PROVIDER_ERROR, $orderId, $summary, (string)($resp['raw_summary'] ?? null));
            app_log('RetailFulfill provider fail ' . $orderId . ' ' . $summary, 'ERROR');
            return ['success' => false, 'reason' => 'provider_error'];
        }

        // Success → write provider refs.
        $obj = $resp['_raw']['obj'] ?? [];
        $providerRef = $resp['provider_ref'] ?? ($obj['orderNo'] ?? null);
        $tid = (string)($obj['transactionId'] ?? $orderId);
        try {
            $pdo->prepare('UPDATE `order` SET muasim=1, orderNo=?, transactionId=?, updated_at=NOW() WHERE order_id=?')
                ->execute([$providerRef, $tid, $orderId]);
        } catch (Throwable $e) {
            app_log('RetailFulfill order ref update fail ' . $orderId . ' ' . $e->getMessage(), 'ERROR');
        }

        // Try to immediately fetch esimlist + email (best effort; cron will retry otherwise).
        try {
            (new EsimService())->getByOrder($orderId);
        } catch (Throwable $e) {
            $this->enqueueAdmin(AdminFailedOrderQueue::KIND_EMAIL_ERROR, $orderId, 'post-buy esim/email: ' . $e->getMessage(), null);
            app_log('RetailFulfill post-buy esim/email ' . $orderId . ' ' . $e->getMessage(), 'INFO');
        }

        return ['success' => true, 'reason' => 'fulfilled', 'provider_ref' => $providerRef];
    }

    public function fulfillPaidTopup(string $tid): array {
        $pdo = db();
        $st = $pdo->prepare('SELECT t.tid, t.iccid, t.status, t.topup_status, t.plan_id, p.topup_packcode FROM topup_order t LEFT JOIN plan p ON p.id=t.plan_id WHERE t.tid=? LIMIT 1');
        $st->execute([$tid]);
        $o = $st->fetch();
        if (!$o) {
            app_log('RetailFulfill topup not found ' . $tid, 'WARN');
            return ['success' => false, 'reason' => 'not_found'];
        }
        if ((int)$o['status'] !== 0) {
            return ['success' => true, 'reason' => 'already_processed', 'status' => (int)$o['status']];
        }
        $packCode = (string)($o['topup_packcode'] ?? '');
        $iccid = (string)($o['iccid'] ?? '');
        if ($packCode === '' || $iccid === '') {
            $this->enqueueAdmin(AdminFailedOrderQueue::KIND_TOPUP_ORDER, $tid, 'missing pack_code or iccid', null);
            return ['success' => false, 'reason' => 'invalid_topup'];
        }

        // Atomic 0 → 2.
        $upd = $pdo->prepare('UPDATE topup_order SET status=2, paid_at=NOW(), updated_at=NOW() WHERE tid=? AND status=0');
        $upd->execute([$tid]);
        if ($upd->rowCount() === 0) {
            return ['success' => true, 'reason' => 'race_lost'];
        }

        try {
            $resp = (new LegacyProviderClient())->topupEsim($iccid, $packCode, $tid);
        } catch (Throwable $e) {
            $this->enqueueAdmin(AdminFailedOrderQueue::KIND_PROVIDER_ERROR, $tid, 'topupEsim threw: ' . $e->getMessage(), null);
            app_log('RetailFulfill topupEsim threw ' . $tid . ' ' . $e->getMessage(), 'ERROR');
            return ['success' => false, 'reason' => 'provider_exception'];
        }

        if (empty($resp['success'])) {
            $summary = trim((string)($resp['error_code'] ?? '') . ' ' . (string)($resp['error_message'] ?? ''));
            if ($summary === '') $summary = 'provider topupEsim failed';
            $this->enqueueAdmin(AdminFailedOrderQueue::KIND_PROVIDER_ERROR, $tid, $summary, (string)($resp['raw_summary'] ?? null));
            app_log('RetailFulfill topup provider fail ' . $tid . ' ' . $summary, 'ERROR');
            return ['success' => false, 'reason' => 'provider_error'];
        }

        try {
            $pdo->prepare('UPDATE topup_order SET topup_status=1, updated_at=NOW() WHERE tid=?')->execute([$tid]);
        } catch (Throwable $e) {
            app_log('RetailFulfill topup status update fail ' . $tid . ' ' . $e->getMessage(), 'ERROR');
        }

        // Best-effort email.
        try {
            (new MailService())->sendTopupIfNeeded($tid);
        } catch (Throwable $e) {
            // sendTopupIfNeeded may not exist on every install — swallow but log once.
            app_log('RetailFulfill topup email skipped ' . $tid . ' ' . $e->getMessage(), 'INFO');
        }

        return ['success' => true, 'reason' => 'fulfilled'];
    }

    private function enqueueAdmin(string $kind, string $refId, string $summary, ?string $payloadRedacted): void {
        try {
            (new AdminFailedOrderQueue())->enqueue($kind, $refId, $summary, $payloadRedacted);
        } catch (Throwable $e) {
            app_log('RetailFulfill enqueueAdmin failed ' . $refId . ' ' . $e->getMessage(), 'ERROR');
        }
    }
}
