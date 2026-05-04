<?php
declare(strict_types=1);

/**
 * Service boundary around the legacy provider for CTV calls.
 * - Reuses EsimAccessClient under the hood.
 * - Always logs a redacted request/response into ctv_provider_logs.
 * - Test mode (CTV_PROVIDER_TEST_MODE=1) short-circuits and never calls the real provider.
 */
final class CtvProviderClient {
    public static function isTestMode(): bool {
        $v = (string)app_config('CTV_PROVIDER_TEST_MODE', getenv('CTV_PROVIDER_TEST_MODE') ?: '0');
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    public function createOrder(int $ctvId, string $refId, string $packCode, string $transactionId, int $count = 1): array {
        $endpoint = 'order';
        $start = microtime(true);
        $reqRedacted = self::redactJson(['transactionId' => $transactionId, 'packageCode' => $packCode, 'count' => $count]);

        if (self::isTestMode()) {
            $resp = ['success' => true, 'obj' => ['orderNo' => 'TEST-' . substr(md5($refId), 0, 10), 'transactionId' => $transactionId], '_test' => true];
            $this->logProvider($ctvId, 'ctv_order', $refId, $endpoint, $reqRedacted, self::redactJson($resp), 200, true, null, $start);
            return $resp;
        }
        try {
            $resp = (new EsimAccessClient())->createOrder($packCode, $transactionId, $count);
            $ok = !empty($resp['success']);
            $err = $ok ? null : (string)($resp['errorMsg'] ?? $resp['msg'] ?? '');
            $this->logProvider($ctvId, 'ctv_order', $refId, $endpoint, $reqRedacted, self::redactJson($resp), $ok ? 200 : 502, $ok, $err, $start);
            return $resp;
        } catch (Throwable $e) {
            $this->logProvider($ctvId, 'ctv_order', $refId, $endpoint, $reqRedacted, null, 0, false, $e->getMessage(), $start);
            throw $e;
        }
    }

    public function topup(int $ctvId, string $refId, string $iccid, string $packCode, string $transactionId): array {
        if ((string)app_config('TOPUP_LOCKED', '0') === '1' && !self::isTestMode()) {
            throw new RuntimeException('Topup tạm khoá: tính năng nạp dung lượng đang bảo trì.');
        }
        $endpoint = 'topup';
        $start = microtime(true);
        $reqRedacted = self::redactJson(['transactionId' => $transactionId, 'packageCode' => $packCode, 'iccid' => self::maskIccid($iccid)]);

        if (self::isTestMode()) {
            $resp = ['success' => true, 'obj' => ['transactionId' => $transactionId], '_test' => true];
            $this->logProvider($ctvId, 'ctv_topup', $refId, $endpoint, $reqRedacted, self::redactJson($resp), 200, true, null, $start);
            return $resp;
        }
        try {
            $resp = (new EsimAccessClient())->topup($iccid, $packCode, $transactionId);
            $ok = !empty($resp['success']);
            $err = $ok ? null : (string)($resp['errorMsg'] ?? $resp['msg'] ?? '');
            $this->logProvider($ctvId, 'ctv_topup', $refId, $endpoint, $reqRedacted, self::redactJson($resp), $ok ? 200 : 502, $ok, $err, $start);
            return $resp;
        } catch (Throwable $e) {
            $this->logProvider($ctvId, 'ctv_topup', $refId, $endpoint, $reqRedacted, null, 0, false, $e->getMessage(), $start);
            throw $e;
        }
    }

    private function logProvider(?int $ctvId, string $refType, ?string $refId, string $endpoint, ?string $req, ?string $resp, int $httpStatus, bool $success, ?string $err, float $start): void {
        try {
            db()->prepare('INSERT INTO ctv_provider_logs(ctv_id,ref_type,ref_id,endpoint,request_redacted,response_redacted,http_status,success,error_message,duration_ms) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute([$ctvId, $refType, $refId, $endpoint, $req, $resp, $httpStatus, $success ? 1 : 0, $err, (int)round((microtime(true) - $start) * 1000)]);
        } catch (Throwable $e) {
            app_log('ctv_provider_logs insert failed: ' . $e->getMessage(), 'ERROR');
        }
    }

    public static function redactJson(mixed $data): string {
        $masked = self::maskRecursive($data);
        $j = json_encode($masked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($j === false) return '';
        if (strlen($j) > 4000) $j = substr($j, 0, 4000) . '…';
        return $j;
    }

    private static function maskRecursive(mixed $v): mixed {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $val) {
                $kl = is_string($k) ? strtolower($k) : '';
                if (in_array($kl, ['accesscode', 'rt-accesscode', 'rtaccesscode', 'authorization', 'apikey', 'api_key', 'token', 'secret', 'password', 'mailgun_api_key', 'recaptcha_secret'], true)) {
                    $out[$k] = '***';
                } elseif ($kl === 'iccid' && is_string($val)) {
                    $out[$k] = self::maskIccid($val);
                } else {
                    $out[$k] = self::maskRecursive($val);
                }
            }
            return $out;
        }
        return $v;
    }

    public static function maskIccid(string $iccid): string {
        $iccid = preg_replace('/\s+/', '', $iccid);
        $len = strlen($iccid);
        if ($len < 8) return $iccid;
        return substr($iccid, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($iccid, -4);
    }
}
