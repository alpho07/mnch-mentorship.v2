# MNCHGPT — LLM-Powered Mentorship Assistant Design

## Overview

A fourth mentorship-creation method, "MNCHGPT," that lets a user type free-form
text ("Set up an EmONC mentorship at Kisumu District Hospital for 8 mentees
starting next Monday") and have an LLM (DeepSeek-V3, via tool-calling) extract
and fill the same setup fields the existing Chat Setup fills one click at a
time. Beyond setup, the same conversational surface also answers read-only
analytics questions about mentorships, mentees, trends, dashboard coverage,
and facility assessments — reusing (or extracting into reusable services)
the exact query/scoping logic those areas already use, never inventing new
authorization rules.

This is additive: `ChatMentorshipSetup` (the existing click-driven page) is
untouched behaviorally — its slot-answering logic is extracted into a shared
base so both pages stay in sync, but nothing about how it looks or behaves
changes. MNCHGPT is a new page, a new button, a new setting toggle — same
pattern as the three existing creation methods.

## Goals

- Free text can fill multiple mentorship-setup slots in one message.
- Free text can also ask analytics questions (mentorship/mentee counts,
  trends, county/program coverage, training completion, assessment status,
  facility readiness, executive summaries) and get a natural-language answer
  sourced from real, correctly-scoped data.
- Every setup value the LLM proposes is validated through the exact same
  `Slot::validate()` the click-driven flow already uses — the LLM never
  bypasses validation.
- Every query tool re-checks the same role/scope rules the equivalent page
  already enforces — MNCHGPT can never reveal data a user couldn't already
  see by clicking to the real page.
- A failed extraction never blocks progress — it silently falls back to the
  real card/button picker for that one slot.

## Non-goals (out of scope for this spec)

- The composite setup stages that aren't simple `Slot`s (module picking with
  per-track dates, mentee search/enrollment) stay click/picker-only. The LLM
  does not attempt to extract module or mentee selections from free text.
- Modifying `ChatMentorshipSetup`'s UI/behavior beyond the internal refactor
  needed to share slot logic.
- The full geographic drill-down (`AnalyticsDashboardController`'s
  county → facility → program → participant hierarchy) is not exposed
  wholesale — only the three specific summaries listed under Dashboard
  Analytics Tools below.
- Multi-provider LLM abstraction — this builds directly against DeepSeek-V3's
  API. Swapping providers later is a follow-up if needed, not designed for
  up front.
- Streaming responses — each turn is a single request/response round trip
  (well within DeepSeek's typical latency for this message size).

## Architecture

### Core: the tool-calling loop

**`App\Services\Chat\LlmMentorshipAssistantService`** — wraps DeepSeek-V3's
OpenAI-compatible chat completions endpoint via Laravel's `Http` facade (no
new SDK dependency). Runs the standard two-step tool-calling loop for every
turn, setup or query alike:

1. Send the user's message, recent conversation history, and the current
   tool schema (from `ChatToolRegistry`, scoped to the authenticated user).
2. Receive one or more tool calls from the model.
3. Execute each tool call server-side (see Tool Families below) — this is
   where validation/authorization actually happens, not inside the LLM.
4. Send the tool results back to the model in a follow-up completion.
5. Receive the model's final natural-language reply — this becomes the
   assistant's transcript message.

Every turn produces a spoken-language reply this way, whether the user was
filling setup slots or asking a question. For setup, the deterministic card
UI still drives what's shown next for any slot that didn't get filled —
the model's reply is a friendly acknowledgment, not the source of truth for
what happens next.

**`App\Services\Chat\ChatTool`** (interface) — implemented by each tool:

```php
interface ChatTool
{
    public function name(): string;
    public function description(): string;
    public function schema(): array; // JSON schema for the tool's parameters
    public function authorize(User $user): bool;
    public function execute(array $args, User $user): array; // returns data for the model to summarize
}
```

