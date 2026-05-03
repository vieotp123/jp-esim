<?php
declare(strict_types=1);

/**
 * Minimal admin gate. Enforces HTTP basic auth using env config:
 *  - ADMIN_USER (defaults to "admin")
 *  - ADMIN_PASS (required; admin section is disabled if missing)
 *
 * This is intentionally lightweight: it is the foundation for CTV admin only.
 * Any future global admin module can replace this guard.
 */
function admin_ctv_require(): array {
    $expectedUser = (string)app_config('ADMIN_USER', 'admin');
    $expectedPass = (string)app_config('ADMIN_PASS', '');
    if ($expectedPass === '') {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Admin section is disabled. Set ADMIN_PASS in config to enable.';
        exit;
    }
    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW'] ?? '';
    if (!hash_equals($expectedUser, (string)$u) || !hash_equals($expectedPass, (string)$p)) {
        header('WWW-Authenticate: Basic realm="jp-esim admin"');
        http_response_code(401);
        echo 'Authentication required.';
        exit;
    }
    return ['user' => $expectedUser];
}

function admin_layout_header(string $title, array $admin): void {
    security_headers(true);
    ?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars($title) ?> - Admin CTV</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; background: #0b0f17; color: #e5e7eb; }
header { background: #1f2937; padding: 12px 20px; display: flex; gap: 14px; align-items: center; }
header a { color: #93c5fd; text-decoration: none; font-weight: 600; }
main { max-width: 1180px; margin: 18px auto; padding: 0 16px; }
.card { background: #111827; border: 1px solid #1f2937; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th, td { padding: 8px; border-bottom: 1px solid #1f2937; text-align: left; }
th { background: #0f172a; }
input, select, textarea { background: #0b0f17; color: #e5e7eb; border: 1px solid #1f2937; border-radius: 6px; padding: 6px 10px; }
button.btn { background: #2563eb; color: #fff; border: 0; padding: 8px 14px; border-radius: 6px; cursor: pointer; }
.btn.danger { background: #dc2626; }
.btn.secondary { background: #6b7280; }
.flash.ok { background: #064e3b; color: #d1fae5; padding: 8px 12px; border-radius: 6px; margin-bottom: 8px; }
.flash.err { background: #7f1d1d; color: #fee2e2; padding: 8px 12px; border-radius: 6px; margin-bottom: 8px; }
.kbd { font-family: ui-monospace, Menlo, monospace; background: #0f172a; padding: 2px 6px; border-radius: 4px; }
form.inline { display: inline; }
</style>
</head><body>
<header>
  <strong>Admin CTV</strong>
  <a href="/admin/ctv/index.php">CTV</a>
  <a href="/admin/ctv/orders.php">Đơn CTV</a>
  <a href="/admin/ctv/logs.php">Logs</a>
  <span style="margin-left:auto;">Xin chào, <?= htmlspecialchars($admin['user']) ?></span>
</header>
<main>
    <?php
}

function admin_layout_footer(): void { echo '</main></body></html>'; }
