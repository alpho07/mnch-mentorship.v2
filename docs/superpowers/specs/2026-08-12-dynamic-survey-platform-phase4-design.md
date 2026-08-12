# Dynamic Survey Platform — Phase 4: AI Narrative Layer — Design

**Date:** 2026-08-12
**Status:** Approved for planning

## 1. Background

Phases 1–3 (generic survey/instrument builder, longitudinal events,
auto-generated dashboards) are complete and merged to `main`.
`SurveyDashboardService::build(Survey $survey, ?SurveyEvent $event = null): array`
already produces a stable, chart-agnostic data structure — completion
meters, per-section per-question chart data, trend lines — designed from
the start (per the original platform spec §3.4) to be consumed unchanged
by this phase.

This document designs Phase 4 — a plain-English narrative summary of a
survey's dashboard, generated on demand, per the phasing in the original
platform spec
(`docs/superpowers/specs/2026-08-12-dynamic-survey-platform-design.md`
§2, §3.5).

## 2. Scope

**In scope:**
- `SurveyInsightService::summarize(array $dashboardData): string` — takes
  the **already-computed** `SurveyDashboardService::build()` output (never
  raw response rows, never direct DB access) and calls the Claude API
  using the exact pattern already proven in
  `app/Http/Controllers/Api/ChatController.php`.
- A "Generate Summary" button on the existing `SurveyDashboard` Filament
  page, rendering the result as a text block once generated.
- The full `dashboardData` array — every section, every question's chart
  data, completion percentages, response counts — is serialized into the
  prompt uncompressed; no separate "what's notable" pre-filtering logic.

**Explicitly out of scope for Phase 4:**
- Persisting the generated summary anywhere. It lives only in the
  `SurveyDashboard` page's Livewire state for that session — reloading the
  page or a different admin visiting the same dashboard sees no summary
  until they click the button themselves. No new migration, no new column.
- Natural-language querying of response data (chat-style Q&A over survey
  results) — flagged during the original Phase 1–3 brainstorming as a much
  larger, separately-scoped follow-on, not part of this platform's four
  planned phases.
- Any change to `SurveyDashboardService`, `SurveyScoringService`,
  `ScoringEngine`, or any earlier phase's code. This phase only reads
  Phase 3's output.

## 3. Architecture & Data Flow

```
SurveyInsightService::summarize(array $dashboardData): string
  → if $dashboardData['response_count'] === 0, return a fixed
    "not enough data yet" string — never call the API for an empty dashboard
  → formatDashboardData() serializes the full array into compact plain text
    (not raw JSON — see §4)
  → calls Claude via Http::withHeaders([...])->timeout(30)->post(
      'https://api.anthropic.com/v1/messages',
      ['model' => 'claude-sonnet-4-20250514', 'max_tokens' => 1000, ...]
    ), reading config('services.anthropic.api_key') — identical to
    ChatController's proven call shape, including its exact 30s timeout and
    1000 max_tokens (the response is capped to "a few short paragraphs" per
    §4's system prompt regardless of how large the input prompt is, so the
    existing 1000-token ceiling is expected to be enough headroom; nothing
    here rules out raising it later if real usage shows it's tight)
  → missing API key, a failed HTTP response, or a thrown exception each
    return a friendly fallback string — summarize() never throws

SurveyDashboard (existing Filament page, app/Filament/Resources/
SurveyResource/Pages/SurveyDashboard.php)
  → gains public ?string $summary = null and public bool $generatingSummary
  → gains generateSummary(): calls SurveyInsightService::summarize(
      $this->dashboardData) and stores the result in $summary
  → the existing updatedEventId() hook (which already reloads
    $dashboardData when the event dropdown changes) additionally sets
    $summary = null — a summary generated for "All Events" must not persist
    next to charts now scoped to one event
  → the Blade view gains a "Generate Summary" button (plain Livewire
    wire:click, matching the page's existing plain-Blade/Livewire-property
    style — not a Filament header Action) and, once $summary is set, a text
    block rendered above the section tabs
```

`SurveyInsightService` never queries the database and never calls
`SurveyScoringService`/`ScoringEngine` — its only input is the array
`SurveyDashboardService::build()` already produced.

## 4. Prompt Design

`formatDashboardData()` renders the array as compact plain-language text,
not a raw JSON dump — JSON's punctuation overhead spends tokens the model
doesn't need, and plain sentences read more naturally as narratable data.
Shape:

```
Survey: {response_count} submitted responses. Overall completion: {pct}% ({grade}).

Section "{name}" (completion: {pct}%, {grade}):
- "{question text}" (bar): {label}: {count}, {label}: {count}, ...
- "{question text}" (histogram): avg {avg}, min {min}, max {max}
- "{question text}" (diverging_stack): row "{row label}": {column}: {count}, ...
- "{question text}" (status_bar): Complete: {n}, Incomplete: {n}
- "{question text}" (list): {n} free-text response(s) on file
- "{question text}" (table): {row_count} row(s) across {response_count} response(s)
[Trend across events: {label}: {value}, {label}: {value}, ...]
```

Sections/questions with a `null` chart type (date, datetime, file_upload,
signature — Phase 3's explicitly uncharted types) are omitted from the
prompt text entirely; they carry no chartable data to narrate.

**System prompt** instructs the model to: narrate only, using exactly the
numbers given; never invent, estimate, or recompute a statistic not
present in the text; call out sections with low completion or a
yellow/red grade, and any question whose distribution looks skewed or
otherwise worth a manager's attention; keep the response to a few short
paragraphs.

## 5. Resilience

Mirrors `ChatController`'s existing behavior exactly — `summarize()` never
throws, always returns a string:

| Condition | Returned string |
|---|---|
| `dashboardData['response_count'] === 0` | `"Not enough data yet to generate a summary — no responses have been submitted."` (checked before any HTTP call) |
| `config('services.anthropic.api_key')` is empty | `"AI summary is not configured yet. Please ask the administrator to set the ANTHROPIC_API_KEY."` |
| The HTTP request fails (`$response->failed()`) | `"Sorry, the summary is temporarily unavailable. Please try again later."` |
| An exception is thrown during the request | Same "temporarily unavailable" string, caught and returned, not propagated |

## 6. Testing

- **`SurveyInsightServiceTest`**: `Http::fake()` to verify — a successful
  API response returns the model's narrated text; a missing API key
  returns the fallback string with **zero** HTTP calls made (assert
  `Http::assertNothingSent()`); a failed HTTP response returns the
  "temporarily unavailable" string; `response_count === 0` short-circuits
  before any HTTP call, same assertion.
- **`SurveyDashboard` page test addition**: with `Http::fake()`, clicking
  `generateSummary` (calling the Livewire method directly in the test)
  sets `$summary` to the faked response text; changing `eventId` afterward
  clears `$summary` back to `null`.
- **Regression**: full existing suite (kernel, Phase 1–3 Survey* tests,
  facility-assessment tests) stays green — this phase only adds one new
  service and extends one existing page's Livewire state, touching nothing
  from earlier phases.

## 7. What does not change

- `SurveyDashboardService`, `SurveyScoringService`, `ScoringEngine`,
  `SurveyFormBuilder` — untouched; this phase only reads Phase 3's output.
- The public survey link, `SurveyQuestionResource`,
  `SurveyResponseResource`, `SurveyResource` — untouched.
- The facility-assessment engine (`Assessment*` tables/models/resources)
  and `ChatController` itself — untouched; `ChatController`'s call shape is
  read as a pattern to follow, not modified or shared as a dependency.
