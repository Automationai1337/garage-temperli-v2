# Zantua AI — ChatGPT ↔ Claude Handoff

## Purpose
This file is the shared coordination channel for Garage Temperli work. Both assistants must read the latest state before acting and append/update the handoff after material changes.

## Current priority
Finish Garage Temperli before dashboard cleanup or unrelated projects.

## Current architecture decision
- Temperli should run through its own website/server path, not depend unnecessarily on the zantua-ai.com website.
- Reuse n8n workshop stack: `09HVPJgxGyFCRIeJ`.
- Required: website AI, OpenAI target path, Telegram human handoff, poll/read, appointment-request storage, vehicle identifier, name, phone, requested date/time, security/cost guard.
- Not required for Temperli: Outlook calendar, automatic calendar booking, email handoff.

## Website state
Repository: `Automationai1337/garage-temperli-v2`
Staging: `https://garage-temperli.zantua-ai.com/`
- Premium responsive site exists.
- Contact/appointment form exists.
- Temporary test email recipient is `kontakt@zantua-ai.com`; later switch to `info@garagetemperli.ch`.
- Form email delivery is not yet E2E-proven.
- Visible AI widget exists, but its real backend connection is not yet E2E-proven.

## Security rules
- No API/widget secrets in browser code.
- Tenant resolved/fixed server-side.
- Origin allowlist and request-body allowlist.
- Server-side rate limiting before n8n/model.
- Do not trust client-supplied proxy/IP headers.
- Unknown tenant/request fails closed.
- Block before paid model call where possible.
- No unnecessary paid AI calls or external sends during development.

## Collaboration protocol
1. Read this file and the latest n8n HANDOFF sticky before work.
2. Verify claims against repo/workflow where possible.
3. Reuse existing components; do not build parallel flows without evidence they are missing.
4. Work autonomously on reversible/static/configured steps.
5. Stop only when user input, credentials, a destructive action, a paid external test, or a consequential production decision is genuinely required.
6. After work, update this file AND the n8n HANDOFF sticky with:
   - exact changes
   - unchanged items
   - tests and exact results
   - evidence level: static / configured / real E2E
   - `production_changed`
   - `paid_ai_calls`
   - `external_sends`
   - credentials/config still needed, without secrets
   - remaining risks/open issues
   - exactly one recommended next step

## Current user authorization
The user has authorized continuing the Temperli implementation without repeatedly asking for routine handoff confirmations. Small API cost for the required final AI connection is acceptable. Still stop before destructive cleanup, secret disclosure, uncontrolled external sends, or materially risky production changes.

## Last ChatGPT update — 2026-09-03
Working branch: `work/temperli-ai-bridge-20260903` (not merged to `main`). Draft PR: `#1`.

### Exact changes
- Added `chat-proxy.php` as a same-origin server bridge for the website AI.
- Proxy fixes `tenant=garage-temperli` and `source=garage-temperli-web` server-side.
- Added strict Origin allowlist, JSON/body allowlist, body size limit, session validation and server-side rate limiting before n8n/model.
- Proxy uses only `REMOTE_ADDR`; client-supplied proxy/IP headers are not trusted.
- Added server-only config contract: `TEMPERLI_N8N_URL` and `TEMPERLI_N8N_SHARED_SECRET`.
- Proxy sends `X-Zantua-Bridge-Key` to n8n and fails closed when URL/secret is absent or invalid.
- TLS verification is required for upstream; redirects are disabled.
- Updated branch `script.js` so `GT_AI_CONFIG.endpoint` points to same-origin `chat-proxy.php` before `ai-chat.js` loads.
- Hardened branch `contact.php`: removed hard mbstring dependency, added request-key allowlist, explicit service/date/time validation and lock failure handling. Test recipient remains internal.
- Added `health.php` as a zero-model-call readiness endpoint: 200 only when cURL/temp runtime and required server settings are present; otherwise 503. No secret values are exposed.
- Added `.gitignore` to block common secret/private-key/local-runtime files from future commits.
- Added/updated `TEMPERLI_AI_BRIDGE.md` with deployment/auth contract and test evidence.

### Unchanged
- `main` is unchanged by this work.
- No n8n node/workflow/credential was changed because this run had no n8n tool access.
- No OpenAI prompt/model configuration changed.
- No Telegram configuration changed.
- Customer email recipient was not changed.

### Tests and exact results
- `php -l chat-proxy.php`: PASS on PHP 8.4.23.
- Valid staging Origin with missing AI server config: HTTP 503, fail-closed before upstream call.
- AI invalid Origin: HTTP 403; unknown field: HTTP 400; invalid session: HTTP 422.
- `node --check` for updated `script.js`: PASS.
- Existing `contact.php` was reproduced locally and exposed a PHP 500 when mbstring is absent (`mb_substr` undefined).
- Hardened `contact.php`: `php -l` PASS; valid payload reached mail stage and returned expected HTTP 503 because local mail is unavailable, not HTTP 500; unknown field HTTP 400; invalid service HTTP 422.
- `health.php`: PHP syntax PASS; missing config/runtime returns HTTP 503; POST returns HTTP 405.
- Lightweight default-branch code search found no obvious OpenAI key/secret tokens. This is not a Git-history secret audit.

### Evidence level
- Website AI bridge: **static**.
- Frontend endpoint wiring: **configured on branch**.
- Contact hardening: **static**.
- Health/readiness monitoring: **static**.
- n8n header gate: **not verified**.
- OpenAI response: **not E2E verified**.
- Request storage: **not verified**.
- Telegram handoff/poll-read: **not E2E verified**.
- Form mail delivery: **not E2E verified**.

`production_changed = false`
`paid_ai_calls = 0`
`external_sends = 0`

### Current AI/n8n technology assessment
- Do not add Responses API background mode or WebSocket mode to Temperli now. Those capabilities can help a future long-running agency orchestrator, but they do not remove the current n8n/Telegram E2E blocker and would add integration complexity now.
- Before exposing the Temperli workflow, verify the installed n8n version is fully patched/current. 2026 n8n advisories include critical/high issues affecting older 1.x/2.x releases, including webhook/file-access and task-runner isolation issues.
- After access is available, run the built-in n8n security audit and use the current safe Save/Publish workflow rather than changing production blindly.

### Credentials/config still needed (do not expose secret values)
- Real production webhook URL for approved Temperli path in workflow `09HVPJgxGyFCRIeJ`.
- Strong shared secret configured both server-side and in an n8n gate that checks `X-Zantua-Bridge-Key` before any paid/external node.

### Remaining risks/open issues
- Need current n8n HANDOFF sticky/workflow inspection before asserting payload compatibility or existing security gate.
- Need installed n8n version/security-audit verification before enabling the public AI path.
- Need Hostinger runtime verification for PHP cURL/temp-file rate limiting and actual mail transport.
- Need controlled real E2E for AI response, request storage, Telegram handoff and poll/read.
- Repository visibility is public; proprietary code is not private until repo visibility is corrected.

### Exactly one recommended next step
Inspect workflow `09HVPJgxGyFCRIeJ` and its latest HANDOFF sticky, verify n8n is current/patched, then configure/verify the `X-Zantua-Bridge-Key` gate before the first controlled paid E2E call.
