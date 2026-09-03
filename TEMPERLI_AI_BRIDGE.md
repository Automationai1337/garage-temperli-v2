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
- `health.php` provides a zero-model-call readiness check: HTTP 200 only when required PHP runtime capabilities and the two server-side bridge settings are present; otherwise HTTP 503. It exposes no secret values.
- `contact.php` is hardened separately for the existing website form; its test recipient remains unchanged.
- `.gitignore` blocks common secret/runtime files from future commits.

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
- `chat-proxy.php` PHP 8.4 syntax: PASS (`php -l`).
- Missing AI server config: PASS, HTTP 503 before upstream call.
- Invalid AI Origin: PASS, HTTP 403.
- Unknown AI request field: PASS, HTTP 400.
- Invalid AI session ID: PASS, HTTP 422.
- `script.js` JavaScript syntax: PASS (`node --check`).
- Original contact endpoint reproduced a PHP 500 without mbstring; hardened `contact.php` removes that hard dependency.
- Hardened `contact.php` PHP syntax: PASS; valid local payload reaches mail stage (local mail unavailable -> expected HTTP 503, not 500); unknown field HTTP 400; invalid service HTTP 422.
- `health.php` PHP syntax: PASS; missing config/runtime HTTP 503; POST method HTTP 405.

## Not yet proven
- Hostinger/PHP runtime compatibility on the real staging host.
- n8n header-auth gate and webhook payload compatibility.
- OpenAI response through the real workflow.
- Appointment/request storage.
- Telegram handoff and poll/read.
- Real website-to-n8n E2E.
- Real form mail delivery.

Do not call this production-ready until those points are proven with the minimum controlled E2E test.
