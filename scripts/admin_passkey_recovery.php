#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$bootstrap = admin_recovery_bootstrap_path();
if ($bootstrap === null) {
    admin_recovery_bootstrap_failure('Application bootstrap is not readable.');
}

$configPath = admin_recovery_db_config_path($bootstrap);
if ($configPath !== null && !is_readable($configPath)) {
    admin_recovery_bootstrap_failure('Database config is not readable by this shell user.', $configPath);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once $bootstrap;
} catch (Throwable) {
    admin_recovery_bootstrap_failure('Application bootstrap failed.');
} finally {
    restore_error_handler();
}

$command = (string)($argv[1] ?? 'status');
$adminUser = (string)app_config('ADMIN_USER', 'admin');
$adminId = crc32($adminUser);
$pdo = db();

$countAdminPasskeys = static function () use ($pdo, $adminId): int {
    $st = $pdo->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_type = ? AND user_id = ?');
    $st->execute(['admin', $adminId]);
    return (int)$st->fetchColumn();
};

if ($command === 'status') {
    echo 'admin_passkey_count=' . $countAdminPasskeys() . PHP_EOL;
    echo 'admin_require_passkey=' . (admin_recovery_config_value('ADMIN_REQUIRE_PASSKEY', '0') === '1' ? '1' : '0') . PHP_EOL;
    exit(0);
}

if ($command === 'remove-admin-passkeys') {
    if (($argv[2] ?? '') !== '--confirm-remove-admin-passkeys') {
        fwrite(STDERR, "Refusing to modify passkeys without --confirm-remove-admin-passkeys\n");
        exit(2);
    }

    $before = $countAdminPasskeys();
    $st = $pdo->prepare('DELETE FROM user_passkeys WHERE user_type = ? AND user_id = ?');
    $st->execute(['admin', $adminId]);
    $after = $countAdminPasskeys();

    echo 'removed_admin_passkeys=' . $before - $after . PHP_EOL;
    echo 'admin_passkey_count=' . $after . PHP_EOL;
    echo "Password auth remains unchanged. Visit /admin/ctv/passkey-setup.php to add a new admin passkey.\n";
    exit(0);
}

fwrite(STDERR, "Usage:\n");
fwrite(STDERR, "  php scripts/admin_passkey_recovery.php status\n");
fwrite(STDERR, "  php scripts/admin_passkey_recovery.php remove-admin-passkeys --confirm-remove-admin-passkeys\n");
exit(2);

function admin_recovery_config_value(string $key, string $default): string
{
    $value = app_config($key, $default);
    return is_scalar($value) ? (string)$value : $default;
}

function admin_recovery_bootstrap_path(): ?string
{
    $paths = [
        '/home/foamljf4kvet/app/bootstrap.php',
        __DIR__ . '/../home/foamljf4kvet/app/bootstrap.php',
    ];

    foreach ($paths as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}

function admin_recovery_db_config_path(string $bootstrap): ?string
{
    $absolutePath = '/home/foamljf4kvet/db_config.php';
    if (is_file($absolutePath)) {
        return $absolutePath;
    }

    $localPath = dirname($bootstrap) . '/../db_config.php';
    if (is_file($localPath)) {
        return $localPath;
    }

    return null;
}

function admin_recovery_bootstrap_failure(string $reason, ?string $path = null): never
{
    fwrite(STDERR, $reason . PHP_EOL);
    if ($path !== null) {
        fwrite(STDERR, 'Path: ' . $path . PHP_EOL);
    }
    fwrite(STDERR, "Run this command as the application owner or with appropriate production permissions, for example via sudo -u <app-owner> php scripts/admin_passkey_recovery.php status.\n");
    exit(1);
}
