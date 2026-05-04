<?php
declare(strict_types=1);

function admin_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('jp_esim_admin_ctv');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/admin/ctv/','secure'=>!empty($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function admin_csrf_token(): string {
    admin_session_start();
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['admin_csrf'];
}
function admin_csrf_field(): void {
    echo '<input type="hidden" name="csrf" value="'.htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8').'">';
}
function admin_require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!hash_equals(admin_csrf_token(), (string)($_POST['csrf'] ?? ''))) { http_response_code(400); echo 'Mã CSRF không hợp lệ.'; exit; }
}
function admin_ctv_require(): array {
    $expectedUser = (string)app_config('ADMIN_USER', 'admin');
    $expectedPass = (string)app_config('ADMIN_PASS', '');
    if ($expectedPass === '') { http_response_code(503); header('Content-Type: text/plain; charset=utf-8'); echo 'Khu vực quản trị đã tắt.'; exit; }
    $u = $_SERVER['PHP_AUTH_USER'] ?? ''; $p = $_SERVER['PHP_AUTH_PW'] ?? '';
    if (!hash_equals($expectedUser, (string)$u) || !hash_equals($expectedPass, (string)$p)) { header('WWW-Authenticate: Basic realm="jp-esim admin"'); http_response_code(401); echo 'Yêu cầu xác thực.'; exit; }
    admin_session_start();
    if (empty($_SESSION['admin_authenticated'])) { session_regenerate_id(true); $_SESSION['admin_authenticated'] = 1; }
    return ['user' => $expectedUser];
}
function admin_passkey_required(): bool {
    return (string)app_config('ADMIN_REQUIRE_PASSKEY', '0') === '1';
}
function admin_passkey_verified(): bool {
    admin_session_start();
    if (empty($_SESSION['admin_passkey_verified'])) return false;
    $verifiedAt = (int)($_SESSION['admin_passkey_verified_at'] ?? 0);
    if ($verifiedAt > 0 && (time() - $verifiedAt) > 28800) {
        unset($_SESSION['admin_passkey_verified'], $_SESSION['admin_passkey_verified_at']);
        return false;
    }
    return true;
}
function admin_require_passkey_if_enabled(): void {
    if (!admin_passkey_required()) return;
    if (admin_passkey_verified()) return;
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $exempt = ['/admin/ctv/passkey-api.php', '/admin/ctv/passkey-verify.php'];
    if (in_array($script, $exempt, true)) return;
    header('Location: /admin/ctv/passkey-verify.php');
    exit;
}
function admin_nav_active(string $href): string {
    $cur = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    return str_starts_with($cur, $href) ? ' class="active"' : '';
}
function admin_layout_header(string $title, array $admin): void {
    security_headers(true);
    $assetVer = '20260504a';
    ?>
<!doctype html><html lang="vi"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title><?= htmlspecialchars($title) ?> · jp-esim Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/admin/ctv/admin_theme.css?v=<?= $assetVer ?>">
</head>
<body>
<header class="admin-h">
  <div class="brand">
    <span class="brand-mark">JP</span>
    <span class="brand-name">jp-esim <em>Admin</em></span>
  </div>
  <nav>
    <a href="/admin/ctv/dashboard-admin.php"<?= admin_nav_active('/admin/ctv/dashboard-admin.php') ?>>Tổng quan</a>
    <a href="/admin/ctv/index.php"<?= admin_nav_active('/admin/ctv/index.php') ?>>Danh sách CTV</a>
    <a href="/admin/ctv/orders.php"<?= admin_nav_active('/admin/ctv/orders.php') ?>>Đơn CTV</a>
    <a href="/admin/ctv/email-queue.php"<?= admin_nav_active('/admin/ctv/email-queue.php') ?>>Hàng đợi Email</a>
    <a href="/admin/ctv/queue.php"<?= admin_nav_active('/admin/ctv/queue.php') ?>>Đơn lỗi</a>
    <a href="/admin/ctv/topup-requests.php"<?= admin_nav_active('/admin/ctv/topup-requests.php') ?>>Nạp ví</a>
    <a href="/admin/ctv/notifications.php"<?= admin_nav_active('/admin/ctv/notifications.php') ?>>Thông báo</a>
    <a href="/admin/ctv/logs.php"<?= admin_nav_active('/admin/ctv/logs.php') ?>>Nhật ký</a>
    <a href="/admin/ctv/audit.php"<?= admin_nav_active('/admin/ctv/audit.php') ?>>Kiểm toán</a>
    <a href="/admin/ctv/passkey-setup.php"<?= admin_nav_active('/admin/ctv/passkey-setup.php') ?>>Passkey</a>
  </nav>
  <span class="right">
    <span class="vip-tag">VIP</span>
    <span><?= htmlspecialchars($admin['user']) ?></span>
  </span>
</header>
<main>
<div class="page-title"><h1><?= htmlspecialchars($title) ?></h1><span class="crumb">/admin/ctv</span></div>
<?php }
function admin_layout_footer(): void { echo '</main></body></html>'; }
