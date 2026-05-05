<?php
declare(strict_types=1);

final class CtvTopupService {
    public function lookup(array $ctv, string $iccid): array {
        $iccid = preg_replace('/\s+/', '', $iccid);
        if (!preg_match('/^[0-9]{15,32}$/', (string)$iccid)) throw new InvalidArgumentException('ICCID không hợp lệ. Vui lòng nhập 15-32 chữ số.');

        $retail = (new TopupService())->lookup($iccid);
        $current = is_array($retail['current'] ?? null) ? $retail['current'] : [];
        unset($current['planName'], $current['packageName'], $current['packageCode']);
        $carrier = trim((string)($current['carrier'] ?? ''));
        $plans = [];
        $message = '';

        $ownedPlan = '';
        $st = db()->prepare('SELECT o.plan_name, o.carrier, p.day FROM ctv_esims e JOIN ctv_orders o ON o.ctv_order_id=e.ctv_order_id AND o.ctv_id=e.ctv_id LEFT JOIN plan p ON p.id=o.plan_id WHERE e.iccid=? AND e.ctv_id=? LIMIT 1');
        $st->execute([$iccid, (int)$ctv['id']]);
        $owned = $st->fetch();
        if ($owned) {
            $parts = [];
            $c = trim((string)($owned['carrier'] ?? ''));
            if ($c !== '') $parts[] = $c;
            $parts[] = $this->planDataLabel((string)($owned['plan_name'] ?? ''));
            $d = (int)($owned['day'] ?? 0);
            if ($d > 0) $parts[] = $d . ' ngày';
            $ownedPlan = implode(' · ', $parts);
        }

        if ($carrier === '') {
            $message = 'eSIM này chưa xác định được nhà mạng hỗ trợ nạp data. Vui lòng liên hệ admin để kiểm tra.';
        } else {
            $plans = (new CtvPricingService())->listFor($ctv, 'topup', $carrier)['plans'];
            if (!$plans) {
                $message = 'Hiện chưa có gói nạp data tương thích cho eSIM này.';
            }
        }

        return [
            'iccid' => (string)($retail['iccid'] ?? $iccid),
            'current' => $current,
            'ownedPlan' => $ownedPlan,
            'plans' => $plans,
            'compatiblePlanIds' => array_map(static fn(array $p): int => (int)$p['id'], $plans),
            'message' => $message,
        ];
    }

