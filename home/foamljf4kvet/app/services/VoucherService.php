<?php
declare(strict_types=1);
final class VoucherService {
    public function check(?string $code, int $price): array {
        $code = trim((string)$code);
        if ($code === '') return ['valid'=>false,'coin'=>0,'code'=>null,'message'=>''];
        if (!preg_match('/^[A-Za-z0-9._-]{4,64}$/', $code)) return ['valid'=>false,'coin'=>0,'code'=>null,'message'=>'Voucher không hợp lệ'];
        $stmt = db()->prepare('SELECT id, code, coin, active, start_at, expired_at, min_price, max_use FROM voucher WHERE UPPER(code)=UPPER(?) LIMIT 1');
        $stmt->execute([$code]); $row = $stmt->fetch();
        if (!$row || (int)$row['active'] !== 1) return ['valid'=>false,'coin'=>0,'code'=>null,'message'=>'Voucher không hợp lệ'];
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $parse = fn($s) => $s ? new DateTimeImmutable((string)$s, new DateTimeZone('UTC')) : null;
        $st=$parse($row['start_at'] ?? null); $ex=$parse($row['expired_at'] ?? null);
        if ($st && $now < $st) return ['valid'=>false,'coin'=>0,'code'=>null,'message'=>'Voucher chưa bắt đầu'];
        if ($ex && $now >= $ex) return ['valid'=>false,'coin'=>0,'code'=>null,'message'=>'Voucher đã hết hạn'];
        if ((int)$row['min_price'] > 0 && $price < (int)$row['min_price']) return ['valid'=>false,'coin'=>0,'code'=>null,'message'=>'Đơn chưa đủ điều kiện'];
        if ($row['max_use'] !== null) {
            $c = db()->prepare('SELECT COUNT(*) FROM `order` WHERE voucher=? AND status IN (1,2,3)'); $c->execute([$row['code']]);
            if ((int)$c->fetchColumn() >= (int)$row['max_use']) return ['valid'=>false,'coin'=>0,'code'=>null,'message'=>'Voucher đã hết lượt'];
        }
        $coin = max(0, (int)$row['coin']);
        return ['valid'=>true,'coin'=>$coin,'code'=>(string)$row['code'],'message'=>'+'.number_format($coin,0,',','.').' xu'];
    }
}
