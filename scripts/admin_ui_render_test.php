<?php
declare(strict_types=1);
/**
 * Render-test admin pages with a fake authenticated session (DIAGNOSTIC ONLY).
 * Creates a session file in /var/lib/php/sessions/, fetches each admin page
 * via curl with that cookie, and reports HTTP code + body length + PHP error
 * traces detected in the response.
 *
 * Run as www-data: sudo -u www-data php scripts/admin_ui_render_test.php
 */
if (PHP_SAPI !== 'cli') exit(1);
require_once '/home/foamljf4kvet/app/bootstrap.php';

$base = (string)app_config('APP_BASE_URL', 'https://jp-esim.vip');
$sessName = 'jp_esim_admin_ctv';
// PHP default session ID validator accepts [a-z0-9,-]{22,256} (depends on session.sid_bits_per_character).
// Default sid_length=26 with bits=4 → hex; we use 32 hex for safety.
$sid = bin2hex(random_bytes(16));
$sessPath = '/var/lib/php/sessions/sess_' . $sid;

// Build minimal admin session payload (PHP serialize format)
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
// PHP "php" session serializer format
$payload = '';
foreach ($_SESSION as $k => $v) {
    $payload .= $k . '|' . serialize($v);
}
file_put_contents($sessPath, $payload);
chmod($sessPath, 0600);

$pages = [
    '/admin/ctv/dashboard-admin.php' => 'Tổng quan',
    '/admin/ctv/index.php' => 'Đối tác (list)',
    '/admin/ctv/orders.php' => 'Đơn hàng',
    '/admin/ctv/queue.php' => 'Đơn lỗi',
    '/admin/ctv/topup-orders.php' => 'Nạp data',
    '/admin/ctv/topup-requests.php' => 'Nạp ví',
    '/admin/ctv/email-queue.php' => 'Email queue',
    '/admin/ctv/notifications.php' => 'Thông báo',
    '/admin/ctv/logs.php' => 'Nhật ký',
    '/admin/ctv/audit.php' => 'Kiểm toán',
    '/admin/ctv/export.php' => 'Xuất CSV',
    '/admin/ctv/passkey-setup.php' => 'Passkey setup',
    '/admin/ctv/health.php' => 'Sức khoẻ',
    '/admin/ctv/activity.php' => 'Hoạt động',
    '/admin/ctv/activity.php?kind=ctv_order' => 'Activity ctv_order',
    '/admin/ctv/activity.php?kind=audit' => 'Activity audit',
    '/admin/ctv/activity.php?kind=BAD' => 'Activity bad filter',
    '/admin/ctv/backups.php' => 'Sao lưu',
    '/admin/ctv/system-log.php' => 'Log hệ thống',
    '/admin/ctv/system-log.php?log=ctv_fulfillment_poll.log&n=50' => 'Log poll',
    '/admin/ctv/system-log.php?log=BAD' => 'Log bad name',
    '/admin/ctv/search.php' => 'Tìm kiếm',
    '/admin/ctv/search.php?q=NM8RTTL1' => 'Tìm theo retail order',
    '/admin/ctv/search.php?q=CWPLYSA7' => 'Tìm theo CTV order',
    '/admin/ctv/search.php?q=ctv_test' => 'Tìm theo email partner',
    '/admin/ctv/search.php?q=8981440039' => 'Tìm theo ICCID prefix',
    '/admin/ctv/search.php?q=NOMATCH' => 'Tìm không kết quả',
    '/admin/ctv/view.php?id=5' => 'CTV detail #5',
    '/admin/ctv/order-view.php?id=CWPLYSA7' => 'Order detail',
    '/admin/ctv/queue.php?status=resolved' => 'Queue resolved',
    '/admin/ctv/orders.php?failed=1' => 'Orders failed only',
    '/admin/ctv/orders.php?q=CWPLYSA7' => 'Orders search',
    '/admin/ctv/topup-requests.php?status=pending' => 'Topup req pending',
    '/admin/ctv/topup-orders.php?status=3' => 'Topup orders failed',
    '/admin/ctv/email-queue.php?status=error' => 'Email error',
    '/admin/ctv/email-queue.php?status=pending' => 'Email pending',
    '/admin/ctv/audit.php?q=admin' => 'Audit search',
    '/admin/ctv/logs.php?ctv_id=5' => 'Logs by CTV',
];

printf("%-40s %-22s %5s %8s %s\n", 'PAGE', 'TITLE', 'CODE', 'BYTES', 'NOTES');
$failures = 0;
foreach ($pages as $path => $title) {
    $url = $base . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIE => $sessName . '=' . $sid,
        CURLOPT_USERAGENT => 'jpesim-admin-render-diag',
    ]);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $notes = [];
    $bodyLen = strlen($body);
    if ($code === 302) {
        if (preg_match('/Location:\s*([^\r\n]+)/i', $body, $m)) $notes[] = 'redir->' . trim($m[1]);
        else $notes[] = 'redir';
    }
    if (preg_match('/(Fatal error|Parse error|Uncaught\s+\w+Exception|Stack trace)/i', $body)) {
        $notes[] = 'PHP_ERROR';
        $failures++;
    }
    if ($code >= 500) { $notes[] = 'HTTP_5XX'; $failures++; }
    if ($code === 200 && $bodyLen < 500) { $notes[] = 'TINY_BODY'; }
    if ($code === 200 && stripos($body, 'mã CSRF') !== false) { /* ok */ }
    if ($code === 200 && stripos($body, $title) === false && stripos($body, htmlentities($title, ENT_QUOTES, 'UTF-8')) === false) { $notes[] = 'TITLE_MISSING'; }

    printf("%-40s %-22s %5d %8d %s\n", $path, mb_strimwidth($title, 0, 21, '…'), $code, $bodyLen, implode(' ', $notes));
}

@unlink($sessPath);
echo "\n";
echo "Failures: $failures\n";
exit($failures ? 2 : 0);
