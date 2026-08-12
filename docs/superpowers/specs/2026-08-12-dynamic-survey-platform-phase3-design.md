# Dynamic Survey Platform — Phase 3: Auto-Generated Dashboards — Design

**Date:** 2026-08-12
**Status:** Approved for planning

## 1. Background

Phase 1 (generic survey/instrument builder) and Phase 2 (longitudinal
events) are complete and merged to `main`: `Survey` → `SurveySection` →
`SurveyQuestion`, filled into `SurveyResponse` → `SurveyQuestionResponse`,
scored via `SurveyScoringService`/`ScoringEngine` into `SurveySectionScore`,
with optional `SurveyEvent` timepoints (fixed or repeatable) and
event-to-section mapping.

This document designs Phase 3 — auto-generated dashboards, per the phasing
in the original platform spec
(`docs/superpowers/specs/2026-08-12-dynamic-survey-platform-design.md` §2,
§3.4): given any survey's questions and responses, produce charts/tables by
convention on `question_type`, with zero per-survey developer work.

While designing the chart types, the codebase's `dataviz` skill was
consulted (its trigger — "whenever you are about to create ANY chart" —
applies directly here) and materially refined several chart choices the
original spec had only sketched loosely ("bar/pie", "gauge"). Those
corrections are reflected in §3 below and are a deliberate improvement over
the original sketch, not a deviation from it.

## 2. Scope

**In scope:**
- `SurveyDashboardService::build(Survey $survey, ?SurveyEvent $event = null): array`
  — a pure, read-only aggregation service producing a stable, chart-agnostic
  data structure from a survey's **submitted-only** responses.
- A new `SurveyDashboard` Filament page, nested under `SurveyResource`
  (`admin/surveys/{record}/dashboard`), reached via a row action on the
  Surveys list and a header action on `EditSurvey`.
- Per-section tabs (one per active `SurveySection`, in order), each showing
  that section's charts, built from `SurveyDashboardService`'s output.
- For longitudinal surveys: an event dropdown (default "All Events") that
  re-scopes every chart to one event's responses; numeric/rating questions
  additionally get a trend line across events when "All Events" is
  selected.
- Chart.js loaded via the same CDN `<script>` + inline-script pattern
  already used in `resources/views/filament/pages/emonc-dashboard.blade.php`
  — no new charting stack, no build step.

**Explicitly out of scope for Phase 3:**
- Any AI narrative/summary generation — that is Phase 4
  (`SurveyInsightService`), which will consume this phase's output array
  unchanged.
- Charting for `date`, `datetime`, `file_upload`, `signature` question
  types — no current survey need justifies the complexity; they still
  contribute to the section completion meter.
- Any change to `SurveyScoringService`/`ScoringEngine` — the dashboard only
  *reads* already-computed `SurveySectionScore` rows, never recalculates
  anything.
- A new Shield permission — the dashboard is gated by the existing
  `view_survey` permission `SurveyResource` already requires.

## 3. Chart-Type Mapping (dataviz-corrected)

