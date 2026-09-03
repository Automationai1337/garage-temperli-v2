# Garage Temperli AI bridge deployment

## Goal
Keep the existing Werkstatt-Assistent tenant credential server-side and route the public Garage Temperli widget through same-origin PHP bridges before n8n/model calls or human-handoff operations.

## Verified existing n8n contract
The File Library export `werkstatt-assistent-backend-EXPORT.json` states it was exported from live workflow `09HVPJgxGyFCRIeJ` after the v7 merge and had a successful live test on execution 383 with a test tenant.

The exported live web-chat contract is:
- Chat webhook path: `werkstatt-chat`.
- Authorization header: `X-Widget-Key` / `x-widget-key`.
- Tenant resolution: `Werkstaetten.widget_key` -> active tenant row. The browser does not need to send a tenant ID.
- Request fields consumed by the existing path include `message`, `conversationId`, `visitorId`, optional `messageId`, and `channel`.
- Success response contains `reply`, `conversationId`, and `escalate`.
- Poll webhook path: `werkstatt-chat-poll`, authorized by the same widget key and tenant-scoped before reading `Team-Antworten`.
- Read webhook path: `werkstatt-chat-read`, authorized by the same widget key and tenant-scoped before updating read state.
- Request/message/conversation storage already exists in that workflow.
- Telegram escalation already exists. The Telegram reply trigger inside the main backend is intentionally disabled because the export says the separate active workflow `Werkstatt-Assistent – Telegram-Team` owns that trigger.

Important: this export currently calls Anthropic (`Claude fragen` -> `https://api.anthropic.com/v1/messages`). It is **not evidence that the requested OpenAI path is configured**.

## Current branch implementation
- `script.js` configures `chat-proxy.php`, `chat-poll.php`, and `chat-read.php` before `ai-chat.js` loads.
- `chat-proxy.php` keeps the real Temperli widget key server-side and sends it upstream as `X-Widget-Key` to reuse the existing live n8n authorization contract instead of creating a parallel auth architecture.
- The browser cannot choose a tenant. n8n resolves the tenant from the server-held widget key.
- Browser `sessionId` is hashed server-side into stable restricted IDs:
  - `conversationId = gtc-<sha256-prefix>`
  - `visitorId = gtv-<sha256-prefix>`
  - each request receives a unique `messageId = gtm-<request-id>`
- Browser-supplied `tenant`, `page`, and `origin` metadata is accepted only for backward compatibility and is not forwarded for tenant selection.
- Strict website Origin allowlist: staging plus `garagetemperli.ch` and `www.garagetemperli.ch`.
- JSON/body allowlists and size limits.
- `chat-proxy.php` rate-limits by `REMOTE_ADDR` before n8n/model; client proxy/IP headers are not trusted.
- `chat-poll.php` uses the same server-side widget key and derived conversation ID, with an additional poll-rate guard.
- `chat-read.php` uses the same server-side widget key and derived conversation ID and accepts only a bounded list of reply IDs.
- `ai-chat.js` performs adaptive polling after normal chat activity and longer polling after an escalation. Polling pauses when the panel is closed/hidden. Human replies are rendered as `Garage Temperli Team`; read acknowledgement is sent only while the chat is open and visible.
- `health.php` is a zero-model-call readiness endpoint. It returns HTTP 200 only when PHP cURL/temp runtime and all four required server settings are present. It exposes no secret values.
- `contact.php` separately validates request fields, real calendar dates, past dates, Sunday closure and published opening-hour windows. Its recipient remains internal during test phase.
- `.gitignore` blocks common secret/runtime files from future commits.

## Server configuration required before controlled E2E
Set these only on the Garage Temperli website server, never in GitHub/browser code:

- `TEMPERLI_N8N_URL` = production URL ending in the approved `werkstatt-chat` webhook.
- `TEMPERLI_N8N_POLL_URL` = production URL for `werkstatt-chat-poll`.
- `TEMPERLI_N8N_READ_URL` = production URL for `werkstatt-chat-read`.
- `TEMPERLI_WIDGET_KEY` = existing active Temperli `widget_key` from the n8n `Werkstaetten` table.

Do not commit or paste the widget key into repository files, browser JavaScript, documentation, chat messages, or screenshots.

## Compatibility result
The previous branch design used a new `X-Zantua-Bridge-Key` and a different request body. Inspection of the exported live backend showed that design would not authenticate against the existing workflow. The branch has therefore been corrected to reuse the existing `X-Widget-Key` contract server-side. This removes an unnecessary new n8n security-gate architecture while keeping the key out of the browser.

## Verification performed
Previously verified on the earlier bridge revision:
- PHP syntax for the original `chat-proxy.php`: PASS on PHP 8.4.
- Missing server config failed closed before upstream call.
- Invalid Origin, unknown request field, and invalid session were rejected.
- Previous `script.js`: `node --check` PASS.
- Earlier hardened `contact.php`: PHP syntax PASS and local validation tests reached the expected mail-stage failure rather than PHP 500.
- Earlier `health.php`: syntax/method/fail-closed checks passed.

Current revision:
- New `ai-chat.js` with adaptive poll/read logic: `node --check` PASS before commit.
- GitHub accepted all current PHP/JS branch updates.
- The exact current PHP revisions (`chat-proxy.php`, `chat-poll.php`, `chat-read.php`, `health.php`, latest `contact.php`) have **not** yet been executed on a PHP runtime after their latest edits. Treat them as static/configured, not runtime-proven.

## Evidence level now
- Website UI and bridge wiring: **configured on branch**.
- JavaScript poll/read implementation: **static syntax checked**.
- Existing live n8n auth/storage/poll/read architecture: **verified from 2026-08-25 export; export metadata reports earlier live execution 383 for the backend test tenant**.
- Exact current website-PHP -> live n8n compatibility: **not E2E proven**.
- OpenAI model path: **not configured/verified; inspected export uses Anthropic Claude**.
- Separate `Werkstatt-Assistent – Telegram-Team` current live state: **not inspected in this run**.
- Hostinger environment/runtime: **not verified**.
- Real contact-form mail: **not E2E proven**.

## Minimum path to production
1. Confirm the current live n8n workflow has not materially diverged from the inspected export and inspect the separate Telegram-Team handoff workflow.
2. Configure the four server environment values above without exposing the widget key.
3. If OpenAI remains the required production model, replace/abstract the current Anthropic model step in n8n and statically verify structured output compatibility before sending a paid request.
4. Run one controlled E2E: website message -> PHP bridge -> authorized tenant -> model response -> storage -> forced/controlled Telegram escalation -> team reply -> poll -> visible customer reply -> read acknowledgement.
5. Only after that E2E and monitoring/health checks pass, merge/deploy the branch and mark Temperli `PRODUCTION + MONITORED`.

Do not call this production-ready before those points are proven.
