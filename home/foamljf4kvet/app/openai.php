<?php
declare(strict_types=1);
final class OpenAIHelper {
    public function classify(string $message): ?array {
        $key = (string)app_config('OPENAI_API_KEY', '');
        if ($key === '' || !function_exists('curl_init')) return null;
        $model = (string)app_config('OPENAI_MODEL', 'gpt-4o-mini');
        $prompt = 'Bạn chỉ trả JSON. Phân loại intent bán eSIM: list_plans,recommend_plan,create_order,payment_status,get_esim,topup_info,create_topup,check_usage,check_expiry,human_support,unknown. Extract email, orderId, tid, iccid, gb, carrier nếu có.';
        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $message],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
        ];
        try {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            if (!$ch) return null;
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                ],
                CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
            $j = $raw ? json_decode($raw, true) : null;
            $content = $j['choices'][0]['message']['content'] ?? null;
            $out = $content ? json_decode($content, true) : null;
            return is_array($out) ? $out : null;
        } catch (Throwable $e) {
            app_log('openai classify failed ' . $e->getMessage(), 'WARN');
            return null;
        }
    }
}
