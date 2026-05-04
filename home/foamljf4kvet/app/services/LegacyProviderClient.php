<?php
declare(strict_types=1);

/**
 * Service boundary around the legacy provider for RETAIL (B2C) calls.
 * - Wraps EsimAccessClient under the hood.
 * - Always logs a redacted request/response into ctv_provider_logs (ref_type prefix 'retail_').
 * - Test mode (PROVIDER_TEST_MODE=1 or CTV_PROVIDER_TEST_MODE=1) short-circuits the network call.
 *
 * NOTE: Intentionally NOT named "EsimAccess..." — owner wants to decouple from the global
 * marketplace name so the service can be re-pointed to a different upstream without renaming.
 */
final class LegacyProviderClient {
    public static function isTestMode(): bool {
        $a = (string)app_config('PROVIDER_TEST_MODE', getenv('PROVIDER_TEST_MODE') ?: '0');
        $b = (string)app_config('CTV_PROVIDER_TEST_MODE', getenv('CTV_PROVIDER_TEST_MODE') ?: '0');
        $on = ['1', 'true', 'yes', 'on'];
        return in_array(strtolower($a), $on, true) || in_array(strtolower($b), $on, true);
    }

    /**
     * Create eSIM at upstream provider.
     * Normalized response keys: success, provider_ref, iccid, qr, raw_summary, error_code, error_message, _raw
     */
    public function createEsim(string $packCode, string $transactionId, int $count = 1): array {
        $endpoint = 'order';
        $start = microtime(true);
        $reqRedacted = CtvProviderClient::redactJson(['transactionId' => $transactionId, 'packageCode' => $packCode, 'count' => $count]);

        if (self::isTestMode()) {
            $raw = ['success' => true, 'obj' => [
                'orderNo' => 'TEST-O-' . substr(md5($transactionId), 0, 10),
                'transactionId' => $transactionId,
            ], '_test' => true];
            $this->logProvider('retail_order', $transactionId, $endpoint, $reqRedacted, CtvProviderClient::redactJson($raw), 200, true, null, $start);
            return $this->normalize($raw);
        }
        try {
            $raw = (new EsimAccessClient())->createOrder($packCode, $transactionId, $count);
            $ok = !empty($raw['success']);
            $err = $ok ? null : (string)($raw['errorMsg'] ?? $raw['msg'] ?? '');
            $this->logProvider('retail_order', $transactionId, $endpoint, $reqRedacted, CtvProviderClient::redactJson($raw), $ok ? 200 : 502, $ok, $err, $start);
            return $this->normalize($raw);
        } catch (Throwable $e) {
            $this->logProvider('retail_order', $transactionId, $endpoint, $reqRedacted, null, 0, false, $e->getMessage(), $start);
            throw $e;
        }
    }

    public function topupEsim(string $iccid, string $packCode, string $transactionId): array {
        $endpoint = 'topup';
        $start = microtime(true);
        $reqRedacted = CtvProviderClient::redactJson([
            'transactionId' => $transactionId,
            'packageCode' => $packCode,
            'iccid' => CtvProviderClient::maskIccid($iccid),
        ]);

        if (self::isTestMode()) {
            $raw = ['success' => true, 'obj' => [
                'transactionId' => $transactionId,
                'orderNo' => 'TEST-T-' . substr(md5($transactionId), 0, 10),
            ], '_test' => true];
            $this->logProvider('retail_topup', $transactionId, $endpoint, $reqRedacted, CtvProviderClient::redactJson($raw), 200, true, null, $start);
            return $this->normalize($raw, $iccid);
        }
        try {
            $raw = (new EsimAccessClient())->topup($iccid, $packCode, $transactionId);
            $ok = !empty($raw['success']);
            $err = $ok ? null : (string)($raw['errorMsg'] ?? $raw['msg'] ?? '');
            $this->logProvider('retail_topup', $transactionId, $endpoint, $reqRedacted, CtvProviderClient::redactJson($raw), $ok ? 200 : 502, $ok, $err, $start);
            return $this->normalize($raw, $iccid);
        } catch (Throwable $e) {
            $this->logProvider('retail_topup', $transactionId, $endpoint, $reqRedacted, null, 0, false, $e->getMessage(), $start);
            throw $e;
        }
    }

