<?php
declare(strict_types=1);
require_once __DIR__ . '/_dispatch.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') ctv_api_dispatch('orders.create');
if ($method === 'GET') {
    if (isset($_GET['id']) && $_GET['id'] !== '') ctv_api_dispatch('orders.get');
    ctv_api_dispatch('orders.list');
}
http_response_code(405);
echo json_encode(['ok' => false, 'code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']);
