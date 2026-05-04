<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
security_headers(false);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

$publicActions = ['authenticate_begin', 'authenticate_finish'];
$user = null;

if (!in_array($action, $publicActions, true)) {
    $user = CtvAuth::currentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Vui lòng đăng nhập'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $svc = new PasskeyService();

    switch ($action) {
        case 'register_begin':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Phương thức không hợp lệ');
            $options = $svc->registerBegin(
                'ctv',
                (int)$user['id'],
                (string)$user['email'],
                (string)($user['display_name'] ?: $user['email'])
            );
            echo json_encode(['ok' => true, 'data' => $options], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'register_finish':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Phương thức không hợp lệ');
            $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
            $clientDataJSON = base64_decode((string)($body['clientDataJSON'] ?? ''));
            $attestationObject = base64_decode((string)($body['attestationObject'] ?? ''));
            $deviceName = trim((string)($body['deviceName'] ?? ''));
            if ($clientDataJSON === '' || $attestationObject === '') {
                throw new InvalidArgumentException('Dữ liệu attestation không hợp lệ');
            }
            $result = $svc->registerFinish('ctv', (int)$user['id'], $clientDataJSON, $attestationObject, $deviceName ?: null);
            echo json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'authenticate_begin':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Phương thức không hợp lệ');
            $rl = new RateLimiter();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!$rl->check('passkey_auth:' . $ip, 20, 60)) {
                throw new RuntimeException('Quá nhiều yêu cầu. Vui lòng thử lại sau.');
            }
            $options = $svc->authenticateBegin('ctv', null);
            echo json_encode(['ok' => true, 'data' => $options], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'authenticate_finish':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Phương thức không hợp lệ');
            $rl = new RateLimiter();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!$rl->check('passkey_auth:' . $ip, 20, 60)) {
                throw new RuntimeException('Quá nhiều yêu cầu. Vui lòng thử lại sau.');
            }
            $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
            $credentialIdB64 = (string)($body['credentialId'] ?? '');
            $clientDataJSON = base64_decode((string)($body['clientDataJSON'] ?? ''));
            $authenticatorData = base64_decode((string)($body['authenticatorData'] ?? ''));
            $signature = base64_decode((string)($body['signature'] ?? ''));
            $userHandle = isset($body['userHandle']) ? (string)$body['userHandle'] : null;
            if ($credentialIdB64 === '' || $clientDataJSON === '' || $authenticatorData === '' || $signature === '') {
                throw new InvalidArgumentException('Dữ liệu assertion không hợp lệ');
            }
            $result = $svc->authenticateFinish('ctv', $credentialIdB64, $clientDataJSON, $authenticatorData, $signature, $userHandle);
            $session = CtvAuth::loginWithPasskey($result['userId']);
            echo json_encode(['ok' => true, 'data' => ['email' => $session['email']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'list':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new InvalidArgumentException('Phương thức không hợp lệ');
            $credentials = $svc->listCredentials('ctv', (int)$user['id']);
            echo json_encode(['ok' => true, 'data' => ['passkeys' => $credentials]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'revoke':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Phương thức không hợp lệ');
            $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
            $passkeyId = (int)($body['id'] ?? 0);
            if ($passkeyId <= 0) throw new InvalidArgumentException('ID không hợp lệ');
            $ok = $svc->revokeCredential('ctv', (int)$user['id'], $passkeyId);
            if (!$ok) throw new RuntimeException('Không thể xoá passkey');
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
    app_log('passkey-api error: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine(), 'ERROR');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => app_debug() ? $e->getMessage() : 'Lỗi hệ thống'], JSON_UNESCAPED_UNICODE);
}
