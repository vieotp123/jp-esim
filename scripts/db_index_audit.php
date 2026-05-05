<?php
declare(strict_types=1);
/**
 * Run EXPLAIN on hot admin/CTV queries; flag full-table scans (type=ALL),
 * missing keys (key=NULL), and large rows-examined estimates. Suggests
 * candidate indexes.
 *
 * Run as www-data: sudo -u www-data php scripts/db_index_audit.php
 */
if (PHP_SAPI !== 'cli') exit(1);
require_once '/home/foamljf4kvet/app/bootstrap.php';

$queries = [
    'admin: top5 partners' =>
        "SELECT u.id, u.email, SUM(o.total_charge) rev, COUNT(*) cnt FROM ctv_orders o JOIN ctv_users u ON u.id=o.ctv_id WHERE o.status=2 AND o.created_at >= (CURDATE() - INTERVAL 30 DAY) GROUP BY u.id ORDER BY rev DESC LIMIT 5",
    'admin: revenue retail today' =>
        "SELECT COALESCE(SUM(total),0) FROM `order` WHERE status >= 2 AND paid_at >= (NOW() - INTERVAL 1 DAY)",
    'admin: revenue ctv today' =>
        "SELECT COALESCE(SUM(total_charge),0) FROM ctv_orders WHERE status=2 AND created_at >= (NOW() - INTERVAL 1 DAY)",
    'admin: queue open by kind' =>
        "SELECT kind, COUNT(*) cnt FROM order_admin_queue WHERE status='open' GROUP BY kind",
    'admin: pending topup reqs' =>
        "SELECT COUNT(*) FROM ctv_topup_requests WHERE status='pending'",
    'admin: failed topups needing admin' =>
        "SELECT COUNT(*) FROM ctv_topup_orders WHERE status=3 AND needs_admin=1",
    'admin: pending emails (joined)' =>
        "SELECT COUNT(*) FROM ctv_esims e JOIN ctv_orders o ON o.ctv_order_id=e.ctv_order_id WHERE e.email_sent_at IS NULL AND (e.email_last_error IS NULL OR e.email_last_error='') AND o.email IS NOT NULL AND o.email<>''",
    'admin: provider errors 1h' =>
        "SELECT COUNT(*) FROM order_admin_queue WHERE kind='provider_error' AND created_at >= (NOW() - INTERVAL 1 HOUR)",
    'admin: ctv list with rollups' =>
        "SELECT u.id,u.email,u.balance,(SELECT COUNT(*) FROM ctv_orders WHERE ctv_id=u.id) AS total_orders FROM ctv_users u ORDER BY u.id DESC LIMIT 200",
    'admin: ctv orders list' =>
        "SELECT o.*, u.email AS ctv_email, (SELECT COUNT(*) FROM ctv_esims e WHERE e.ctv_order_id=o.ctv_order_id) AS esim_count FROM ctv_orders o LEFT JOIN ctv_users u ON u.id=o.ctv_id ORDER BY o.id DESC LIMIT 200",
    'admin: audit log search' =>
        "SELECT * FROM admin_audit_log WHERE admin_user LIKE '%admin%' ORDER BY id DESC LIMIT 100",
    'ctv: my orders' =>
        "SELECT * FROM ctv_orders WHERE ctv_id=1 ORDER BY id DESC LIMIT 50",
    'ctv: my esims' =>
        "SELECT * FROM ctv_esims WHERE ctv_id=1 ORDER BY id DESC LIMIT 30",
    'ctv: my topup orders' =>
        "SELECT * FROM ctv_topup_orders WHERE ctv_id=1 ORDER BY id DESC LIMIT 50",
    'fulfillment poll: pending sync' =>
        "SELECT * FROM ctv_orders WHERE status=2 AND (iccid IS NULL OR iccid='') AND created_at >= (NOW() - INTERVAL 1440 MINUTE) ORDER BY id ASC LIMIT 50",
    'email retry worker' =>
        "SELECT DISTINCT ctv_order_id FROM ctv_esims WHERE email_sent_at IS NULL AND email_attempts < 5 AND created_at >= (NOW() - INTERVAL 7 DAY) AND iccid IS NOT NULL AND iccid <> '' AND ac IS NOT NULL AND ac <> '' ORDER BY id DESC LIMIT 20",
];

$pdo = db();
$flagged = [];
printf("%-38s %-20s %-12s %-12s %s\n", 'QUERY', 'TABLE', 'TYPE', 'KEY', 'ROWS');
foreach ($queries as $name => $sql) {
    try {
        $rows = $pdo->query('EXPLAIN ' . $sql)->fetchAll(PDO::FETCH_ASSOC);
        $first = true;
        foreach ($rows as $r) {
            $tbl = (string)($r['table'] ?? '?');
            $type = (string)($r['type'] ?? '?');
            $key = (string)($r['key'] ?? '');
            $rwsExamined = (int)($r['rows'] ?? 0);
            $bad = ($type === 'ALL') || ($key === '' && $rwsExamined > 100);
            $flag = $bad ? ' ⚠' : '';
            printf("%-38s %-20s %-12s %-12s %d%s\n",
                $first ? mb_strimwidth($name, 0, 37, '…') : '',
                $tbl, $type, $key === '' ? '<none>' : $key, $rwsExamined, $flag);
            if ($bad) $flagged[] = ['q' => $name, 'table' => $tbl, 'type' => $type, 'rows' => $rwsExamined];
            $first = false;
        }
    } catch (Throwable $e) {
        printf("%-38s %s\n", mb_strimwidth($name, 0, 37, '…'), 'EXPLAIN_FAIL: ' . $e->getMessage());
    }
}

echo "\nFlagged (full scan or no-key+>100 rows): " . count($flagged) . "\n";
foreach ($flagged as $f) {
    printf("  • %-38s table=%-20s type=%-6s rows=%d\n", $f['q'], $f['table'], $f['type'], $f['rows']);
}
