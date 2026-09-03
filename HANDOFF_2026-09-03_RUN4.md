# Garage Temperli — Handoff 2026-09-03 Run 4

## Goal
Finish Garage Temperli safely without building a parallel backend. Production remains unchanged until the security/runtime gate and one controlled E2E pass.

## Changed in this run
- Hardened `chat-poll.php` reply normalization.
- A human reply is now returned to the browser **only when a valid matching reply ID exists**.
- Reply and reply ID are appended atomically after validation and duplicate-ID rejection.
- This closes the remaining case where a visible Team reply could have no corresponding acknowledgement ID and therefore reappear on later polls.
- Re-checked repository state: repo is still `public`.
- Re-checked current n8n advisories published 2026-09-02. The existing production gate version floor (`>=2.38.2`, maintained `>=2.37.7`, legacy `>=1.123.76`) also covers the newly confirmed workflow-summary prototype-pollution advisory on those maintained lines.

## Not changed
- `main` / deployed production: unchanged.
- Current n8n workflows: unchanged; no workflow write performed.
- Telegram configuration: unchanged.
- OpenAI adapter: not installed yet.
- Hostinger environment variables: unchanged.
- No credentials or secret values were read or written.
- No customer message, Telegram message, email, booking, or other external business action was sent.

## Tests performed
1. Local PHP syntax check of the exact proposed `chat-poll.php`: `No syntax errors detected` — PASS.
2. Deterministic adversarial pairing fixture: replies with missing ID, invalid ID, and duplicate ID were rejected; only the valid pair survived — output `[["ok"],["id1"]]` — PASS.
3. GitHub branch compare after commit `0b21ee25a1b857ff4e2205194d5bf4c1530cc183`: branch is 37 commits ahead of `main`, 0 behind; merge base still equals current `main` — PASS.
4. GitHub combined status for the new head returned no CI statuses. This is absence of CI evidence, not a pass.

## Evidence level
- `chat-poll.php` syntax: STATIC PASS.
- Poll adversarial reply/ID normalization: DETERMINISTIC LOCAL PASS.
- Branch relationship to `main`: GITHUB READBACK VERIFIED.
- Repository visibility: GITHUB READBACK VERIFIED — still PUBLIC.
- Current n8n security version: NOT VERIFIED.
- Current live empty-poll node behavior: NOT VERIFIED.
- Current separate Telegram-Team conversation-first tenant binding: NOT VERIFIED.
- OpenAI path: PLANNED, NOT IMPLEMENTED/PROVEN.
- Full website -> n8n -> model -> storage -> Telegram -> poll -> read: NOT E2E PROVEN.

## Current blockers / risks
1. **n8n runtime readback unavailable in this automation run.** The n8n connector requested interactive user input, so the running version and current LIVE workflows could not be read safely here.
2. **Repository remains public.** Proprietary branch/code is visible until repository visibility is changed to Private.
3. Hostinger/PHP runtime and cURL readiness are still not proven for the latest bridge files.
4. No PR-triggered CI status exists for the current head.

## Credentials/config still needed — do not expose values
- Existing Temperli widget credential/key, server-side only.
- `TEMPERLI_N8N_URL`
- `TEMPERLI_N8N_POLL_URL`
- `TEMPERLI_N8N_READ_URL`
- OpenAI credential in n8n for the minimal model-adapter migration.

## Recommended next step
As soon as n8n read access is available: read the actual running n8n version; read current LIVE `09HVPJgxGyFCRIeJ`; read the separate `Werkstatt-Assistent – Telegram-Team`; prove empty-poll output-on-empty and conversation-first tenant binding. Only then install/mock-test the minimal OpenAI adapter, configure the server-only bridge values, runtime-check PHP, and run one controlled E2E.

## Safety/accounting
- `production_changed = false`
- `paid_ai_calls = 0`
- `external_sends = 0`
