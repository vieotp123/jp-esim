<?php
declare(strict_types=1);
final class EsimAccessClient {
    private string $accessCode;
    public function __construct() { $this->accessCode = (string)app_config('RT_ACCESSCODE', app_config('ACCESS_CODE', '')); }
    private function post(string $url, array $body, int $timeout = 30): array {
        if ($url === '' || $this->accessCode === '') return ['success'=>false,'errorMsg'=>'API not configured'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout, CURLOPT_CONNECTTIMEOUT=>(int)app_config('ESIMACCESS_CONNECT_TIMEOUT', 15), CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'RT-AccessCode: '.$this->accessCode], CURLOPT_POSTFIELDS=>json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if (!$raw) return ['success'=>false,'errorMsg'=>$err ?: 'Empty API response'];
        $json = json_decode($raw, true);
        if (!is_array($json)) return ['success'=>false,'errorMsg'=>'Invalid API JSON','raw'=>$raw];
        return $json;
    }
    public function createOrder(string $packageCode, string $transactionId, int $count = 1): array {
        $count = max(1, min($count, 100));
        return $this->post((string)app_config('ESIM_ORDER_URL', 'https://api.esimaccess.com/api/v1/open/esim/order'), ['transactionId'=>$transactionId, 'packageInfoList'=>[['packageCode'=>$packageCode, 'count'=>$count, 'periodNum'=>1]]], 30);
    }
    public function queryOrder(?string $orderNo = null, ?string $transactionId = null, ?string $iccid = null, int $pageSize = 50): array {
        $body = ['pager'=>['pageNum'=>1,'pageSize'=>max(20, min($pageSize, 200))]];
        if ($orderNo) $body['orderNo'] = $orderNo;
        if ($transactionId) $body['transactionId'] = $transactionId;
        if ($iccid) $body['iccid'] = $iccid;
        return $this->post((string)app_config('ESIM_QUERY_URL', 'https://api.esimaccess.com/api/v1/open/esim/query'), $body, 30);
    }
    public function queryUsage(string $esimTranNo): array {
        return $this->post((string)app_config('ESIM_USAGE_URL', 'https://api.esimaccess.com/api/v1/open/esim/usage/query'), ['esimTranNoList'=>[$esimTranNo]], 20);
    }
    public function topup(string $iccid, string $packageCode, string $transactionId): array {
        return $this->post((string)app_config('ESIM_TOPUP_URL', 'https://api.esimaccess.com/api/v1/open/esim/topup'), ['iccid'=>$iccid, 'packageCode'=>$packageCode, 'transactionId'=>$transactionId], 30);
    }
}
