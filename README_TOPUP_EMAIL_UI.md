# Topup email + UI update

Bản này bổ sung:

- Tự gửi email cho đơn topup khi webhook gọi topup thành công (`topup_status >= 1`).
- Nếu lúc webhook chưa gửi được, `/api/payment.php?type=topup` sẽ thử gửi lại khi user poll trạng thái.
- Email topup dùng cùng style inline/table/bo góc với email eSIM, có thông tin: TID, ICCID, gói nạp, dung lượng, số tiền, ngày thanh toán, hạn hiện tại và nút Messenger.
- Tab quảng cáo tự lướt ở màn hình mua eSIM: Không cần SIM vật lý, Hỗ trợ phát WiFi, Data cộng dồn, Nhận QR tự động.
- Màn hỗ trợ có link `m.me/muaesim` và `fb.com/muaesim`.
- Ô nhập chat/nút gửi đã responsive lại cho mobile.

File chính đã sửa:

```text
home/foamljf4kvet/app/services/MailService.php
home/foamljf4kvet/app/services/TopupService.php
home/foamljf4kvet/app/services/PaymentService.php
public_html/index.php
public_html/assets/app.css
public_html/assets/app.js
```

DB `topup_order` hiện đã có `emailsent`, nên không cần migration mới nếu dùng đúng DB full `foamljf4kvet_jp`.
