<?php
declare(strict_types=1);
/**
 * /r/qr.php?o=<orderId>&i=<iccid> — retail QR PNG (self-hosted).
 * Validates orderId+iccid via esimlist; serves PNG; never echoes LPA/provider URLs.
 */
require_once '/home/foamljf4kvet/app/bootstrap.php';

$orderId = strtoupper(trim((string)($_GET['o'] ?? '')));
$iccid   = preg_replace('/[^0-9]/', '', (string)($_GET['i'] ?? '')) ?? '';
if ($orderId === '' || !preg_match('/^[A-Z0-9]{4,32}$/', $orderId)) { http_response_code(400); exit; }
if ($iccid === '' || !preg_match('/^\d{10,25}$/', $iccid)) { http_response_code(400); exit; }

try {
    $st = db()->prepare('SELECT ac FROM esimlist WHERE BINARY order_id = BINARY ? AND iccid = ? LIMIT 1');
    $st->execute([$orderId, $iccid]);
    $ac = (string)($st->fetchColumn() ?: '');
} catch (Throwable $e) { if(function_exists('app_log')) app_log('r/qr db err: '.$e->getMessage(),'ERROR'); http_response_code(500); exit; }

if ($ac === '' || stripos($ac, 'LPA:') !== 0) { http_response_code(404); exit; }

try {
    $png = QrService::pngBytes($ac, 6, 2, 'M');
} catch (Throwable $e) { if(function_exists('app_log')) app_log('r/qr render err: '.$e->getMessage(),'ERROR'); http_response_code(500); exit; }

header('Content-Type: image/png');
header('Cache-Control: private, max-age=600');
header('Content-Length: ' . strlen($png));
header('X-Content-Type-Options: nosniff');
echo $png;
