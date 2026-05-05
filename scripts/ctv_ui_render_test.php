<?php
declare(strict_types=1);
/**
 * Render-test CTV partner pages with a temporary DB session.
 * Inserts a row into ctv_sessions for the most recent active CTV user,
 * fetches each /ctv/ page with that cookie, prints results, then deletes
 * the diagnostic session. Run as www-data.
 */
if (PHP_SAPI !== 'cli') exit(1);
require_once '/home/foamljf4kvet/app/bootstrap.php';

$pdo = db();
$ctv = $pdo->query('SELECT id,email FROM ctv_users WHERE status=1 AND email_verified=1 ORDER BY id DESC LIMIT 1')->fetch();
if (!$ctv) { fwrite(STDERR, "no active CTV user\n"); exit(1); }

$sid = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 600);
$pdo->prepare('INSERT INTO ctv_sessions(id,ctv_id,ip,user_agent,expires_at) VALUES(?,?,?,?,?)')
    ->execute([$sid, (int)$ctv['id'], '127.0.0.1', 'jpesim-ctv-render-diag', $expires]);

$base = (string)app_config('CTV_BASE_URL', 'https://jp-esim.vip');
$pages = [
    '/ctv/dashboard.php' => 'Tổng quan CTV',
    '/ctv/orders.php' => 'Đơn hàng',
    '/ctv/esims.php' => 'eSIM list',
    '/ctv/create-esim.php' => 'Tạo eSIM',
    '/ctv/topup-esim.php' => 'Nạp data eSIM',
    '/ctv/topup-orders.php' => 'Lịch sử nạp data',
    '/ctv/topup-request.php' => 'Yêu cầu nạp ví',
    '/ctv/api-keys.php' => 'API keys',
    '/ctv/security.php' => 'Bảo mật',
    '/ctv/pricing.php' => 'Bảng giá',
    '/ctv/export.php' => 'Xuất CSV',
    '/ctv/activity.php' => 'Hoạt động CTV',
    '/ctv/profile.php' => 'Hồ sơ CTV',
    '/ctv/install.php' => 'Hướng dẫn cài đặt',
    '/ctv/orders.php?status=2' => 'Orders thành công',
    '/ctv/orders.php?status=3' => 'Orders thất bại',
    '/ctv/topup-orders.php?status=3' => 'Topup thất bại',
    '/ctv/esims.php?page=1' => 'Esims page 1',
];

printf("%-46s %-22s %5s %8s %s\n", 'PAGE', 'TITLE', 'CODE', 'BYTES', 'NOTES');
$failures = 0;
foreach ($pages as $path => $title) {
    $url = $base . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIE => 'ctv_session=' . $sid,
        CURLOPT_USERAGENT => 'jpesim-ctv-render-diag',
    ]);
    $resp = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrLen = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr($resp, 0, $hdrLen);
    $body = substr($resp, $hdrLen);
    $notes = [];
    if ($code === 302 && preg_match('/Location:\s*([^\r\n]+)/i', $headers, $m)) $notes[] = 'redir->' . trim($m[1]);
    if (preg_match('/(Fatal error|Parse error|Uncaught\s+\w+Exception|Stack trace)/i', $body)) { $notes[] = 'PHP_ERROR'; $failures++; }
    if ($code >= 500) { $notes[] = 'HTTP_5XX'; $failures++; }
    if ($code === 200 && strlen($body) < 500) $notes[] = 'TINY_BODY';

    printf("%-46s %-22s %5d %8d %s\n", $path, mb_strimwidth($title, 0, 21, '…'), $code, strlen($body), implode(' ', $notes));
}

$pdo->prepare('DELETE FROM ctv_sessions WHERE id=?')->execute([$sid]);
echo "\nFailures: $failures\n";
exit($failures ? 2 : 0);
