<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';

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

http_response_code($ok ? 200 : 503);
echo json_encode([
    'status' => $ok ? 'healthy' : 'degraded',
    'checks' => $checks,
    'ts' => gmdate('c'),
], JSON_UNESCAPED_UNICODE);
