# Support CSKH Agent

This branch contains an independent customer-support agent that can be integrated from the jp-esim control panel later without merging into main first. It is intentionally scoped to public eSIM support: install help, QR activation, order lookup guidance, top-up guidance, data/day-use questions, and public support policy.

## Files

- Endpoint: `public_html/api/support-agent.php`
- Widget include: `public_html/support-agent-widget.php`
- Widget assets: `public_html/assets/support-agent-widget.js`, `public_html/assets/support-agent-widget.css`
- Agent module: `home/foamljf4kvet/app/support_agent/`
- Config helper/schema: `home/foamljf4kvet/app/support_agent/SupportAgentConfig.php`
- Memory directory: `home/foamljf4kvet/support_agent_state/`
- Local test: `scripts/support_agent_test.php`

## Config Keys

`SupportAgentConfig::publicSchema()` returns the admin-ready list of fields, labels, defaults, value types, and safe descriptions. It never includes a real secret value.

| Field | Key | Default | Notes |
| --- | --- | --- | --- |
| `enabled` | `SUPPORT_AGENT_ENABLED` | `false` | Enables outbound AI calls. When false, the endpoint still serves local guarded answers and performs no provider network call. |
| `provider` | `SUPPORT_AGENT_PROVIDER` | `9router` | Current adapter name. |
| `router_api_key` | `SUPPORT_AGENT_9ROUTER_API_KEY` | empty | Secret. Store only in environment/server config. |
| `router_model` | `SUPPORT_AGENT_9ROUTER_MODEL` | `support-agent` | Chat model name sent to 9router. |
| `router_endpoint` | `SUPPORT_AGENT_9ROUTER_ENDPOINT` | `https://api.9router.ai/v1/chat/completions` | Must remain HTTPS. |
| `widget_enabled` | `SUPPORT_AGENT_WIDGET_ENABLED` | `true` | Controls whether the widget include renders asset tags. |
| `memory_path` | `SUPPORT_AGENT_MEMORY_PATH` | `home/foamljf4kvet/support_agent_state` | Keep outside `public_html`. |
| `memory_ttl_seconds` | `SUPPORT_AGENT_MEMORY_TTL_SECONDS` | `86400` | Clamped by the helper. |
| `memory_message_limit` | `SUPPORT_AGENT_MEMORY_MESSAGE_LIMIT` | `8` | Stores recent redacted turns only. |
| `rate_limit_max` | `SUPPORT_AGENT_RATE_LIMIT_MAX` | `12` | Requests per client IP per window. |
| `rate_limit_window_seconds` | `SUPPORT_AGENT_RATE_LIMIT_WINDOW_SECONDS` | `60` | Rate-limit window. |
| `escalation_text` | `SUPPORT_AGENT_ESCALATION_TEXT` | Vietnamese handoff text | Customer-facing handoff copy. |

## 9router Setup

Set the values in environment or server config, not in versioned files:

```php
'SUPPORT_AGENT_ENABLED' => '1',
'SUPPORT_AGENT_PROVIDER' => '9router',
'SUPPORT_AGENT_9ROUTER_API_KEY' => '<server-side-secret>',
'SUPPORT_AGENT_9ROUTER_MODEL' => '<model-name>',
'SUPPORT_AGENT_9ROUTER_ENDPOINT' => 'https://api.9router.ai/v1/chat/completions',
```

For local tests, keep `SUPPORT_AGENT_TEST_NO_NETWORK=1`. The test suite must not call 9router or any payment/top-up/provider API.

## Endpoint Contract

`POST /api/support-agent.php`

Request JSON:

```json
{
  "message": "Cach cai eSIM tren iPhone?",
  "conversation_id": "optional-client-id",
  "locale": "vi",
  "page_context": {
    "path": "/tra-cuu.php",
    "title": "Tra cuu don hang",
    "section": "optional",
    "product_type": "optional"
  }
}
```

Success response, HTTP `200`:

```json
{
  "answer": "Customer-facing answer",
  "conversation_id": "sa_generated_or_reused_id",
  "safe_topic": "install_ios",
  "escalation": false,
  "citations": [],
  "help_links": [{"title": "Tra cuu don hang", "url": "/tra-cuu.php"}],
  "locale": "vi"
}
```

Error response:

```json
{
  "ok": false,
  "code": "VALIDATION_ERROR",
  "message": "Customer-facing error"
}
```

Status codes: `400`, `403`, `405`, `413`, `429`, and `500`.

The endpoint accepts same-origin browser calls only. Request body is capped at 8192 bytes and message text at 1600 characters.

## Widget Include

Add this include on pages where the support widget should appear:

```php
<?php include __DIR__ . '/support-agent-widget.php'; ?>
```

The include checks `SUPPORT_AGENT_WIDGET_ENABLED`. The browser stores only the conversation id in `localStorage`; conversation content is stored server-side after redaction.

## Memory

Conversation memory is JSON under `SUPPORT_AGENT_MEMORY_PATH`, defaulting to `home/foamljf4kvet/support_agent_state/`. Keep this directory outside `public_html`, writable by PHP, and not backed by public downloads. Stored messages are redacted and trimmed to `SUPPORT_AGENT_MEMORY_MESSAGE_LIMIT`; expired memory is ignored after `SUPPORT_AGENT_MEMORY_TTL_SECONDS`.

## Safety Policy

- Allowed: public eSIM installation help, QR/activation help, order lookup guidance, top-up guidance, public refund/support policy, and human handoff.
- Blocked: secret/config requests, prompt extraction, source/config disclosure, credentials, server paths, database details, control-panel data, partner data, provider details, internal plan identifiers, and unrelated content.
- The agent must not claim live order, payment, fulfillment, top-up, or provider state unless a later integration explicitly supplies that public-safe context.
- Tests must use local fakes/config and must not call AI, payment, top-up, or provider networks.
