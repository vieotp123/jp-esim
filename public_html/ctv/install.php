<?php
declare(strict_types=1);
/**
 * /ctv/install.php?id=<ICCID>
 *
 * Server-side redirect to Apple's eSIM install URL (esimsetup.apple.com).
 * The LPA payload is built server-side from ctv_esims.ac and passed as
 * carddata=…; the user lands directly on Apple, never sees provider URLs.
 * Per-CTV ownership enforced.
 */
require_once '/home/foamljf4kvet/app/bootstrap.php';

$user = CtvAuth::requireUser();

$iccid = isset($_GET['id']) ? (string)$_GET['id'] : '';
if (!preg_match('/^\d{18,22}$/', $iccid)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'bad id';
    exit;
}

$st = db()->prepare('SELECT ac FROM ctv_esims WHERE iccid = ? AND ctv_id = ? LIMIT 1');
$st->execute([$iccid, (int)$user['id']]);
$row = $st->fetch();
if (!$row || empty($row['ac'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'not found';
    exit;
}

$lpa = trim((string)$row['ac']);
if (stripos($lpa, 'LPA:') !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'no lpa';
    exit;
}

$target = 'https://esimsetup.apple.com/esim_qrcode_provisioning?carddata=' . rawurlencode($lpa);
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
header('Location: ' . $target, true, 302);
echo "<!doctype html><meta charset=utf-8><title>Đang chuyển…</title><a href=\"" . htmlspecialchars($target, ENT_QUOTES) . "\">Continue</a>";
