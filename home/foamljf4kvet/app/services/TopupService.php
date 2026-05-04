<?php
declare(strict_types=1);

final class TopupService {
    public function lookup(string $identifier): array {
        $identifier = trim($identifier);
        if ($identifier === '') throw new InvalidArgumentException('Thiếu ICCID hoặc mã đơn');

        $iccid = $identifier;
        $orderId = null;

        // Chỉ mã đơn Nxxxxxxx mới truy vấn DB. ICCID thì gọi thẳng eSIMAccess.
        if (preg_match('/^N[A-Z0-9]{7}$/i', $identifier)) {
            $orderId = strtoupper($identifier);
            $iccid = $this->iccidFromOrder($orderId);
            if ($iccid === '') throw new RuntimeException('Không tìm thấy ICCID của mã đơn này');
        }

        $iccid = preg_replace('/\s+/', '', $iccid);
        if (!preg_match('/^[0-9]{15,32}$/', $iccid)) throw new InvalidArgumentException('ICCID không hợp lệ');

        $remote = $this->queryRemoteByIccid($iccid);
        $telecom = $this->detectTelecomFromRemote($remote);
        $plans = (new PlanService())->list('topup', $telecom ?: null)['plans'];
        if (!$plans) $plans = (new PlanService())->list('topup')['plans'];

        return [
            'iccid' => $iccid,
            'current' => [
                'orderId' => $orderId ?: '',
                'carrier' => $telecom ?: '',
                'planName' => $remote['planName'] ?? '',
                'remainingVolume' => $remote['remainingVolume'] ?? null,
                'remainingGB' => $remote['remainingGB'] ?? null,
                'expiredAt' => $remote['expiredAt'] ?? null,
                'status' => $remote['status'] ?? '',
                'activatedAt' => $remote['activatedAt'] ?? null,
                'lastUpdateAt' => $remote['lastUpdateAt'] ?? null,
                'totalVolume' => $remote['totalVolume'] ?? 0,
                'totalGB' => $remote['totalGB'] ?? null,
                'usedVolume' => $remote['usedVolume'] ?? 0,
                'usedGB' => $remote['usedGB'] ?? null,
            ],
            'plans' => $plans,
        ];
    }

    public function create(string $iccid, int $planId, string $email): array {
        if ((string)app_config('TOPUP_LOCKED', '0') === '1') {
            throw new InvalidArgumentException('Chức năng nạp data đang tạm dừng. Bạn vẫn có thể tra cứu ICCID, vui lòng thử lại sau.');
        }
        if (!valid_email($email)) throw new InvalidArgumentException('Email không hợp lệ');
        $iccid = preg_replace('/\s+/', '', $iccid);
        if (!preg_match('/^[0-9]{15,32}$/', $iccid)) throw new InvalidArgumentException('ICCID không hợp lệ');

        $plan = (new PlanService())->findActive($planId);
        if (!$plan || empty($plan['topup_packcode'])) throw new InvalidArgumentException('Gói nạp không hợp lệ');

        $tid = $this->newTid();
        preg_match('/(\d+)/', (string)$plan['plan'], $m);
        $gb = (int)($m[1] ?? 0);

        $info = $this->queryRemoteByIccid($iccid);

        $pdo = db();
        $st = $pdo->prepare('INSERT INTO topup_order (tid,iccid,email,plan_id,gb,price,expired_time,total_volume,volume,created_at,status,topup_status) VALUES (?,?,?,?,?,?,?,?,?,NOW(),0,0)');
        $st->execute([$tid,$iccid,$email,(int)$plan['id'],$gb,(int)$plan['price'],$info['expiredAt'] ?? null,$info['totalVolume'] ?? 0,$info['usedVolume'] ?? 0]);

        return array_merge(vietqr_payload($tid, (int)$plan['price'], 'topup'), ['tid'=>$tid,'plan'=>(new PlanService())->format($plan),'iccid'=>$iccid,'createdAt'=>date('Y-m-d H:i:s')]);
    }

