<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

$configPath = '/home/foamljf4kvet/db_config.php';
if (!is_file($configPath)) {
    $configPath = APP_ROOT . '/../db_config.php';
}
$config = is_file($configPath) ? require $configPath : [];
if (!is_array($config)) { $config = []; }

function app_config(?string $key = null, mixed $default = null): mixed {
    static $cfg = null;
    if ($cfg === null) {
        $path = '/home/foamljf4kvet/db_config.php';
        if (!is_file($path)) { $path = __DIR__ . '/../db_config.php'; }
        $cfg = is_file($path) ? require $path : [];
        if (!is_array($cfg)) { $cfg = []; }
    }
    if ($key === null) return $cfg;
    if (array_key_exists($key, $cfg)) return $cfg[$key];
    $env = getenv($key);
    return ($env !== false && $env !== '') ? $env : $default;
}

function app_debug(): bool {
    $v = (string)app_config('APP_DEBUG', getenv('APP_DEBUG') ?: '0');
    return in_array(strtolower($v), ['1','true','yes','on'], true);
}

function app_log(string $message, string $level = 'INFO'): void {
    $path = (string)app_config('LOG_PATH', '/home/foamljf4kvet/app.log');
    @file_put_contents($path, '['.date('Y-m-d H:i:s')."] [$level] $message\n", FILE_APPEND | LOCK_EX);
}

spl_autoload_register(function (string $class): void {
    $class = str_replace('\\', '/', $class);
    $paths = [APP_ROOT . '/' . $class . '.php', APP_ROOT . '/services/' . basename($class) . '.php'];
    foreach ($paths as $p) { if (is_file($p)) { require_once $p; return; } }
});

require_once APP_ROOT . '/response.php';
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/security.php';
require_once APP_ROOT . '/vietqr.php';
require_once APP_ROOT . '/esimaccess.php';
