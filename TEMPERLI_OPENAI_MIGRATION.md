# Garage Temperli — OpenAI migration plan

Status: STATIC PLAN ONLY. No n8n production node or credential changed by this document.

## Decision

Do **not** rebuild the Werkstatt-Assistent. Replace only the current model-adapter step and its response parser while preserving the existing tenant lookup, prompt builder, storage, escalation, Telegram, poll/read and appointment branches.

Use the OpenAI **Responses API** with a forced custom function and Structured Outputs (`strict: true`) so the output contract remains deterministic. Keep the existing business contract named `kunden_antwort` and preserve its fields (`reply`, `escalate`, customer/contact, appointment, intent, lead/follow-up, frustration, review and upsell signals).

Recommended first production model: `gpt-5.6-terra` because the current OpenAI model catalog positions it as the balance of intelligence and cost. Do not switch to a cheaper model solely for price. After Temperli is stable, benchmark `gpt-5.6-luna` against pinned, representative workshop conversations and change only if it passes the same safety/quality gates.

## Smallest n8n change

Current model path in the inspected 2026-08-25/26 workflow artifacts:

`Verlauf serverseitig aufbauen -> Claude fragen -> Antwort auslesen`

Target path:

`Verlauf serverseitig aufbauen -> OpenAI fragen -> OpenAI Antwort auslesen`

Everything before and after those two adapter nodes stays unchanged unless current live readback proves otherwise.

## OpenAI request contract

HTTP request:

- Method: `POST`
- URL: `https://api.openai.com/v1/responses`
- Auth: OpenAI credential / `Authorization: Bearer ...` held in n8n credential storage, never in workflow code or website.
- Model: start with `gpt-5.6-terra`.
- `instructions`: existing tenant-specific `systemPrompt`.
- `input`: server-built conversation history from `Verlauf serverseitig aufbauen`.
- `tools`: one custom function named `kunden_antwort` using the existing schema, with `strict: true`.
- `tool_choice`: force `kunden_antwort` so downstream logic never depends on free-form prose.
- Bound output tokens to the minimum that safely covers the structured answer.

Do not enable web search, file search, background mode or other tools for Temperli. They add cost/latency/attack surface without a proven customer requirement.

## Schema rule

Port the existing `kunden_antwort` JSON schema exactly first. For strict mode, ensure the schema conforms to the OpenAI-supported JSON Schema subset. Do not silently loosen required fields. If a field is intentionally optional, represent that explicitly in the schema rather than allowing arbitrary output.

The major benefit of `strict: true` is that valid function-call arguments are constrained to the schema. Keep server-side defensive validation anyway; structured output is not a substitute for business-rule validation.

## Response adapter

The new parser should:

1. Find the OpenAI Responses output item whose type is the function-call item and whose name is `kunden_antwort`.
2. Parse its `arguments` JSON.
3. Validate the expected fields/types before mapping them to the existing downstream names used today (`reply`, `escalate`, `kundeName`, `kundeTelefon`, `terminDatum`, `terminUhrzeit`, `terminDienstleistung`, `intent`, `leadScore`, `followupNeeded`, `followupReason`, `customerFrustrated`, `reviewCandidate`, `upsellSuggestions`, `conversationId`, transcript).
4. Preserve the existing verified WhatsApp rule that a technically verified Meta sender number takes priority over an AI-extracted phone number. For web/Temperli, use only customer-provided chat data.
5. On API error, missing function call, invalid arguments or parser failure: use the existing safe fallback response and `escalate=true`; never invent a diagnosis/price/booking confirmation.

## Static acceptance gate before any paid call

- Current live workflow read back first.
- Confirm the empty-poll `Antworten abrufen` path returns cleanly with zero replies.
- Confirm separate Telegram-Team trigger ownership and tenant binding.
- Duplicate the current model/parser nodes in an isolated staging/candidate workflow or make a reversible draft version; do not replace the only proven production model path without rollback.
- Validate the OpenAI request body and parser with pinned/mock response data at zero API cost.
- Read back all changed nodes and compare downstream output fields against the old contract.

## Controlled E2E after static gate

Spend the minimum model calls needed to prove:

1. Normal workshop question returns a useful structured reply.
2. Safety-critical symptom produces immediate safe guidance + escalation.
3. Appointment request never falsely claims a confirmed slot before backend confirmation.
4. Forced escalation reaches Telegram.
5. Team reply reaches website through poll, renders visibly, and read acknowledgement is accepted.
6. Storage remains tenant-scoped and no cross-tenant data appears.

Only after these pass can OpenAI be marked REAL E2E TESTED.

## Evidence level

- Current Anthropic model path: VERIFIED FROM DATED WORKFLOW ARTIFACTS.
- OpenAI architecture in this file: STATIC DESIGN.
- OpenAI credential: NOT VERIFIED.
- OpenAI node/parser in current live: NOT IMPLEMENTED/NOT VERIFIED.
- OpenAI E2E: NOT TESTED.

`production_changed = false`
`paid_ai_calls = 0`
`external_sends = 0`
