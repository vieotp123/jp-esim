<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!function_exists('app_config')) {
    function app_config(?string $key = null, mixed $default = null): mixed {
        static $cfg = null;
        if ($cfg === null) {
            $paths = [
                dirname(APP_ROOT) . '/db_config.php',
                '/home/foamljf4kvet/db_config.php',
            ];
            $cfg = [];
            foreach ($paths as $path) {
                if (is_readable($path)) {
                    $loaded = require $path;
                    $cfg = is_array($loaded) ? $loaded : [];
                    break;
                }
            }
        }
        if ($key === null) return $cfg;
        if (array_key_exists($key, $cfg)) return $cfg[$key];
        $env = getenv($key);
        return ($env !== false && $env !== '') ? $env : $default;
    }
}

if (!function_exists('app_debug')) {
    function app_debug(): bool {
        return in_array(strtolower((string)app_config('APP_DEBUG', '0')), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('app_log')) {
    function app_log(string $message, string $level = 'INFO'): void {
        $path = (string)app_config('LOG_PATH', sys_get_temp_dir() . '/jpesim_app.log');
        @file_put_contents($path, '[' . date('Y-m-d H:i:s') . "] [$level] $message\n", FILE_APPEND | LOCK_EX);
    }
}

require_once APP_ROOT . '/response.php';
require_once APP_ROOT . '/security.php';
require_once APP_ROOT . '/services/RateLimiter.php';
require_once __DIR__ . '/SupportAgentSanitizer.php';
require_once __DIR__ . '/SupportAgentConfig.php';
