# jpesim / esimtravel Roadmap

## Project State
- current_project: jpesim
- current_task: Phase A - fix Google reCAPTCHA v2 invisible checkout flow
- current_phase: Phase A
- web_url: https://jp-esim.vip
- captcha_status: Phase A code updated for reCAPTCHA v2 invisible; needs real browser token test and matching secret confirmation
- ctv_status: Phase B v1 scaffold landed (auth, wallet, pricing, orders, topup, api keys, admin foundation, migration 001_ctv_foundation.sql) — needs DB migration applied + Mailgun + admin password configured before go-live
- admin_status: existing admin features must be read before extension
- provider_api_status: use legacy provider URLs from env (`ESIM_ORDER_URL`, `ESIM_TOPUP_URL`, `ESIM_QUERY_URL`), not EsimAccess priority
- github_status: unknown; check before commit/push
- current_model: cx/gpt-5.5 via 9Router for Hermes brain
- fallback_active: false; use cc/claude-opus-4-7 via 9Router if GPT-5.5 quota is exhausted
- claude_cli_status: installed on VPS, but not logged in as of latest check (`claude -p` returned login required)
- blocked_reason: none for local code inspection; Claude CLI coding is blocked until `/login` is completed
- next_action: real browser checkout token test; if Google returns secret/key mismatch, owner must provide matching RECAPTCHA_SECRET

## Operating Rules
- Read source, database schema, and env/config before large changes.
- Do not invent products/prices; use the real database.
- Do not commit `.env`, runtime DB dumps, logs, private keys, tokens, or provider secrets.
- Run `git status` and inspect relevant diffs before edits; preserve existing dirty worktree changes.
- Use Claude CLI on VPS as the main code worker for large tasks when logged in; do not run multiple Claude tasks in parallel.
- If Claude CLI is limited, pause and retry later; Hermes continues coordination.

## Phase A - Fix Captcha
Priority: first.

Requirements:
- Confirm and implement Google reCAPTCHA v2 invisible flow.
- Frontend must load the correct v2 invisible script, render/execute correctly, collect a token, and submit it in the field backend reads.
- Backend verifies with Google `siteverify` using `RECAPTCHA_SECRET` and frontend token; include `remoteip` only if safely available.
- Do not bypass captcha on public production.
- If site key and secret are not a matching pair, report that owner must provide the matching `RECAPTCHA_SECRET`.
- Improve UX: clear load/verify failure messages, no crashes.
- Tests: homepage 200, plans API OK, missing captcha returns `CAPTCHA_FAILED`, `.env` remains 403, PHP lint OK, JS syntax/check OK, browser token test if possible.
- Do not call real eSIM provider API during captcha tests.

Known keys from owner:
- Site key: `6Lfqe9csAAAAAFT8nYlASEdlv9ggOjQgEmG10Lc9`
- Another value owner called publickey: `6Lfqe9csAAAAAAI9elubFlU43I1a_avgyU8hG7Oc`
- Do not assume which value is secret without verifying config and Google response.

## Phase B - CTV / Reseller Foundation
Priority: after captcha.

CTV behavior:
- CTV has separate email/password login.
- New CTV must verify email using Mailgun credentials from env/config.
- Login only for verified and active accounts.
- CTV prepays wallet balance before creating or renewing eSIMs.
- Admin can credit/debit wallet manually.
- CTV can create eSIM in bulk from panel and API.
- CTV can renew by ICCID or order code.
- CTV and retail customer orders/eSIMs must be clearly distinguished in DB and source fields.

Discount model:
- Retail and CTV prices come from the same product table.
- CTV price = retail price - `discount_per_esim`.
- Discounts apply to all products and renewals.
- Admin can configure discount by CTV or tier, for example level 1 = 10k off, level 2 = 20k off.

Suggested tables if missing:
- `ctv_users` / `partners` / `resellers`
- `ctv_wallets` or balance fields
- `ctv_wallet_transactions`
- `ctv_orders`
- `ctv_esims`
- `ctv_api_logs`

CTV pages v1:
- `/ctv/register`, `/ctv/verify-email`, `/ctv/login`, `/ctv/logout`
- `/ctv/dashboard`, `/ctv/pricing`, `/ctv/orders`, `/ctv/esims`
- `/ctv/create-esim`, `/ctv/topup-esim`, `/ctv/api-keys`, `/ctv/export`

CTV API v1:
- API-key auth with hashed keys only.
- Endpoints: `GET /api/ctv/products`, `POST /api/ctv/quote`, `POST /api/ctv/orders`, `GET /api/ctv/orders/{id}`, `POST /api/ctv/topup`, `GET /api/ctv/esims`.
- Must check balance and log request/response summaries without provider secrets.

Admin v1 for CTV:
- Manage CTV list/detail/status, discounts/tier, wallet credit/debit, wallet transactions, CTV orders, provider/API logs, and retry failed orders.

## Phase C - Retail Bank Webhook Flow
Priority: after CTV foundation.

- Read existing bank webhook, SQL, and auto-bank code before edits.
- Retail customers do not log in; email is enough to buy.
- On successful bank webhook payment, call legacy provider `ESIM_ORDER_URL`.
- Show QR on web and email QR to the customer.
- Keep and improve order lookup by order code/email.
- If provider fails, mark failed or pending admin and expose admin retry.

## Provider API Rules
- Use legacy env/config endpoints: `ESIM_ORDER_URL`, `ESIM_TOPUP_URL`, `ESIM_QUERY_URL`.
- Retail: call create API only after paid bank webhook.
- CTV: call create/topup API immediately only if wallet has enough balance.
- On provider error: mark order failed and leave for admin retry/manual handling.
- Log provider request/response with secrets/tokens redacted.
- Do not call real provider API in tests without explicit safe test case.

## Reporting Checklist
After each milestone report:
1. Files/database/env read.
2. Flow understood.
3. Changes made.
4. Test results.
5. Commit/push status.
6. Owner inputs needed.
