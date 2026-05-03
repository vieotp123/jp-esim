<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
security_headers(false);

function ctv_api_response_error(string $code, string $message, int $status): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok'=>false,'error'=>['code'=>$code,'message'=>$message]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function ctv_api_response_ok(array $data = [], int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function ctv_api_key_from_request(): string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($auth, 'Bearer ') === 0) return trim(substr($auth, 7));
    $h = (string)($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($h !== '') return trim($h);
    return '';
}

function ctv_log_api_call(?array $ctv, ?int $apiKeyId, string $endpoint, string $method, mixed $reqBody, int $status, mixed $respBody, float $start): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $req = CtvProviderClient::redactJson($reqBody);
        $resp = CtvProviderClient::redactJson($respBody);
        db()->prepare('INSERT INTO ctv_api_logs(ctv_id,api_key_id,endpoint,method,ip,request_summary,response_status,response_summary,duration_ms) VALUES(?,?,?,?,?,?,?,?,?)')
            ->execute([$ctv['id'] ?? null, $apiKeyId, $endpoint, $method, $ip, $req, $status, $resp, (int)round((microtime(true) - $start) * 1000)]);
    } catch (Throwable $e) { app_log('ctv_api_logs insert failed: ' . $e->getMessage(), 'ERROR'); }
}

function ctv_api_authenticate(): array {
    $token = ctv_api_key_from_request();
    if ($token === '') ctv_api_response_error('UNAUTHORIZED', 'API key required', 401);
    $row = (new CtvApiKeyService())->authenticate($token);
    if (!$row) ctv_api_response_error('UNAUTHORIZED', 'API key khong hop le', 401);
    return $row;
}

function ctv_api_rate_limit(array $ctv, int $apiKeyId, string $endpoint): void {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $limit = (int)app_config('CTV_API_RATE_LIMIT_PER_MINUTE', 60);
    $st = db()->prepare('SELECT COUNT(*) FROM ctv_api_logs WHERE api_key_id=? AND endpoint=? AND ip=? AND created_at >= (NOW() - INTERVAL 60 SECOND)');
    $st->execute([$apiKeyId, $endpoint, $ip]);
    if ((int)$st->fetchColumn() >= $limit) ctv_api_response_error('RATE_LIMITED', 'Too many requests', 429);
}
function ctv_api_dispatch(string $endpoint): void {
    $start = microtime(true); $ctv = null; $apiKeyId = null;
    try {
        $ctv = ctv_api_authenticate();
        $apiKeyId = (int)($ctv['key_id'] ?? 0);
        ctv_api_rate_limit($ctv, $apiKeyId, $endpoint);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $body = ($method === 'POST' || $method === 'PUT') ? read_json_body() : $_GET;
        $resp = ctv_api_handle($endpoint, $method, $ctv, $body);
        ctv_log_api_call($ctv, $apiKeyId, $endpoint, $method, $body, 200, ['ok' => true], $start);
        ctv_api_response_ok($resp);
    } catch (InvalidArgumentException $e) {
        ctv_log_api_call($ctv, $apiKeyId, $endpoint, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_REQUEST, 400, $e->getMessage(), $start);
        $status = stripos($e->getMessage(), 'Method not allowed') !== false ? 405 : 400;
        ctv_api_response_error($status === 405 ? 'METHOD_NOT_ALLOWED' : 'VALIDATION_ERROR', $e->getMessage(), $status);
    } catch (RuntimeException $e) {
        ctv_log_api_call($ctv, $apiKeyId, $endpoint, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_REQUEST, 422, $e->getMessage(), $start);
        ctv_api_response_error('RUNTIME_ERROR', $e->getMessage(), 422);
    } catch (Throwable $e) {
        app_log('ctv api ['.$endpoint.'] '.$e->getMessage().' '.$e->getFile().':'.$e->getLine(), 'ERROR');
        ctv_log_api_call($ctv, $apiKeyId, $endpoint, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_REQUEST, 500, $e->getMessage(), $start);
        ctv_api_response_error('SERVER_ERROR', app_debug() ? $e->getMessage() : 'Loi he thong', 500);
    }
}

function ctv_api_handle(string $endpoint, string $method, array $ctv, mixed $body): array {
    $body = is_array($body) ? $body : [];
    switch ($endpoint) {
        case 'products':
            if ($method !== 'GET') throw new InvalidArgumentException('Method not allowed');
            $type = ($body['type'] ?? 'esim') === 'topup' ? 'topup' : 'esim';
            return (new CtvPricingService())->listFor($ctv, $type, $body['telecom'] ?? null);
        case 'quote':
            if ($method !== 'POST') throw new InvalidArgumentException('Method not allowed');
            $plan = (new PlanService())->findActive((int)($body['planId'] ?? 0));
            if (!$plan) throw new InvalidArgumentException('Gói không tồn tại');
            $qty = (int)($body['quantity'] ?? 1);
            if ($qty < 1 || $qty > 100) throw new InvalidArgumentException('Quantity must be between 1 and 100');
            $pricing = (new CtvPricingService())->priceFor($ctv, $plan);
            return ['planId' => (int)$plan['id'], 'quantity' => $qty, 'pricing' => $pricing, 'totalCharge' => $pricing['ctvPrice'] * $qty];
        case 'orders.create':
            if ($method !== 'POST') throw new InvalidArgumentException('Method not allowed');
            return (new CtvOrderService())->createEsim(
                $ctv,
                (int)($body['planId'] ?? 0),
                (int)($body['quantity'] ?? 1),
                'api',
                isset($body['clientRef']) ? (string)$body['clientRef'] : null,
                isset($body['email']) ? (string)$body['email'] : null,
                isset($body['notes']) ? (string)$body['notes'] : null
            );
        case 'orders.list':
            if ($method !== 'GET') throw new InvalidArgumentException('Method not allowed');
            return ['orders' => (new CtvOrderService())->listForCtv((int)$ctv['id'], (int)($body['limit'] ?? 50), (int)($body['offset'] ?? 0), $body['status'] ?? null)];
        case 'orders.get':
            if ($method !== 'GET') throw new InvalidArgumentException('Method not allowed');
            return (new CtvOrderService())->status((int)$ctv['id'], (string)($body['id'] ?? ''));
        case 'topup.create':
            if ($method !== 'POST') throw new InvalidArgumentException('Method not allowed');
            return (new CtvTopupService())->create(
                $ctv,
                (string)($body['iccid'] ?? ''),
                (int)($body['planId'] ?? 0),
                'api',
                isset($body['clientRef']) ? (string)$body['clientRef'] : null
            );
        case 'esims.list':
            if ($method !== 'GET') throw new InvalidArgumentException('Method not allowed');
            $limit = max(1, min((int)($body['limit'] ?? 50), 200));
            $offset = max(0, (int)($body['offset'] ?? 0));
            $st = db()->prepare('SELECT iccid,ctv_order_id,carrier,package_name,expired_time,esim_status,created_at FROM ctv_esims WHERE ctv_id=? ORDER BY id DESC LIMIT '.(int)$limit.' OFFSET '.(int)$offset);
            $st->execute([(int)$ctv['id']]);
            return ['esims' => $st->fetchAll()];
        case 'wallet':
            if ($method !== 'GET') throw new InvalidArgumentException('Method not allowed');
            return ['balance' => (new CtvWalletService())->balance((int)$ctv['id'])];
    }
    throw new InvalidArgumentException('Endpoint không hỗ trợ');
}
