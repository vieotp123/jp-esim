<?php
declare(strict_types=1);
final class OrderService {
    public function create(string $email, int $planId, ?string $voucher = null, bool $skipCaptcha = false): array {
        if (!valid_email($email)) throw new InvalidArgumentException('Email không hợp lệ');
        $plan = (new PlanService())->findActive($planId);
        if (!$plan || empty($plan['pack_code'])) throw new InvalidArgumentException('Gói không tồn tại hoặc ngừng bán');
        $price = (int)$plan['price'];
        $v = (new VoucherService())->check($voucher, $price);
        $orderId = $this->newOrderId();
        $ratingHash = bin2hex(random_bytes(32));
        $stmt = db()->prepare('INSERT INTO `order` (order_id,email,plan_id,plan_name,price,cost,total,voucher,coin_reward,pack_code,carrier,status,ratinghash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$orderId,$email,(int)$plan['id'],(string)$plan['plan'],$price,(int)$plan['cost'],$price,$v['valid']?$v['code']:null,$v['valid']?(int)$v['coin']:0,(string)$plan['pack_code'],(string)$plan['telecom'],0,$ratingHash]);
        return array_merge(
            vietqr_payload($orderId, $price, 'order'),
            ['orderId'=>$orderId, 'plan'=>(new PlanService())->format($plan), 'coinReward'=>$v['valid']?(int)$v['coin']:0, 'createdAt'=>date('Y-m-d H:i:s')]
        );
    }
    public function newOrderId(): string {
        do { $id = 'N'.rand_alnum(7); $st=db()->prepare('SELECT 1 FROM `order` WHERE order_id=?'); $st->execute([$id]); } while ($st->fetchColumn());
        return $id;
    }
    /**
     * Kept for backward compatibility. Delegates to RetailFulfillmentService.
     * Existing callers (cron, admin tools) keep working unchanged.
     */
    public function markPaidAndBuy(string $orderId): void {
        (new RetailFulfillmentService())->fulfillPaidOrder($orderId);
    }
}
