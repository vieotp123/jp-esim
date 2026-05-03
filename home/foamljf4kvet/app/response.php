<?php
declare(strict_types=1);
function json_ok(array $data = [], int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function json_error(string $code, string $message, int $status = 400, array $extra = []): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $out = ['ok' => false, 'code' => $code, 'message' => $message];
    if (app_debug() && $extra) $out['debug'] = $extra;
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function read_json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function format_vnd(int|float $amount): string { return number_format((float)$amount, 0, ',', '.') . '₫'; }
