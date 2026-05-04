# CTV API

Read/write API for verified CTV accounts. All responses are JSON except the QR endpoint, which returns PNG.

## Base URL

```text
https://jp-esim.vip/api/ctv
```

## Authentication

Send the API key shown once in the CTV panel (`/ctv/api-keys.php`) by either header:

```http
Authorization: Bearer ctvK_<prefix>_<secret>
X-API-Key: ctvK_<prefix>_<secret>
```

Keys are scoped to the owning CTV. Requests from inactive/unverified CTVs are rejected.

## Safety Rules

- The API never returns raw LPA (`ac`), provider QR URLs, `shortUrl`, or provider domains.
- QR images are rendered server-side from stored activation data.
- `/api/ctv/esim_qr.php` requires the same API key and only serves ICCIDs owned by that CTV.
- Topup endpoints exist but production topup tests must stay locked unless explicitly approved.

## Endpoints

### `GET /products.php`

Query active CTV pricing.

Parameters:

- `type`: `esim` or `topup` (default: `esim`)
- `telecom`: optional carrier filter

Example:

```bash
curl -sS -H "X-API-Key: $CTV_API_KEY" \
  "https://jp-esim.vip/api/ctv/products.php?type=esim"
```

### `POST /quote.php`

Quote an eSIM order.

Body:

```json
{
  "planId": 123,
  "quantity": 1
}
```

### `POST /orders.php`

Create an eSIM order.

Body:

```json
{
  "planId": 123,
  "quantity": 1,
  "clientRef": "customer-optional-id",
  "email": "customer@example.com",
  "notes": "optional"
}
```

### `GET /orders.php`

List CTV orders.

Parameters:

- `limit`: default `50`
- `offset`: default `0`
- `status`: optional status filter

### `GET /orders.php?id=<order_id>`

Get one CTV order status. The result is scoped to the API key owner.

### `GET /esims.php`

List owned eSIMs.

Parameters:

- `limit`: default `50`, max `200`
- `offset`: default `0`

Each row includes a safe relative `qrUrl`:

```json
{
  "iccid": "8985...",
  "qrUrl": "/api/ctv/esim_qr.php?iccid=8985...",
  "email_sent_at": "2026-05-04 10:00:00"
}
```

### `GET /esim_qr.php?iccid=<iccid>`

Returns `image/png` for a CTV-owned ICCID.

Example:

```bash
curl -sS -H "X-API-Key: $CTV_API_KEY" \
  -o qr.png \
  "https://jp-esim.vip/api/ctv/esim_qr.php?iccid=$ICCID"
```

### `GET /wallet.php`

Returns current CTV wallet balance.

### `POST /topup.php`

Create a topup order for an owned ICCID. Do not use for real topup tests while `TOPUP_LOCKED=1`.

## Error Format

```json
{
  "ok": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "API key required"
  }
}
```

Common codes: `UNAUTHORIZED`, `RATE_LIMITED`, `VALIDATION_ERROR`, `METHOD_NOT_ALLOWED`, `RUNTIME_ERROR`, `SERVER_ERROR`.