**`App\Services\Chat\ChatToolRegistry`** — collects every registered tool,
filters to `authorize($user) === true`, and exposes the resulting schema
list to `LlmMentorshipAssistantService`. This is the sole extension point:
adding a new tool family means registering one more provider here, nothing
else in the core changes.

### Shared base with the existing Chat Setup

**`App\Filament\Resources\MentorshipResource\Pages\Concerns\HasMentorshipChatSlots`**
(trait) — holds `slots()`, `nextUnfilledSlot()`, `answer()`, and the
stage-completion side effects (creating the Training/Class records),
extracted verbatim from `ChatMentorshipSetup` with no behavior change.
Both `ChatMentorshipSetup` and the new `MnchGptSetup` use it. Verified via
the existing `ChatMentorshipSetupTest.php` suite passing unmodified after
the extraction (the same verification approach used for the original
Guided Setup → Chat Setup extraction earlier this project).

### New page

**`App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup`** —
extends the shared base, adds:
- `sendMessage(string $text): void` — the Livewire action bound to the new
  free-text input. Builds the tool schema (setup tools reflect current
  `nextUnfilledSlot()`/eligible-slots state; query tools are always
  available), calls `LlmMentorshipAssistantService`, applies results.
- A "thinking…" loading state while awaiting the LLM response.
- Falls back to the same card/button rendering as `ChatMentorshipSetup` for
  whatever the free text didn't (or couldn't) fill — reuses the existing
  blade partials for that part of the UI.

Route/navigation: same pattern as the other three creation methods — a
button on `ListMentorshipTrainings`, gated by a new setting.

**`Setting::MNCHGPT_BUTTON_ENABLED`** — new constant, toggle added to
`MentorshipSettings`'s "Mentorship Creation Methods" section alongside the
three existing toggles, following that exact existing pattern
(`Forms\Components\Toggle` with `onColor`/`offColor`/`afterStateUpdated`
persisting via `Setting::setBool()`).

## Tool Families

### 1. Setup tools — `MentorshipSetupToolProvider`

A single batched tool, `fill_mentorship_setup_slots`, whose JSON schema has
one optional property per currently-eligible unfilled slot (from
`MentorshipChatScript::build($page)`, filtered by `Slot::isVisible()` the
same way `nextUnfilledSlot()` already filters). Each property's type/enum is
built live from that slot's `optionsFrom()` closure — e.g. `facility_id`'s
enum is the real, current list of facilities in the selected county, not a
free-text guess the model has to get exactly right character-for-character.

Execution: for each slot value the model proposes, run it through the exact
same `Slot::validate()` used by `answer()` today (reusing `answer()`
itself, not reimplementing it). Values that pass are committed and echoed
in the transcript exactly as a click-driven answer would be; values that
fail are silently dropped — the reply summarizes what *did* get captured,
and the normal question+card UI renders for whatever's still missing.

### 2. Mentorship stats tools — `MentorshipStatsToolProvider`

Backed by a new **`App\Services\MentorshipStatsService`**, extracted from
`MentorshipStatsOverview`'s existing query logic (`baseTrainingQuery()`,
`menteesQuery()`, `programStats()`, `overallStats()`) so the widget and the
chat tools share one scoped source of truth — same role check
(`hasRole(['super_admin','admin','division'])` sees everything, else
`forMentorOrCoMentor($user->id)`), same "live mentorships only, pilot runs
excluded" rule.

- `get_mentorship_counts(program_name?: string)` → overall and, if a
  program is named, that program's mentorship + mentee counts.
- `get_mentorship_trends(period: "monthly"|"quarterly", periods_back?: number)`
  → mentorships-created and mentees-enrolled per period, scoped the same
  way. New query logic — grouping `Training`/`ClassParticipant` creation
  dates by period — since nothing existing computes this today; the one
  piece of this spec without a direct precedent to extract from.

### 3. Dashboard analytics tools — `DashboardAnalyticsToolProvider`

