# Dynamic Survey Platform ("REDCap-like") — Design

**Date:** 2026-08-12
**Status:** Approved for planning (Phase 1)

## 1. Background

The platform's facility-assessment engine (`AssessmentType` → `AssessmentSection`
→ `AssessmentQuestion`, rendered by `DynamicFormBuilder`, scored by
`DynamicScoringService`) has grown, over the last several sessions, into a
genuinely dynamic form system: 12 question types (yes/no, yes/no/partial,
proportion, number, text, short_text, select, radio, group_completeness,
mortality_three_month, repeater, cadre_select), conditional/branching logic
(`ConditionalLogicEvaluator`), grouped/table-style layout, and section/overall
scoring — all driven by data (`assessment_questions` rows), not per-template
code.

The ask: generalize this into a platform capability — build **any** survey, in
any format, with any input types, sections, and score matrices, and get
dashboards from the responses automatically — comparable to REDCap, but native
to this app and (eventually) AI-assisted. This is not driven by one specific
survey request; it's infrastructure for future survey needs (M&E, training
feedback, mentee satisfaction, etc.) so they don't each require bespoke
development.

The scope is large enough to be four dependent sub-projects. This document
designs all four so later phases don't force rework of earlier ones, but only
**Phase 1** is planned/built next; Phases 2–4 get their own plan when picked
up.

## 2. Goals

- **Phase 1 — Generic survey/instrument builder.** A `Survey` template
  (sections, questions, all input types, conditional logic, scoring) that
  anyone can build via a Filament resource, filled either by authenticated
  users in the admin panel or via a public token link — without a developer
  writing a new migration/model/Filament page per survey.
- **Phase 2 — Repeating instruments / longitudinal events.** The same survey
  filled multiple times per subject over time (e.g. Baseline, 3-month,
  6-month), REDCap's signature feature.
- **Phase 3 — Auto-generated dashboards.** Any survey's responses render as
  charts/tables by convention (keyed off question type), with zero
  per-survey dashboard code.
- **Phase 4 — AI narrative layer.** Plain-English summaries of a dashboard's
  already-computed data, generated on demand.

**Non-goals (explicitly out of scope, all phases):** REDCap's randomization
module, e-signature/regulatory (21 CFR Part 11) workflows, a public survey
API/SDK for third-party integration, natural-language querying of raw
response data (flagged in brainstorming as a much larger, separately-scoped
follow-on if ever pursued), and migrating existing `Assessment*` data/tables
onto the new schema — facility assessments keep working exactly as they do
today, untouched.

## 3. Architecture: Shared Kernel + New Generic Tables

Three approaches were considered:

- **Retrofit `Assessment*` in place** (add polymorphic subject, rename
  toward `Survey*`): reuses everything with no duplication, but is a live
  migration of production data and of code (PDF/export/dashboard/API) that
  currently hard-assumes `facility_id`. Rejected — regression risk to a
  system relied on today.
- **Fully separate new domain, duplicate the logic**: zero risk, fastest to
  start, but copies ~1000 lines of field-builder/scoring/conditional-logic
  code that will drift out of sync with the original over time. Rejected —
  the existing logic is correct and non-trivial; duplicating it creates a
  maintenance trap.
- **Extract a shared kernel; build new generic tables on top** (chosen):
  `Assessment*` tables, Filament resources, and facility-assessment behavior
  are untouched — zero risk. The subject-agnostic parts of
  `DynamicFormBuilder`/`DynamicScoringService` move into reusable classes
  that both the existing assessment engine and the new survey engine call.
  New `Survey*` tables are designed properly generic from day one (not
  retrofitted), including a polymorphic subject and a first-class events
  table for Phase 2.

### 3.1 Kernel extraction

New namespace `app/Services/FormKernel/`:

- **`QuestionFieldBuilder`** — one method per question type, ported from
  `DynamicFormBuilder`'s `build*Field()` methods. Takes a plain question DTO
  (code, text, type, options, validation_rules, scoring_map, group,
  display_conditions) rather than an `AssessmentQuestion` Eloquent model, so
  both `AssessmentQuestion` and the new `SurveyQuestion` can feed it.
  `AssessmentQuestion`/`SurveyQuestion` each get a thin `toFieldDto()` (or
  equivalent) that adapts their own columns into this shape.
- **`ConditionalLogicEvaluator`** — already model-agnostic (takes a
  value-resolver closure); reused as-is, not moved.
