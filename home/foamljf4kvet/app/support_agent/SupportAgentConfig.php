<?php
declare(strict_types=1);

final class SupportAgentConfig {
    private const BOOL_TRUE = ['1', 'true', 'yes', 'on'];

    public static function definitions(): array {
        return [
            'enabled' => [
                'key' => 'SUPPORT_AGENT_ENABLED',
                'type' => 'bool',
                'default' => false,
                'label' => 'Enable AI provider calls',
                'description' => 'When disabled, the endpoint still returns local safety-filtered knowledge-base answers and never calls the AI provider.',
            ],
            'provider' => [
                'key' => 'SUPPORT_AGENT_PROVIDER',
                'type' => 'string',
                'default' => '9router',
                'allowed' => ['9router'],
                'label' => 'AI provider',
                'description' => 'Provider adapter used by the independent support agent.',
            ],
            'router_api_key' => [
                'key' => 'SUPPORT_AGENT_9ROUTER_API_KEY',
                'type' => 'secret',
                'default' => '',
                'label' => '9router API key',
                'description' => 'Secret token for the AI provider. Store only in environment or server config.',
            ],
            'router_model' => [
                'key' => 'SUPPORT_AGENT_9ROUTER_MODEL',
                'type' => 'string',
                'default' => 'support-agent',
                'label' => '9router model',
                'description' => 'Chat model name to send to the provider adapter.',
            ],
            'router_endpoint' => [
                'key' => 'SUPPORT_AGENT_9ROUTER_ENDPOINT',
                'type' => 'url',
                'default' => 'https://api.9router.ai/v1/chat/completions',
                'label' => '9router endpoint',
                'description' => 'HTTPS chat completions endpoint for the provider adapter.',
            ],
            'widget_enabled' => [
                'key' => 'SUPPORT_AGENT_WIDGET_ENABLED',
                'type' => 'bool',
                'default' => true,
                'label' => 'Show support widget',
                'description' => 'Controls whether the PHP widget include renders CSS and JavaScript tags.',
            ],
            'memory_path' => [
                'key' => 'SUPPORT_AGENT_MEMORY_PATH',
                'type' => 'path',
                'default' => dirname(APP_ROOT) . '/support_agent_state',
                'label' => 'Conversation memory path',
                'description' => 'Server-side JSON memory directory outside public_html.',
            ],
            'memory_ttl_seconds' => [
                'key' => 'SUPPORT_AGENT_MEMORY_TTL_SECONDS',
                'type' => 'int',
                'default' => 86400,
                'min' => 300,
                'max' => 2592000,
                'label' => 'Memory TTL seconds',
                'description' => 'How long recent redacted conversation turns may be reused.',
            ],
            'memory_message_limit' => [
                'key' => 'SUPPORT_AGENT_MEMORY_MESSAGE_LIMIT',
                'type' => 'int',
                'default' => 8,
                'min' => 2,
                'max' => 40,
                'label' => 'Memory message limit',
                'description' => 'Maximum stored redacted message entries per conversation.',
            ],
            'rate_limit_max' => [
                'key' => 'SUPPORT_AGENT_RATE_LIMIT_MAX',
                'type' => 'int',
                'default' => 12,
                'min' => 1,
                'max' => 120,
                'label' => 'Rate limit requests',
                'description' => 'Maximum POST requests per client IP in the rate-limit window.',
            ],
            'rate_limit_window_seconds' => [
                'key' => 'SUPPORT_AGENT_RATE_LIMIT_WINDOW_SECONDS',
                'type' => 'int',
                'default' => 60,
                'min' => 10,
                'max' => 3600,
                'label' => 'Rate limit window seconds',
                'description' => 'Sliding window length for endpoint rate limiting.',
            ],
            'escalation_text' => [
                'key' => 'SUPPORT_AGENT_ESCALATION_TEXT',
                'type' => 'text',
                'default' => 'Dạ em đã ghi nhận yêu cầu cần nhân viên hỗ trợ. Anh/chị vui lòng để lại mã đơn hoặc ICCID đã được che bớt thông tin nhạy cảm, nhân viên sẽ kiểm tra và phản hồi.',
                'label' => 'Escalation text',
                'description' => 'Customer-facing fallback text when a human support handoff is needed.',
            ],
        ];
    }

    public static function defaults(): array {
        $defaults = [];
        foreach (self::definitions() as $name => $definition) {
            $defaults[$name] = $definition['default'];
        }
        return $defaults;
    }

    public static function value(string $name): mixed {
        $definitions = self::definitions();
        if (!isset($definitions[$name])) {
            throw new InvalidArgumentException('Unknown support agent config: ' . $name);
        }
        $definition = $definitions[$name];
        $raw = app_config((string)$definition['key'], $definition['default']);
        if ($raw === '' && $definition['default'] !== '') {
            $raw = $definition['default'];
        }
        return match ($definition['type']) {
            'bool' => in_array(strtolower((string)$raw), self::BOOL_TRUE, true),
            'int' => self::intValue($raw, (int)$definition['default'], (int)$definition['min'], (int)$definition['max']),
            'secret' => (string)$raw,
            default => SupportAgentSanitizer::text($raw, 500),
        };
    }

    public static function publicSchema(): array {
        $schema = self::definitions();
        foreach ($schema as &$definition) {
            if (($definition['type'] ?? '') === 'secret') {
                $definition['default'] = '';
                $definition['secret'] = true;
            }
        }
        unset($definition);
        return $schema;
    }

    private static function intValue(mixed $value, int $default, int $min, int $max): int {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            $int = $default;
        }
        return max($min, min($max, (int)$int));
    }
}
