<?php
declare(strict_types=1);
final class ReviewService {
    public function submit(string $ratingHash, int $star, string $comment): array {
        if ($star < 1 || $star > 5) throw new InvalidArgumentException('Số sao không hợp lệ');
        $comment = trim($comment); if ($comment === '' || mb_strlen($comment)>1000) throw new InvalidArgumentException('Nội dung đánh giá không hợp lệ');
        $st=db()->prepare('SELECT order_id,email,plan_name,carrier,total,paid_at,rated FROM `order` WHERE ratinghash=? LIMIT 1'); $st->execute([$ratingHash]); $o=$st->fetch();
        if(!$o) throw new RuntimeException('Link đánh giá không hợp lệ');
        if((int)$o['rated']===1) throw new RuntimeException('Đơn này đã được đánh giá');
        db()->prepare('INSERT INTO review (email,star,comment,carrier,price,status,avatar_link,order_id,paid_at,gb) VALUES (?,?,?,?,?,0,?,?,?,?)')
            ->execute([$o['email'],$star,$comment,$o['carrier'] ?: '',(int)$o['total'],'https://ui-avatars.com/api/?name='.urlencode(substr((string)$o['email'],0,1)).'&background=0D8ABC&color=fff&size=48',$o['order_id'],$o['paid_at'],$o['plan_name']]);
        db()->prepare('UPDATE `order` SET rated=1 WHERE order_id=?')->execute([$o['order_id']]);
        return ['message'=>'Cảm ơn bạn đã đánh giá'];
    }
}
