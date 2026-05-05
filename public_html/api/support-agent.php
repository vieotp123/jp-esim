<?php
declare(strict_types=1);

$bootstrap = dirname(__DIR__, 2) . '/home/foamljf4kvet/app/support_agent/SupportAgentBootstrap.php';
if (!is_file($bootstrap)) {
    $bootstrap = '/home/foamljf4kvet/app/support_agent/SupportAgentBootstrap.php';
}
require_once $bootstrap;
require_once APP_ROOT . '/support_agent/SupportAgentService.php';

security_headers(false);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'Phương thức không hợp lệ', 405);
    }
    if (!RateLimiter::isAdminIp()) {
        $rl = new RateLimiter();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$rl->check('api:support-agent:' . $ip, 12, 60)) {
            json_error('RATE_LIMITED', 'Quá nhiều yêu cầu. Vui lòng thử lại sau.', 429);
        }
    }
    $payload = read_json_body();
    if (!is_array($payload) || trim((string)($payload['message'] ?? '')) === '') {
        json_error('VALIDATION_ERROR', 'Vui lòng nhập nội dung cần hỗ trợ.', 400);
    }
    $data = (new SupportAgentService())->handle($payload);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    app_log('support-agent endpoint failed: ' . $e->getMessage(), 'ERROR');
    json_error('SERVER_ERROR', app_debug() ? $e->getMessage() : 'Lỗi hệ thống', 500);
}
