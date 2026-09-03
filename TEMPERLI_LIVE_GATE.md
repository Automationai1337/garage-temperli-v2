# Garage Temperli — Live Production Gate

Status: BLOCKED FOR PRODUCTION until the current live n8n workflow and the separate Telegram-Team workflow are read back, the remaining poll zero-result risk is ruled out, the Telegram reply owner is tenant-safe, the OpenAI path is intentionally configured, and one controlled E2E passes.

## Corrected zero-result evidence discovered 2026-09-03

The 2026-08-26 v14 STAGING test trail documents a real n8n `Data Table -> get` zero-result behavior: without `alwaysOutputData=true`, a lookup returning zero rows could produce no item and stop downstream "not found" logic. STAGING failures `#385`, `#387`, `#388` were followed by real passes after the fix, including first-contact `#389` and invalid-key `#394`.

A later production-gate note in the same 2026-08-26 evidence **corrects an earlier claim** about LIVE: it states that the LIVE workflow already had the relevant `alwaysOutputData` settings on the main first-contact path. It identifies three live exceptions at that point: `Session pruefen (Haupt)` (considered likely harmless in its merge path), `Antworten abrufen` (explicitly called a possible real live risk and not verified), and `Reservierungen pruefen` (considered unlikely to hit zero after the reservation insert). LIVE was not changed in that test.

Therefore: **do not claim the live first-contact path is broken.** The stronger, narrower blocker is that the current live state is still unread, and the dated evidence explicitly leaves the live `Antworten abrufen` empty-poll path unverified. Temperli now depends on that poll path for human replies.

## Telegram cross-tenant evidence

The later v14 STAGING evidence also found a real tenant-ambiguity problem in Telegram handoff: at least two tenants shared the same `telegram_chat_id`, while the older reply path could resolve a workshop by Telegram chat ID alone using a first match. That could bind a legitimate team reply to the wrong tenant.

STAGING was hardened to resolve the tenant from the referenced `conversation_id` first, then load that tenant's workshop row, then verify that its stored `telegram_chat_id` equals the incoming Telegram chat ID. This protection applies to normal team replies, `/bot` release, and related handoff paths. The dated real tests reported:

- `#395`: legitimate conversation + matching chat ID -> ALLOW, reply stored, human takeover, team confirmation sent.
- `#396`: mismatched chat ID -> BLOCK, no reply stored / no takeover.
- `#398`: invented conversation ID + real chat ID -> BLOCK, safety message sent.

This is strong evidence for the **required design**, but not proof that the current separate live `Werkstatt-Assistent – Telegram-Team` workflow contains the same hardening. The separate live workflow still needs readback before Temperli production.

## What must be verified on CURRENT LIVE

1. Read back current `09HVPJgxGyFCRIeJ` rather than applying an old STAGING patch blindly.
2. Confirm the main chat zero-result lookups still have their required output-on-empty behavior.
3. Inspect `Antworten abrufen` in the LIVE poll path specifically. An empty poll must still reach `Antworten sammeln` / webhook response and return an empty reply list instead of hanging.
4. Check `Reservierungen pruefen` and `Session pruefen (Haupt)` only if those paths remain relevant; do not change them solely because an old note listed them.
5. Read back the separate `Werkstatt-Assistent – Telegram-Team` workflow and verify that it is the single active Telegram reply owner.
6. In that Telegram-Team workflow, require conversation-first tenant binding: referenced `conversation_id` -> stored `tenant_id` -> workshop row -> incoming chat ID equality. Reject unknown conversation, missing tenant, or chat mismatch before reply storage or takeover.
7. Verify replies are stored with both the validated `conversation_id` and validated `tenant_id`, not a tenant selected by chat-ID first match.

## Required evidence before Temperli E2E

1. CURRENT live chat + poll/read contract matches the website bridge, especially empty-poll behavior.
2. CURRENT Telegram-Team handoff is tenant-safe using conversation-first binding and trigger ownership is unambiguous.
3. Model path is intentionally OpenAI if OpenAI remains the product requirement. The inspected 2026-08-25/26 artifacts use Anthropic Claude, so OpenAI is not yet evidenced.
4. Website server has chat/poll/read webhook URLs plus Temperli widget key configured server-side only.
5. Latest PHP bridge files pass runtime syntax/fail-closed checks on the actual Hostinger/PHP environment.
6. One controlled E2E passes: website -> authorized tenant -> model -> storage -> forced escalation -> Telegram -> team reply -> poll -> customer-visible reply -> read acknowledgement.
7. `health.php` and monitoring remain green after deploy.

## Evidence levels

- Website branch implementation: CONFIGURED / STATIC.
- Frontend JS syntax: STATIC PASS.
- Latest PHP revisions: STATIC / NOT YET RUNTIME-PROVEN.
- n8n architecture: VERIFIED FROM DATED EXPORTS.
- STAGING zero-result fix: REAL-TEST EVIDENCE.
- LIVE main first-contact zero-result configuration as of the later 2026-08-26 note: REPORTED/READ-COMPARED AS CORRECT, not current proof.
- LIVE `Antworten abrufen` empty-poll behavior: EXPLICITLY UNVERIFIED IN DATED EVIDENCE.
- STAGING Telegram conversation-first tenant hardening: REAL-TEST EVIDENCE (`#395`, `#396`, `#398`).
- CURRENT separate live Telegram-Team hardening: UNKNOWN UNTIL READBACK.
- Current live state generally: UNKNOWN UNTIL READBACK.
- OpenAI path: NOT PROVEN.
- Full Temperli E2E: NOT PROVEN.

## Safety / cost record for this work

`production_changed = false`
`paid_ai_calls = 0`
`external_sends = 0`

## Exactly one next step

Read back CURRENT live `09HVPJgxGyFCRIeJ` **and** the separate `Werkstatt-Assistent – Telegram-Team`, with first priority on `Antworten abrufen` empty-poll behavior and conversation-first Telegram tenant binding. Only after that static gate is clean should a paid OpenAI E2E be spent.
