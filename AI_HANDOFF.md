# Zantua AI — ChatGPT ↔ Claude Handoff

## Purpose
This file is the shared coordination channel for Garage Temperli work. Both assistants must read the latest state before acting and update the handoff after material changes.

## Current priority
Finish Garage Temperli before dashboard cleanup or unrelated projects.

## Current architecture decision
- Temperli runs through its own website/server path.
- Reuse existing n8n workshop stack `09HVPJgxGyFCRIeJ`; do not build a parallel backend without proof it is necessary.
- Required: website AI, OpenAI target path, Telegram human handoff, poll/read, request/conversation storage, vehicle identifier, name, phone, requested date/time, security/cost guard, monitoring and E2E.
- Not required for Temperli: Outlook automatic calendar booking or email handoff.

## Website state
Repository: `Automationai1337/garage-temperli-v2`
Staging: `https://garage-temperli.zantua-ai.com/`
Working branch: `work/temperli-ai-bridge-20260903`
Draft PR: `#1`
- Premium responsive site exists.
- Contact/appointment form exists.
- Temporary test email recipient remains `kontakt@zantua-ai.com`; later switch only after E2E/approval.
- Visible AI widget exists.
- Current branch adds secure same-origin chat, poll and read bridges; not merged/deployed.

## Security rules
- No API/widget secrets in browser code or GitHub.
- Tenant resolved server-side from the server-held Temperli widget key.
- Origin allowlist and request-body allowlists.
- Server-side rate limiting before n8n/model.
- Do not trust client-supplied proxy/IP headers.
- Unknown/invalid requests fail closed.
- Reuse existing tenant auth rather than inventing an unnecessary second auth system.
- No unnecessary paid AI calls or external sends during development.

## Collaboration protocol
1. Read this file and the latest n8n HANDOFF evidence before work.
2. Verify claims against repo/workflow/export where possible.
3. Reuse existing components; do not build parallel flows without evidence they are missing.
4. Work autonomously on reversible/static/configured steps.
5. Stop only when credentials, a destructive action, a paid external test, or a consequential production decision is genuinely required.
6. After material work record exact changes, unchanged items, tests/results, evidence level, production change, paid calls/sends, missing config, risks and exactly one next step.

## Current user authorization
Routine Temperli implementation may continue without repeated confirmations. Small API cost for the final required connection is acceptable. Do not expose secrets, send uncontrolled customer communications, or perform destructive/risky production changes without the necessary gate.

## Latest ChatGPT update — 2026-09-03, run 2

### Evidence inspected
A File Library export named `werkstatt-assistent-backend-EXPORT.json` states it was exported from the live workflow `09HVPJgxGyFCRIeJ` after the v7 merge and that backend execution 383 passed with a test tenant. This export is dated 2026-08-25, so it is strong architecture evidence but not proof that the live workflow has not changed since then.

The export proves the existing web-chat contract at that point:
- `werkstatt-chat` authenticates with `X-Widget-Key` / `x-widget-key`.
- Tenant is resolved from `Werkstaetten.widget_key` and active state.
- Chat accepts `message`, `conversationId`, `visitorId`, optional `messageId`, channel metadata.
- Response returns `reply`, `conversationId`, `escalate`.
- Request/message/conversation/visitor/customer storage exists and is tenant-scoped in key paths.
- `werkstatt-chat-poll` and `werkstatt-chat-read` exist and use the same widget-key tenant authorization.
- Telegram escalation exists.
- The main workflow's Telegram reply trigger is intentionally disabled because the export states a separate active workflow `Werkstatt-Assistent – Telegram-Team` owns the reply trigger/handoff chain.
- The model node in this inspected export is `Claude fragen`, calling Anthropic Messages with `claude-sonnet-5`. Therefore the required OpenAI production path is **not proven and appears not configured in this export**.

### Exact changes this run
1. `contact.php`
   - Added semantic calendar-date validation with Europe/Zurich timezone.
   - Rejects past dates.
   - Rejects Sunday.
   - Requires a date when a time is supplied.
   - Rejects requested times outside Mon–Fri 07:30–12:00 / 13:00–18:00 and Saturday 08:00–12:00.
   - Test mail recipient unchanged.
   - Commit: `f6f7d1b0ccb4957f6899943223b018b48940e495`.

2. `chat-proxy.php`
   - Removed the incompatible new `X-Zantua-Bridge-Key` design after inspecting the existing backend contract.
   - Now reuses the existing `X-Widget-Key` contract, with the widget key held only in server env `TEMPERLI_WIDGET_KEY`.
   - Browser cannot choose tenant; existing n8n tenant lookup resolves Temperli from the server-held key.
   - Stable restricted `conversationId`/`visitorId` are SHA-256-derived from the browser session; unique `messageId` is added for n8n dedupe.
   - Keeps strict Origin/body validation, server-side rate limiting, TLS validation, redirect blocking and fail-closed config.
   - Commit: `4a5af88c410b703fe98618062dacadac3e46458c`.

