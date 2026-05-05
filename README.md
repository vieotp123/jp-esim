# JP eSIM iOS-style SPA Source

Bản này viết lại source theo kiến trúc mới: frontend không reload trang, backend API tách service, giữ tương thích DB `foamljf4kvet_jp` với các bảng `plan`, `order`, `esimlist`, `topup_order`, `bank_transactions`, `voucher`, `review`.

## Deploy

1. Copy thư mục `public_html/*` vào webroot hiện tại.
2. Copy thư mục `home/foamljf4kvet/app/*` vào `/home/foamljf4kvet/app/`.
3. Giữ `/home/foamljf4kvet/db_config.php` và `/home/foamljf4kvet/.env` ngoài `public_html`.
4. Import migration nếu muốn tạo bảng session support:

```sql
source migrations/001_support_sessions.sql;
```

`ConversationService` cũng tự tạo bảng `support_sessions` nếu user DB có quyền `CREATE TABLE`.

## Route chính

- `/` hoặc `/index.php`: app shell all-in-one.
- `/#topup`: view nạp data.
- `/#support`: view hỗ trợ.
- `/api/plans.php`: lấy gói.
- `/api/orders.php`: tạo đơn eSIM.
- `/api/payment.php`: poll thanh toán.
- `/api/esim.php`: lấy QR/LPA eSIM.
- `/api/topup.php`: tra eSIM và tạo đơn topup.
- `/api/voucher.php`: check voucher.
- `/api/support.php`: support API cho web chat.
- `/webhook/bank.php`: webhook ngân hàng.
- `/webhook/facebook.php`: webhook Facebook Page.

`topup.php` và `support.php` chỉ redirect mềm về app shell để giữ link cũ.

## Env cần có

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=foamljf4kvet_jp
DB_USER=...
DB_PASS=...
DB_CHARSET=utf8mb4

RECAPTCHA_SITE=...
RECAPTCHA_SECRET=...

RT_ACCESSCODE=...
RT_SECRETKEY=...
ESIM_ORDER_URL=https://api.esimaccess.com/api/v1/open/esim/order
ESIM_TOPUP_URL=https://api.esimaccess.com/api/v1/open/esim/topup
ESIM_QUERY_URL=https://api.esimaccess.com/api/v1/open/esim/query
ESIM_USAGE_URL=https://api.esimaccess.com/api/v1/open/esim/usage/query

SECURE_TOKEN=...
BANK_CODE=OCB
BANK_ACCOUNT=CASSS
BANK_ACCOUNT_NAME=LE VAN RIN
LOG_PATH=/home/foamljf4kvet/webhook_security.log

FB_PAGE_ACCESS_TOKEN=...
FB_VERIFY_TOKEN=...
FB_APP_SECRET=...
OPENAI_API_KEY=...
OPENAI_MODEL=gpt-4o-mini
APP_DEBUG=0
```

### Support agent AI endpoint

The customer-facing support agent is disabled by default unless explicitly enabled. Use environment variables or the private config file; do not commit real keys.

```env
SUPPORT_AGENT_ENABLED=0
SUPPORT_AGENT_PROVIDER=9router
SUPPORT_AGENT_9ROUTER_API_KEY=replace-with-private-key
SUPPORT_AGENT_9ROUTER_ENDPOINT=https://api.9router.ai/v1/chat/completions
SUPPORT_AGENT_9ROUTER_MODEL=replace-with-approved-model
SUPPORT_AGENT_WIDGET_ENABLED=0
```

`/api/support-agent.php` only supports customer eSIM guidance. It rejects cross-origin browser requests, rate limits by IP, caps messages at 1600 characters, stores no-cache responses, and redacts customer identifiers before writing short conversation memory.

To opt into the lightweight floating widget on the homepage, set `SUPPORT_AGENT_WIDGET_ENABLED=1`. For another PHP page, include `public_html/support-agent-widget.php` after the page has loaded the normal app bootstrap/config.

## Webhook ngân hàng

Gọi:

```text
POST /webhook/bank.php?token=SECURE_TOKEN
```

Hoặc gửi header:

```text
X-Webhook-Token: SECURE_TOKEN
```

Webhook match:

- Đơn eSIM: `N[A-Z0-9]{7}`
- Đơn topup: `T[A-Z0-9]{7}`

Sau khi match, webhook tự gọi eSIMAccess order/topup.

## Facebook webhook

Callback URL:

```text
https://domain.com/webhook/facebook.php
```

Verify token dùng `FB_VERIFY_TOKEN`.

Bot support có thể: tư vấn gói, tạo đơn sau khi xác nhận, gửi QR VietQR, kiểm tra thanh toán, gửi lại QR eSIM, tạo đơn topup, kiểm tra dung lượng/hạn dùng nếu eSIMAccess trả usage.

## Lưu ý kỹ thuật

- Source dùng PHP 8.1+.
- API trả JSON thống nhất `{ ok, data }` hoặc `{ ok:false, code, message }`.
- reCAPTCHA nếu chưa cấu hình secret sẽ cho qua để test local; production phải set `RECAPTCHA_SECRET`.
- Không upload `.env`, `db_config.php`, `error_log`, zip/log vào `public_html`.
- Browser cache JS có thể làm lỗi cũ còn xuất hiện; hard refresh nếu cần.

## Kiểm tra nhanh

```bash
php -l public_html/index.php
php -l public_html/api/orders.php
php -l home/foamljf4kvet/app/services/OrderService.php
```

Flow test:

1. Mở `/`.
2. Chọn gói → nhập email → tạo đơn.
3. Modal QR hiện ngay, không redirect.
4. Gửi webhook test có nội dung `Nxxxxxxx` và amount đúng.
5. App poll payment rồi poll eSIM.
6. Mở `/#topup`, nhập ICCID, chọn gói, tạo đơn topup.
