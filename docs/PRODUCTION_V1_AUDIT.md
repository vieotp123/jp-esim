# Production V1 Audit — 2026-05-04

## Admin Pages (14 total)

| Page | Auth | Mobile Cards | Status |
|------|------|-------------|--------|
| index.php | admin_ctv_require | Yes | OK |
| dashboard-admin.php | admin_ctv_require | Yes (2x) | OK |
| view.php | admin_ctv_require | Yes (2x) | OK |
| orders.php | admin_ctv_require | Yes | OK |
| email-queue.php | admin_ctv_require | Yes | OK |
| audit.php | admin_ctv_require | Yes | OK |
| logs.php | admin_ctv_require | Yes (3x) | OK |
| notifications.php | admin_ctv_require | Yes | OK |
| queue.php | admin_ctv_require | Yes | OK |
| topup-requests.php | admin_ctv_require | Yes | OK |
| passkey-setup.php | admin_ctv_require | Yes | OK (fixed this run) |
| passkey-verify.php | admin_ctv_require | N/A (modal) | OK |
| passkey-api.php | admin_ctv_require | N/A (JSON) | OK |
| _guard.php | N/A (helper) | N/A | OK |

## CTV Pages (19 total)

| Page | Auth | Mobile Cards | Status |
|------|------|-------------|--------|
| dashboard.php | requireUser | Stats grid | OK |
| orders.php | requireUser | Yes | OK |
| orders/view.php | requireUser | Responsive grid | OK |
| esims.php | requireUser | Yes | OK |
| pricing.php | requireUser | Yes | OK |
| api-keys.php | requireUser | Yes | OK |
| security.php | requireUser | Yes | OK |
| create-esim.php | requireUser | N/A (form) | OK |
| topup-esim.php | requireUser | N/A (form) | OK (TOPUP_LOCKED added this run) |
| export.php | requireUser | N/A (form) | OK |
| install.php | requireUser | N/A (redirect) | OK |
| qr.php | requireUser | N/A (image) | OK |
| index.php | Public | N/A | OK |
| login.php | Public | N/A | OK |
| register.php | Public | N/A | OK |
| logout.php | Public | N/A | OK |
| verify-email.php | Public | N/A | OK |
| passkey-api.php | Public (rate-limited) | N/A (JSON) | OK |
| notifications-api.php | requireUser | N/A (JSON) | OK |

## Topup Behavior

- TOPUP_LOCKED=1 enforced in both topup-esim.php (direct) and orders/view.php (renewal)
- When locked: form hidden, flash message shown, POST rejected
- CtvTopupService: debit-first, auto-refund on failure
- No real provider calls while test mode on

## Admin Passkey State

- PasskeyService operational for both 'ctv' and 'admin' realms
- ADMIN_REQUIRE_PASSKEY optional gate in _guard.php
- 8-hour session timeout on passkey verification
- Passwords always remain as fallback
- Rate limiting: 20 requests/60s on public auth endpoints

## CSV Export Capability

| Type | Fields | Limit |
|------|--------|-------|
| orders | ctv_order_id, plan, retail, discount, ctv_price, quantity, total, status, iccid, created_at | 10,000 |
| topups | ctv_topup_id, iccid, plan, retail, discount, ctv_price, total, status, created_at | 10,000 |
| esims | iccid, ctv_order_id, carrier, package_name, expired_time, esim_status, created_at | 10,000 |
| wallet | created_at, reason, amount, balance_after, ref_type, ref_id, note | 10,000 |

All exports include UTF-8 BOM, CSRF protection, date range filtering.

## Responsive Status

- All admin/CTV tables now have m-cards mobile alternative
- Forms (create-esim, topup-esim, export) are single-card layouts, stack naturally
- Dashboard stats use CSS grid, responsive at 768px/480px
- Admin nav: hamburger drawer at <760px
- CTV nav: hamburger drawer at <768px

## Fixes Applied This Run

1. passkey-setup.php: added m-cards for passkey list table
2. topup-esim.php: added TOPUP_LOCKED guard (form + POST rejection + flash message)
