<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
security_headers(false);

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
    if ($token === '') json_error('UNAUTHORIZED', 'API key required', 401);
    $row = (new CtvApiKeyService())->authenticate($token);
    if (!$row) json_error('UNAUTHORIZED', 'API key không hợp lệ', 401);
    return $row;
}

function ctv_api_dispatch(string $endpoint): void {
    $start = microtime(true); $ctv = null; $apiKeyId = null;
    try {
        $ctv = ctv_api_authenticate();
        $apiKeyId = (int)($ctv['key_id'] ?? 0);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $body = ($method === 'POST' || $method === 'PUT') ? read_json_body() : $_GET;
        $resp = ctv_api_handle($endpoint, $method, $ctv, $body);
        ctv_log_api_call($ctv, $apiKeyId, $endpoint, $method, $body, 200, ['ok' => true], $start);
        json_ok($resp);
    } catch (InvalidArgumentException $e) {
        ctv_log_api_call($ctv, $apiKeyId, $endpoint, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_REQUEST, 400, $e->getMessage(), $start);
        json_error('VALIDATION_ERROR', $e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ctv_log_api_call($ctv, $apiKeyId, $endpoint, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_REQUEST, 422, $e->getMessage(), $start);
        json_error('RUNTIME_ERROR', $e->getMessage(), 422);
    } catch (Throwable $e) {
        app_log('ctv api ['.$endpoint.'] '.$e->getMessage().' '.$e->getFile().':'.$e->getLine(), 'ERROR');
        ctv_log_api_call($ctv, $apiKeyId, $endpoint, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_REQUEST, 500, $e->getMessage(), $start);
        json_error('SERVER_ERROR', app_debug() ? $e->getMessage() : 'Lỗi hệ thống', 500);
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
            $qty = max(1, min((int)($body['quantity'] ?? 1), 100));
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