3. Added `chat-poll.php`
   - Same-origin, server-keyed proxy to existing `werkstatt-chat-poll` contract.
   - Derives conversation ID server-side from session.
   - Bounded reply parsing and extra poll-rate guard.
   - Commit: `e97e3a63562dd7bdd267a19cf6d2fc74927ba215`.

4. Added `chat-read.php`
   - Same-origin, server-keyed proxy to existing `werkstatt-chat-read` contract.
   - Derives conversation ID server-side and validates/bounds reply IDs.
   - Commit: `249e3a0bd58e0e7700921f283f82f9df2d254c3c`.

5. `script.js`
   - Configures `chat-proxy.php`, `chat-poll.php`, `chat-read.php`.
   - Commit: `11c4c16a37ad8c02582d7c9f285bbddc2a5521e9`.

6. `ai-chat.js`
   - Removed the misleading fake-success fallback when no backend is configured; now shows a real connection error/fallback phone number.
   - Adds adaptive polling after normal chat activity and longer polling after escalation.
   - Polling stops when the chat is closed/hidden, reducing unnecessary n8n executions.
   - Human replies render as `Garage Temperli Team`.
   - Read acknowledgement is sent only while the chat panel is open and the page is visible.
   - Commit: `6b758bbfc36ced3c3081ea51f4e3516736dd3b59`.

7. `health.php`
   - Readiness now requires all full-handoff config: chat URL, poll URL, read URL, widget key, cURL and writable temp runtime.
   - Latest commit: `9e1c03acb11be5476d12cd14a8d3a7eea7586f33`.

8. `TEMPERLI_AI_BRIDGE.md`
   - Rewritten to document the verified existing n8n contract, corrected server-side auth approach, poll/read path, evidence levels and minimum production path.
   - Commit: `b8a770f9cde223463b2bdd1173a6307508c15047`.

### Unchanged
- `main` unchanged.
- Production/deployed website unchanged by these branch commits.
- No live n8n workflow/node/credential changed in this run.
- No OpenAI configuration changed.
- No Telegram configuration changed.
- No customer email recipient changed.
- Repository visibility remains public until manually corrected.

### Tests / exact results
- New readable `ai-chat.js`: `node --check` PASS before commit.
- GitHub accepted all current branch file changes.
- Existing earlier revisions had passed PHP 8.4 syntax/fail-closed checks, but the **exact latest PHP revisions from this run have not been executed on a PHP runtime** after editing because this environment could not obtain a local GitHub checkout/runtime path for them. Do not upgrade their evidence level beyond static/configured.
- No paid model call performed.
- No Telegram/customer/email send performed.
- No production deployment performed.

### Evidence level
- Website UI: **existing / not changed in production**.
- Latest frontend bridge + poll/read wiring: **configured on branch**.
- Latest `ai-chat.js`: **static syntax checked**.
- Latest PHP files: **static/configured, not runtime-tested after latest edits**.
- Existing n8n widget-key auth/storage/poll/read architecture: **verified from 2026-08-25 live-workflow export; export metadata reports prior real backend execution 383 with test tenant**.
- Current live n8n state after 2026-08-25: **not verified**.
- Separate Telegram-Team workflow current state: **not inspected**.
- OpenAI: **not configured/proven; inspected backend export uses Anthropic Claude**.
- Website -> PHP -> n8n -> model -> storage -> Telegram -> poll/read: **not E2E proven**.

`production_changed = false`
`paid_ai_calls = 0`
`external_sends = 0`

### Server config still needed (never expose values)
- `TEMPERLI_N8N_URL` -> current production `werkstatt-chat` URL.
- `TEMPERLI_N8N_POLL_URL` -> current production `werkstatt-chat-poll` URL.
- `TEMPERLI_N8N_READ_URL` -> current production `werkstatt-chat-read` URL.
- `TEMPERLI_WIDGET_KEY` -> existing active Temperli widget key from `Werkstaetten`, set only server-side.

### Remaining risks/open issues
- Need current live n8n read/access to verify that the 2026-08-25 export still matches production.
- Need inspect current separate `Werkstatt-Assistent – Telegram-Team` before claiming human reply E2E.
- Need switch/abstract the current Anthropic model node to OpenAI if OpenAI is still a hard Temperli requirement.
- Need Hostinger PHP runtime/config deployment and exact latest PHP syntax/runtime verification.
- Need one controlled E2E with minimum paid/external actions.
- Need monitor/readiness check after deploy.
- Repository is still public; proprietary code is not private until visibility is changed.

### Exactly one recommended next step
Obtain current n8n workflow access/readback, verify `09HVPJgxGyFCRIeJ` plus the separate Telegram-Team workflow against the inspected contract, then make the smallest OpenAI-compatible model change and run one controlled E2E through chat -> storage -> Telegram -> poll -> read before merging/deploying.
