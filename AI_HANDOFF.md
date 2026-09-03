# Zantua AI — ChatGPT ↔ Claude Handoff

## Purpose
Shared coordination channel for Garage Temperli. Read the latest state before acting and update after material changes.

## Current priority
Finish Garage Temperli before dashboard cleanup or unrelated projects.

## Architecture decision
- Temperli runs through its own website/server path.
- Reuse n8n workshop stack `09HVPJgxGyFCRIeJ`.
- Required: website AI, OpenAI path, Telegram handoff, poll/read, request storage, vehicle identifier, name, phone, requested date/time, security/cost guard.
- Outlook/calendar may be supplied later and must not block the staging AI/Telegram test.
- Controlled staging E2E may use the existing Zantua test Telegram bot/target.
- Final customer email/Telegram/calendar are configuration swaps after staging proof.

## Security rules
- No API/widget secrets in browser code.
- Tenant fixed/resolved server-side.
- Origin and request-body allowlists.
- Server-side rate limiting before n8n/model.
- Do not trust client-supplied proxy/IP headers.
- Unknown tenant/request fails closed.
- Block before paid model call where possible.

## Current user authorization
Routine reversible work may continue without repeated confirmations. Small API cost for the required final AI connection is acceptable. Zantua test Telegram may be used for the controlled staging handoff test. Stop before destructive cleanup, secret disclosure, uncontrolled customer sends, or materially risky production changes.

## Definition of Done
1. Website production-ready/responsive.
2. AI real backend E2E-proven.
3. Required request fields persist with correct tenant/source.
4. Telegram staging handoff + poll/read return path E2E-proven.
5. Server-side security/guard before paid model call; no browser secret.
6. Monitoring backed by real data.
7. Recoverable backup exists.
8. Final customer domain/email/Telegram/optional calendar switched after staging proof.

## Latest implementation update — 2026-09-03
### Changed
- `ai-chat.js` was upgraded from single-endpoint-only wiring to backward-compatible Chat/Poll/Read-ready frontend wiring.
- Config now supports `chatEndpoint` (or legacy `endpoint`), `pollEndpoint`, `readEndpoint`, `pollIntervalMs`, and `pollMaxMs`.
- Stable `conversationId` and `visitorId` are kept in localStorage; `messageId` is tracked for deduplication/read acknowledgement.
- Browser request payload was narrowed to the intended bridge contract: `message`, `conversationId`, `visitorId`, `messageId`, `history`. Client-side tenant/channel/origin routing fields are no longer sent by the AI widget.
- Polling only starts when a poll endpoint is configured and the panel is open; it stops when closed/hidden and uses bounded exponential backoff after errors.
- Polling has a default 10-second interval and 3-minute active window to avoid an uncontrolled execution loop.
- `index.html` cache-bust was updated to load `ai-chat.js?v=20260903-ai4`.
- No backend URL, secret, widget key, customer Telegram target, calendar or production email target was added.

### Evidence / tests
- GitHub write/read evidence for the changed frontend files only.
- No live deploy was triggered from this environment.
- No HTTP E2E, n8n execution, OpenAI call, Telegram send or customer email occurred.
- Evidence level: **configured/static, not E2E**.
- `production_changed = false` from the available evidence (Hostinger deploy not triggered here).
- `paid_ai_calls = 0`
- `external_sends = 0`

### Remaining blockers/risks
1. The server-side Chat/Poll/Read bridge is still not deployed/proven.
2. Exact n8n bridge contract/response schema must be verified against `09HVPJgxGyFCRIeJ`; frontend parsing is intentionally tolerant but not an E2E proof.
3. Form email delivery remains not E2E-proven.
4. n8n workshop stack/Telegram cannot be re-verified with the currently connected tools.
5. Repository metadata was observed as `public`. Do not commit proprietary backend/security implementation or secrets there. Make this repository private or use a separate private backend repository before adding the bridge.
6. Final customer email/Telegram/calendar configuration remains intentionally unchanged.

### Exactly one recommended next step
Make `Automationai1337/garage-temperli-v2` private (or provide a private backend repo). Then add/deploy the Temperli-owned server-side Chat/Poll/Read bridge and run one controlled staging E2E with the Zantua test Telegram bot.