- **`GroupedFieldRenderer`** — the run-collapsing/table-fieldset/group-fieldset
  layout logic (`renderRuns`, `buildTableFieldset`, `buildGroupFieldset`,
  `normalizeColumnSpans`), pure layout with no persistence dependency.
- **`ScoringEngine`** — the section-score/overall-score/group-completeness
  algorithm, parameterized over "which responses belong to this scored unit"
  (a section today; a `SurveySection` tomorrow) rather than hardcoded to
  `AssessmentSection`/`AssessmentQuestionResponse`.

`DynamicFormBuilder` becomes a thin wrapper: it still owns
`AssessmentQuestion`-specific domain quirks that are **not** generic form
concerns — `INFRA_NBU`/`INFRA_PAED` unit-capacity fields and the 3-month
mortality register — and delegates everything else to the kernel.
`DynamicScoringService` becomes a thin wrapper over `ScoringEngine` the same
way. Existing facility-assessment tests must continue passing unchanged
(behavior-preserving refactor, verified by the existing test suite before
any new survey code is written).

**New question types**, added to `QuestionFieldBuilder` directly (available
to both engines): `date`, `datetime`, `email`, `phone`, `checkbox`
(multi-select), `file_upload`, `signature`, `rating` (numeric scale, e.g.
1–5 or 1–10), `matrix` (a grid: shared option set × several sub-question
rows, e.g. a Likert battery).

### 3.2 Data model

```
Survey (template — parallel to AssessmentType)
  id, code, name, description, version, is_active, is_public, metadata
  → SurveyEvent[]        (Phase 2; empty for a non-longitudinal survey)
  → SurveySection[]      (parallel to AssessmentSection: name, order, is_scored)
       → SurveyQuestion[] (parallel to AssessmentQuestion: question_code,
                            question_text, question_type, options,
                            validation_rules, scoring_map, group,
                            display_conditions, is_required, is_scored, order)

SurveyEvent (Phase 2)
  id, survey_id, code, name, order, repeatable (bool)

SurveyResponseSet (one filled-out instance — parallel to Assessment)
  id, survey_id,
  survey_event_id nullable, event_instance_number nullable (Phase 2 —
    which occurrence, for repeatable events),
  subject_type nullable, subject_id nullable (polymorphic — Facility, User,
    MentorshipClass, ...; null = anonymous),
  respondent_name, respondent_email, respondent_contact (nullable free text —
    always available regardless of whether subject is set),
  status (draft|submitted), submitted_at,
  access_token nullable (set when filled via a public link),
  overall_score, overall_percentage nullable (only populated if the survey
    opts into scoring)

SurveyQuestionResponse (parallel to AssessmentQuestionResponse)
  id, survey_response_set_id, survey_question_id, response_value,
  explanation, metadata, score
```

Key decisions:

- **`subject` is polymorphic and nullable.** Anonymous surveys (e.g. open
  training feedback) capture only `respondent_name`/`respondent_email` on
  the response set. A facility-targeted survey sets
  `subject_type=Facility::class`. Extensible to new subject models later
  without a migration.
- **No separate "record" table for longitudinal grouping.** REDCap invents
  an abstract Record ID to group events for one participant; this platform
  doesn't need that abstraction because subjects already have durable
  identity (`Facility`, `User`, etc. rows). Grouping a subject's responses
  across events is just `WHERE subject_type = ? AND subject_id = ?`.
- **`access_token`** reuses the exact pattern `MentorshipClass` already uses
  for `/enroll/{token}` (`Str::random(32)`). A token can optionally
  pre-bind a `subject_id` (e.g. "send this specific facility its own link")
  or be left subject-less for a fully open public survey.
- **Repeaters vs. events are unrelated.** A `repeater` question type
  (add/remove rows within one answer, e.g. a list of action items) lives
  inside one `SurveyResponseSet`. A `SurveyEvent` repeats the *entire*
  response set. Both are kept, doing different jobs, matching how the
  existing `repeater` question type already works in facility assessments.

### 3.3 Form rendering & distribution

- **`SurveyFormBuilder`** (parallel to `DynamicFormBuilder`, thin wrapper
  over the kernel) renders a `SurveySection`'s questions using the same
  grouping/table/repeater/conditional-visibility logic assessments already
  get, since both call `GroupedFieldRenderer`/`QuestionFieldBuilder`.
- **Admin panel**: `SurveyResource` (Filament) for building survey templates
  — sections, questions, types, conditional logic, scoring — mirroring
  `AssessmentTypeResource`'s existing builder UX. A `SurveyResponseResource`
  (or nested pages under `SurveyResource`, TBD at plan time) lists/fills
  response sets for authenticated users, mirroring `AssessmentResource`.
