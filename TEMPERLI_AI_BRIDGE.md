# Garage Temperli AI bridge deployment

## Goal
Keep all model/n8n secrets server-side and route the public website widget through a same-origin PHP bridge before any paid model call.

## Current branch implementation
- `script.js` sets the widget endpoint to `chat-proxy.php` before `ai-chat.js` loads.
- `chat-proxy.php` fixes `tenant=garage-temperli` and `source=garage-temperli-web` server-side.
- Browser-supplied tenant/page/origin metadata is never forwarded upstream.
- Strict origin allowlist: staging plus `garagetemperli.ch` and `www.garagetemperli.ch`.
- JSON/body allowlist and size limits.
- Rate limiting by `REMOTE_ADDR` before n8n/model; proxy headers are not trusted.
- n8n URL and shared secret are read only from server environment.
- Proxy fails closed when URL/secret is missing or invalid.
- TLS verification is required for the n8n upstream and redirects are disabled.

## Server configuration required before merge/deploy
Set these values on the Garage Temperli website server, never in GitHub/browser code:

- `TEMPERLI_N8N_URL` = production webhook URL for the approved Garage Temperli path in n8n workflow `09HVPJgxGyFCRIeJ`.
- `TEMPERLI_N8N_SHARED_SECRET` = strong random shared secret used only between the website bridge and n8n.

## n8n requirement before paid AI is enabled
The inbound Temperli path must verify header `X-Zantua-Bridge-Key` against the same secret **before** any OpenAI/model node, Telegram send, storage write, or other external side effect. A mismatch or missing header must fail closed.

Expected request body from the proxy:

```json
{
  "tenant": "garage-temperli",
  "source": "garage-temperli-web",
  "message": "...",
  "sessionId": "gt_..."
}
```

Expected successful n8n response: JSON containing one non-empty string field among `answer`, `reply`, `output`, or `message`.

## Static verification performed
- PHP 8.4 syntax: PASS (`php -l`).
- Missing server config: PASS, HTTP 503 before upstream call.
- Invalid Origin: PASS, HTTP 403.
- Unknown request field: PASS, HTTP 400.
- Invalid session ID: PASS, HTTP 422.
- `script.js` JavaScript syntax: PASS (`node --check`).

## Not yet proven
- Hostinger/PHP runtime compatibility on the real staging host.
- n8n header-auth gate and webhook payload compatibility.
- OpenAI response through the real workflow.
- Telegram handoff and poll/read.
- Real website-to-n8n E2E.

Do not call this production-ready until those points are proven with the minimum controlled E2E test.