    public function create(array $ctv, string $iccid, int $planId, string $source = 'panel', ?string $clientRef = null): array {
        if ((string)app_config('TOPUP_LOCKED', '0') === '1' && !CtvProviderClient::isTestMode()) {
            throw new RuntimeException('Chức năng nạp data đang tạm khoá. Vui lòng thử lại sau.');
        }

        $ctvId = (int)$ctv['id'];
        $iccid = preg_replace('/\s+/', '', $iccid);
        if (!preg_match('/^[0-9]{15,32}$/', (string)$iccid)) throw new InvalidArgumentException('ICCID không hợp lệ');

        $lookup = $this->lookup($ctv, $iccid);
        if (!in_array($planId, $lookup['compatiblePlanIds'], true)) {
            throw new InvalidArgumentException('Gói nạp không tương thích với ICCID này. Vui lòng tra cứu lại trước khi nạp.');
        }

        $plan = (new PlanService())->findActive($planId);
        if (!$plan || empty($plan['topup_packcode'])) throw new InvalidArgumentException('Gói nạp không hợp lệ');
        $pricing = (new CtvPricingService())->priceFor($ctv, $plan);
        $totalCharge = $pricing['ctvPrice'];

        $pdo = db();
        if ($clientRef !== null && $clientRef !== '') {
            $st = $pdo->prepare('SELECT ctv_topup_id FROM ctv_topup_orders WHERE ctv_id=? AND client_ref=? LIMIT 1');
            $st->execute([$ctvId, $clientRef]);
            $existing = $st->fetchColumn();
            if ($existing) return $this->status($ctvId, (string)$existing);
        }

        $tid = $this->newTid();
        (new CtvWalletService())->debit($ctvId, $totalCharge, 'topup_charge', 'ctv_topup', $tid, 'Reserve');

        $pdo->prepare('INSERT INTO ctv_topup_orders(ctv_topup_id,ctv_id,plan_id,iccid,carrier,plan_name,retail_price,discount,ctv_price,total_charge,status,source,client_ref) VALUES(?,?,?,?,?,?,?,?,?,?,1,?,?)')
            ->execute([$tid, $ctvId, (int)$plan['id'], $iccid, (string)$plan['telecom'], (string)$plan['plan'], $pricing['retailPrice'], $pricing['discount'], $pricing['ctvPrice'], $totalCharge, $source, $clientRef]);

        $provider = new CtvProviderClient();
        try {
            $resp = $provider->topup($ctvId, $tid, $iccid, (string)$plan['topup_packcode'], $tid);
        } catch (Throwable $e) {
            $this->markFailedAndRefund($ctvId, $tid, $totalCharge, $e->getMessage());
            throw new RuntimeException('Lỗi gọi nhà cung cấp: ' . $e->getMessage());
        }

        if (empty($resp['success'])) {
            $err = (string)($resp['errorMsg'] ?? $resp['msg'] ?? 'Provider failed');
            $this->markFailedAndRefund($ctvId, $tid, $totalCharge, $err);
            return $this->status($ctvId, $tid);
        }

        $pdo->prepare('UPDATE ctv_topup_orders SET status=2, provider_response_json=?, updated_at=NOW() WHERE ctv_topup_id=?')
            ->execute([CtvProviderClient::redactJson($resp), $tid]);
        return $this->status($ctvId, $tid);
    }

    private function markFailedAndRefund(int $ctvId, string $tid, int $totalCharge, string $err): void {
        $pdo = db();
        $pdo->prepare('UPDATE ctv_topup_orders SET status=3, needs_admin=1, error_message=?, updated_at=NOW() WHERE ctv_topup_id=?')
            ->execute([substr($err, 0, 500), $tid]);
        try { (new CtvWalletService())->credit($ctvId, $totalCharge, 'topup_refund', 'ctv_topup', $tid, 'Auto refund on failure'); }
        catch (Throwable $e) { app_log('CTV topup refund failed '.$tid.' '.$e->getMessage(), 'ERROR'); }
    }

    public function status(int $ctvId, string $tid): array {
        $st = db()->prepare('SELECT * FROM ctv_topup_orders WHERE ctv_topup_id=? AND ctv_id=? LIMIT 1');
        $st->execute([$tid, $ctvId]);
        $row = $st->fetch();
        if (!$row) throw new RuntimeException('Đơn nạp CTV không tồn tại');
        $statusMap = [0=>'pending',1=>'processing',2=>'success',3=>'failed'];
        return [
            'topupId' => (string)$row['ctv_topup_id'],
            'iccid' => (string)$row['iccid'],
            'carrier' => (string)$row['carrier'],
            'data' => $this->planDataLabel((string)$row['plan_name']),
            'retailPrice' => (int)$row['retail_price'],
            'discount' => (int)$row['discount'],
            'ctvPrice' => (int)$row['ctv_price'],
            'totalCharge' => (int)$row['total_charge'],
            'status' => $statusMap[(int)$row['status']] ?? 'unknown',
            'source' => (string)$row['source'],
            'errorMessage' => (string)($row['error_message'] ?? ''),
            'needsAdmin' => (int)$row['needs_admin'] === 1,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    private function planDataLabel(string $plan): string {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(GB|MB)\b/i', $plan, $m)) {
            return str_replace(',', '.', $m[1]) . ' ' . strtoupper($m[2]);
        }
        return 'Data';
    }

    private function newTid(): string {
        $pdo = db();
        do {
            $id = 'P' . rand_alnum(7);
            $st = $pdo->prepare('SELECT 1 FROM ctv_topup_orders WHERE ctv_topup_id=?');
            $st->execute([$id]);
        } while ($st->fetchColumn());
        return $id;
    }
}
