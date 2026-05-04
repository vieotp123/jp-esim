<?php
declare(strict_types=1);

/**
 * Admin failed-order queue.
 * Stores items needing human review (provider errors, email errors, amount mismatches, etc).
 * Used by RetailFulfillmentService and BankWebhookService to flag issues without crashing.
 *
 * NOTE: payload_redacted MUST already be redacted before passing in (use CtvProviderClient::redactJson()).
 */
final class AdminFailedOrderQueue {
    public const KIND_PROVIDER_ERROR = 'provider_error';
    public const KIND_EMAIL_ERROR    = 'email_error';
    public const KIND_RETAIL_ORDER   = 'retail_order';
    public const KIND_TOPUP_ORDER    = 'topup_order';
    public const KIND_AMOUNT_MISMATCH = 'amount_mismatch';

    public function enqueue(string $kind, string $refId, string $errorSummary, ?string $payloadRedacted = null): int {
        try {
            $pdo = db();
            $kind = mb_substr(trim($kind), 0, 32);
            $refId = mb_substr(trim($refId), 0, 64);
            $errorSummary = mb_substr($errorSummary, 0, 4000);
            if ($payloadRedacted !== null && strlen($payloadRedacted) > 4000) {
                $payloadRedacted = substr($payloadRedacted, 0, 4000) . '…';
            }
            if ($kind === '' || $refId === '') return 0;

            // Keep one open item per kind/ref so repeated webhook/provider failures do not spam admin.
            $existing = $pdo->prepare('SELECT id FROM order_admin_queue WHERE kind=? AND ref_id=? AND status=? ORDER BY id DESC LIMIT 1');
            $existing->execute([$kind, $refId, 'open']);
            $id = (int)($existing->fetchColumn() ?: 0);
            if ($id > 0) {
                $st = $pdo->prepare('UPDATE order_admin_queue SET error_summary=?, payload_redacted=?, created_at=NOW(), resolver_note=NULL WHERE id=?');
                $st->execute([$errorSummary, $payloadRedacted, $id]);
                return $id;
            }

            $st = $pdo->prepare('INSERT INTO order_admin_queue(kind,ref_id,status,error_summary,payload_redacted) VALUES(?,?,?,?,?)');
            $st->execute([$kind, $refId, 'open', $errorSummary, $payloadRedacted]);
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            app_log('AdminFailedOrderQueue enqueue failed: ' . $e->getMessage(), 'ERROR');
            return 0;
        }
    }

    public function list(string $status = 'open', int $limit = 50): array {
        $limit = max(1, min(500, $limit));
        $st = db()->prepare('SELECT id,kind,ref_id,status,error_summary,payload_redacted,created_at,resolved_at,resolver_note FROM order_admin_queue WHERE status=? ORDER BY id DESC LIMIT ' . $limit);
        $st->execute([$status]);
        return $st->fetchAll() ?: [];
    }

    public function resolve(int $id, string $note = ''): bool {
        $st = db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=? AND status=?');
        $st->execute(['resolved', mb_substr($note, 0, 1000), $id, 'open']);
        return $st->rowCount() > 0;
    }

    public function ignore(int $id, string $note = ''): bool {
        $st = db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=? AND status=?');
        $st->execute(['ignored', mb_substr($note, 0, 1000), $id, 'open']);
        return $st->rowCount() > 0;
    }
}
