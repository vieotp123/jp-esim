#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$bootstrap = '/home/foamljf4kvet/app/bootstrap.php';
if (!is_readable($bootstrap)) {
    $bootstrap = __DIR__ . '/../home/foamljf4kvet/app/bootstrap.php';
}
require_once $bootstrap;

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