    public function queryEsim(?string $orderNo = null, ?string $transactionId = null, ?string $iccid = null): array {
        $endpoint = 'query';
        $start = microtime(true);
        $reqRedacted = CtvProviderClient::redactJson([
            'orderNo' => $orderNo,
            'transactionId' => $transactionId,
            'iccid' => $iccid ? CtvProviderClient::maskIccid($iccid) : null,
        ]);
        if (self::isTestMode()) {
            $raw = ['success' => true, 'obj' => ['esimList' => []], '_test' => true];
            $this->logProvider('retail_query', $transactionId ?: $orderNo, $endpoint, $reqRedacted, CtvProviderClient::redactJson($raw), 200, true, null, $start);
            return $this->normalize($raw, $iccid);
        }
        try {
            $raw = (new EsimAccessClient())->queryOrder($orderNo, $transactionId, $iccid);
            $ok = !empty($raw['success']);
            $err = $ok ? null : (string)($raw['errorMsg'] ?? $raw['msg'] ?? '');
            $this->logProvider('retail_query', $transactionId ?: $orderNo, $endpoint, $reqRedacted, CtvProviderClient::redactJson($raw), $ok ? 200 : 502, $ok, $err, $start);
            return $this->normalize($raw, $iccid);
        } catch (Throwable $e) {
            $this->logProvider('retail_query', $transactionId ?: $orderNo, $endpoint, $reqRedacted, null, 0, false, $e->getMessage(), $start);
            throw $e;
        }
    }

    private function normalize(array $raw, ?string $fallbackIccid = null): array {
        $ok = !empty($raw['success']);
        $obj = $raw['obj'] ?? [];
        $providerRef = (string)($obj['orderNo'] ?? $obj['transactionId'] ?? '');
        $iccid = null;
        $qr = null;
        if (!empty($obj['esimList'][0])) {
            $first = $obj['esimList'][0];
            $iccid = $first['iccid'] ?? null;
            $qr = $first['qrCodeUrl'] ?? $first['shortUrl'] ?? null;
        }
        if ($iccid === null && $fallbackIccid !== null) $iccid = $fallbackIccid;

        $errCode = null;
        $errMsg = null;
        if (!$ok) {
            $errCode = (string)($raw['errorCode'] ?? $raw['error_code'] ?? '');
            $errMsg = (string)($raw['errorMsg'] ?? $raw['msg'] ?? $raw['error_message'] ?? '');
            if ($errCode === '') $errCode = null;
            if ($errMsg === '') $errMsg = null;
        }

        return [
            'success' => $ok,
            'provider_ref' => $providerRef !== '' ? $providerRef : null,
            'iccid' => $iccid,
            'qr' => $qr,
            'raw_summary' => CtvProviderClient::redactJson($raw),
            'error_code' => $errCode,
            'error_message' => $errMsg,
            '_raw' => $raw,
        ];
    }

    private function logProvider(string $refType, ?string $refId, string $endpoint, ?string $req, ?string $resp, int $httpStatus, bool $success, ?string $err, float $start): void {
        try {
            db()->prepare('INSERT INTO ctv_provider_logs(ctv_id,ref_type,ref_id,endpoint,request_redacted,response_redacted,http_status,success,error_message,duration_ms) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute([null, $refType, $refId, $endpoint, $req, $resp, $httpStatus, $success ? 1 : 0, $err, (int)round((microtime(true) - $start) * 1000)]);
        } catch (Throwable $e) {
            app_log('legacy_provider_logs insert failed: ' . $e->getMessage(), 'ERROR');
        }
    }
}
