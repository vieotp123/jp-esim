# CTV Business Rules

## Accounts
- CTV signs up with email/password and must verify email before production API/panel use.
- Admin can activate/disable accounts. Disabled or unverified CTVs cannot authenticate API keys.

## Wallet
- CTV must prepay wallet balance before creating or topping up eSIMs.
- Wallet ledger is append-only through `ctv_wallet_transactions`; balance updates must be atomic and must not go negative.
- Manual admin credit/debit requires admin attribution and note.

## Pricing
- Retail price comes from Japan product `plan` records.
- CTV price = retail price - effective discount per eSIM, floored at 0.
- Effective discount comes from custom `discount_per_esim` first, then tier discount (default 10k/20k tiers), and can be changed by admin.
- The same discount rule applies to new eSIM orders and topups unless owner defines separate topup pricing later.

## Orders and topups
- Panel and API order creation should share the same service rules.
- Quantity must be bounded; bulk order UX should support tour/company use cases without allowing accidental large debits.
- Provider failure means order/topup status failed, `needs_admin=1`, redacted provider log, and admin retry/manual resolution.
- Test mode must never call the real provider.

## API keys
- API keys are stored hashed only; plaintext is shown once at creation/rotate.
- API logs must never include raw Authorization/API key values.
- Rate limit applies by key/IP/endpoint and returns HTTP 429.

## Exports
- CSV/Excel exports are for operations: order reconciliation, ICCID handoff, and agency records.
- Exports must not include secret API key plaintext.
