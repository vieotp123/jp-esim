<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $token = $_GET['token']
        ?? ($_SERVER['HTTP_X_WEBHOOK_TOKEN']
        ?? ($_SERVER['HTTP_SECURE_TOKEN'] ?? ''));
    if (!hash_equals((string)app_config('SECURE_TOKEN', ''), (string)$token)) {
        json_error('BAD_TOKEN', 'Bad token', 403);
    }
    $raw = file_get_contents('php://input') ?: '';
    $js = json_decode($raw, true);
    if (!is_array($js)) {
        json_error('BAD_PAYLOAD', 'Bad payload', 400);
    }
    $result = (new BankWebhookService())->process($js);
    json_ok($result);
} catch (Throwable $e) {
    app_log('bank webhook ' . $e->getMessage(), 'ERROR');
    json_error('SERVER_ERROR', 'Webhook error', 500);
}
