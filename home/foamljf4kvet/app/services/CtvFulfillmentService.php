<?php
declare(strict_types=1);

/**
 * CtvFulfillmentService — fetch eSIM details (QR/ICCID) from provider after a CTV order
 * was successfully placed (status=2 with provider_order_no).
 *
 * Pattern mirrors the retail flow in EsimService::getByOrder():
 *   1. createOrder returned `orderNo` only.
 *   2. We must poll EsimAccessClient::queryOrder($orderNo, $tid) until `obj.esimList[]` is populated.
 *   3. Persist each esim into ctv_esims and stamp ctv_orders.iccid (first one).
 *
 * Idempotent: skips orders that already have ctv_esims rows. Safe to call repeatedly.
 *
 * NOTE: Does NOT touch topup.
 */
final class CtvFulfillmentService {

    /**
     * Try once to fetch and persist esim list for a single CTV order.
     * Returns: ['status'=>'ready'|'processing'|'failed'|'skipped', 'count'=>int, 'message'=>string]
     */
    public function syncOrderEsims(string $ctvOrderId): array {
        $pdo = db();
        $st = $pdo->prepare('SELECT * FROM ctv_orders WHERE ctv_order_id=? LIMIT 1');
        $st->execute([$ctvOrderId]);
        $o = $st->fetch();
        if (!$o) return ['status'=>'failed', 'count'=>0, 'message'=>'order not found'];

        if ((int)$o['status'] !== 2) {
            return ['status'=>'skipped', 'count'=>0, 'message'=>'order not in success state'];
        }

        $orderNo = (string)($o['provider_order_no'] ?? '');
        $tranId  = (string)($o['provider_transaction_id'] ?? $o['ctv_order_id']);
        if ($orderNo === '') {
            return ['status'=>'skipped', 'count'=>0, 'message'=>'no provider_order_no'];
        }

        // Already populated? skip
        $st2 = $pdo->prepare('SELECT COUNT(*) FROM ctv_esims WHERE ctv_order_id=?');
        $st2->execute([$ctvOrderId]);
        if ((int)$st2->fetchColumn() > 0) {
            return ['status'=>'ready', 'count'=>0, 'message'=>'already synced'];
        }

        $requestedQty = max(1, (int)($o['quantity'] ?? 1));

        // TEST mode short-circuit: synthesise fake esim rows matching requested quantity.
        if (CtvProviderClient::isTestMode()) {
            $ins = $pdo->prepare('INSERT INTO ctv_esims(ctv_id,ctv_order_id,iccid,qr_code_url,short_url,ac,apn,total_volume,total_duration,duration_unit,expired_time,package_code,package_name,carrier,smdp_status,esim_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $firstIccid = null;
            for ($n = 0; $n < $requestedQty; $n++) {
                $fakeIccid = 'TEST' . substr(strtoupper(md5($ctvOrderId . '-' . $n)), 0, 16);
                if ($firstIccid === null) $firstIccid = $fakeIccid;
                $ins->execute([
                    (int)$o['ctv_id'], $ctvOrderId, $fakeIccid,
                    'https://example.com/test.png', 'https://example.com/test', 'LPA:TEST-' . ($n + 1), 'internet',
                    1073741824 * 5, 7, 'DAY', date('Y-m-d H:i:s', time()+86400*30),
                    (string)$o['pack_code'], (string)$o['plan_name'],
                    (string)$o['carrier'], 'INSTALLED', 'NEW',
                ]);
            }
            $pdo->prepare('UPDATE ctv_orders SET iccid=?, updated_at=NOW() WHERE ctv_order_id=?')
                ->execute([$firstIccid, $ctvOrderId]);
            return ['status'=>'ready', 'count'=>$requestedQty, 'message'=>'test mode'];
        }

        // Real provider call (uses RT_ACCESSCODE under the hood).
        $start = microtime(true);
        $pageSize = max(50, $requestedQty);
        try {
            $api = (new EsimAccessClient())->queryOrder($orderNo, $tranId, null, $pageSize);
        } catch (Throwable $e) {
            $this->logProvider((int)$o['ctv_id'], 'ctv_query', $ctvOrderId, 'query', null, null, 0, false, $e->getMessage(), $start);
            return ['status'=>'processing', 'count'=>0, 'message'=>'query exception: '.$e->getMessage()];
        }

        $ok = !empty($api['success']);
        $reqRedacted = CtvProviderClient::redactJson(['orderNo'=>$orderNo, 'transactionId'=>$tranId]);
        $respRedacted = CtvProviderClient::redactJson($api);
        $errMsg = $ok ? null : (string)($api['errorMsg'] ?? $api['msg'] ?? 'query failed');
        $this->logProvider((int)$o['ctv_id'], 'ctv_query', $ctvOrderId, 'query', $reqRedacted, $respRedacted, $ok ? 200 : 502, $ok, $errMsg, $start);

        if (!$ok) return ['status'=>'processing', 'count'=>0, 'message'=>$errMsg ?? 'query failed'];

        $list = $api['obj']['esimList'] ?? [];
        if (!$list) return ['status'=>'processing', 'count'=>0, 'message'=>'esim list empty (provider still preparing)'];

        $ins = $pdo->prepare('INSERT INTO ctv_esims(ctv_id,ctv_order_id,iccid,qr_code_url,short_url,ac,apn,total_volume,total_duration,duration_unit,expired_time,package_code,package_name,carrier,smdp_status,esim_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $first = null;
        $count = 0;
        foreach ($list as $e) {
            $pkg = $e['packageList'][0] ?? [];
            $iccid = (string)($e['iccid'] ?? '');
            if (!$first && $iccid !== '') $first = $iccid;
            try {
                $ins->execute([
                    (int)$o['ctv_id'], $ctvOrderId, $iccid,
                    (string)($e['qrCodeUrl'] ?? ''), (string)($e['shortUrl'] ?? ''),
                    (string)($e['ac'] ?? ''), (string)($e['apn'] ?? ''),
                    (int)($e['totalVolume'] ?? 0), (int)($e['totalDuration'] ?? 0),
                    (string)($e['durationUnit'] ?? 'DAY'),
                    (string)($e['expiredTime'] ?? ''),
                    (string)($pkg['packageCode'] ?? $o['pack_code']),
                    (string)($pkg['packageName'] ?? $o['plan_name']),
                    (string)$o['carrier'],
                    (string)($e['smdpStatus'] ?? ''),
                    (string)($e['esimStatus'] ?? ''),
                ]);
                $count++;
            } catch (Throwable $insE) {
                app_log('ctv_esims insert fail '.$ctvOrderId.' '.$insE->getMessage(), 'ERROR');
            }
        }
        if ($first !== null) {
            $pdo->prepare('UPDATE ctv_orders SET iccid=?, updated_at=NOW() WHERE ctv_order_id=?')
                ->execute([$first, $ctvOrderId]);
        }

        $partial = $count > 0 && $count < $requestedQty;
        if ($partial) {
            $pdo->prepare('UPDATE ctv_orders SET needs_admin=1, updated_at=NOW() WHERE ctv_order_id=?')
                ->execute([$ctvOrderId]);
            try {
                $pdo->prepare('INSERT INTO order_admin_queue(order_id,reason,detail,created_at) VALUES(?,?,?,NOW())')
                    ->execute([$ctvOrderId, 'partial_provision', 'Provisioned '.$count.'/'.$requestedQty.' eSIMs']);
            } catch (Throwable $qE) {
                app_log('admin queue insert fail '.$ctvOrderId.' '.$qE->getMessage(), 'ERROR');
            }
        }

        // Best-effort: notify the customer email, never fail the sync if mail breaks.
        if ($count > 0 && !$partial) {
            try {
                (new CtvMailService)->sendForOrderIfNeeded($ctvOrderId);
            } catch (Throwable $mailE) {
                if (function_exists('app_log')) {
                    app_log('CtvMail hook fail '.$ctvOrderId.': '.$mailE->getMessage(), 'WARN');
                }
            }
        }
        $status = $partial ? 'partial' : 'ready';
        return ['status'=>$status, 'count'=>$count, 'message'=>$partial ? 'partial: '.$count.'/'.$requestedQty : 'synced'];
    }

    /**
     * Bulk-sync any CTV order that is status=2 with no iccid yet.
     * Capped to $limit per call to bound runtime.
     * Returns summary array with counts.
     */
    public function syncPendingForCtv(int $ctvId, int $limit = 20): array {
        $st = db()->prepare('SELECT ctv_order_id FROM ctv_orders WHERE ctv_id=? AND status=2 AND (iccid IS NULL OR iccid=\'\') ORDER BY id DESC LIMIT '.(int)$limit);
        $st->execute([$ctvId]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        $out = ['ready'=>0, 'processing'=>0, 'skipped'=>0, 'failed'=>0, 'partial'=>0, 'orders'=>[]];
        foreach ($ids as $oid) {
            $r = $this->syncOrderEsims((string)$oid);
            $out[$r['status']] = ($out[$r['status']] ?? 0) + 1;
            $out['orders'][] = ['orderId'=>$oid, 'status'=>$r['status'], 'message'=>$r['message'] ?? ''];
        }
        return $out;
    }

    public function syncPendingGlobal(int $limit = 50, int $maxAgeMinutes = 1440): array {
        // Only retry orders younger than maxAgeMinutes (default 24h) to avoid hammering long-stuck
        // ones forever. Require provider_order_no — without it there's nothing to query.
        $st = db()->prepare('SELECT ctv_order_id FROM ctv_orders WHERE status=2 AND (iccid IS NULL OR iccid=\'\') AND provider_order_no IS NOT NULL AND provider_order_no<>\'\' AND updated_at >= (NOW() - INTERVAL ? MINUTE) ORDER BY id ASC LIMIT '.(int)$limit);
        $st->execute([$maxAgeMinutes]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        $out = ['ready'=>0, 'processing'=>0, 'skipped'=>0, 'failed'=>0, 'partial'=>0];
        foreach ($ids as $oid) {
            $r = $this->syncOrderEsims((string)$oid);
            $out[$r['status']] = ($out[$r['status']] ?? 0) + 1;
        }
        return $out;
    }

    private function logProvider(?int $ctvId, string $refType, ?string $refId, string $endpoint, ?string $req, ?string $resp, int $httpStatus, bool $success, ?string $err, float $start): void {
        try {
            db()->prepare('INSERT INTO ctv_provider_logs(ctv_id,ref_type,ref_id,endpoint,request_redacted,response_redacted,http_status,success,error_message,duration_ms) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute([$ctvId, $refType, $refId, $endpoint, $req, $resp, $httpStatus, $success ? 1 : 0, $err, (int)round((microtime(true) - $start) * 1000)]);
        } catch (Throwable $e) {
            app_log('ctv_provider_logs insert failed: ' . $e->getMessage(), 'ERROR');
        }
    }
}
