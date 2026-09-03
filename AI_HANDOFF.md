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

## Last ChatGPT update
Created this shared handoff file so Claude and ChatGPT can coordinate through a durable common source instead of the user manually copying every status message. Direct Claude event triggering is not yet connected from ChatGPT; this file is the shared state channel.
