<?php
declare(strict_types=1);
/**
 * /api/ctv/esim_qr.php?iccid=<iccid> — CTV API QR PNG (key-auth, scoped per-CTV).
 *
 * Returns a self-rendered PNG of the eSIM LPA. Key-auth via Authorization: Bearer
 * <key> or X-API-Key header. Owner-scoped: 404 if iccid not in this CTV's pool.
 */
require_once '/home/foamljf4kvet/app/bootstrap.php';
security_headers(false);

// Light error/json helpers (consistent with _dispatch.php style).
function _ctv_qr_err(int $code, string $msg): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok'=>false,'error'=>['code'=>$code===401?'UNAUTHORIZED':($code===404?'NOT_FOUND':'BAD_REQUEST'),'message'=>$msg]], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') _ctv_qr_err(405, 'Phương thức không hợp lệ');

// Auth — reuse helpers from _dispatch.
require_once __DIR__ . '/_dispatch.php';
$token = ctv_api_key_from_request();
if ($token === '') _ctv_qr_err(401, 'Cần API key');
$ctv = (new CtvApiKeyService())->authenticate($token);
if (!$ctv) _ctv_qr_err(401, 'API key không hợp lệ');

$iccid = preg_replace('/[^0-9]/', '', (string)($_GET['iccid'] ?? '')) ?? '';
if ($iccid === '' || !preg_match('/^\d{10,25}$/', $iccid)) _ctv_qr_err(400, 'ICCID không hợp lệ');

try {
    $st = db()->prepare('SELECT ac FROM ctv_esims WHERE iccid = ? AND ctv_id = ? LIMIT 1');
    $st->execute([$iccid, (int)$ctv['id']]);
    $ac = (string)($st->fetchColumn() ?: '');
} catch (Throwable $e) {
    if (function_exists('app_log')) app_log('api esim_qr db err: '.$e->getMessage(), 'ERROR');
    _ctv_qr_err(500, 'Lỗi hệ thống');
}

if ($ac === '' || stripos($ac, 'LPA:') !== 0) _ctv_qr_err(404, 'Không tìm thấy');

try { $png = QrService::pngBytes($ac, 8, 2, 'M'); }
catch (Throwable $e) {
    if (function_exists('app_log')) app_log('api esim_qr render err: '.$e->getMessage(), 'ERROR');
    _ctv_qr_err(500, 'Lỗi tạo mã QR');
}

header('Content-Type: image/png');
header('Cache-Control: private, max-age=600');
header('Content-Length: ' . strlen($png));
header('X-Content-Type-Options: nosniff');
echo $png;
