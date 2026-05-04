<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';

security_headers(false);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$admin = admin_ctv_require();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

try {
    $svc = new PasskeyService();
    $adminUser = $admin['user'];
    $adminId = crc32($adminUser);

    switch ($action) {
        case 'register_begin':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Method not allowed');
            $options = $svc->registerBegin('admin', $adminId, $adminUser, $adminUser);
            echo json_encode(['ok' => true, 'data' => $options], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'register_finish':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Method not allowed');
            $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
            $clientDataJSON = base64_decode((string)($body['clientDataJSON'] ?? ''));
            $attestationObject = base64_decode((string)($body['attestationObject'] ?? ''));
            $deviceName = trim((string)($body['deviceName'] ?? ''));
            if ($clientDataJSON === '' || $attestationObject === '') {
                throw new InvalidArgumentException('Dữ liệu attestation không hợp lệ');
            }
            $result = $svc->registerFinish('admin', $adminId, $clientDataJSON, $attestationObject, $deviceName ?: null);
            AuditLog::log($adminUser, 'admin_passkey_register', 'admin', (string)$adminId);
            echo json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'authenticate_begin':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Method not allowed');
            $options = $svc->authenticateBegin('admin', $adminId);
            echo json_encode(['ok' => true, 'data' => $options], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'authenticate_finish':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Method not allowed');
            $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
            $credentialIdB64 = (string)($body['credentialId'] ?? '');
            $clientDataJSON = base64_decode((string)($body['clientDataJSON'] ?? ''));
            $authenticatorData = base64_decode((string)($body['authenticatorData'] ?? ''));
            $signature = base64_decode((string)($body['signature'] ?? ''));
            $userHandle = isset($body['userHandle']) ? (string)$body['userHandle'] : null;
            if ($credentialIdB64 === '' || $clientDataJSON === '' || $authenticatorData === '' || $signature === '') {
                throw new InvalidArgumentException('Dữ liệu assertion không hợp lệ');
            }
            $svc->authenticateFinish('admin', $credentialIdB64, $clientDataJSON, $authenticatorData, $signature, $userHandle);
            admin_session_start();
            $_SESSION['admin_passkey_verified'] = 1;
            $_SESSION['admin_passkey_verified_at'] = time();
            AuditLog::log($adminUser, 'admin_passkey_verify', 'admin', (string)$adminId);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;

        case 'list':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new InvalidArgumentException('Method not allowed');
            $credentials = $svc->listCredentials('admin', $adminId);
            echo json_encode(['ok' => true, 'data' => ['passkeys' => $credentials]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'revoke':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Method not allowed');
            $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
            $passkeyId = (int)($body['id'] ?? 0);
            if ($passkeyId <= 0) throw new InvalidArgumentException('ID không hợp lệ');
            $ok = $svc->revokeCredential('admin', $adminId, $passkeyId);
            if (!$ok) throw new RuntimeException('Không thể xoá passkey');
            AuditLog::log($adminUser, 'admin_passkey_revoke', 'admin', (string)$adminId, ['passkeyId' => $passkeyId]);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;

        default:
            throw new InvalidArgumentException('Action không hợp lệ');
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    app_log('admin passkey-api error: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine(), 'ERROR');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => app_debug() ? $e->getMessage() : 'Lỗi hệ thống'], JSON_UNESCAPED_UNICODE);
}
