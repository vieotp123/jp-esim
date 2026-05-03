<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = (string)app_config('DB_HOST', 'localhost');
    $port = (int)app_config('DB_PORT', 3306);
    $name = (string)app_config('DB_NAME', '');
    $user = (string)app_config('DB_USER', '');
    $pass = (string)app_config('DB_PASS', '');
    $charset = (string)app_config('DB_CHARSET', 'utf8mb4');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}
