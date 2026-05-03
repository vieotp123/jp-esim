# Existing Production Features Audit

## Config and bootstrap
- `home/foamljf4kvet/app/bootstrap.php` loads `/home/foamljf4kvet/db_config.php` first, then falls back to getenv via `app_config()`.
- `.env` exists in repo working tree but is not the primary runtime config for Apache/PHP production unless values are exported elsewhere.
- Important configured keys present by name: DB, RECAPTCHA, SECURE_TOKEN, ESIM_ORDER_URL, ESIM_TOPUP_URL, ESIM_QUERY_URL, RT access keys, Mailgun/SMTP, APP_BASE_URL, CTV_BASE_URL, CTV_PROVIDER_TEST_MODE.

## Retail/public flow to preserve
- `public_html/index.php` is the retail Japan eSIM landing/checkout UI with reCAPTCHA site key, `/assets/app.js`, plan selection, email capture, VietQR payment, and QR lookup display.
- `public_html/api/orders.php` creates retail eSIM orders through `OrderService::create()` after `verify_recaptcha(..., 'order')`.
- `public_html/api/payment.php` uses `PaymentService::status()` to poll order/topup payment status.
- `public_html/api/esim.php` uses `EsimService::getByOrder()` for QR/ICCID lookup by order id.
- `public_html/topup.php` and `public_html/api/topup.php` support ICCID/order lookup and retail topup creation after captcha.

## Bank webhook
- `public_html/webhook/bank.php` validates `SECURE_TOKEN`, records `bank_transactions`, parses transfer descriptions for `Nxxxxxxx` retail orders and `Txxxxxxx` topup orders, marks paid orders, and triggers downstream fulfillment logic.
- This is production-critical and must be refactored only behind tests/backups.

## Provider API
- `home/foamljf4kvet/app/esimaccess.php` wraps provider calls using `ESIM_ORDER_URL`, `ESIM_TOPUP_URL`, `ESIM_QUERY_URL` and RT access code. Despite class name `EsimAccessClient`, runtime endpoints come from the old `.env`/config URLs and should remain the priority.
- `OrderService`, `TopupService`, and CTV provider services call this boundary for order/topup/query.

## Email
- `home/foamljf4kvet/app/services/MailService.php` sends QR emails with Mailgun and marks `emailsent` on retail `order`/`topup_order`.
- Existing email template still contains old branding text in places and should be corrected carefully for jp-esim without changing delivery mechanics.

## Captcha
- `home/foamljf4kvet/app/security.php::verify_recaptcha()` validates Google reCAPTCHA and logs failures without printing secret values.
- Retail order and topup API enforce captcha.

## Admin existing/new
- Admin CTV pages now exist under `public_html/admin/ctv/` with Basic Auth from `db_config.php`, CSRF helpers, list/detail/orders/logs, and wallet forms.
- Broader legacy admin/product/order screens still need a deeper audit before refactor.

## CTV foundation added
- CTV pages under `public_html/ctv/` provide register, verify-email, login/logout, dashboard, pricing, orders, esims, create-esim, topup-esim, api-keys, export.
- CTV API v1 endpoints under `public_html/api/ctv/` provide products, quote, orders, topup, esims, wallet with API key auth, API logs, basic rate limit, and standardized response shape.
- Services added: `CtvAuth`, `CtvMailer`, `CtvPricingService`, `CtvWalletService`, `CtvOrderService`, `CtvTopupService`, `CtvApiKeyService`, `CtvProviderClient`.

## Database tables observed
- Legacy: `order`, `esimlist`, `plan`, `topup_order`, `topup_list`, `bank_transactions`, `voucher`, `review`, `users`, `login_otp`, `user_sessions`, `support_sessions`, `chat_conversations`, `chat_rate_limit`, `throttle`, `point`.
- CTV: `ctv_users`, `ctv_tiers`, `ctv_wallet_transactions`, `ctv_orders`, `ctv_esims`, `ctv_topup_orders`, `ctv_api_keys`, `ctv_api_logs`, `ctv_provider_logs`, `ctv_sessions`.

## Known risks needing refactor
- Several public API wrapper files contain duplicated switch logic by basename; keep behavior but consolidate later.
- Bank webhook is dense inline logic and should become a service with idempotency tests.
- Retail provider/email flow is real production behavior; do not rewrite without fixtures and rollback plan.
- CTV order/topup flow needs stronger transaction/idempotency and admin retry/manual resolution queue.
