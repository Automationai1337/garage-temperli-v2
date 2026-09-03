# Zantua AI — ChatGPT ↔ Claude Handoff

## Purpose
This file is the shared coordination channel for Garage Temperli work. Both assistants must read the latest state before acting and append/update the handoff after material changes.

## Current priority
Finish Garage Temperli before dashboard cleanup or unrelated projects.

## Current architecture decision
- Temperli should run through its own website/server path, not depend unnecessarily on the zantua-ai.com website.
- Reuse n8n workshop stack: `09HVPJgxGyFCRIeJ`.
- Required: website AI, OpenAI target path, Telegram human handoff, poll/read, appointment-request storage, vehicle identifier, name, phone, requested date/time, security/cost guard.
- Outlook/calendar configuration may be supplied by the user later; do not block the current staging AI/Telegram test on it.
- For the controlled staging E2E, use the existing Zantua test Telegram bot/target first. Do not wait for the customer's final Telegram destination.
- Customer-facing production recipient/Telegram target are configuration swaps after the staging path is proven.

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
The user has authorized continuing the Temperli implementation without repeatedly asking for routine handoff confirmations. Small API cost for the required final AI connection is acceptable. The existing Zantua Telegram bot may be used for the controlled staging handoff test. Still stop before destructive cleanup, secret disclosure, uncontrolled customer sends, or materially risky production changes.

## Definition of Done for customer handoff
Temperli is not DONE merely because the website renders. Required evidence states are:
1. Website: production-ready/responsive.
2. AI: real backend connection E2E-proven.
3. Request storage: required fields persist with correct tenant/source.
4. Human handoff: staging Telegram send + poll/read return path E2E-proven.
5. Security: server-side bridge/guard configured before paid model call; no browser secret.
6. Monitoring: customer-relevant health/request view backed by real data, not fake counters.
7. Backup: recoverable code checkpoint exists.
8. Final customer configuration: domain/DNS, customer email/Telegram target and optional Outlook calendar switched only after staging proof.

## Last Control Tower audit — 2026-09-03
### Verified changes/state
- Reviewed current `main` files `index.html`, `script.js`, `contact.php`, and `ai-chat.js` statically.
- Contact endpoint is configured for JSON POST only, exact origin allowlist, 12 KB body cap, honeypot, server-side IP rate limit (5 requests / 15 minutes), required-field checks, optional email validation, and bounded field lengths.
- Contact form is still deliberately routed to `kontakt@zantua-ai.com` with `[TEST]` subject; no production recipient change was made.
- AI widget reads its backend from `window.GT_AI_CONFIG.endpoint`; current `index.html` does not define `GT_AI_CONFIG`, so the repo as inspected does not prove a real AI backend connection. When endpoint is empty, the widget uses a local fallback message rather than a model call.
- Current `ai-chat.js` contains no poll/read endpoint handling; repository search for poll/read/GT_AI_CONFIG endpoint wiring returned no implementation.
- No secret/API key was found in the inspected browser-side AI wiring.
- A recoverable Git branch checkpoint `backup/temperli-2026-09-03` exists.

### Unchanged
- No website runtime logic, styling, endpoint, recipient, credential, n8n workflow, or production configuration changed in this audit/update.
- n8n HANDOFF sticky was not available through the currently connected tools, so it was not updated and no n8n claim is treated as re-verified here.

### Tests / exact results
- Static repository inspection only; no successful live HTTP fetch, email send, n8n execution, model call, browser E2E, or production deploy was performed in this update.
- External web fetch of the staging domain was attempted but returned a cache/fetch failure from the available web environment; this is not evidence that the site is down.
- Evidence level: **static**.
- `production_changed = false`
- `paid_ai_calls = 0`
- `external_sends = 0`

### Remaining risks/open issues
1. Form delivery remains not E2E-proven.
2. AI backend remains not E2E-proven; current repo does not expose a configured `GT_AI_CONFIG.endpoint` in `index.html`.
3. Poll/read is absent from the current frontend implementation.
4. n8n workshop stack and Telegram handoff cannot be re-verified from the currently connected sources.
5. The repository was observed as public during the latest GitHub metadata check; proprietary backend/security logic should not be committed there until repository visibility is made private or a separate private backend repository is used.
6. Production email/Telegram/customer configuration must remain unchanged until staging proof is complete.

### Exactly one recommended next step
Make the Temperli code/backend repository private (or provide a private backend repository), then add/deploy the Temperli-owned server-side Chat/Poll/Read bridge without exposing widget secrets and connect the existing frontend to it for one controlled staging E2E using the Zantua test Telegram bot.
