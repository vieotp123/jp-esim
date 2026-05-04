<?php
declare(strict_types=1);
/**
 * /ctv/install.php?id=<ICCID>
 *
 * Server-side redirect to Apple eSIM provisioning.
 * LPA payload built server-side from ctv_esims.ac.
 * Per-CTV ownership enforced.
 */
require_once '/home/foamljf4kvet/app/bootstrap.php';

$user = CtvAuth::requireUser();

$iccid = isset($_GET['id']) ? (string)$_GET['id'] : '';
if (!preg_match('/^\d{18,22}$/', $iccid)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Mã không hợp lệ';
    exit;
}

$st = db()->prepare('SELECT ac FROM ctv_esims WHERE iccid = ? AND ctv_id = ? LIMIT 1');
$st->execute([$iccid, (int)$user['id']]);
$row = $st->fetch();
if (!$row || empty($row['ac'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Không tìm thấy';
    exit;
}

$lpa = trim((string)$row['ac']);
if (stripos($lpa, 'LPA:') !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Không có dữ liệu';
    exit;
}

$target = 'https://esimsetup.apple.com/esim_qrcode_provisioning?carddata=' . rawurlencode($lpa);
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
header('Location: ' . $target, true, 302);
echo '<!doctype html><meta charset=utf-8><title>Đang chuyển…</title><p>Đang chuyển hướng cài đặt eSIM…</p>';
