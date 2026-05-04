<?php
declare(strict_types=1);
/**
 * /r/install.php?o=<orderId>&i=<iccid>&os=ios|android — server-side redirect.
 * Hides LPA from page HTML/source. Browser URL bar still shows carddata after redirect (inherent).
 */
require_once '/home/foamljf4kvet/app/bootstrap.php';

$orderId = strtoupper(trim((string)($_GET['o'] ?? '')));
$iccid   = preg_replace('/[^0-9]/', '', (string)($_GET['i'] ?? '')) ?? '';
$os      = strtolower(trim((string)($_GET['os'] ?? 'ios')));
if ($os !== 'ios' && $os !== 'android') { http_response_code(400); exit; }
if ($orderId === '' || !preg_match('/^[A-Z0-9]{4,32}$/', $orderId)) { http_response_code(400); exit; }
if ($iccid === '' || !preg_match('/^\d{10,25}$/', $iccid)) { http_response_code(400); exit; }

try {
    $st = db()->prepare('SELECT ac FROM esimlist WHERE BINARY order_id = BINARY ? AND iccid = ? LIMIT 1');
    $st->execute([$orderId, $iccid]);
    $ac = (string)($st->fetchColumn() ?: '');
} catch (Throwable $e) { if(function_exists('app_log')) app_log('r/install db err: '.$e->getMessage(),'ERROR'); http_response_code(500); exit; }
if ($ac === '' || stripos($ac, 'LPA:') !== 0) { http_response_code(404); exit; }

$base = $os === 'ios'
    ? 'https://esimsetup.apple.com/esim_qrcode_provisioning'
    : 'https://esimsetup.android.com/esim_qrcode_provisioning';
header('Location: ' . $base . '?carddata=' . rawurlencode($ac), true, 302);
header('Cache-Control: private, no-store');
exit;
