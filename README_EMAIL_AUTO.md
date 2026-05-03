# Auto email eSIM without cron

Bản này bỏ phụ thuộc cron để gửi email eSIM.

## Cơ chế mới

Email sẽ được gửi tự động khi một trong các API/service này thấy eSIM đã sẵn sàng:

1. `OrderService::markPaidAndBuy()` sau khi webhook ngân hàng jp-esim thành công sẽ thử query eSIM một lần. Nếu eSIMAccess đã trả QR thì hệ thống lưu `esimlist` và gửi email ngay.
2. `GET /api/esim.php?orderId=Nxxxxxxx` khi frontend poll lấy QR eSIM. Khi API lấy được QR hoặc thấy QR đã có sẵn trong DB, hệ thống gọi `MailService::sendOrderIfNeeded()`.
3. `GET /api/payment.php?id=Nxxxxxxx&type=order` nếu đơn đã có `getinfosim=1` nhưng `emailsent=0`, hệ thống sẽ thử gửi email lại.

Như vậy không cần chạy file cron retry chỉ để gửi email nữa.

## Template email

`MailService.php` giữ nguyên giao diện email từ file cron bạn gửi:

- Subject: `Thông tin eSIM – Đơn hàng {ORDER_ID}`
- Inline QR image qua Mailgun `inline[]` và `cid:`
- CSS inline, card, chip, nút đánh giá giống template cũ
- Review URL: `https://jp-esim.vip/review.php?c={ratinghash}`

## Config cần có trong `.env` hoặc `db_config.php`

```env
MAILGUN_DOMAIN=jp-esim.vip
MAILGUN_API_KEY=...
MAILGUN_REGION=EU
SMTP_FROM=noreply@jp-esim.vip
SMTP_NAME="jp-esim.vip"
```

## Idempotent

Trước khi gửi, hệ thống check `order.emailsent`. Gửi thành công mới update:

```sql
UPDATE `order` SET emailsent=1, updated_at=NOW() WHERE order_id=? AND emailsent=0
```

Có dùng `GET_LOCK('mail_' + orderId)` để tránh gửi trùng nếu nhiều request poll cùng lúc.
