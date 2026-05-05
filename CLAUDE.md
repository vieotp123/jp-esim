# JP-eSIM (esimtravel) — Project Context

## Architecture
- PHP legacy app (no framework), Nginx + PHP-FPM + MariaDB on Ubuntu VPS
- Web root: `/home/levanrin2404/esimtravel/public_html/`
- App services: `/home/foamljf4kvet/app/services/`
- Config: `/home/foamljf4kvet/db_config.php` (DO NOT EDIT)
- Env: `/home/levanrin2404/esimtravel/.env` (DO NOT COMMIT)
- Domain: https://jp-esim.vip

## Key Commands
- `php -l <file>` — lint check PHP files after edits
- `node --check <file>` — syntax check JS files
- `git status && git diff --stat` — before any edit
- `curl -sk https://jp-esim.vip/ -o /dev/null -w "%{http_code}"` — homepage smoke

## Code Standards
- PHP: 4-space indent, no short tags, strict error handling
- JS: 2-space indent in frontend assets
- Always backup before editing production files: `cp file file.bak.$(date +%Y%m%d_%H%M%S)`
- Test with `php -l` after every PHP edit

## Safety Rules (CRITICAL)
1. **DO NOT** edit `db_config.php` casually — owned by root:www-data, requires sudo + backup. Only edit on explicit user request, always backup first (`db_config.php.bak.<reason>.<ts>`).
2. **DO NOT** commit `.env`, `db_config.php`, private keys, logs, `*.bak.*`, runtime DB
3. **PROD v1 LIVE**: provider API (ESIM_ORDER_URL/ESIM_TOPUP_URL) is now called for real. `PROVIDER_TEST_MODE=0` and `CTV_PROVIDER_TEST_MODE=0`. Test changes against staging or with `TEST-DEMO-*` refs first. Any code that touches provider in a new path needs explicit user sign-off before deploy.
4. **DO NOT** call real Mailgun sends without DRY_RUN confirmation
5. **TOPUP_LOCKED=0** as of 2026-05-05 (production v1 unlock per user request). Topup eSIM and queue retry now hit real provider. Do not flip back without explicit request.
6. **DO NOT** expose provider domain/name (qrsim.net, simlessly, rsp-eu, carddata, esimsetup.apple.com) in UI/email/chat/API
7. **DO NOT** expose raw LPA string, qrCodeUrl, shortUrl, providerOrderNo to CTV or retail users
8. **DO NOT** print/log secrets, API keys, tokens
9. **DO NOT** push with token embedded in remote URL — use GIT_ASKPASS pattern
10. **Admin login is Passkey-only** (`ADMIN_REQUIRE_PASSKEY=1`). Password login disabled when admin has passkey registered. Bootstrap path (no passkey yet) still allows password to register first key.

## QR Self-Host Pattern
- Retail QR: `/r/qr.php?t=<base64(ac:XXXX)>` renders PNG server-side
- CTV QR: `/api/ctv/esim_qr.php?iccid=XXX` with API key auth
- Never download/proxy provider QR URLs — render from LPA activation code only

## Database
- Main tables: orders, order_items, esims, ctv_users, ctv_wallets, ctv_wallet_transactions, ctv_orders, ctv_esims, ctv_api_keys, ctv_api_logs, order_admin_queue, esim_email_queue
- Read schema with `SHOW CREATE TABLE tablename` before major changes

## Git
- Branch: main
- Remote: https://github.com/vieotp123/jp-esim
- Push pattern: temporary GIT_ASKPASS with GITHUB_TOKEN from .env, delete after push
- Latest commit: check with `git log --oneline -3`

## Current Status
- Phase A (captcha): done
- Phase B (CTV foundation): done — auth, wallet, pricing, orders, QR proxy, email queue, dashboard, API docs
- Phase C (retail/admin hardening): done — queue fix, dedupe, webhook amount validation, cancel/refund admin flows, provider leak audit, security headers, error sanitisation
- Phase D (large-scale hardening): done
  - Part 1: Admin CTV management polish — enhanced user list (orders/spent/wallet history), admin dashboard (revenue/stats/queue)
  - Part 2: CTV panel improvements — notification system (bell icon, API, admin broadcast), CSV export with date range, wallet top-up request with proof upload + admin approval
  - Part 3: Security — file-based rate limiter (public + CTV API), security headers (already existed), admin audit log with viewer
  - Part 4: Retail experience — /tra-cuu order tracking page with progress bar, Vietnamese email templates (esim_ready, order_confirmed, topup_confirmed, order_failed)
  - Part 5: Database migrations (ctv_notifications, ctv_topup_requests, admin_audit_log)
  - Provider leak scan: removed provider_order_no from CTV exports and pages
- Phase E (passkey auth): Phase 1 done — optional passkey for CTV and admin
  - PasskeyService wrapper around lbuchs/WebAuthn v2.2 (RP ID: jp-esim.vip)
  - DB: user_passkeys + webauthn_challenges tables (migration 004)
  - CTV: /ctv/security.php register/revoke, /ctv/login.php passkey login button, /ctv/passkey-api.php
  - Admin: /admin/ctv/passkey-setup.php register/revoke, /admin/ctv/passkey-api.php, optional ADMIN_REQUIRE_PASSKEY=1 enforcement
  - Client: /assets/passkey.js (WebAuthn create/get helpers)
  - Passwords always remain as fallback
- Phase F (i18n + UX polish): done
  - Full Vietnamese error messages in CTV/admin endpoints (qr, install, notifications-api, guard, orders)
  - Translated all English UI labels: status tags, buttons, table headers, toast messages across admin and CTV
  - Provider leak scan: clean — no provider domains in user/CTV/retail-facing output
  - Mobile responsive CSS for CTV and admin panels (760px/480px breakpoints, table horizontal scroll)
  - SEO: noarchive on all protected pages, cleaned sitemap (removed noindex pages, added lastmod)
  - Smoke test script: scripts/smoke_test.sh (11 checks, no secrets needed)
- Phase G (production v1 go-live, 2026-05-05):
  - Unified `/auth` entry: `public_html/auth/index.php` landing (admin/partner cards) + `?role=admin|partner` shortcuts
  - `/admin` redirect: bare `/admin` and `/admin/` now redirect to `/admin/ctv/login.php` (was 403)
  - Admin Passkey-only enforced: `ADMIN_REQUIRE_PASSKEY=1` set in db_config.php; `_guard.php` default flipped to '1'; `login.php` hides password form when admin has passkey + enforce on; basic-auth fallback rejected. Bootstrap path (no passkey yet) still allows password.
  - Topup unlock: `TOPUP_LOCKED=0` (db_config.php + .env). Real provider calls active. Backup at `/home/foamljf4kvet/db_config.php.bak.unlock.20260505_130904`.
- Next: monitoring/alerting on provider error rate, webhook replay testing, post-go-live audit (~T+24h)

## Reporting
After each task: list files changed, tests run, results, commit hash.
