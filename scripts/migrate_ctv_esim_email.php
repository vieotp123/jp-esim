<?php
declare(strict_types=1);
/**
 * Idempotent migration: add email tracking columns to ctv_esims.
 * Run from CLI: sudo -u www-data php scripts/migrate_ctv_esim_email.php
 */
require_once '/home/foamljf4kvet/app/bootstrap.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function colExists(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
    $st->execute([$table, $col]);
    return ((int)$st->fetchColumn()) > 0;
}

$changes = [];
if (!colExists($pdo, 'ctv_esims', 'email_sent_at')) {
    $pdo->exec("ALTER TABLE ctv_esims ADD COLUMN email_sent_at DATETIME NULL DEFAULT NULL AFTER updated_at");
    $changes[] = 'email_sent_at';
}
if (!colExists($pdo, 'ctv_esims', 'email_attempts')) {
    $pdo->exec("ALTER TABLE ctv_esims ADD COLUMN email_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER email_sent_at");
    $changes[] = 'email_attempts';
}
if (!colExists($pdo, 'ctv_esims', 'email_last_error')) {
    $pdo->exec("ALTER TABLE ctv_esims ADD COLUMN email_last_error VARCHAR(255) NULL DEFAULT NULL AFTER email_attempts");
    $changes[] = 'email_last_error';
}

echo "added: " . (empty($changes) ? "(nothing — already migrated)" : implode(',', $changes)) . "\n";