Backed by a new **`App\Services\DashboardAnalyticsQueryService`**. Applies
`User::isAboveSite()` / `scopedCountyIds()` / `scopedFacilityIds()` directly
(the documented RBAC helpers, per CLAUDE.md) rather than reusing
`AnalyticsDashboardController`'s methods — those are large,
HTTP-response-oriented, and built for the map UI, not a clean data return.

- `get_county_coverage_summary(county_name: string)` → facility, mentorship,
  and mentee counts for that county, respecting the user's own geographic
  scope (a county the user isn't scoped to returns "not accessible" rather
  than data).
- `get_program_summary(program_name: string)` → totals plus a per-county
  breakdown for that program, same scoping.
- `get_training_completion_stats(program_name?: string)` → completion rate
  and participant counts, scoped the same way.

### 4. Assessment summary tools — `AssessmentSummaryToolProvider`

Reuses `AssessmentResource::getEloquentQuery()`'s existing scoping (assessor
sees only their own assessments; super_admin/admin/division see all) via a
thin **`App\Services\AssessmentSummaryQueryService`**.

- `get_assessment_status_counts(status?: "draft"|"in_progress"|"completed")`
  → counts, scoped as above.
- `get_facility_readiness_scores(facility_name?: string, below_percentage?: number)`
  → list of `{facility, overall_percentage, overall_grade}`, optionally
  filtered to one facility or a score threshold.
- `get_facility_executive_summary(facility_name: string)` → the CEO
  Insights array already generated by
  `AssessmentExecutiveDashboardController::generateInsights()` for that
  facility's latest completed assessment — not regenerated, the exact same
  text a human sees on that facility's executive dashboard, scoped to
  assessments the asking user can see.

## Data Flow (turn-by-turn)

1. User types a message into MNCHGPT's free-text box, submits.
2. `MnchGptSetup::sendMessage()` appends the user message to the transcript,
   builds the current tool schema via `ChatToolRegistry` (setup tools
   reflecting eligible unfilled slots + all four query tool families,
   filtered by `authorize()`), and calls
   `LlmMentorshipAssistantService::respond()`.
3. The service sends the request to DeepSeek, receives tool call(s).
4. Each tool call is dispatched to its provider's `execute()`:
   - Setup: validates via `Slot::validate()`, commits valid values via the
     shared `answer()` logic, triggers the same stage-completion side
     effects (creating Training/Class records) as today.
   - Query: runs the scoped read-only lookup, returns structured data.
5. Tool results are sent back to DeepSeek; the model's final natural-language
   reply is appended to the transcript as the assistant's turn.
6. If any setup slots remain unfilled, the normal question + card/button UI
   renders below the transcript for the next one — exactly as
   `ChatMentorshipSetup` does today. The user can keep typing free text
   (filling more slots at once) or click the card — both paths converge on
   the same `answer()` method.

## Guardrails

- **Setup values are never trusted from the LLM directly.** Every proposed
  value passes through the existing, already-tested `Slot::validate()`
  before being committed. A rejected value is silently dropped, not shown
  as an error to the user — the fallback card UI for that slot is simply
  what renders next.
- **Every query tool re-authorizes independently.** `authorize(User $user)`
  re-checks the same role/scope rule the equivalent existing page enforces.
  A tool is not registered into the schema at all if `authorize()` fails —
  the model never even knows the capability exists for that user, let alone
  gets to call it.
