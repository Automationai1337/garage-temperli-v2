# Garage Temperli — Structured Handoff — 2026-09-03 Run 3

## What changed

1. Hardened `chat-poll.php` reply/read acknowledgement correlation.
   - Previous code filtered `replies` and `replyIds` independently.
   - That could acknowledge an ID whose corresponding reply had been rejected/empty, potentially marking an unseen team reply as read.
   - New logic walks replies by original index and only forwards an ID when its matching reply was accepted; duplicate IDs are suppressed.

2. Hardened `health.php` readiness.
   - Previous health gate only checked that chat/poll/read env vars were non-empty.
   - It could report ready even with malformed/non-HTTPS upstream URLs.
   - New gate validates all three upstream URLs and requires HTTPS before returning `ok:true`.

3. Updated `TEMPERLI_OPENAI_MIGRATION.md` against current OpenAI public documentation.
   - `gpt-5.6-terra` remains the recommended first production model for the intelligence/cost balance.
   - Added explicit `store: false` for Responses API customer chats because Temperli already owns tenant-scoped conversation history/storage.
   - Keep web search, background mode and OpenAI-hosted conversation state disabled for this flow unless a proven requirement appears.

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

The poll correlation issue was a concrete reliability/data-integrity defect in the website bridge that could be fixed safely without touching production or guessing current n8n state. The health change prevents a false-green readiness signal. The OpenAI change reduces unnecessary provider-side application-state retention and vendor dependence while keeping the existing backend as source of truth.

## Tests performed / exact results

- `chat-poll.php` candidate: PHP 8.4 `php -l` -> PASS (`No syntax errors detected`).
- Poll normalization adversarial static fixture: input replies `[empty, valid, also]` with IDs `[wrong, id2, id2]` -> output replies `[valid, also]`, replyIds `[id2]`; the ID belonging to the rejected empty reply was not acknowledged and duplicate ID was removed.
- `health.php` candidate: PHP 8.4 `php -l` -> PASS (`No syntax errors detected`).
- Local CLI does not have the PHP cURL extension, so a positive `health.php` runtime-ready result cannot be proven in this environment; this is expected to remain a Hostinger runtime gate.
- GitHub accepted both exact PHP revisions and the OpenAI plan update on the work branch.
- Current n8n connector readback could not run in this non-interactive automation context because the connector requested user interaction. No old STAGING patch was applied blindly.

## Evidence level

- Updated `chat-poll.php`: STATIC SYNTAX PASS + deterministic normalization fixture PASS; not Hostinger runtime/E2E proven.
- Updated `health.php`: STATIC SYNTAX PASS; positive runtime readiness not proven because local PHP lacks cURL and Hostinger has not been checked.
- OpenAI migration plan: STATIC DESIGN; current public OpenAI docs re-checked 2026-09-03.
- Existing n8n architecture: VERIFIED FROM DATED LIVE EXPORT.
- Current live n8n + separate Telegram-Team state: STILL NOT VERIFIED in this run.
- Full website -> n8n -> OpenAI -> storage -> Telegram -> poll -> read: NOT E2E PROVEN.

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

- Current live `09HVPJgxGyFCRIeJ` still needs readback, with `Antworten abrufen` empty-poll behavior first.
- Current separate Telegram-Team still needs readback; must confirm single trigger ownership and conversation-first tenant binding.
- OpenAI adapter/parser still needs implementation in n8n and zero-cost pin/mock validation before any paid E2E.
- Latest PHP bridge files still need runtime checks on actual Hostinger/PHP with cURL enabled.
- Server-only environment values still need deployment.
- One controlled E2E is still required before merge/deploy/PRODUCTION status.
- Repository visibility is still public and should be PRIVATE if the code is proprietary.

## Recommended next step

At the next context with direct n8n read access, read current `09HVPJgxGyFCRIeJ` and the separate `Werkstatt-Assistent – Telegram-Team`; verify empty-poll behavior and conversation-first tenant binding. If clean, implement the minimal OpenAI Responses adapter with `store:false`, validate it with pinned/mock data at zero API cost, then spend only the minimum controlled E2E calls needed for the production gate.
