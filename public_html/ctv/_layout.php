<?php
declare(strict_types=1);

if (!function_exists('ctv_layout_header')) {
    function ctv_layout_header(string $title, ?array $user = null): void {
        security_headers(true);
        ?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars($title) ?> - CTV jp-esim.vip</title>
<style>
:root { color-scheme: light dark; --brand:#0d6efd; --ink:#111827; --muted:#6b7280; --line:#e5e7eb; }
* { box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f5f7fb; color: #111; }
header.ctv-h { background: #0d6efd; color: #fff; padding: 14px 20px; display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
header.ctv-h a { color: #fff; text-decoration: none; font-weight: 600; }
header.ctv-h .brand { font-size: 18px; margin-right: 12px; }
header.ctv-h nav a { margin-right: 14px; opacity: .9; }
header.ctv-h nav a:hover { opacity: 1; text-decoration: underline; }
header.ctv-h .right { margin-left: auto; font-size: 13px; opacity: .9; }
main { max-width: 1180px; margin: 24px auto; padding: 0 16px; }
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; overflow:auto; box-shadow:0 8px 28px rgba(15,23,42,.05); }
.card h2 { margin: 0 0 12px; font-size: 18px; }
label { display: block; font-size: 13px; color: #555; margin-bottom: 4px; }
input[type=text], input[type=email], input[type=password], input[type=number], select, textarea { width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid #d4d4d8; border-radius: 8px; background: #fff; }
.field { margin-bottom: 12px; }
button.btn, .btn { display: inline-block; background: #0d6efd; color: #fff; border: 0; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; }
button.btn:disabled { opacity: .6; cursor: not-allowed; }
.btn.secondary { background: #6b7280; }
.btn.danger { background: #dc2626; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
th { background: #f9fafb; font-weight: 600; }
.flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; }
.flash.error { background: #fee2e2; color: #991b1b; }
.flash.ok { background: #dcfce7; color: #166534; }
.flash.warn { background: #fef3c7; color: #92400e; }
.muted { color: #6b7280; font-size: 13px; }
.kbd { font-family: ui-monospace, Menlo, monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
.row { display: flex; gap: 12px; flex-wrap: wrap; }
.row > * { flex: 1 1 220px; }
.grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
.metric { font-size:26px; font-weight:800; margin:8px 0; }
.actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
.copy { cursor:pointer; }
.tag { display: inline-block; padding: 2px 8px; font-size: 12px; border-radius: 999px; background: #e0e7ff; color: #3730a3; }
.tag.ok { background: #dcfce7; color: #166534; }
.tag.warn { background: #fef3c7; color: #92400e; }
.tag.err { background: #fee2e2; color: #991b1b; }
@media (max-width:760px){ header.ctv-h{display:block} header.ctv-h nav a{display:inline-block;margin:8px 10px 0 0} table{font-size:12px} }
@media (prefers-color-scheme: dark) {
  body { background: #0b0f17; color: #e5e7eb; }
  .card { background: #11151f; border-color: #1f2937; }
  th { background: #0f172a; }
  th, td { border-color: #1f2937; }
  input, select, textarea { background: #0b0f17; color: #e5e7eb; border-color: #1f2937; }
  .muted { color: #94a3b8; }
}
</style>
</head>
<body>
<header class="ctv-h">
  <span class="brand">jp-esim CTV</span>
  <nav>
    <?php if ($user): ?>
      <a href="/ctv/dashboard.php">Tổng quan</a>
      <a href="/ctv/pricing.php">Bảng giá</a>
      <a href="/ctv/orders.php">Đơn eSIM</a>
      <a href="/ctv/esims.php">eSIM</a>
      <a href="/ctv/create-esim.php">Tạo eSIM</a>
      <a href="/ctv/topup-esim.php">Nạp data</a>
      <a href="/ctv/api-keys.php">API Keys</a>
      <a href="/ctv/export.php">Xuất CSV</a>
    <?php else: ?>
      <a href="/ctv/login.php">Đăng nhập</a>
      <a href="/ctv/register.php">Đăng ký</a>
    <?php endif; ?>
  </nav>
  <span class="right">
    <?php if ($user): ?>
      <?= htmlspecialchars((string)$user['email']) ?> ·
      Số dư: <strong><?= htmlspecialchars(format_vnd((int)$user['balance'])) ?></strong> ·
      <a href="/ctv/logout.php">Thoát</a>
    <?php endif; ?>
  </span>
</header>
<main>
        <?php
    }
}

if (!function_exists('ctv_layout_footer')) {
    function ctv_layout_footer(): void {
        echo '<script>document.querySelectorAll("[data-copy]").forEach(el=>el.addEventListener("click",()=>navigator.clipboard&&navigator.clipboard.writeText(el.dataset.copy)));</script></main></body></html>';
    }
}

if (!function_exists('ctv_flash_render')) {
    function ctv_flash_render(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!empty($_SESSION['ctv_flash'])) {
            foreach ((array)$_SESSION['ctv_flash'] as $f) {
                $type = htmlspecialchars((string)($f['type'] ?? 'ok'));
                $msg = htmlspecialchars((string)($f['msg'] ?? ''));
                echo '<div class="flash ' . $type . '">' . $msg . '</div>';
            }
            unset($_SESSION['ctv_flash']);
        }
    }
}

if (!function_exists('ctv_flash_set')) {
    function ctv_flash_set(string $type, string $msg): void {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $_SESSION['ctv_flash'][] = ['type' => $type, 'msg' => $msg];
    }
}
