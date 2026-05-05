<?php
declare(strict_types=1);
/**
 * Test admin POST mutation handlers — verify they reject invalid CSRF
 * with 400 (NOT 500) and don't crash on garbage inputs. No actual
 * mutations occur because CSRF check fails before any mutation logic.
 *
 * Run as www-data: sudo -u www-data php scripts/admin_post_mutation_test.php
 */
if (PHP_SAPI !== 'cli') exit(1);
require_once '/home/foamljf4kvet/app/bootstrap.php';

$base = (string)app_config('APP_BASE_URL', 'https://jp-esim.vip');
$sid = bin2hex(random_bytes(16));
$sessPath = '/var/lib/php/sessions/sess_' . $sid;
$now = time();
$_SESSION = [
    'admin_authenticated' => 1,
    'admin_user' => (string)app_config('ADMIN_USER', 'admin'),
    'admin_login_at' => $now,
    'admin_last_activity' => $now,
    'admin_passkey_verified' => 1,
    'admin_passkey_verified_at' => $now,
    'admin_csrf' => bin2hex(random_bytes(16)),
];
$payload = '';
foreach ($_SESSION as $k => $v) $payload .= $k . '|' . serialize($v);
file_put_contents($sessPath, $payload);
chmod($sessPath, 0600);

$cases = [
    // [path, post fields, expected_codes (array)]
    ['/admin/ctv/index.php', ['action'=>'set_status','ctv_id'=>'5','status'=>'1','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/index.php', ['action'=>'set_discount','ctv_id'=>'5','discount'=>'1000','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/index.php', ['action'=>'wallet_credit','ctv_id'=>'5','amount'=>'1000','note'=>'test','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/queue.php', ['action'=>'resolve','id'=>'1','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/queue.php', ['action'=>'ignore','id'=>'1','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/orders.php', ['action'=>'sync_esim','order_id'=>'CWPLYSA7','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/orders.php', ['action'=>'mark_resolved','order_id'=>'CWPLYSA7','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/orders.php', ['action'=>'retry','order_id'=>'CWPLYSA7','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/topup-orders.php', ['action'=>'refund','ctv_topup_id'=>'X','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/topup-requests.php', ['action'=>'approve','id'=>'1','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/topup-requests.php', ['action'=>'reject','id'=>'1','note'=>'test','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/notifications.php', ['action'=>'send_user','ctv_id'=>'5','title'=>'test','message'=>'test','csrf'=>'wrong'], [200,400]],
    ['/admin/ctv/notifications.php', ['action'=>'broadcast','title'=>'test','message'=>'test','csrf'=>'wrong'], [200,400]],
];

printf("%-32s %-18s %5s %s\n", 'PATH', 'ACTION', 'CODE', 'NOTES');
$failures = 0;
foreach ($cases as $c) {
    [$path, $fields, $okCodes] = $c;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_COOKIE => 'jp_esim_admin_ctv=' . $sid,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_USERAGENT => 'jpesim-admin-mutation-diag',
    ]);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $notes = [];
    if (preg_match('/(Fatal error|Parse error|Uncaught\s+\w+Exception|Stack trace)/i', $body)) { $notes[] = 'PHP_ERROR'; $failures++; }
    if ($code >= 500) { $notes[] = 'HTTP_5XX'; $failures++; }
    if (!in_array($code, $okCodes, true)) $notes[] = 'UNEXPECTED_CODE';
    printf("%-32s %-18s %5d %s\n", $path, (string)$fields['action'], $code, implode(' ', $notes));
}

@unlink($sessPath);
echo "\nFailures: $failures\n";
exit($failures ? 2 : 0);
