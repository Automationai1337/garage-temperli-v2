# Garage Temperli — Live Production Gate

Status: BLOCKED FOR PRODUCTION until the current live n8n workflow and the separate Telegram-Team workflow are read back, the remaining poll zero-result risk is ruled out, the Telegram reply owner is tenant-safe, the OpenAI path is intentionally configured, the running n8n version passes the current security-version gate, and one controlled E2E passes.

## Current n8n security-version gate — added 2026-09-03

A new n8n security advisory published 2026-09-02 (`GHSA-6xcw-7xm6-48c6`) describes a **High** severity expression-sandbox escape that can lead to code execution on instances using the legacy expression engine. The advisory lists these patched lines:

- n8n `>= 2.38.2`, or
- n8n `>= 2.37.7` on the maintained 2.37 line, or
- n8n `>= 1.123.76` on the maintained legacy 1.x line.

The installed Zantua/n8n runtime version has **not** been read in this run. Therefore no public Temperli E2E or production sign-off should proceed until the actual running version is checked against the applicable patched line. Do not infer safety from a Docker image tag such as `latest`; read the runtime version actually running.

If an immediate upgrade is impossible and the instance is affected, the advisory states the `vm` expression engine is not affected. Treat `N8N_EXPRESSION_ENGINE=vm` only as a temporary mitigation until an upgrade is verified. Also keep workflow editing and credential access restricted to trusted users.

A separate High advisory published 2026-07-22 (`GHSA-64xh-79j6-r5v8`) affected several AI/LLM nodes that could bypass credential "Allowed HTTP Request Domains" controls when shared credentials were used. Its patched lines are `>=2.32.1` / `>=2.31.5`. Any version satisfying the newer 2026-09-02 2.x gate above also clears this older version floor, but credential sharing still needs least-privilege review.

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

1. Read the actual running n8n version and prove it satisfies the applicable 2026-09-02 patched line before public production testing.
2. Read back current `09HVPJgxGyFCRIeJ` rather than applying an old STAGING patch blindly.
3. Confirm the main chat zero-result lookups still have their required output-on-empty behavior.
4. Inspect `Antworten abrufen` in the LIVE poll path specifically. An empty poll must still reach `Antworten sammeln` / webhook response and return an empty reply list instead of hanging.
5. Check `Reservierungen pruefen` and `Session pruefen (Haupt)` only if those paths remain relevant; do not change them solely because an old note listed them.
6. Read back the separate `Werkstatt-Assistent – Telegram-Team` workflow and verify that it is the single active Telegram reply owner.
7. In that Telegram-Team workflow, require conversation-first tenant binding: referenced `conversation_id` -> stored `tenant_id` -> workshop row -> incoming chat ID equality. Reject unknown conversation, missing tenant, or chat mismatch before reply storage or takeover.
8. Verify replies are stored with both the validated `conversation_id` and validated `tenant_id`, not a tenant selected by chat-ID first match.
9. Review shared credential permissions with least privilege, especially credentials usable by AI/LLM or arbitrary-request-capable nodes.

## Required evidence before Temperli E2E

1. Running n8n version passes the current security-version gate.
2. CURRENT live chat + poll/read contract matches the website bridge, especially empty-poll behavior.
3. CURRENT Telegram-Team handoff is tenant-safe using conversation-first binding and trigger ownership is unambiguous.
4. Model path is intentionally OpenAI if OpenAI remains the product requirement. The inspected 2026-08-25/26 artifacts use Anthropic Claude, so OpenAI is not yet evidenced.
5. Website server has chat/poll/read webhook URLs plus Temperli widget key configured server-side only.
6. Latest PHP bridge files pass runtime syntax/fail-closed checks on the actual Hostinger/PHP environment.
7. One controlled E2E passes: website -> authorized tenant -> model -> storage -> forced escalation -> Telegram -> team reply -> poll -> customer-visible reply -> read acknowledgement.
8. `health.php` and monitoring remain green after deploy.

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
- Current running n8n version: UNKNOWN UNTIL RUNTIME READBACK.
- OpenAI path: NOT PROVEN.
- Full Temperli E2E: NOT PROVEN.

## Safety / cost record for this work

`production_changed = false`
`paid_ai_calls = 0`
`external_sends = 0`

## Exactly one next step

In a context with current infrastructure read access, read the **actual running n8n version**, current live `09HVPJgxGyFCRIeJ`, and the separate `Werkstatt-Assistent – Telegram-Team`. First prove the runtime is on a patched n8n line, then verify `Antworten abrufen` empty-poll behavior and conversation-first Telegram tenant binding. Only after that static/security gate is clean should a paid OpenAI E2E be spent.
