# Topup autofill/direct API fix

- `topup.php?id=...` stores the id in sessionStorage/localStorage then redirects to `/?topup_id=...#topup`.
- `app.js` reads `topup_id`, `iccid`, hash query, and storage fallback, then opens the Topup tab, fills the input and calls lookup.
- `TopupService::lookup()` now only queries the database when the input is an order id matching `N[A-Z0-9]{7}`.
- If the input is an ICCID, it calls eSIMAccess directly by ICCID and does not require the ICCID to exist in local tables.
