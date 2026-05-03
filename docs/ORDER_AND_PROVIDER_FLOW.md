# Order And Provider Flow

## Retail new eSIM
1. Visitor selects Japan plan and enters email on `public_html/index.php`.
2. `public_html/api/orders.php` verifies captcha and calls `OrderService::create()`.
3. Order is saved in legacy `order` table with a payment code like `Nxxxxxxx` and VietQR payload.
4. Bank webhook `public_html/webhook/bank.php` receives transactions, verifies `SECURE_TOKEN`, records `bank_transactions`, matches `Nxxxxxxx`, and marks paid when amount is sufficient.
5. Paid fulfillment calls the legacy provider order endpoint through the existing provider boundary using `ESIM_ORDER_URL`.
6. QR/ICCID is stored in `esimlist`/order fields, shown by lookup API, and emailed through `MailService`.

## Retail topup
1. Visitor enters ICCID or order id on `public_html/topup.php`.
2. `TopupService::lookup()` queries provider through `ESIM_QUERY_URL` to show current status and compatible topup plans.
3. `TopupService::create()` creates `topup_order` and VietQR payment code `Txxxxxxx`.
4. Bank webhook matches `Txxxxxxx`, marks paid, calls provider topup via `ESIM_TOPUP_URL`, then email notification is sent.

## CTV new eSIM
1. CTV authenticates through panel session or API key.
2. Quote uses retail plan price minus CTV discount.
3. Create order validates quantity and wallet balance, debits wallet atomically, creates `ctv_orders`, then calls provider through `CtvProviderClient`.
4. In `CTV_PROVIDER_TEST_MODE=1`, provider is not called and a test success response is logged.
5. Provider success updates processing/success fields; provider failure marks failed and needs admin review with redacted logs.

## CTV topup
1. CTV submits ICCID and topup plan from panel/API.
2. Service validates ICCID, applies CTV discount, debits wallet, creates `ctv_topup_orders`, and calls `ESIM_TOPUP_URL` through provider boundary/test mode.
3. Failure results in failed status, admin review, and redacted log.

## Query/status
- Retail lookup uses order id/email-facing APIs and `EsimService`.
- Topup lookup queries provider by ICCID/order id through `ESIM_QUERY_URL`.
- CTV API exposes order status, eSIM listing, wallet, products, quote, order, and topup.

## Refactor target
- Extract bank webhook logic to a service with idempotency and fixtures.
- Make provider client endpoint names match legacy URLs to avoid global marketplace assumptions.
- Unify status codes between retail and CTV while preserving legacy DB values.
- Keep all provider/API logs redacted.
