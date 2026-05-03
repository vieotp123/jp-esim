<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $token = $_GET['token'] ?? ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ($_SERVER['HTTP_SECURE_TOKEN'] ?? ($_SERVER['HTTP_SECURE_TOKEN'] ?? '')));
    if (!hash_equals((string)app_config('SECURE_TOKEN',''), (string)$token)) json_error('BAD_TOKEN','Bad token',403);
    $raw=file_get_contents('php://input') ?: ''; $js=json_decode($raw,true);
    if(!is_array($js)) json_error('BAD_PAYLOAD','Bad payload',400);
    $txs = isset($js['data']) ? (array_is_list($js['data']) ? $js['data'] : [$js['data']]) : [$js];
    $pdo=db(); $matched=0; $processed=[];
    foreach($txs as $tx){
        $ref=(string)($tx['reference'] ?? $tx['tid'] ?? $tx['transaction_id'] ?? '');
        $desc=strtoupper((string)($tx['description'] ?? $tx['content'] ?? ''));
        $amount=(int)($tx['amount'] ?? $tx['creditAmount'] ?? 0);
        if($ref==='') $ref=hash('sha256',$desc.$amount.($tx['transaction_datetime'] ?? ''));
        try { $pdo->prepare('INSERT INTO bank_transactions (reference,description,amount,transaction_datetime,account_number,bank_name,counter_account_name,counter_account_number) VALUES (?,?,?,?,?,?,?,?)')->execute([$ref,$desc,$amount,date('Y-m-d H:i:s', strtotime((string)($tx['transaction_datetime'] ?? 'now'))),$tx['bank_sub_acc_id'] ?? $tx['account_number'] ?? '',$tx['bankName'] ?? $tx['bank_name'] ?? '',$tx['counterAccountName'] ?? '',$tx['counterAccountNumber'] ?? '']); }
        catch(Throwable $e){ $processed[]=['ref'=>$ref,'duplicate'=>true]; continue; }
        $done=false;
        if(preg_match('/\bT[A-Z0-9]{7}\b/',$desc,$m)){ $st=$pdo->prepare('SELECT tid,price FROM topup_order WHERE status=0 AND tid=? LIMIT 1'); $st->execute([$m[0]]); $o=$st->fetch(); if($o && $amount >= (int)$o['price']){ (new TopupService())->markPaidAndTopup($m[0]); $done=true; }}
        if(!$done && preg_match('/\bN[A-Z0-9]{7}\b/',$desc,$m)){ $st=$pdo->prepare('SELECT order_id,total FROM `order` WHERE status=0 AND order_id=? LIMIT 1'); $st->execute([$m[0]]); $o=$st->fetch(); if($o && $amount >= (int)$o['total']){ (new OrderService())->markPaidAndBuy($m[0]); $done=true; }}
        if($done){ $matched++; $pdo->prepare('UPDATE bank_transactions SET `match`=1 WHERE reference=?')->execute([$ref]); }
        $processed[]=['ref'=>$ref,'matched'=>$done];
    }
    json_ok(['matched'=>$matched,'processed'=>$processed]);
} catch(Throwable $e){ app_log('bank webhook '.$e->getMessage(),'ERROR'); json_error('SERVER_ERROR','Webhook error',500); }