    private function iccidFromOrder(string $orderId): string {
        $st = db()->prepare('SELECT iccid FROM `order` WHERE BINARY order_id = BINARY ? LIMIT 1');
        $st->execute([$orderId]);
        $iccid = trim((string)$st->fetchColumn());
        if ($iccid !== '') return $iccid;
        $st = db()->prepare('SELECT iccid FROM esimlist WHERE BINARY order_id = BINARY ? ORDER BY id DESC LIMIT 1');
        $st->execute([$orderId]);
        return trim((string)$st->fetchColumn());
    }

    private function queryRemoteByIccid(string $iccid): array {
        $api = (new EsimAccessClient())->queryOrder(null, null, $iccid);
        if (empty($api['success'])) {
            app_log('TopupService queryRemoteByIccid failed iccid=' . substr($iccid, 0, 6) . '*** msg=' . ($api['errorMsg'] ?? $api['msg'] ?? ''), 'WARN');
            throw new RuntimeException('Không lấy được thông tin eSIM. Vui lòng kiểm tra lại ICCID hoặc thử lại sau.');
        }
        $e = $api['obj']['esimList'][0] ?? null;
        if (!$e) throw new RuntimeException('Không tìm thấy eSIM theo ICCID này');

        $tran = (string)($e['esimTranNo'] ?? '');
        $usageRow = [];
        if ($tran !== '') {
            $usage = (new EsimAccessClient())->queryUsage($tran);
            $usageRow = $usage['obj']['esimUsageList'][0] ?? [];
        }

        $total = (int)($e['totalData'] ?? $e['totalVolume'] ?? 0);
        $used = (int)($usageRow['dataUsage'] ?? 0);
        $remain = max(0, $total - $used);
        $pkg = $this->oldestPackage($e['packageList'] ?? []);

        return [
            'iccid' => (string)($e['iccid'] ?? $iccid),
            'esimTranNo' => $tran,
            'packageCode' => (string)($pkg['packageCode'] ?? ''),
            'planName' => (string)($pkg['packageName'] ?? ''),
            'totalVolume' => $total,
            'usedVolume' => $used,
            'usedGB' => round($used / 1073741824, 2),
            'remainingVolume' => $remain,
            'remainingGB' => round($remain / 1073741824, 2),
            'totalGB' => round($total / 1073741824, 2),
            'activatedAt' => (string)($e['activateTime'] ?? ''),
            'lastUpdateAt' => (string)($usageRow['lastUpdateTime'] ?? ''),
            'expiredAt' => (string)($e['expiredTime'] ?? ''),
            'status' => (string)($e['smdpStatus'] ?? $e['esimStatus'] ?? ''),
        ];
    }

    private function detectTelecomFromRemote(array $remote): ?string {
        $pkgCode = trim((string)($remote['packageCode'] ?? ''));
        if ($pkgCode === '') return null;
        $st = db()->prepare('SELECT telecom FROM plan WHERE pack_code=? OR topup_packcode=? LIMIT 1');
        $st->execute([$pkgCode, $pkgCode]);
        $telecom = $st->fetchColumn();
        return $telecom ? (string)$telecom : null;
    }

    private function oldestPackage(array $packages): array {
        $oldest = [];
        foreach ($packages as $pkg) {
            if (!$oldest) { $oldest = $pkg; continue; }
            $a = strtotime((string)($pkg['createTime'] ?? '')) ?: PHP_INT_MAX;
            $b = strtotime((string)($oldest['createTime'] ?? '')) ?: PHP_INT_MAX;
            if ($a < $b) $oldest = $pkg;
        }
        return $oldest;
    }

    private function newTid(): string {
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; $pdo=db();
        do { $tid='T'; for($i=0;$i<7;$i++) $tid.=$alphabet[random_int(0, strlen($alphabet)-1)]; $q=$pdo->prepare('SELECT 1 FROM topup_order WHERE tid=?'); $q->execute([$tid]); } while($q->fetch());
        return $tid;
    }

    /**
     * Backward-compat alias used by bank webhook before refactor.
     * Delegates to RetailFulfillmentService::fulfillPaidTopup.
     */
    public function markPaidAndTopup(string $tid): void {
        (new RetailFulfillmentService())->fulfillPaidTopup($tid);
    }
}
