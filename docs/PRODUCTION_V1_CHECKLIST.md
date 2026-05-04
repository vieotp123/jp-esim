# Production V1 Checklist — jp-esim.vip

## Environment Flags

| Flag | Expected Value | Purpose |
|------|---------------|---------|
| `TOPUP_LOCKED` | `1` | Prevents real topup API calls |
| `PROVIDER_TEST_MODE` | `1` | Provider API in test mode |
| `CTV_PROVIDER_TEST_MODE` | `1` | CTV provider calls in test mode |
| `CTV_MAIL_SAFE_MODE` | `1` | CTV emails routed to safe recipient |
| `CTV_MAIL_DRY_RUN` | `1` | CTV email sends logged but not delivered |
| `CTV_MAIL_SAFE_TO` | (set) | Safe email recipient for CTV test sends |
| `RECAPTCHA_SITE` | (set) | Google reCAPTCHA v2 invisible site key |
| `RECAPTCHA_SECRET` | (set) | Google reCAPTCHA v2 secret key |
| `MAILGUN_API_KEY` | (set) | Mailgun API key for email delivery |
| `MAILGUN_DOMAIN` | (set) | Mailgun sending domain |

## DNS / SSL

- [ ] Domain `jp-esim.vip` resolves to VPS IP
- [ ] SSL certificate valid and auto-renewing (Let's Encrypt / Certbot)
- [ ] `www.jp-esim.vip` redirects to `jp-esim.vip` (or vice versa)
- [ ] Nginx server block configured for both www and non-www

## reCAPTCHA

- [ ] Site key and secret key are a matching pair from Google Console
- [ ] reCAPTCHA v2 invisible loaded on homepage order form
- [ ] Missing/invalid captcha returns `CAPTCHA_FAILED` from API
- [ ] Browser test: complete a real order form submission to verify token flow

## Mailgun / Email

- [ ] `CTV_MAIL_SAFE_MODE=1` — CTV emails go to safe recipient only
- [ ] `CTV_MAIL_DRY_RUN=1` — no actual Mailgun API calls
- [ ] Retail email templates ready: `esim_ready`, `order_confirmed`, `topup_confirmed`, `order_failed`
- [ ] CTV email queue visible at `/admin/ctv/email-queue.php`
- [ ] **Manual step**: When ready for real email, set `CTV_MAIL_DRY_RUN=0` and `CTV_MAIL_SAFE_MODE=0`

## Provider / Topup Safety

- [ ] `TOPUP_LOCKED=1` — no real topup API calls
- [ ] `PROVIDER_TEST_MODE=1` — provider API in test mode
- [ ] `CTV_PROVIDER_TEST_MODE=1` — CTV provider in test mode
- [ ] No provider domains/names exposed in UI, email, or API responses
- [ ] Provider leak scan clean: `bash scripts/provider_leak_scan.sh`
- [ ] **Manual step**: Only unlock `TOPUP_LOCKED=0` after manual provider API verification

## Database

- [ ] All migrations applied: `000` through `004`
- [ ] `ctv_users`, `ctv_orders`, `ctv_esims`, `ctv_wallet_transactions` tables exist
- [ ] `ctv_notifications`, `ctv_topup_requests`, `admin_audit_log` tables exist
- [ ] `user_passkeys`, `webauthn_challenges` tables exist
- [ ] Database backup script configured (see `docs/DATABASE_BACKUP.md`)

## Security

- [ ] `.env` returns 403 from web
- [ ] `db_config.php` not web-accessible
- [ ] Security headers set: CSP, X-Content-Type-Options, Referrer-Policy
- [ ] `X-Robots-Tag: noindex, nofollow, noarchive` on admin/CTV pages
- [ ] Rate limiter active on public APIs and CTV API
- [ ] Admin audit log recording actions
- [ ] No secrets in git history or committed files

## Passkey Auth

- [ ] Phase 1 complete: optional passkey for CTV and admin
- [ ] Passwords remain as full fallback
- [ ] `ADMIN_REQUIRE_PASSKEY` not set (optional, enable after human testing)
- [ ] **Manual step**: Test passkey registration + login on real devices (iPhone Safari, Chrome desktop)
- [ ] See `docs/PASSKEY_ROLLOUT_PLAN.md` for full test checklist

## Smoke Tests

- [ ] `bash scripts/smoke_test.sh` — all checks pass (11/11)
- [ ] `bash scripts/provider_leak_scan.sh` — no unsafe leaks
- [ ] Homepage returns 200
- [ ] CTV login returns 200
- [ ] CTV dashboard redirects 302 (unauthenticated)
- [ ] Admin endpoints return 401 (unauthenticated)
- [ ] robots.txt and sitemap.xml return 200

## Cron Jobs

- [ ] `scripts/ctv_fulfillment_poll.php` — polls provider for pending eSIM QR codes
- [ ] Email queue processor (if automated sending enabled)
- [ ] Challenge cleanup: expired `webauthn_challenges` rows
- [ ] Rate limiter file cleanup (stale rate limit files)

## Monitoring / Alerting

- [ ] App logs written to `LOG_PATH`
- [ ] Admin dashboard shows revenue, order stats, queue alerts
- [ ] Order admin queue (`order_admin_queue`) monitored for open items
- [ ] **Manual step**: Set up external uptime monitoring (e.g., UptimeRobot)

## SEO

- [ ] `robots.txt` allows crawling of public pages
- [ ] `sitemap.xml` lists public pages with lastmod dates
- [ ] Homepage has JSON-LD structured data (Organization + Product)
- [ ] `<link rel="canonical">` on public pages
- [ ] Admin/CTV pages have `noindex` meta tag and HTTP header

## Manual Go-Live Steps

These require human verification and cannot be automated:

1. Verify reCAPTCHA works in real browser (not just API test)
2. Test passkey registration + login on iPhone Safari and Chrome
3. Verify DNS for www redirect
4. Set `CTV_MAIL_DRY_RUN=0` when ready for real email
5. Verify Mailgun domain and API key are production-ready
6. Test real bank webhook with small amount
7. Unlock `TOPUP_LOCKED=0` only after provider API manual verification
8. Set up external uptime monitoring
9. Configure database backup cron
10. Review admin audit log for completeness
