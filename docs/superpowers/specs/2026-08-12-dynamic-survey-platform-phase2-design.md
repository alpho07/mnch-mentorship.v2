# Dynamic Survey Platform — Phase 2: Repeating Instruments / Longitudinal Events — Design

**Date:** 2026-08-12
**Status:** Approved for planning

## 1. Background

Phase 1 (generic survey/instrument builder) is complete and merged to
`main`: `Survey` → `SurveySection` → `SurveyQuestion`, filled into
`SurveyResponse` → `SurveyQuestionResponse`, scored via
`SurveyScoringService`/`ScoringEngine`, rendered via `SurveyFormBuilder` and
the shared `app/Services/FormKernel/` (question-type field building, layout,
scoring — shared with the pre-existing facility-assessment engine). Filament
resources (`SurveyResource`, `SurveyQuestionResource`,
`SurveyResponseResource`) and a public token-link flow (`/survey/{token}`)
are live.

This document designs Phase 2 — REDCap's signature feature: filling the same
survey multiple times per subject over time (Baseline, 3-month, 6-month
follow-up, or an open-ended "Follow-up Visit" log) — per the phasing laid
out in the original platform spec
(`docs/superpowers/specs/2026-08-12-dynamic-survey-platform-design.md` §2,
§3.2).

## 2. Scope

**In scope:**
- **Events** — named timepoints a survey can define, each either *fixed*
  (happens once per subject) or *repeatable* (any number of occurrences per
  subject).
- **Event-to-section mapping** — a survey's sections can be scoped to a
  subset of its events (e.g. a "Demographics" section that only appears at
  Baseline), not forced to appear identically at every event.
- **Auto-numbered occurrences** for repeatable events, computed
  automatically per subject at creation time — no user-entered instance
  labels.
- **Filtering the existing response list** by event and subject, so a
  subject's full longitudinal record across events is reachable without a
  new page.

**Explicitly out of scope for Phase 2** (deferred to later phases or not
currently justified by any concrete need):
- A dedicated per-subject timeline/trend visualization — that is Phase 3's
  dashboard work (`SurveyDashboardService`); Phase 2 only needs to make the
  underlying data filterable, not visualized as a chart.
- Public-link (`/survey/{token}`) support for events — a survey with events
  is filled out by authenticated users via `SurveyResponseResource` only,
  where picking an event/subject makes sense. Public links continue to work
  exactly as they do today, unchanged, for non-longitudinal surveys.
- Any change to scoring. Each event occurrence is its own `SurveyResponse`
  row with its own `SurveySectionScore` rows — the scoring Phase 1 already
  built is per-response and needs zero changes to work correctly across
  events.

## 3. Data Model

```
SurveyEvent
  id, survey_id, code, name, order, repeatable (bool, default false), timestamps

survey_event_sections (pivot, no model — plain BelongsToMany)
  survey_event_id, survey_section_id

SurveyResponse — two new nullable columns
  survey_event_id       nullable FK -> survey_events; set null if the event
                         is deleted (nullOnDelete — a response shouldn't be
                         destroyed just because its event definition was)
  event_instance_number nullable int — which occurrence, for repeatable events only
```

The `survey_event_sections` pivot's two foreign keys both `cascadeOnDelete`
— deleting a `SurveyEvent` or `SurveySection` removes its mapping rows,
matching the cascade behavior every other Phase 1 relationship already uses
(`survey_sections.survey_id`, `survey_questions.survey_section_id`, etc.).

### 3.1 Section visibility semantics

**A section with no rows in `survey_event_sections` is shown at every event
of its survey.** This is the load-bearing default: it means adding events to
an existing single-form survey is non-destructive — none of that survey's
current sections silently vanish. A section is excluded from a given event
only once an admin deliberately attaches it to a *different, specific* set
of events via the pivot. A non-longitudinal survey (no `SurveyEvent` rows at
all) is entirely unaffected — `buildForSurvey()`'s event-filtering step is a
no-op when no event is passed in.

### 3.2 No separate "Record" table

Consistent with the Phase 1 spec's original decision: a subject's
longitudinal record across events is not a new abstraction — it's just the
set of `SurveyResponse` rows sharing `survey_id` + `subject_type` +
`subject_id`, orderable by the parent `SurveyEvent.order` then
`event_instance_number`. Subjects already have durable identity as
`Facility`/`User`/etc. rows; inventing a REDCap-style abstract Record ID on
top of that would be redundant.

### 3.3 Instance numbering

