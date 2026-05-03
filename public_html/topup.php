<?php
declare(strict_types=1);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$id = trim((string)($_GET['id'] ?? $_GET['iccid'] ?? $_GET['order_id'] ?? ''));
if ($id === '') {
    header('Location: /#topup', true, 302);
    exit;
}
$safe = json_encode($id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$target = '/?topup_id=' . rawurlencode($id) . '#topup';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đang chuyển đến nạp data</title>
<script>
try {
  sessionStorage.setItem('jp_pending_topup_id', <?= $safe ?>);
  localStorage.setItem('jp_pending_topup_id', <?= $safe ?>);
} catch(e) {}
location.replace(<?= json_encode($target, JSON_UNESCAPED_SLASHES) ?>);
</script>
</head>
<body>
Đang chuyển đến trang nạp data...
<a href="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>">Bấm vào đây nếu chưa tự chuyển</a>
</body>
</html>
