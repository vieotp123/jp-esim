# Japan Market Roadmap

## Product direction
Build jp-esim.vip as the Japan connectivity back office for Vietnamese CTVs, travel companies, visa companies, and wholesale agents. Retail visitors remain supported, but B2B order velocity, wallet control, bulk export, and API reliability drive the roadmap.

## Priority 1: CTV/B2B stability
- Stable CTV login, email verification, wallet, pricing, order/topup, API key, export.
- Admin can activate/disable CTVs, set discount/tier, adjust wallet, view ledger, see CTV orders/logs, and retry failed orders.
- Bulk create eSIM and bulk export should be optimized for tour groups and visa/travel agencies.

## Priority 2: Provider/order correctness
- Preserve legacy provider endpoints: `ESIM_ORDER_URL`, `ESIM_TOPUP_URL`, `ESIM_QUERY_URL`.
- Use test mode for development. Real calls only after owner approval.
- Failed provider responses must produce failed order records, redacted logs, and admin retry/manual queues.

## Priority 3: Retail hardening
- Keep no-login email checkout and bank transfer UX simple.
- Harden captcha, bank webhook idempotency, paid->provider->email QR, and order lookup.
- Align all customer-facing wording to jp-esim/Japan, not global marketplace branding.

## Priority 4: Business operations
- Daily report hooks: retail paid/pending, CTV wallet movements, failed provider orders, low wallet CTVs, top CTVs.
- Admin dashboards for bank logs, provider logs, and failed queue.
- Future business agent should read order/ledger/log state and recommend actions without seeing secrets.
