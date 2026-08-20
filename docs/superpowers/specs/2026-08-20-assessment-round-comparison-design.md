# Facility Assessment Round Comparison — Design

## Problem

`admin/assessments/create` has no notion of assessment "round" (baseline,
midline, endline, or an ad-hoc round). The `assessment_type` enum column
that used to hold this is vestigial — it's not exposed on the create form
and defaults to `'baseline'` for every record.

Because of this, the assessment summary (`ViewAssessmentSummary`, backed by
`AssessmentPdfReportService`) only ever renders one assessment at a time.
There is no way to see how a facility's Human Resources, Health Products,
Infrastructure, Skills Lab, Info Systems, or Quality scores changed across
repeated assessments of the same facility.

Goal: let a user tag an assessment with its round at creation time, and
have the summary automatically show a side-by-side comparison — one column
per round found for that facility — across every structured section, not
just Health Products and Human Resources.

## Key constraint discovered during research

Indicators are **template-specific**, keyed by `question_code` scoped to
an `assessment_type_id` (`AssessmentSection.assessment_type_id`,
`AssessmentQuestion.assessment_section_id`; see
`database/seeders/FacilityAssessment2026/IndicatorsSeeder.php`).
`AssessmentPdfReportService` matches responses to indicators by
`question.question_code`, not a template-independent ID.

This means two assessments only line up row-for-row if they were taken
against the **same `assessment_type_id`** (template). Comparison is
therefore scoped to: same `facility_id` + same `assessment_type_id`,
different round.

Today, `Assessment` creation enforces one record per
`(facility_id, assessment_type_id)` pair (app-level guard in
`CreateAssessment`). That guard must be relaxed to allow multiple rounds
against the same template, while still preventing duplicate rounds.

## Data model changes

- **`assessments.assessment_type`**: convert from `enum('baseline','midline','endline')`
  to `string`. Avoids a Doctrine/DBAL-dependent enum alteration. Valid
  values enforced at the application layer: `baseline`, `midline`,
  `endline`, `other`. Existing rows keep their current value.
- **New column `assessments.round_label`**: nullable `string`. Populated
  only when `assessment_type = 'other'`; holds the free-text "specify"
  value from the create form.
- **`Assessment` model**:
  - Add `round_label` to `$fillable`.
  - Add a `round_display` accessor: returns `ucfirst($assessment_type)`
    for baseline/midline/endline, or `round_label` when `assessment_type
    = 'other'`.
  - Add a round-ordering helper (e.g. a static map or a query scope) that
    orders `baseline` → `midline` → `endline` → `other` (multiple "other"
    rows ordered by `assessment_date`). Used wherever comparison columns
    are built.

## Create form changes (`CreateAssessment.php`)

Add to the Assessment Details section, alongside the existing
facility/template/date fields:

- **"Assessment Round"** — required `Select`: Baseline, Midline, Endline,
  Other.
- **"Specify round"** — `TextInput`, visible and required only when
  Round = Other. Written to `round_label`.

## Duplicate guard

The existing app-level uniqueness check (currently: one `Assessment` per
`facility_id` + `assessment_type_id`, soft-delete aware) is extended to
also key on `assessment_type` (the round), and — for `other` — on
`round_label` too. Result: a facility can have at most one Baseline, one
Midline, one Endline, and any number of distinctly-labeled "Other"
assessments per template.

This is enforced in application code (the existing guard location), not
a DB unique index — MySQL treats each `NULL` in a unique index as
distinct, which would let duplicate baseline/midline/endline rows slip
through since `round_label` is `NULL` for those.

## Comparison in the summary

New method on `AssessmentPdfReportService`, e.g. `prepareComparisonData(Assessment $assessment)`:

1. Find sibling assessments: same `facility_id`, same `assessment_type_id`,
   excluding drafts, ordered by the round-ordering helper.
2. For each sibling, reuse the existing per-section data-prep logic
   (the same code paths `prepareReportData()` already uses for Human
   Resources, Health Products, Infrastructure, Skills Lab, Info Systems,
   Quality, and the overall score/percentage/grade).
3. Merge into a comparison structure: per section, a list of indicator
   rows, each row carrying one value per round-column, matched by
   `question_code`. A `question_code` missing from a given round's
   response set renders as a dash for that column.

`assessment-html-report.blade.php` is reworked so each structured
section renders as a table with one column per round present (instead of
today's single fixed value column). With only one assessment for that
facility+template, it renders exactly as today (one column, no visual
change). The overall score row also gets one column per round so
improvement/decline is visible at a glance.

## Out of scope (this pass)

PDF export and CSV export (`AssessmentPdfReportService`'s PDF path,
`AssessmentExportService`) keep their current single-assessment behavior.
Extending those to the same comparison view is a candidate follow-up, not
part of this change — the ask was specifically about the HTML summary.

## Testing

- Feature test: creating a second assessment for the same facility +
  template with a different round succeeds; creating a duplicate round
  (including two "Other" with the same `round_label`) is rejected with a
  clear validation message.
- Feature/unit test on `AssessmentPdfReportService::prepareComparisonData()`:
  given 2–3 sibling assessments with overlapping and non-overlapping
  `question_code`s, verify column ordering (baseline/midline/endline/other)
  and correct dash-fill for missing codes.
- Manual check: summary page for a facility with only one assessment
  renders unchanged.
