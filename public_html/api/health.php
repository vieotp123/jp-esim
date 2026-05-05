<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
security_headers(false);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$checks = [];
$ok = true;

$t0 = microtime(true);
try {
    $row = db()->query('SELECT 1')->fetch();
    $checks['db'] = ['ok' => true, 'ms' => round((microtime(true) - $t0) * 1000, 1)];
} catch (Throwable $e) {
    $checks['db'] = ['ok' => false, 'error' => 'connection_failed'];
    $ok = false;
}

$checks['php'] = ['ok' => true, 'version' => PHP_VERSION];

$diskFree = @disk_free_space('/');
if ($diskFree !== false) {
    $diskFreeGb = round($diskFree / 1073741824, 1);
    $checks['disk'] = ['ok' => $diskFreeGb > 1, 'free_gb' => $diskFreeGb];
    if ($diskFreeGb <= 1) $ok = false;
} else {
    $checks['disk'] = ['ok' => true];
}

try {
    $pdo = db();
    $queueOpen = (int)$pdo->query("SELECT COUNT(*) FROM order_admin_queue WHERE status='open'")->fetchColumn();
    $queueWarn = (int)app_config('HEALTH_QUEUE_WARN', 50);
    $queueCrit = (int)app_config('HEALTH_QUEUE_CRIT', 200);
    $checks['admin_queue'] = ['ok' => $queueOpen < $queueCrit, 'warn' => $queueOpen >= $queueWarn, 'open' => $queueOpen];
    if ($queueOpen >= $queueCrit) $ok = false;

    $pendingEmails = (int)$pdo->query("SELECT COUNT(*) FROM ctv_esims e JOIN ctv_orders o ON o.ctv_order_id=e.ctv_order_id WHERE e.email_sent_at IS NULL AND (e.email_last_error IS NULL OR e.email_last_error='') AND e.created_at >= (NOW() - INTERVAL 1 DAY) AND o.email IS NOT NULL AND o.email<>''")->fetchColumn();
    $emailWarn = (int)app_config('HEALTH_EMAIL_WARN', 20);
    $checks['email_queue_24h'] = ['ok' => true, 'warn' => $pendingEmails >= $emailWarn, 'pending' => $pendingEmails];

    $failedTopups = (int)$pdo->query("SELECT COUNT(*) FROM ctv_topup_orders WHERE status=3 AND needs_admin=1")->fetchColumn();
    $checks['failed_topups'] = ['ok' => true, 'warn' => $failedTopups > 0, 'count' => $failedTopups];

    $providerErrors1h = (int)$pdo->query("SELECT COUNT(*) FROM order_admin_queue WHERE kind='provider_error' AND created_at >= (NOW() - INTERVAL 1 HOUR)")->fetchColumn();
    $perrWarn = (int)app_config('HEALTH_PROVIDER_ERR_WARN', 5);
    $perrCrit = (int)app_config('HEALTH_PROVIDER_ERR_CRIT', 20);
    $checks['provider_errors_1h'] = ['ok' => $providerErrors1h < $perrCrit, 'warn' => $providerErrors1h >= $perrWarn, 'count' => $providerErrors1h];
    if ($providerErrors1h >= $perrCrit) $ok = false;
} catch (Throwable $e) {
    $checks['queue_metrics'] = ['ok' => false, 'error' => 'query_failed'];
}

$checks['flags'] = [
    'topup_locked' => (string)app_config('TOPUP_LOCKED', '0') === '1',
    'provider_test_mode' => (string)app_config('PROVIDER_TEST_MODE', '0') === '1',
    'admin_passkey_required' => (string)app_config('ADMIN_REQUIRE_PASSKEY', '1') !== '0',
];

http_response_code($ok ? 200 : 503);
echo json_encode([
    'status' => $ok ? 'healthy' : 'degraded',
    'checks' => $checks,
    'ts' => gmdate('c'),
], JSON_UNESCAPED_UNICODE);