- **Tool schema enums come from live data**, not free text the model has to
  match exactly — e.g. `facility_name` parameters are constrained to a
  fuzzy-matched lookup against real facility names server-side (exact
  string match fails silently → "I couldn't find a facility matching that
  name" reply, not a crash or a wrong record).
- **Network/API failures degrade gracefully.** A DeepSeek request timeout or
  error produces "Sorry, I couldn't process that — try again or use the
  buttons below," never a blocked or broken flow; the deterministic card UI
  is always available as a fallback regardless of LLM availability.
- **Cost/abuse containment:** message length cap, a reasonable per-session
  turn cap, and DeepSeek-V3's per-token cost is low enough that this
  internal admin tool's expected volume is trivial — no rate limiting
  beyond what the existing `RateLimiter::for('interactions', ...)` /
  similar limiters in `AppServiceProvider` already provide, reused rather
  than inventing a new one.

## Files

**New:**
- `app/Services/Chat/LlmMentorshipAssistantService.php`
- `app/Services/Chat/ChatTool.php` (interface)
- `app/Services/Chat/ChatToolRegistry.php`
- `app/Services/Chat/Tools/MentorshipSetupToolProvider.php`
- `app/Services/Chat/Tools/MentorshipStatsToolProvider.php`
- `app/Services/Chat/Tools/DashboardAnalyticsToolProvider.php`
- `app/Services/Chat/Tools/AssessmentSummaryToolProvider.php`
- `app/Services/MentorshipStatsService.php`
- `app/Services/DashboardAnalyticsQueryService.php`
- `app/Services/AssessmentSummaryQueryService.php`
- `app/Filament/Resources/MentorshipResource/Pages/Concerns/HasMentorshipChatSlots.php` (trait)
- `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`
- `resources/views/filament/pages/mnchgpt-setup.blade.php` (+ a free-text
  input partial, likely shared with/adapted from the existing chat partials)
- Migration or `Setting` constant addition for `MNCHGPT_BUTTON_ENABLED`
  (checking whether `Setting` values are DB-backed or config-backed before
  planning — matches whatever `CHAT_SETUP_BUTTON_ENABLED` already does)

**Modified:**
- `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
  — shrinks to just what's specific to the click-driven UI once shared
  logic moves to the trait. No behavior change; verified via the existing
  test suite passing unmodified.
- `app/Filament/Widgets/MentorshipStatsOverview.php` — delegates its query
  logic to the new `MentorshipStatsService` instead of querying directly,
  so widget and chat tool can never drift apart.
- `app/Filament/Pages/MentorshipSettings.php` — one more toggle.
- `app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php`
  — one more button.
- `config/services.php` — DeepSeek API key/base URL config.

## Testing

- `HasMentorshipChatSlots` extraction: existing `ChatMentorshipSetupTest.php`
  suite must pass unmodified — proves the refactor didn't change behavior.
- `LlmMentorshipAssistantService`: unit tests with the DeepSeek HTTP call
  faked (`Http::fake()`), covering the two-step loop, tool-call parsing,
  and graceful failure on timeout/error responses.
- Each `ChatTool` implementation: unit tests for `authorize()` (role
  scoping correctness — a facility mentor cannot see another mentor's
  counts, an assessor cannot see another assessor's assessments) and
  `execute()` (correct data for known fixtures).
- `MnchGptSetup::sendMessage()`: feature tests with the LLM service faked,
  covering: valid extraction fills slots and advances the flow; invalid
  extraction is dropped and falls back to the card UI; a query-only message
  doesn't touch `$this->answers` at all; stage-completion side effects
  (Training/Class creation) fire identically to the click-driven path.
- Regression: full suite must stay green, matching the standard established
  throughout this project (pre-existing `ExampleTest`/`LookupApiTest`
  failures are the only acceptable exceptions).

## Open Questions for the Planning Phase

- Exact DeepSeek-V3 request/response shape for tool-calling (verify against
  current API docs when implementing — assumed OpenAI-compatible
  `tools`/`tool_choice`/`tool_calls` format here).
- Whether `Setting` is a simple key-value DB table (matching the
  `getBool()`/`setBool()` calls already used for the other three toggles)
  or needs a migration — check `App\Models\Setting` before planning the
  exact steps.
- Rate limiter reuse: confirm which existing limiter in `AppServiceProvider`
  is the right fit (or whether a new `chat-assistant` limiter is warranted)
  during planning.
