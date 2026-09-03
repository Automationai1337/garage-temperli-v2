# Garage Temperli — Structured Handoff — 2026-09-03 Run 2

## What changed

- Hardened the website appointment/contact validation on the work branch.
- Corrected the website AI bridge to reuse the existing live n8n `X-Widget-Key` tenant-auth contract server-side instead of introducing a parallel bridge-key architecture.
- Added same-origin website proxies for chat, human-reply poll, and read acknowledgement.
- Added stable server-derived conversation/visitor IDs and per-message dedupe IDs.
- Added adaptive browser polling for Telegram/team replies and read acknowledgement only while the chat is open/visible.
- Removed fake local AI-success fallback; unavailable backend now fails visibly and safely.
- Expanded readiness `health.php` to require the complete chat/poll/read server configuration.
- Added cache-busting for the current bridge/handoff scripts.
- Added `TEMPERLI_LIVE_GATE.md` with the corrected live evidence and Telegram tenant-safety gate.
- Added `TEMPERLI_OPENAI_MIGRATION.md` describing the smallest safe migration from the current Anthropic adapter to OpenAI Responses API structured function calling without rebuilding the workflow.

## What did NOT change

- `main` was not changed.
- Production website was not deployed/changed.
- Live n8n workflow `09HVPJgxGyFCRIeJ` was not changed.
- Separate live `Werkstatt-Assistent – Telegram-Team` was not changed.
- No OpenAI credential/model node was changed.
- No Telegram credential/config was changed.
- No customer email recipient was changed.
- No secret was added to GitHub/browser code.

## Why

The existing backend export proves we already have tenant lookup, storage, Telegram escalation and poll/read contracts. Rebuilding those would create unnecessary duplicate architecture. The website bridge therefore adapts to the existing contract and keeps the tenant widget key server-side.

The production gate remains strict because dated evidence is not the same as current live proof. In particular, later 2026-08-26 evidence corrects an earlier live-bug claim: the live main first-contact zero-result settings were reported as already correct, while the live `Antworten abrufen` empty-poll behavior was explicitly left unverified. A separate v14 STAGING test also proved a Telegram cross-tenant ambiguity when tenant selection relied on shared `telegram_chat_id`; the safe conversation-first tenant-binding design passed executions `#395`, `#396`, and `#398` in STAGING.

## Tests performed / exact results

- Current `ai-chat.js`: `node --check` PASS before commit.
- GitHub accepted all branch changes.
- Earlier bridge revisions had passed PHP syntax/fail-closed checks, but the exact latest PHP revisions could not be runtime-linted in this run because the local execution environment could not resolve the GitHub raw host. Therefore latest PHP remains STATIC/CONFIGURED only.
- No controlled paid model E2E was run because current live n8n/Telegram-Team state is not yet read back.

## Evidence level

- Website UI: EXISTING.
- Website bridge/poll/read implementation: CONFIGURED ON BRANCH.
- Frontend JavaScript: STATIC SYNTAX PASS.
- Latest PHP: STATIC/CONFIGURED, NOT CURRENT RUNTIME-PROVEN.
- Existing live n8n chat/storage/poll/read architecture: VERIFIED FROM 2026-08-25 LIVE EXPORT.
- STAGING first-contact zero-result fix: REAL TESTED (`#389`, invalid key `#394`).
- Later dated LIVE main first-contact settings: READ-COMPARED/REPORTED CORRECT, NOT CURRENT PROOF.
- LIVE `Antworten abrufen` empty-poll path: EXPLICITLY UNVERIFIED IN DATED EVIDENCE.
- STAGING Telegram conversation-first tenant binding: REAL TESTED (`#395` allow, `#396` mismatch block, `#398` invented conversation block).
- Current separate live Telegram-Team state: UNKNOWN.
- Current OpenAI path: NOT PROVEN; inspected backend still uses Anthropic Claude.
- Full Temperli website -> n8n -> model -> storage -> Telegram -> poll -> read: NOT E2E PROVEN.

## Costs / external actions

- `production_changed = false`
- `paid_ai_calls = 0`
- `external_sends = 0`

## Credentials/config still needed — do not expose values

Website server only:
- `TEMPERLI_N8N_URL`
- `TEMPERLI_N8N_POLL_URL`
- `TEMPERLI_N8N_READ_URL`
- `TEMPERLI_WIDGET_KEY`

n8n:
- Current OpenAI credential/readiness must be verified before the model adapter is changed.

## Remaining risks / open issues

- Current live `09HVPJgxGyFCRIeJ` readback unavailable in this run.
- Current separate Telegram-Team readback unavailable; must confirm single trigger ownership and conversation-first tenant binding.
- Empty-poll path must return a clean empty response.
- OpenAI adapter/parser still needs implementation in n8n and static validation before any paid E2E.
- Latest PHP must be runtime-checked on the actual Hostinger/PHP environment.
- Server-only environment values still need deployment.
- One controlled E2E is still required before merge/deploy/PRODUCTION status.
- Repository visibility should be PRIVATE if this is proprietary code.

## Recommended next step

Read back CURRENT live `09HVPJgxGyFCRIeJ` and the separate `Werkstatt-Assistent – Telegram-Team`. Verify `Antworten abrufen` empty-poll behavior and conversation-first Telegram tenant binding. If clean, implement the minimal OpenAI Responses adapter from `TEMPERLI_OPENAI_MIGRATION.md`, mock/static-test it first, then spend only the minimum controlled E2E calls needed for the production gate.
