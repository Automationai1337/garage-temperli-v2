# Garage Temperli — Live Production Gate

Status: BLOCKED FOR PRODUCTION until the current live n8n workflow is read back and the first-contact zero-result bug is ruled out or fixed.

## Critical evidence discovered 2026-09-03

A later real-tested v14 STAGING artifact from 2026-08-26 documents a critical `Data Table -> get` behavior in n8n: when a lookup returned zero rows, affected nodes produced no item, so downstream "not found" branches never ran. The artifact records real failures before the fix (`#385`, `#387`, `#388`) and real passes after adding `alwaysOutputData=true` (`#389` full first-contact chat, `#394` invalid key denied cleanly).

Most importantly, that handoff note explicitly states the same code pattern existed in the LIVE workflow `Werkstatt-Assistent Backend` and had **not** been changed there at that time. Therefore the older live export is not enough evidence that first-contact, empty-poll, duplicate-miss and related zero-result paths work today.

## Nodes/patterns that must be verified on CURRENT LIVE

Do not blindly patch by name. Read back the current workflow and verify every `Data Table` `get` where downstream logic expects a "zero rows / not found" branch. The v14 real-test note specifically called out patterns including:

- Rate-limit lookup
- Visitor lookup
- Conversation lookup / conversation-status lookup
- Customer lookup
- Duplicate lookup
- Workshop lookup for chat, poll and read
- Poll answer lookup
- Dashboard/session lookups
- Telegram tenant/conversation lookups
- Prompt-history loads
- Reservation lookup

If the current live node still suppresses output on zero rows, set the n8n equivalent of `alwaysOutputData=true` only where downstream logic explicitly needs an empty item. Verify by readback after save.

## Required evidence before Temperli E2E

1. CURRENT live `09HVPJgxGyFCRIeJ` readback confirms zero-result paths are safe.
2. CURRENT separate `Werkstatt-Assistent – Telegram-Team` readback confirms the single active Telegram reply trigger and tenant-safe reply storage.
3. Model path is intentionally OpenAI if OpenAI remains the product requirement. The inspected 2026-08-25/26 artifacts still use Anthropic Claude, so OpenAI is not yet evidenced.
4. Website server has chat/poll/read webhook URLs plus Temperli widget key configured server-side only.
5. Latest PHP bridge files pass runtime syntax/fail-closed checks on the actual Hostinger/PHP environment.
6. One controlled E2E passes: website -> authorized tenant -> model -> storage -> forced escalation -> Telegram -> team reply -> poll -> customer-visible reply -> read acknowledgement.
7. `health.php` and monitoring remain green after deploy.

## Evidence levels

- Website branch implementation: CONFIGURED / STATIC.
- Frontend JS syntax: STATIC PASS.
- Latest PHP revisions: STATIC / NOT YET RUNTIME-PROVEN.
- n8n architecture: VERIFIED FROM DATED EXPORTS.
- v14 first-contact fix: REAL-TEST EVIDENCE IN STAGING ARTIFACT.
- Current live first-contact fix: UNKNOWN UNTIL READBACK.
- OpenAI path: NOT PROVEN.
- Full Temperli E2E: NOT PROVEN.

## Safety / cost record for this work

`production_changed = false`
`paid_ai_calls = 0`
`external_sends = 0`

## Exactly one next step

Read back CURRENT live `09HVPJgxGyFCRIeJ`; verify/fix the zero-result `Data Table get` pattern first. Do not spend a paid model call on Temperli until that gate is proven.
