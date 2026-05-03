# jpesim Business Model

## Positioning
jpesim / jp-esim.vip is not a global travel eSIM marketplace. It is a Japan-focused SIM/eSIM system for Vietnamese resellers, travel companies, visa agencies, wholesale agents, and secondarily retail travelers to Japan.

## Primary customers
1. CTV and Vietnamese resellers selling Japan eSIM/SIM to end travelers.
2. Travel companies needing repeat/bulk Japan connectivity orders.
3. Visa companies bundling Japan connectivity with visa services.
4. Wholesale agents needing wallet, discount, API, export, and order history.
5. Retail travelers buying without login; this remains supported but is not the strategic center.

## Revenue and operating model
- Retail orders: visitor enters email, pays by bank transfer, bank webhook marks paid, system creates eSIM through the legacy provider API, then shows and emails QR/ICCID.
- CTV/B2B orders: CTV verifies email, prepays wallet balance, receives per-eSIM discount (default tiers 10k/20k or custom), then creates new eSIM/topup via panel or API.
- Admin operation: admin manages products, prices, CTV status, discount/tier, wallet ledger, failed provider orders, provider logs, bank logs, and manual/retry resolution.

## Non-negotiables
- Do not rebuild from scratch or discard the old production flow. Current source includes real bank webhook, order flow, email, captcha, provider calls, lookup, and product DB.
- Refactor production behavior in place, preserving working flows and adding B2B/CTV structure around them.
- Provider calls for tests must stay disabled unless an owner explicitly approves a real-order test.
