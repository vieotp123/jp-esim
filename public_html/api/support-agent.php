<?php
declare(strict_types=1);

$bootstrap = dirname(__DIR__, 2) . '/home/foamljf4kvet/app/support_agent/SupportAgentBootstrap.php';
if (!is_file($bootstrap)) {
    $bootstrap = '/home/foamljf4kvet/app/support_agent/SupportAgentBootstrap.php';
}
require_once $bootstrap;
require_once APP_ROOT . '/support_agent/SupportAgentEndpoint.php';

security_headers(false);
header('Cache-Control: no-store');
header('Pragma: no-cache');

try {
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
        http_response_code(413);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'code' => 'PAYLOAD_TOO_LARGE', 'message' => 'Tin nhắn quá dài. Vui lòng rút gọn nội dung.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $payload = read_json_body();
    $result = (new SupportAgentEndpoint())->handle($payload);
    http_response_code((int)$result['status']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    app_log('support-agent endpoint failed: ' . $e->getMessage(), 'ERROR');
    json_error('SERVER_ERROR', app_debug() ? $e->getMessage() : 'Lỗi hệ thống', 500);
}
