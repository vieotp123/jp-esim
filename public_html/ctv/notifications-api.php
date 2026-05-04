<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
security_headers(false);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = CtvAuth::currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Chưa xác thực'], JSON_UNESCAPED_UNICODE);
    exit;
}

$svc = new CtvNotificationService();
$ctvId = (int)$user['id'];

$action = (string)($_GET['action'] ?? 'list');

if ($action === 'list') {
    $limit = max(1, min((int)($_GET['limit'] ?? 20), 100));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    echo json_encode([
        'ok' => true,
        'data' => [
            'unread' => $svc->countUnread($ctvId),
            'notifications' => $svc->list($ctvId, $limit, $offset),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $nid = (int)($body['id'] ?? 0);
    if ($nid > 0) {
        $svc->markRead($ctvId, $nid);
    } else {
        $svc->markAllRead($ctvId);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Hành động không hợp lệ'], JSON_UNESCAPED_UNICODE);
