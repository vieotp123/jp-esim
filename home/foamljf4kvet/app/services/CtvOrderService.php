<?php
declare(strict_types=1);

final class CtvOrderService {
    public function createEsim(array $ctv, int $planId, int $quantity = 1, string $source = 'panel', ?string $clientRef = null, ?string $email = null, ?string $notes = null): array {
        $ctvId = (int)$ctv['id'];
        if ($quantity < 1 || $quantity > 100) throw new InvalidArgumentException('Số lượng phải từ 1-100');
        $plan = (new PlanService())->findActive($planId);
        if (!$plan || empty($plan['pack_code'])) throw new InvalidArgumentException('Gói không tồn tại hoặc ngừng bán');
        $pricing = (new CtvPricingService())->priceFor($ctv, $plan);
        $totalCharge = $pricing['ctvPrice'] * $quantity;

        $pdo = db();
        if ($clientRef !== null && $clientRef !== '') {
            $st = $pdo->prepare('SELECT ctv_order_id FROM ctv_orders WHERE ctv_id=? AND client_ref=? LIMIT 1');
            $st->execute([$ctvId, $clientRef]);
            $existing = $st->fetchColumn();
            if ($existing) {
                return $this->status($ctvId, (string)$existing);
            }
        }

        $orderId = $this->newOrderId();
        // Reserve balance up-front; refund on failure.
        (new CtvWalletService())->debit($ctvId, $totalCharge, 'order_charge', 'ctv_order', $orderId, 'Reserve');

        $pdo->prepare('INSERT INTO ctv_orders(ctv_order_id,ctv_id,plan_id,pack_code,carrier,plan_name,retail_price,discount,ctv_price,quantity,total_charge,status,source,client_ref,email,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?)')
            ->execute([
                $orderId, $ctvId, (int)$plan['id'], (string)$plan['pack_code'], (string)$plan['telecom'], (string)$plan['plan'],
                $pricing['retailPrice'], $pricing['discount'], $pricing['ctvPrice'], $quantity, $totalCharge,
                $source, $clientRef, $email, $notes,
            ]);

        // Call provider via service boundary.
        $provider = new CtvProviderClient();
        try {
            $resp = $provider->createOrder($ctvId, $orderId, (string)$plan['pack_code'], $orderId);
        } catch (Throwable $e) {
            $this->markFailedAndRefund($ctvId, $orderId, $totalCharge, $e->getMessage());
            throw new RuntimeException('Lỗi gọi nhà cung cấp: ' . $e->getMessage());
        }

        if (empty($resp['success'])) {
            $err = (string)($resp['errorMsg'] ?? $resp['msg'] ?? 'Provider failed');
            $this->markFailedAndRefund($ctvId, $orderId, $totalCharge, $err);
            return $this->status($ctvId, $orderId);
        }

        $orderNo = (string)($resp['obj']['orderNo'] ?? '');
        $tranId = (string)($resp['obj']['transactionId'] ?? $orderId);
        $pdo->prepare('UPDATE ctv_orders SET status=2, provider_order_no=?, provider_transaction_id=?, updated_at=NOW() WHERE ctv_order_id=?')
            ->execute([$orderNo, $tranId, $orderId]);

        return $this->status($ctvId, $orderId);
    }

    private function markFailedAndRefund(int $ctvId, string $orderId, int $totalCharge, string $err): void {
        $pdo = db();
        $pdo->prepare('UPDATE ctv_orders SET status=3, needs_admin=1, error_message=?, updated_at=NOW() WHERE ctv_order_id=?')
            ->execute([substr($err, 0, 500), $orderId]);
        try { (new CtvWalletService())->credit($ctvId, $totalCharge, 'order_refund', 'ctv_order', $orderId, 'Auto refund on failure'); }
        catch (Throwable $e) { app_log('CTV refund failed '.$orderId.' '.$e->getMessage(), 'ERROR'); }
    }

    public function status(int $ctvId, string $orderId): array {
        $st = db()->prepare('SELECT * FROM ctv_orders WHERE ctv_order_id=? AND ctv_id=? LIMIT 1');
        $st->execute([$orderId, $ctvId]);
        $row = $st->fetch();
        if (!$row) throw new RuntimeException('Đơn CTV không tồn tại');
        return $this->format($row);
    }

    public function listForCtv(int $ctvId, int $limit = 50, int $offset = 0, ?string $status = null): array {
        $limit = max(1, min($limit, 200)); $offset = max(0, $offset);
        $sql = 'SELECT * FROM ctv_orders WHERE ctv_id=?';
        $params = [$ctvId];
        if ($status !== null && $status !== '') {
            $map = ['pending'=>0,'processing'=>1,'success'=>2,'failed'=>3];
            if (isset($map[$status])) { $sql .= ' AND status=?'; $params[] = $map[$status]; }
        }
        $sql .= ' ORDER BY id DESC LIMIT '.(int)$limit.' OFFSET '.(int)$offset;
        $st = db()->prepare($sql);
        $st->execute($params);
        return array_map([$this, 'format'], $st->fetchAll());
    }

    private function format(array $r): array {
        $statusMap = [0=>'pending',1=>'processing',2=>'success',3=>'failed'];
        return [
            'orderId' => (string)$r['ctv_order_id'],
            'planId' => (int)$r['plan_id'],
            'packCode' => (string)$r['pack_code'],
            'carrier' => (string)$r['carrier'],
            'planName' => (string)$r['plan_name'],
            'retailPrice' => (int)$r['retail_price'],
            'discount' => (int)$r['discount'],
            'ctvPrice' => (int)$r['ctv_price'],
            'quantity' => (int)$r['quantity'],
            'totalCharge' => (int)$r['total_charge'],
            'status' => $statusMap[(int)$r['status']] ?? 'unknown',
            'source' => (string)$r['source'],
            'iccid' => (string)($r['iccid'] ?? ''),
            'providerOrderNo' => (string)($r['provider_order_no'] ?? ''),
            'errorMessage' => (string)($r['error_message'] ?? ''),
            'needsAdmin' => (int)$r['needs_admin'] === 1,
            'createdAt' => $r['created_at'],
            'updatedAt' => $r['updated_at'],
        ];
    }

    private function newOrderId(): string {
        $pdo = db();
        do {
            $id = 'C' . rand_alnum(7);
            $st = $pdo->prepare('SELECT 1 FROM ctv_orders WHERE ctv_order_id=?');
            $st->execute([$id]);
        } while ($st->fetchColumn());
        return $id;
    }
}
