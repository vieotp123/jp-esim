<?php
declare(strict_types=1);

final class SupportAgentSanitizer {
    public static function text(mixed $value, int $maxLen = 2000): string {
        $text = is_scalar($value) ? (string)$value : '';
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[^\P{C}\t\r\n]+/u', '', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        return trim(mb_substr($text, 0, $maxLen, 'UTF-8'));
    }

    public static function locale(mixed $value): string {
        $locale = strtolower(self::text($value, 16));
        return preg_match('/^[a-z]{2}([_-][a-z]{2})?$/', $locale) ? $locale : 'vi';
    }

    public static function conversationId(mixed $value): string {
        $id = self::text($value, 80);
        if (preg_match('/^[A-Za-z0-9_-]{12,80}$/', $id)) return $id;
        return 'sa_' . bin2hex(random_bytes(16));
    }

    public static function pageContext(mixed $value): array {
        if (!is_array($value)) return [];
        $allowed = ['path', 'title', 'section', 'product_type'];
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $value)) {
                $out[$key] = self::redact(self::text($value[$key], 160));
            }
        }
        return array_filter($out, static fn($v): bool => $v !== '');
    }

    public static function redact(string $text): string {
        $text = preg_replace('/([A-Z0-9._%+\-]{2})[A-Z0-9._%+\-]*(@[A-Z0-9.\-]+\.[A-Z]{2,})/iu', '$1***$2', $text) ?? $text;
        $text = preg_replace_callback('/\b(89\d{13,30})\b/', static function (array $m): string {
            return substr($m[1], 0, 6) . str_repeat('*', max(4, strlen($m[1]) - 10)) . substr($m[1], -4);
        }, $text) ?? $text;
        $text = preg_replace('/\b([NT][A-Z0-9]{2})[A-Z0-9]{3,12}([A-Z0-9]{2})\b/i', '$1***$2', $text) ?? $text;
        $text = preg_replace('/\b(\+?\d[\d .\-]{7,}\d)\b/', '[số điện thoại đã ẩn]', $text) ?? $text;
        $text = preg_replace('/(?i)\b(api[_ -]?key|secret|token|password|access[_ -]?code)\s*[:=]\s*\S+/', '$1=[đã ẩn]', $text) ?? $text;
        $text = preg_replace('/(?i)\b(api[_ -]?key|secret|token|password|credential|system prompt|internal prompt|package[_ -]?(code|name)|packcode|packagename|provider[_ -]?(domain|name|url)|database|db_config|endpoint)\b/u', '[nội dung nội bộ đã ẩn]', $text) ?? $text;
        return $text;
    }

    public static function containsForbiddenLeak(string $text): bool {
        return (bool)preg_match('/(?i)\b(api[_ -]?key|secret|token|password|credential|database|db_config|system prompt|internal prompt|package[_ -]?(code|name)|packcode|packagename|provider[_ -]?(domain|name|url)|admin|partner|ctv|sql|endpoint)\b/u', $text);
    }
}
