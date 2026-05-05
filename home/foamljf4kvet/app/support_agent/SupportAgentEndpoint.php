<?php
declare(strict_types=1);

require_once __DIR__ . '/SupportAgentService.php';

final class SupportAgentEndpoint {
    private const MAX_BODY_BYTES = 8192;
    private const MAX_MESSAGE_CHARS = 1600;

    public function handle(array $payload, array $server = [], ?RateLimiter $rateLimiter = null, ?SupportAgentService $service = null): array {
        $server = $server ?: $_SERVER;
        $method = strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST') {
            return $this->error('METHOD_NOT_ALLOWED', 'Phương thức không hợp lệ', 405);
        }
        if (!$this->sameOrigin($server)) {
            return $this->error('ORIGIN_DENIED', 'Nguồn yêu cầu không hợp lệ.', 403);
        }
        $contentLength = (int)($server['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > self::MAX_BODY_BYTES) {
            return $this->error('PAYLOAD_TOO_LARGE', 'Tin nhắn quá dài. Vui lòng rút gọn nội dung.', 413);
        }
        $message = trim((string)($payload['message'] ?? ''));
        if ($message === '') {
            return $this->error('VALIDATION_ERROR', 'Vui lòng nhập nội dung cần hỗ trợ.', 400);
        }
        if (mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE_CHARS) {
            return $this->error('MESSAGE_TOO_LONG', 'Tin nhắn tối đa 1600 ký tự.', 413);
        }
        if (!$this->isAdminIp($server)) {
            $rl = $rateLimiter ?? new RateLimiter();
            $ip = (string)($server['REMOTE_ADDR'] ?? 'unknown');
            if (!$rl->check('api:support-agent:' . $ip, SupportAgentConfig::value('rate_limit_max'), SupportAgentConfig::value('rate_limit_window_seconds'))) {
                return $this->error('RATE_LIMITED', 'Quá nhiều yêu cầu. Vui lòng thử lại sau.', 429);
            }
        }

        $data = ($service ?? new SupportAgentService())->handle($payload);
        return ['status' => 200, 'body' => $data];
    }

    public function sameOrigin(array $server): bool {
        $origin = trim((string)($server['HTTP_ORIGIN'] ?? ''));
        $referer = trim((string)($server['HTTP_REFERER'] ?? ''));
        $host = strtolower($this->hostOnly((string)($server['HTTP_HOST'] ?? '')));
        if ($host === '') return true;
        if ($origin !== '') {
            return strtolower($this->hostOnly((string)(parse_url($origin, PHP_URL_HOST) ?: ''))) === $host;
        }
        if ($referer !== '') {
            return strtolower($this->hostOnly((string)(parse_url($referer, PHP_URL_HOST) ?: ''))) === $host;
        }
        return true;
    }

    private function hostOnly(string $host): string {
        $host = trim($host);
        if ($host === '') return '';
        if ($host[0] === '[') {
            $end = strpos($host, ']');
            return $end === false ? $host : substr($host, 1, $end - 1);
        }
        return explode(':', $host, 2)[0];
    }

    private function isAdminIp(array $server): bool {
        $ip = (string)($server['REMOTE_ADDR'] ?? '');
        $raw = (string)app_config('ADMIN_IPS', getenv('ADMIN_IPS') ?: '');
        if ($raw === '') return false;
        $list = array_map('trim', explode(',', $raw));
        return in_array($ip, $list, true);
    }

    private function error(string $code, string $message, int $status): array {
        return ['status' => $status, 'body' => ['ok' => false, 'code' => $code, 'message' => $message]];
    }
}
