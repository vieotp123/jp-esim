<?php
declare(strict_types=1);
/**
 * /ctv/qr.png?id=<ICCID>
 *
 * Self-hosted QR PNG renderer. Looks up the eSIM by ICCID, verifies it
 * belongs to the current logged-in CTV, then renders the LPA payload
 * (column ctv_esims.ac) directly. Provider domains are NOT exposed.
 */
require_once '/home/foamljf4kvet/app/bootstrap.php';

$user = CtvAuth::requireUser();

$iccid = isset($_GET['id']) ? (string)$_GET['id'] : '';
if ($iccid === '' || !preg_match('/^\d{18,22}$/', $iccid)) {
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

try {
    $bytes = QrService::pngBytes($lpa, 8, 2, 'M');
} catch (Throwable $e) {
    if (function_exists('app_log')) app_log('QR render fail '.$iccid.' '.$e->getMessage(), 'ERROR');
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Lỗi tạo mã QR';
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . strlen($bytes));
header('X-Content-Type-Options: nosniff');
echo $bytes;
