<?php
declare(strict_types=1);

final class CtvTopupService {
    public function create(array $ctv, string $iccid, int $planId, string $source = 'panel', ?string $clientRef = null): array {
        $ctvId = (int)$ctv['id'];
        $iccid = preg_replace('/\s+/', '', $iccid);
        if (!preg_match('/^[0-9]{15,32}$/', (string)$iccid)) throw new InvalidArgumentException('ICCID không hợp lệ');
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
            'planName' => (string)$row['plan_name'],
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
