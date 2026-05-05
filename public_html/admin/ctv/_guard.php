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
    admin_session_start();

    if (!empty($_SESSION['admin_authenticated']) && !empty($_SESSION['admin_user'])) {
        $idleMax = (int)app_config('ADMIN_IDLE_MAX_SECONDS', 3600);
        $lastActivity = (int)($_SESSION['admin_last_activity'] ?? $_SESSION['admin_login_at'] ?? time());
        if ($idleMax > 0 && (time() - $lastActivity) > $idleMax) {
            admin_logout();
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            if ($script !== '/admin/ctv/login.php') {
                header('Location: /auth?role=admin&idle=1');
                exit;
            }
        } else {
            $_SESSION['admin_last_activity'] = time();
            admin_require_passkey_if_enabled((string)$_SESSION['admin_user']);
            return ['user' => (string)$_SESSION['admin_user']];
        }
    }

    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW'] ?? '';
    if ((string)$u !== '' && !admin_passkey_enforced_strict() && hash_equals($expectedUser, (string)$u) && hash_equals($expectedPass, (string)$p)) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = 1;
        $_SESSION['admin_user'] = $expectedUser;
        $_SESSION['admin_login_at'] = time();
        $_SESSION['admin_last_activity'] = time();
        admin_require_passkey_if_enabled($expectedUser);
        return ['user' => $expectedUser];
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script === '/admin/ctv/login.php') {
        return ['user' => ''];
    }

    if ((string)$u !== '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rl = new RateLimiter();
        if (!$rl->check('admin_basic_auth:' . $ip, 12, 300)) {
            http_response_code(429);
            echo 'Quá nhiều yêu cầu. Vui lòng thử lại sau.';
            exit;
        }
        header('WWW-Authenticate: Basic realm="jp-esim admin"');
        http_response_code(401);
        echo 'Yêu cầu xác thực.';
        exit;
    }

    header('Location: /auth?role=admin');
    exit;
}
function admin_login(string $user): void {
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = 1;
    $_SESSION['admin_user'] = $user;
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_last_activity'] = time();
}
function admin_logout(): void {
    admin_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
function admin_passkey_required(): bool {
    return (string)app_config('ADMIN_REQUIRE_PASSKEY', '1') !== '0';
}
function admin_passkey_enforced_strict(): bool {
    if (!admin_passkey_required()) return false;
    try {
        $adminId = crc32((string)app_config('ADMIN_USER', 'admin'));
        return (new PasskeyService())->hasPasskey('admin', $adminId);
    } catch (Throwable $e) {
        return false;
    }
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
function admin_require_passkey_if_enabled(?string $adminUser = null): void {
    if (!admin_passkey_required()) return;
    if (admin_passkey_verified()) return;
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (in_array($script, ['/admin/ctv/login.php', '/admin/ctv/passkey-api.php', '/admin/ctv/passkey-verify.php'], true)) return;

    $adminUser = $adminUser ?? (string)app_config('ADMIN_USER', 'admin');
    try {
        $hasPasskey = (new PasskeyService())->hasPasskey('admin', crc32($adminUser));
    } catch (Throwable $e) {
        app_log('admin passkey readiness check failed: ' . $e->getMessage(), 'ERROR');
        return;
    }

    if (!$hasPasskey) {
        if ($script === '/admin/ctv/passkey-setup.php') return;
        header('Location: /admin/ctv/passkey-setup.php?passkey_required=1');
        exit;
    }

    header('Location: /admin/ctv/passkey-verify.php');
    exit;
}
function admin_nav_active(string $href): string {
    $cur = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    return str_starts_with($cur, $href) ? ' class="active"' : '';
}
function admin_layout_header(string $title, array $admin): void {
    security_headers(true);
    $assetVer = '20260504b';
    $passkeyText = admin_passkey_required()
        ? (admin_passkey_verified() ? 'Đã xác thực Passkey' : 'Yêu cầu Passkey')
        : 'Passkey tùy chọn';
    ?>
<!doctype html><html lang="vi"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title><?= htmlspecialchars($title) ?> · jp-esim Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/admin/ctv/admin_theme.css?v=<?= $assetVer ?>">
</head>
<body>
<div class="nav-overlay" id="navOverlay"></div>
<header class="admin-h">
  <button class="hamburger" id="menuBtn" aria-label="Menu" aria-expanded="false" aria-controls="mainNav"><span></span></button>
  <div class="brand">
    <span class="brand-mark">JP</span>
    <span class="brand-name">jp-esim <em>Admin</em></span>
  </div>
  <nav id="mainNav">
    <a href="/admin/ctv/dashboard-admin.php"<?= admin_nav_active('/admin/ctv/dashboard-admin.php') ?>>Tổng quan</a>
    <a href="/admin/ctv/search.php"<?= admin_nav_active('/admin/ctv/search.php') ?>>Tìm kiếm</a>
    <a href="/admin/ctv/index.php"<?= admin_nav_active('/admin/ctv/index.php') ?>>Đối tác</a>
    <a href="/admin/ctv/orders.php"<?= admin_nav_active('/admin/ctv/orders.php') ?>>Đơn hàng</a>
    <a href="/admin/ctv/email-queue.php"<?= admin_nav_active('/admin/ctv/email-queue.php') ?>>Email</a>
    <a href="/admin/ctv/queue.php"<?= admin_nav_active('/admin/ctv/queue.php') ?>>Đơn lỗi</a>
    <a href="/admin/ctv/topup-orders.php"<?= admin_nav_active('/admin/ctv/topup-orders.php') ?>>Nạp data</a>
    <a href="/admin/ctv/topup-requests.php"<?= admin_nav_active('/admin/ctv/topup-requests.php') ?>>Nạp ví</a>
    <a href="/admin/ctv/notifications.php"<?= admin_nav_active('/admin/ctv/notifications.php') ?>>Thông báo</a>
    <a href="/admin/ctv/logs.php"<?= admin_nav_active('/admin/ctv/logs.php') ?>>Nhật ký</a>
    <a href="/admin/ctv/export.php"<?= admin_nav_active('/admin/ctv/export.php') ?>>Xuất CSV</a>
    <a href="/admin/ctv/audit.php"<?= admin_nav_active('/admin/ctv/audit.php') ?>>Kiểm toán</a>
    <a href="/admin/ctv/health.php"<?= admin_nav_active('/admin/ctv/health.php') ?>>Sức khoẻ</a>
    <a href="/admin/ctv/passkey-setup.php"<?= admin_nav_active('/admin/ctv/passkey-setup.php') ?>>Passkey</a>
  </nav>
  <span class="right">
    <span class="vip-tag">ADMIN</span>
    <span class="passkey-tag"><?= htmlspecialchars($passkeyText) ?></span>
    <span><?= htmlspecialchars($admin['user']) ?></span>
    <a href="/admin/ctv/logout.php" style="color:var(--a-muted);font-size:12px;text-decoration:none" onclick="return confirm('Đăng xuất?')">Đăng xuất</a>
  </span>
</header>
<main>
<div class="page-title"><h1><?= htmlspecialchars($title) ?></h1></div>
<?php }
function admin_layout_footer(): void {
    echo '</main>';
    echo '<script>(function(){var b=document.getElementById("menuBtn"),n=document.getElementById("mainNav"),o=document.getElementById("navOverlay");function closeNav(){n.classList.remove("open");o.classList.remove("open");b.setAttribute("aria-expanded","false");}if(b&&n&&o){b.addEventListener("click",function(){var open=!n.classList.contains("open");n.classList.toggle("open",open);o.classList.toggle("open",open);b.setAttribute("aria-expanded",open?"true":"false");});o.addEventListener("click",closeNav);document.addEventListener("keydown",function(e){if(e.key==="Escape")closeNav();});n.querySelectorAll("a").forEach(function(a){a.addEventListener("click",closeNav);});}})();</script>';
    echo '</body></html>';
}