Scoped to `(survey_event_id, subject_type, subject_id)`: computed as
`max(event_instance_number) + 1` (or `1` if none exist) at the moment a new
response is created for a repeatable event. Never user-entered.

**Edge case, deliberately not optimized for:** a repeatable event's response
created with no subject (an admin creates it without picking one — legal,
since subject is optional per Phase 1). Instance numbers still increment
correctly, scoped to the shared "no subject" bucket (`subject_type IS NULL
AND subject_id IS NULL`) rather than per-individual, since there's no
individual to scope to. This doesn't produce wrong data, just a less
meaningful sequence — acceptable because admin-created subject-less
responses to a repeatable event aren't a scenario this phase needs to make
elegant, only not break on.

## 4. Form Rendering

`App\Services\SurveyFormBuilder::buildForSurvey()` gains an optional third
parameter:

```php
public static function buildForSurvey(Survey $survey, ?int $surveyResponseId = null, ?SurveyEvent $event = null): array
```

When `$event` is given, sections are filtered before being built:

```php
$sections = $survey->sections()->active()->orderBy('order')->get()
    ->filter(fn ($s) => $s->events->isEmpty() || $s->events->contains($event->id));
```

New relations: `SurveySection::events()` (BelongsToMany, through
`survey_event_sections`) and `SurveyEvent::sections()` (the inverse).
`SurveyResponse` gains `event()` (BelongsTo `SurveyEvent`).
`EditSurveyResponse::form()` passes `$this->record->event` into
`buildForSurvey()`, so a response tied to "3-month Follow-up" renders only
that event's sections. Nothing else in the render/save/score path changes —
field building, conditional logic, and scoring are all keyed off
`survey_section_id`/`survey_question_id`, never the event.

## 5. Admin UI

- **`SurveyResource`** gains an `EventsRelationManager` (parallel to the
  existing `SectionsRelationManager`): fields are `code`, `name`, `order`,
  `repeatable` (toggle, default off).
- **`SectionsRelationManager`**'s form gains a `CheckboxList` of the owning
  survey's events, visible only when the survey has at least one event —
  writes to the `events()` pivot. Empty selection means "all events," per
  §3.1.
- **`SurveyResponseResource`**'s Create form: when the selected `Survey` has
  events, an `Event` `Select` appears (options ordered by
  `SurveyEvent.order`). When the chosen event is repeatable, a helper note
  informs the user this will become the next occurrence for the selected
  subject; the actual `event_instance_number` is computed in
  `mutateFormDataBeforeCreate`, never entered by hand.
- **`SurveyResponseResource`**'s table: new `Event` and `Instance #` columns
  (both blank/placeholder for non-longitudinal responses), an `Event`
  filter, and a **Subject filter** (search by the polymorphic subject's
  display name) — added now because "see one subject's longitudinal
  record" requires it and Phase 1 had no subject-filtering UI at all yet.

## 6. What does not change

- `app/Services/FormKernel/` (`QuestionFieldBuilder`, `GroupedFieldRenderer`,
  `ScoringEngine`) — untouched. Events are a survey/response-instance
  concept, not a question-rendering or scoring-algorithm concept.
- `SurveyScoringService` / `ScoringEngine` — untouched; scoring is already
  correctly isolated per `SurveyResponse`.
- The public survey link (`/survey/{token}`) and `PublicSurveyForm` Livewire
  component — untouched; events are admin-panel-only per §2.
- `SurveyQuestionResource` — untouched; questions belong to sections, not
  directly to events.
- The facility-assessment engine (`Assessment*` tables/models/resources) —
  untouched, as throughout this whole platform effort.

## 7. Testing

- **Migrations/models**: `SurveyEvent` CRUD, the `survey_event_sections`
  pivot relations both directions, `SurveyResponse::event()`.
- **Section visibility**: a section with no event rows appears at every
  event; a section attached to one event is excluded from a different event
  of the same survey; a non-longitudinal survey's `buildForSurvey()` output
  is unaffected by passing no `$event`.
- **Instance numbering**: first response for a subject+repeatable-event gets
  `1`; a second gets `2`; a different subject's first gets `1` independently;
  a fixed (non-repeatable) event's response always has a null
  `event_instance_number`.
- **Admin UI**: creating a `SurveyEvent` via the relation manager; the
  Create-response form's Event select appearing only for surveys with
  events; the list's Event/Subject filters narrowing correctly.
- **Regression**: full existing suite (kernel, Phase 1 Survey* tests,
  facility-assessment tests) stays green — event support must be strictly
  additive.