- **Public link**: `Survey.is_public` exposes a "Get link" action that
  generates `access_token`. New `SurveyController@show`/`@submit` at
  `GET/POST /survey/{token}`, modeled directly on the existing
  `MenteeEnrollmentController` (`/enroll/{token}`) — same "resolve by
  token, render form, accept submission" shape, no authentication required.

### 3.4 Dashboard auto-generation (Phase 3)

`SurveyDashboardService::build(Survey $survey, ?SurveyEvent $event = null): array`
produces a dashboard data structure from a survey's responses, by convention
on `question_type`, requiring zero per-survey code:

| Question type | Default visualization |
|---|---|
| `select`, `radio`, `yes_no`, `checkbox` | Bar/pie of response distribution |
| `number`, `proportion`, `rating` | Average + min/max, histogram |
| `matrix`, likert-style | Stacked bar per sub-question |
| `repeater` | Aggregate row count + flattened data table |
| `text`, `short_text` | Response list (no chart) |
| *(any type)* | Contributes to an overall answered/total completion-rate gauge |

For longitudinal surveys (Phase 2), the same builder can slice by
`SurveyEvent` to produce trend lines across timepoints (e.g. one line per
numeric question, x-axis = event order). Rendered as a new Filament page
(`SurveyDashboard`), reusing the existing Chart.js + Livewire wiring already
used by `AssessmentDashboard`/`EmoncDashboard` — no new charting stack.

### 3.5 AI narrative layer (Phase 4)

`SurveyInsightService::summarize(array $dashboardData): string` takes the
**already-computed** dashboard data structure from `SurveyDashboardService`
(never raw response rows, never direct DB access) and calls the Claude API
using the exact pattern already proven in
`app/Http/Controllers/Api/ChatController.php`
(`Http::withHeaders([...])->post('https://api.anthropic.com/v1/messages', [
'model' => 'claude-sonnet-4-20250514', ...])`, reading
`config('services.anthropic.api_key')`). The prompt embeds the precomputed
numbers and asks only for narration (trends, outliers, sections needing
attention) — the model never computes a statistic itself, which avoids
hallucinated figures. Rendered as an on-demand ("Generate summary" button)
text block atop `SurveyDashboard`, not generated on every page load, to
control API cost.

Note: `MenteeAiAdvisor` (mentioned in prior session memory as "calls Claude
API") was checked while designing this and is actually pure rule-based
scoring with no LLM call — `ChatController` is the real, verified precedent
for API usage in this codebase.

## 4. Phasing / build order

1. **Phase 1** (next, gets its own implementation plan): kernel extraction
   (behavior-preserving refactor of the existing engine, verified by the
   current assessment test suite) → new `Survey*` migrations/models →
   `SurveyFormBuilder` → `SurveyResource` admin CRUD → public token link →
   new question types.
2. **Phase 2**: `SurveyEvent` + `event_instance_number`, event-scoped
   response listing, "add another occurrence" UI for repeatable events.
3. **Phase 3**: `SurveyDashboardService` + `SurveyDashboard` Filament page,
   per-question-type chart mapping, event-sliced trend view.
4. **Phase 4**: `SurveyInsightService`, on-demand summary button, prompt
   design/testing for accuracy against known dashboard data.

Each phase is its own spec → plan → implementation cycle; this document is
the shared architectural reference all four build against, so Phase 2's
events table, Phase 3's dashboard conventions, and Phase 4's data contract
don't require revisiting Phase 1's schema.

## 5. Testing

- **Regression**: existing facility-assessment PHPUnit suite must pass
  unchanged after the kernel extraction (Phase 1's first step), proving the
  refactor is behavior-preserving before any new survey code is added.
- **Kernel**: new unit tests for `QuestionFieldBuilder` (one per question
  type, including the new ones), `ScoringEngine` (section/overall scoring,
  group-completeness resolution, conditional exclusion), and
  `GroupedFieldRenderer` (run-collapsing, table merging) — decoupled from
  both `Assessment*` and `Survey*` models, exercised via plain DTOs.
- **Survey engine**: feature tests mirroring the existing assessment feature
  tests — building a `Survey`, rendering its form, submitting responses
  (authenticated + public-token paths), scoring, conditional-logic
  visibility.
- **Dashboard/AI** (Phases 3–4): tests assert the dashboard data structure
  shape per question type, and that `SurveyInsightService` is called with
  exactly the precomputed data (mocked HTTP call — no live API calls in
  tests).
