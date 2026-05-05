# Production V1 Checklist — jp-esim.vip

**Status: Phase G complete (2026-05-05). Live with real provider calls.**

## Environment Flags (current)

| Flag | Value | Notes |
|------|-------|-------|
| `TOPUP_LOCKED` | `0` | Live — real topup API calls |
| `PROVIDER_TEST_MODE` | `0` | Live — real provider API |
| `CTV_PROVIDER_TEST_MODE` | `0` | Live |
| `ADMIN_REQUIRE_PASSKEY` | `1` | Admin must use passkey; password disabled when registered |
| `ADMIN_IDLE_MAX_SECONDS` | `3600` (default) | 1h idle → re-auth |
| `LOG_PATH` | `/var/log/jpesim/app.log` | Repointed from old un-writable path |
| `ALERT_EMAIL` | (unset) | **Pending**: set in db_config.php to enable provider error alerts |
| `RECAPTCHA_SITE/SECRET` | set | reCAPTCHA v2 invisible |
| `MAILGUN_API_KEY/DOMAIN` | set | EU region |
| `CTV_MAIL_DRY_RUN` | `0` | Real email |
| `CTV_MAIL_SAFE_MODE` | `0` | Real recipients |

## Routes

- [x] `/auth` — unified entry (admin/partner cards)
- [x] `/auth?role=admin` redirects to admin login
- [x] `/auth?role=partner` redirects to CTV login
- [x] `/admin` redirects to `/auth?role=admin`
- [x] `/ctv` redirects to `/auth?role=partner`
- [x] Custom 404 page (was silently rendering homepage)

## Authentication

- [x] Admin Passkey-only when registered (`ADMIN_REQUIRE_PASSKEY=1`)
- [x] Admin idle timeout 1h, re-auth at `/auth?role=admin&idle=1`
- [x] Basic-auth fallback rejected when passkey enforced
- [x] CTV login: passkey (if registered) → password fallback
- [x] All UI links to old login URLs migrated to `/auth`
- [x] CTV password reset gracefully degraded until migration 006 applied

## Database

- [x] Migrations 000–005 applied
- [ ] **Pending**: migrations 006 (password_reset cols) + 007 (company_name col) — graceful guards in place
- [x] All admin/CTV pages render without column-missing crashes
- [x] Daily backup at 04:00 UTC, gzipped, 7-day retention

## Operability

- [x] `/api/health.php` JSON: db ping, disk, queue depth, provider error 1h/24h, failed topups, flags
- [x] `/admin/ctv/health.php` UI: card grid + flag table + systemd states + recent errors
- [x] `/admin/ctv/health.php` test-email button (verifies Mailgun + ALERT_EMAIL)
- [x] `/admin/ctv/backups.php` — list daily backup files
- [x] `/admin/ctv/system-log.php` — tail viewer for `/var/log/jpesim/*.log`
- [x] `/admin/ctv/activity.php` — 60-event feed across 7 tables, filterable
- [x] `/admin/ctv/search.php` — global search (ICCID/email/order_id)
- [x] `/ctv/activity.php` — partner-scoped activity feed

## Bulk admin actions

- [x] `/admin/ctv/topup-requests.php`: bulk approve/reject, max 50/batch
- [x] `/admin/ctv/queue.php`: bulk resolve/ignore, max 100/batch
- [x] All bulk endpoints CSRF-protected (verified)

## Systemd timers (installed)

| Unit | Schedule |
|---|---|
| `jpesim-ctv-fulfillment-poll.timer` | every 2 min |
| `jpesim-email-retry.timer` | every 15 min |
| `jpesim-provider-alert.timer` | every 10 min (no-op until ALERT_EMAIL set) |
| `jpesim-db-backup.timer` | daily 04:00 UTC |

## Logging

- [x] `/etc/logrotate.d/jpesim`: weekly, 4 rotations, compress, maxsize 100M
- [x] `/var/log/jpesim/` writable by www-data
- [x] `app_log()` writes to `app.log` correctly

## Security

- [x] `.env` returns 403, `db_config.php` not web-accessible
- [x] CSP, HSTS preload, X-Frame, COOP, CORP, Permissions-Policy on all pages
- [x] X-Robots-Tag noindex on admin + CTV
- [x] Webhook bank.php requires SECURE_TOKEN (constant-time compare)
- [x] Webhook facebook.php verifies signature + verify_token
- [x] Webhook idempotency: `bank_transactions.reference UNIQUE`
- [x] Rate limiter on public APIs (per-endpoint per-IP) and CTV API key auth
- [x] Admin audit log records every mutation
- [x] CSRF on all admin POST handlers (verified by automated test)

## Diagnostics in `scripts/`

- [x] `smoke_test.sh` — 30 checks
- [x] `provider_leak_scan.sh` — clean
- [x] `admin_query_audit_v2.php` — SQL extraction + EXPLAIN
- [x] `admin_ui_render_test.php` — fake-session render of every admin page (37 currently)
- [x] `ctv_ui_render_test.php` — every CTV page (17)
- [x] `admin_post_mutation_test.php` — 18 mutation handlers, all reject invalid CSRF with 400
- [x] `db_index_audit.php` — EXPLAIN hot queries

## Pending manual steps

1. Apply DB migrations 006 + 007 (`mysql ... < migrations/006_*.sql && < migrations/007_*.sql`)
2. Set `ALERT_EMAIL` in `db_config.php` to your monitoring inbox
3. After monitoring inbox set, click "Gửi email test" on `/admin/ctv/health.php` to verify
4. Review `app.log` daily for first week (size, error frequency)
5. Re-run `db_index_audit.php` once row counts grow 10× (currently 8-16 rows per table)
6. Set up external uptime probe pointing at `/api/health.php` (UptimeRobot etc.)

## Verified end-to-end

- 30/30 smoke
- 37/37 admin pages render 200 (incl. detail + filtered views)
- 17/17 CTV pages render 200
- 18/18 admin mutation handlers reject invalid CSRF with 400 (no 500s)
- Provider leak scan: clean
- 4 systemd timers active and running
