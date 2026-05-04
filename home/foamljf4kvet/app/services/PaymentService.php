<?php
declare(strict_types=1);
final class PaymentService {
    public function status(string $id, string $type): array {
        $pdo = db();
        if ($type === 'topup') {
            $sql = 'SELECT t.*, p.plan AS plan_name_ref, p.telecom AS telecom_ref FROM topup_order t LEFT JOIN plan p ON p.id=t.plan_id WHERE t.tid=? LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute([$id]);
            $r = $st->fetch();
            if (!$r) throw new RuntimeException('Không tìm thấy đơn nạp data');

            $paid = (int)$r['status'] >= 2 || !empty($r['paid_at']);
            if ($paid && (int)($r['topup_status'] ?? 0) >= 1 && (int)($r['emailsent'] ?? 0) === 0) {
                try { (new MailService())->sendTopupIfNeeded((string)$r['tid']); }
                catch (Throwable $e) { app_log('Payment topup email attempt ' . $id . ' ' . $e->getMessage(), 'ERROR'); }
            }

            $planName = trim((string)($r['plan_name'] ?? ''));
            if ($planName === '') $planName = trim((string)($r['plan_name_ref'] ?? ''));
            if ($planName === '') {
                $gb = (int)($r['gb'] ?? 0);
                $planName = $gb > 0 ? ($gb . 'GB') : 'Gói nạp data';
            }
            $carrier = trim((string)($r['telecom'] ?? '')) ?: trim((string)($r['telecom_ref'] ?? ''));
            $detailTitle = 'Đơn nạp thêm ' . $planName . ' cho ICCID ' . (string)($r['iccid'] ?? '');

            $base = [
                'type' => 'topup',
                'id' => $id,
                'paymentStatus' => $paid ? 'paid' : ((int)$r['status'] === 1 ? 'expired' : 'pending'),
                'paid' => $paid,
                'topupStatus' => (int)($r['topup_status'] ?? 0) >= 1 ? 'done' : ($paid ? 'processing' : 'pending'),
                'createdAt' => $r['created_at'],
                'paidAt' => $r['paid_at'],
                'planName' => $planName,
                'carrier' => $carrier,
                'gb' => (int)($r['gb'] ?? 0),
                'iccid' => (string)($r['iccid'] ?? ''),
                'detailTitle' => $detailTitle,
            ];
            return array_merge(vietqr_payload($id, (int)$r['price'], 'topup'), $base, $this->paymentExpiryFields((string)($r['created_at'] ?? '')));
        }

        $sql = 'SELECT order_id,status,total,paid_at,muasim,getinfosim,emailsent,iccid,created_at,plan_name,carrier FROM `order` WHERE order_id=? LIMIT 1';
        $st = $pdo->prepare($sql);
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) throw new RuntimeException('Không tìm thấy đơn hàng');

        $paid = (int)$r['status'] >= 2 || !empty($r['paid_at']);
        $ful = !$paid ? 'pending' : ((int)$r['getinfosim'] === 1 ? 'esim_ready' : ((int)$r['muasim'] === 1 ? 'ordered' : 'ordering'));
        if ((int)($r['getinfosim'] ?? 0) === 1 && (int)($r['emailsent'] ?? 0) === 0) {
            try { (new MailService())->sendOrderIfNeeded((string)$r['order_id']); }
            catch (Throwable $e) { app_log('Payment email attempt ' . $id . ' ' . $e->getMessage(), 'ERROR'); }
        }

        $planName = trim((string)($r['plan_name'] ?? '')) ?: 'eSIM Nhật Bản';
        $carrier = trim((string)($r['carrier'] ?? ''));
        $detailTitle = 'Đơn hàng eSIM Nhật Bản ' . trim($carrier . ' ' . $planName);

        $base = [
            'type' => 'order',
            'id' => $id,
            'paymentStatus' => $paid ? 'paid' : ((int)$r['status'] === 1 ? 'expired' : 'pending'),
            'paid' => $paid,
            'expired' => (int)$r['status'] === 1,
            'fulfillmentStatus' => $ful,
            'esimReady' => (int)$r['getinfosim'] === 1,
            'createdAt' => $r['created_at'],
            'paidAt' => $r['paid_at'],
            'iccid' => $r['iccid'],
            'planName' => $planName,
            'carrier' => $carrier,
            'detailTitle' => $detailTitle,
        ];
        return array_merge(vietqr_payload($id, (int)$r['total'], 'order'), $base, $this->paymentExpiryFields((string)($r['created_at'] ?? '')));
    }
    private function paymentExpiryFields(?string $createdAt): array {
        $base = $createdAt ? strtotime($createdAt) : time();
        if ($base === false || $base <= 0) $base = time();
        $expires = $base + 900;
        return [
            'serverNow' => time(),
            'expiresAt' => date('c', $expires),
            'expiresAtMs' => $expires * 1000,
            'expiresIn' => max(0, $expires - time()),
        ];
    }
}
