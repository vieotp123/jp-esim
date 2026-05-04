<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
security_headers(false);
try {
    $name = basename(__FILE__, '.php');
    if (!RateLimiter::isAdminIp()) {
        $rl = new RateLimiter();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limits = ['orders' => [10, 60], 'plans' => [30, 60], 'topup' => [5, 60], 'payment' => [30, 60], 'esim' => [20, 60], 'support' => [10, 60], 'voucher' => [10, 60], 'review' => [5, 60]];
        [$rlLimit, $rlWindow] = $limits[$name] ?? [30, 60];
        if (!$rl->check('api:' . $name . ':' . $ip, $rlLimit, $rlWindow)) {
            json_error('RATE_LIMITED', 'Quá nhiều yêu cầu. Vui lòng thử lại sau.', 429);
        }
    }
    switch ($name) {
        case 'plans':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('METHOD_NOT_ALLOWED','Method not allowed',405);
            json_ok((new PlanService())->list($_GET['type'] ?? 'esim', $_GET['telecom'] ?? null));
        case 'orders':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('METHOD_NOT_ALLOWED','Method not allowed',405);
            $d = read_json_body();
            if (!verify_recaptcha((string)($d['captcha'] ?? ''), 'order')) json_error('CAPTCHA_FAILED','Captcha không hợp lệ',400);
            json_ok((new OrderService())->create((string)($d['email'] ?? ''), (int)($d['planId'] ?? 0), $d['voucher'] ?? null));
        case 'payment':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('METHOD_NOT_ALLOWED','Method not allowed',405);
            json_ok((new PaymentService())->status(strtoupper(trim((string)($_GET['id'] ?? ''))), (string)($_GET['type'] ?? 'order')));
        case 'esim':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('METHOD_NOT_ALLOWED','Method not allowed',405);
            json_ok((new EsimService())->getByOrder(strtoupper(trim((string)($_GET['orderId'] ?? $_GET['order_id'] ?? '')))));
        case 'topup':
            $svc = new TopupService();
            if ($_SERVER['REQUEST_METHOD'] === 'GET') json_ok($svc->lookup(trim((string)($_GET['iccid'] ?? $_GET['id'] ?? ''))));
            if ($_SERVER['REQUEST_METHOD'] === 'POST') { $d=read_json_body(); if(!verify_recaptcha((string)($d['captcha'] ?? ''),'topup')) json_error('CAPTCHA_FAILED','Captcha không hợp lệ',400); json_ok($svc->create((string)($d['iccid'] ?? ''),(int)($d['planId'] ?? 0),(string)($d['email'] ?? ''))); }
            json_error('METHOD_NOT_ALLOWED','Method not allowed',405);
        case 'voucher':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('METHOD_NOT_ALLOWED','Method not allowed',405);
            $d=read_json_body(); $plan=(new PlanService())->findActive((int)($d['planId'] ?? 0)); json_ok((new VoucherService())->check((string)($d['code'] ?? ''), (int)($plan['price'] ?? 0)));
        case 'support':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('METHOD_NOT_ALLOWED','Method not allowed',405);
            $d=read_json_body(); $user=(string)($d['userId'] ?? session_id() ?: 'web'); $channel=(string)($d['channel'] ?? 'web'); json_ok((new SupportService())->handleMessage($user,(string)($d['message'] ?? ''),$channel,$d['context'] ?? []));
        case 'review':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('METHOD_NOT_ALLOWED','Method not allowed',405);
            $d=read_json_body(); json_ok((new ReviewService())->submit((string)($d['ratinghash'] ?? ''),(int)($d['star'] ?? 0),(string)($d['comment'] ?? '')));
    }
} catch (InvalidArgumentException $e) { json_error('VALIDATION_ERROR', $e->getMessage(), 400); }
catch (RuntimeException $e) { json_error('NOT_FOUND', $e->getMessage(), 404); }
catch (Throwable $e) { app_log($e->getMessage().' in '.$e->getFile().':'.$e->getLine(),'ERROR'); json_error('SERVER_ERROR', app_debug()?$e->getMessage():'Lỗi hệ thống', 500); }
