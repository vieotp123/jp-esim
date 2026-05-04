<?php
declare(strict_types=1);

function assert_true(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

function app_log(string $message, string $level = 'INFO'): void {}

$root = dirname(__DIR__);

$apiTopup = file_get_contents($root . '/public_html/api/topup.php');
assert_true($apiTopup !== false, 'read retail topup API');
assert_true(strpos($apiTopup, "app_config('TOPUP_LOCKED'") !== false, 'retail topup API checks TOPUP_LOCKED');
assert_true(strpos($apiTopup, "json_error('TOPUP_LOCKED'") !== false, 'retail topup API returns TOPUP_LOCKED error');
assert_true(strpos($apiTopup, "if (\$_SERVER['REQUEST_METHOD'] === 'GET') json_ok(\$svc->lookup") !== false, 'retail topup lookup remains allowed');
$topupCase = substr($apiTopup, strpos($apiTopup, "case 'topup':"));
assert_true(strpos($topupCase, "app_config('TOPUP_LOCKED'") < strpos($topupCase, 'read_json_body()'), 'retail topup lock is checked before POST body handling');

$topupService = file_get_contents($root . '/home/foamljf4kvet/app/services/TopupService.php');
assert_true($topupService !== false, 'read TopupService');
assert_true(strpos($topupService, "app_config('TOPUP_LOCKED'") !== false, 'TopupService create has TOPUP_LOCKED defense');

$ctvLogin = file_get_contents($root . '/public_html/ctv/login.php');
assert_true($ctvLogin !== false, 'read CTV login');
assert_true(strpos($ctvLogin, 'ctv_login_ip:') !== false, 'CTV password login has IP rate limit');
assert_true(strpos($ctvLogin, 'ctv_login_email:') !== false, 'CTV password login has IP+email rate limit');

$adminGuard = file_get_contents($root . '/public_html/admin/ctv/_guard.php');
assert_true($adminGuard !== false, 'read admin guard');
assert_true(strpos($adminGuard, 'admin_basic_auth:') !== false, 'admin Basic Auth has failed-attempt rate limit');
assert_true(strpos($adminGuard, "empty(\$_SESSION['admin_authenticated'])") !== false, 'admin Basic Auth skips limiter for authenticated sessions');
assert_true(strpos($adminGuard, 'RateLimiter::isAdminIp()') !== false, 'admin Basic Auth preserves configured admin IP bypass');

require_once $root . '/home/foamljf4kvet/app/services/RateLimiter.php';
$dir = sys_get_temp_dir() . '/jpesim_ratelimit_test_' . bin2hex(random_bytes(6));
$rl = new RateLimiter($dir);
assert_true($rl->check('security-test', 2, 60), 'RateLimiter allows first hit');
assert_true($rl->check('security-test', 2, 60), 'RateLimiter allows second hit');
assert_true(!$rl->check('security-test', 2, 60), 'RateLimiter blocks over limit');
array_map('unlink', glob($dir . '/*.json') ?: []);
@rmdir($dir);

echo "Security hardening checks passed.\n";
