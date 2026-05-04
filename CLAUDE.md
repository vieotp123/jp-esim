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
1. **DO NOT** edit `db_config.php` — read-only, owned by root:www-data
2. **DO NOT** commit `.env`, `db_config.php`, private keys, logs, `*.bak.*`, runtime DB
3. **DO NOT** call real eSIM provider API (ESIM_ORDER_URL/ESIM_TOPUP_URL) — test mode only
4. **DO NOT** call real Mailgun sends without DRY_RUN confirmation
5. **DO NOT** call real topup — `TOPUP_LOCKED=1` must stay
6. **DO NOT** expose provider domain/name (qrsim.net, simlessly, rsp-eu, carddata, esimsetup.apple.com) in UI/email/chat/API
7. **DO NOT** expose raw LPA string, qrCodeUrl, shortUrl, providerOrderNo to CTV or retail users
8. **DO NOT** print/log secrets, API keys, tokens
9. **DO NOT** push with token embedded in remote URL — use GIT_ASKPASS pattern
10. `CTV_PROVIDER_TEST_MODE=1` and `PROVIDER_TEST_MODE=1` are intentionally ON — do not change

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
- Phase C (retail/admin hardening): done — queue fix, dedupe, webhook amount validation (zero/negative guard + overpayment detection), cancel/refund admin flows, provider leak audit, install.php security headers, TopupService error sanitisation
- Next: Phase D — admin CTV management polish, webhook replay testing, monitoring/alerting, production readiness review

## Reporting
After each task: list files changed, tests run, results, commit hash.
