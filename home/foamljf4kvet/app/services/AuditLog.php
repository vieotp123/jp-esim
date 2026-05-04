<?php
declare(strict_types=1);

final class AuditLog {
    public static function log(string $adminUser, string $action, ?string $targetType = null, ?string $targetId = null, ?array $details = null): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $detailsJson = $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            if ($detailsJson !== null && strlen($detailsJson) > 4000) {
                $detailsJson = substr($detailsJson, 0, 4000);
            }
            db()->prepare('INSERT INTO admin_audit_log(admin_user,action,target_type,target_id,details_json,ip) VALUES(?,?,?,?,?,?)')
                ->execute([$adminUser, $action, $targetType, $targetId, $detailsJson, $ip]);
        } catch (Throwable $e) {
            app_log('AuditLog write failed: ' . $e->getMessage(), 'ERROR');
        }
    }

    public static function list(int $limit = 50, int $offset = 0, ?string $action = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null): array {
        $where = [];
        $params = [];
        if ($action !== null && $action !== '') {
            $where[] = 'action=?';
            $params[] = $action;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(admin_user LIKE ? OR target_id LIKE ? OR details_json LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $where[] = 'created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== null && $dateTo !== '') {
            $where[] = 'created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        $countSt = db()->prepare("SELECT COUNT(*) FROM admin_audit_log $whereSql");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $st = db()->prepare("SELECT * FROM admin_audit_log $whereSql ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
        $st->execute($params);
        return ['rows' => $st->fetchAll(), 'total' => $total];
    }

    public static function actions(): array {
        return [
            'ctv_activate', 'ctv_deactivate', 'ctv_discount_update',
            'wallet_credit', 'wallet_debit',
            'queue_resolve', 'queue_ignore', 'queue_cancel', 'queue_refund', 'queue_retry', 'queue_reopen',
            'topup_request_approve', 'topup_request_reject',
        ];
    }
}