Every question type maps to exactly one chart treatment, determined solely
by `question_type` — never configured per-survey. The dataviz skill's
"choose the form by the job, then color last" procedure corrected three
things the original spec only gestured at: **no pie charts** (a
part-to-whole share of >2 options is a stacked bar, and this dashboard's
"how many chose each option" is actually a *magnitude comparison*, not
part-to-whole — see below), **Likert-style questions get a diverging
stacked bar** (not a plain stacked bar — polarity is the whole point of a
Likert scale), and **the completion indicator is a meter, not a donut** (a
single ratio against a limit is explicitly *not* "a pie of 2 slices" per
the skill's own form-choice table).

| Question type(s) | Chart | Rationale |
|---|---|---|
| `select`, `radio`, `checkbox`, `cadre_select`, `yes_no`, `yes_no_partial`, `rating` | Horizontal bar, one bar per option (in the question's configured order), **every bar the same single sequential hue**, value labels directly on each bar | "How many respondents chose each option" is a magnitude comparison across categories (one series, N categories on the axis) — coloring each bar a different categorical hue would be the "value-ramp on nominal categories" anti-pattern; one series always takes one hue |
| `group_completeness` | Horizontal bar, 2 bars (Complete / Incomplete), **status colors** (good/critical) | The one case in this dashboard where the answer *is* an inherent good/bad state, not an arbitrary participant choice — the dataviz skill's "collision rule" says a series that means good/bad wears status tokens, never categorical |
| `number`, `proportion` | Histogram (binned counts, single sequential hue) + a stat-tile row above it (avg / min / max) | Distribution and headline number are two different jobs; a single overloaded chart would blur both |
| `matrix` | One **diverging stacked bar per row** (sub-question), centered on that row's neutral option, diverging blue↔aqua pair + neutral gray midpoint | This is the skill's named case: "ordered-scale share (Likert, sentiment, agree↔disagree) → diverging stacked bar, centered on neutral" |
| `repeater` | Stat tile ("N rows across M responses") + a plain paginated data table, no chart | Free-form, admin-defined columns can't be charted meaningfully; a table is the honest answer |
| `text`, `short_text`, `email`, `phone` | Capped list of the most recent 20 raw responses, no chart | Per the approved decision — open-ended answers are read, not visualized; capped to bound page size, not for privacy (the dashboard is already permission-gated to the same audience that can already read individual `SurveyResponse` records) |
| `date`, `datetime`, `file_upload`, `signature` | No chart | Out of scope per §2; still counted in the section's completion meter |
| *(every type, regardless of chart)* | Contributes to one **section-level completion meter** — a status-colored (green ≥80% / yellow ≥50% / red <50%, reusing `ScoringEngine::calculateGrade()`'s exact thresholds) horizontal progress bar, always paired with the percentage as visible text | "A single ratio against a limit → meter (same-ramp track)," never a 2-slice donut; status color always ships with a text label, never color alone |

**Matrix diverging-center rule**: a matrix question's columns are assumed
to represent an ordered scale (the Likert convention this question type
exists for). An odd number of columns centers on the true middle column;
an even number centers between the two middle columns (standard
diverging-chart convention for scales with no exact neutral point).

**Trend lines** (longitudinal surveys, "All Events" selected only): one
small line chart per `number`/`proportion`/`rating` question, **never
overlaid with another question on the same axis** (avoiding the "dual-axis
chart" anti-pattern — two differently-scaled measures never share a plot),
single series, single hue, x-axis = event order. For a repeatable event
with multiple instances, the point plotted at that event is the **average**
of all instances' values across all subjects (per the approved decision) —
one point per event, not one per instance.

**Palette**: chart colors are the dataviz skill's documented default
palette (`references/palette.md`) — sequential blue ramp for magnitude
bars/histograms, the blue↔aqua diverging pair for matrix rows, and the
fixed status scale (good/warning/critical) for completion meters and the
group-completeness split. These are pre-validated (colorblind-safe,
contrast-checked) reference values, used verbatim rather than re-derived.
The existing app's dark-blue `#1e3a5f` section-banner color and its
informal green/yellow/red grade convention are UI chrome, not chart data —
left untouched; the dataviz skill's status palette is a close, validated
superset of that same green/yellow/red intent, so charts and existing UI
read as one consistent system rather than clashing.

## 4. Data Contract

`SurveyDashboardService::build()` returns:

```php
[
    'overall_completion' => ['percentage' => float, 'grade' => 'green'|'yellow'|'red', 'answered' => int, 'total' => int],
    'response_count' => int,               // submitted responses this build() covers
    'events' => [ ['id' => int, 'name' => string], ... ],  // empty if non-longitudinal
    'sections' => [
        [
            'id' => int, 'name' => string, 'order' => int,
            'completion' => ['percentage' => float, 'grade' => string] | null,  // null if section is not is_scored
            'questions' => [
                [
                    'id' => int, 'text' => string, 'type' => string,
                    'chart' => 'bar' | 'status_bar' | 'histogram' | 'diverging_stack' | 'list' | 'table' | null,
                    'data' => array,   // shape below, keyed by 'chart' value
                    'trend' => ['labels' => array<string>, 'values' => array<float>] | null,
                ],
                ...
            ],
        ],
        ...
    ],
]
```

`'data'` shape per `'chart'` value:
- `bar`: `[['label' => string, 'count' => int], ...]` — one entry per
  configured option, in the question's own order (not sorted by count).
- `status_bar`: `['complete' => int, 'incomplete' => int]`.
- `histogram`: `['bins' => [['range' => string, 'count' => int], ...], 'avg' => float, 'min' => float, 'max' => float]`.
- `diverging_stack`: `['rows' => [['label' => string, 'counts' => [column_label => int, ...]] , ...], 'columns' => array<string>, 'neutral_index' => int]`.
- `list`: `['responses' => array<string>]` — most recent 20 by the parent response's `submitted_at`, newest first.
- `table`: `['row_count' => int, 'response_count' => int, 'rows' => array<array<string, mixed>>]` — capped to a reasonable page size; the response-level `SurveyResponseResource` remains the place to see the full uncapped data.
- `null` chart: `'data'` is `[]`.

This is the exact structure Phase 4's `SurveyInsightService::summarize()`
will later receive unchanged — designed now as a plain, chart-agnostic
array (not Filament/Chart.js objects) specifically so Phase 4 doesn't
require revisiting this service.

## 5. Page Structure

`SurveyDashboard` extends Filament's `Page` (not `EditRecord` — this page
never edits the `Survey` record itself), nested under `SurveyResource`.

- **Route**: `admin/surveys/{record}/dashboard`, registered in
  `SurveyResource::getPages()`.
- **Entry points**: a "Dashboard" icon action on the Surveys list table
  (alongside the existing "Get Link" action) and a header action on
  `EditSurvey`.
- **Mount**: resolves `$survey` via `SurveyResource::getEloquentQuery()`
  (same scoping pattern `AssessmentDashboard::mount()` already uses).
- **Event dropdown**: rendered only when `$survey->events()->exists()`; a
  Livewire `live()` select, default "All Events" (`null`). Changing it
  re-runs `SurveyDashboardService::build($survey, $event)` and re-renders.
- **Tabs**: one per active `SurveySection` with at least one active
  question, in `order` — a section with zero active questions is skipped
  entirely (no empty tab). Each tab renders that section's `'questions'`
  array from the built data: charts via Chart.js canvases (one per
  question, IDs derived from question id), lists/tables via plain Blade
  loops.
- **Completion meters**: the overall meter sits above the tabs; each
  scored section's own meter sits at the top of that section's tab.

## 6. Testing

- **`SurveyDashboardServiceTest`**: one test per chart-type bucket's
  aggregation logic (bar counts in configured option order, histogram
  binning + avg/min/max, diverging-stack per-row column splits with correct
  neutral-index centering for both odd and even column counts,
  group-completeness complete/incomplete split, overall and per-section
  completion percentage/grade, trend-line averaging across repeatable-event
  instances, submitted-only filtering excludes drafts). Plain array
  assertions against `build()`'s return value — no Filament/Livewire
  needed.
- **`SurveyDashboardTest`** (Livewire): page mounts and scopes via
  `SurveyResource::getEloquentQuery()`; the event dropdown swaps which data
  renders; `view_survey` permission gates access — mirrors the existing
  `SurveyResourceTest` pattern.
- **Regression**: full existing suite (kernel, Phase 1/2 Survey* tests,
  facility-assessment tests) stays green — this phase is additive, reading
  existing tables, writing nothing new.

## 7. What does not change

- `app/Services/FormKernel/` (`QuestionFieldBuilder`, `GroupedFieldRenderer`,
  `ScoringEngine`) — untouched.
- `SurveyScoringService` — untouched; the dashboard reads
  `SurveySectionScore`, never recalculates it.
- `SurveyFormBuilder`, the public survey link, `SurveyQuestionResource`,
  `SurveyResponseResource` — untouched.
- The facility-assessment engine (`Assessment*` tables/models/resources) —
  untouched, as throughout this whole platform effort.
