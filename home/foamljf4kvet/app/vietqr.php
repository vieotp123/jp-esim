<?php
declare(strict_types=1);
function vietqr_payload(string $id, int $amount, string $type): array {
    $bankCode = (string)app_config('BANK_CODE', 'OCB');
    $bankAccount = (string)app_config('BANK_ACCOUNT', 'CASSS');
    $bankName = (string)app_config('BANK_ACCOUNT_NAME', 'LE VAN RIN');
    $qr = 'https://img.vietqr.io/image/' . rawurlencode($bankCode) . '-' . rawurlencode($bankAccount) . '-compact.jpg?amount=' . rawurlencode((string)$amount) . '&addInfo=' . rawurlencode($id) . '&accountName=' . rawurlencode($bankName);
    $now = time();
    return [
        'type'=>$type,
        'id'=>$id,
        'amount'=>$amount,
        'amountText'=>format_vnd($amount),
        'expiresIn'=>900,
        'serverNow'=>$now,
        'expiresAt'=>date('c', $now + 900),
        'expiresAtMs'=>($now + 900) * 1000,
        'bank'=>['code'=>$bankCode,'account'=>$bankAccount,'name'=>$bankName],
        'qrUrl'=>$qr
    ];
}
