# Facility Readiness Assessment 2026 — Phase 1: Engine Capabilities

**Status:** Approved
**Date:** 2026-08-13
**Depends on:** existing Facility Readiness Assessment engine (`AssessmentType`, `AssessmentSection`, `AssessmentQuestion`, `AssessmentQuestionResponse`, `Commodity`, `CommodityCategory`, `AssessmentDepartment`, `MainCadre`, `ConditionalLogicEvaluator`, `FormKernel/*`, `DynamicFormBuilder`, `DynamicScoringService`, `CommodityScoringService`)
**Followed by:** Phase 2 (2026 content seeder built row-by-row from `Assessments. v2.xlsx`), Phase 3 (visual polish)

## Context

The "Facility Readiness Assessment" is being revised into a 2026 version, sourced from `Assessments. v2.xlsx` (395 rows, one sheet: "Mentorship questionnaire survey"). The spreadsheet's own notes (rows A1–A2) specify the new rules:

> "Whenever an answer is no, provide an area to enter reason. Comma separated items are individual line lists that require their own YesNo Response, also number them appropriately with a,b like others then also indent any followup question inner than the parent question."
>
> "ALWAYS READ THE NOTES for the assessments, be careful with conditional logic or skip patterns available then also note the checklists we must show them as popups on the side of the questions."

An audit of the current engine (see conversation history) found:

- `AssessmentType.version` is a free-text string with no real versioning/isolation — each "version" today is simply a separate `AssessmentType` row.
- `AssessmentSection`/`AssessmentQuestion`/`Assessment` are already scoped to `assessment_type_id`. `Commodity`, `CommodityCategory`, `AssessmentDepartment`, and `MainCadre` are **not** — they are global, shared, unscoped master tables.
- Individual-question conditional display (`display_conditions` JSON + `ConditionalLogicEvaluator`, applied in `DynamicFormBuilder` and excluded from scoring via `ScoringEngine::excludeConditionallyHiddenQuestions()`) already works and is subject-agnostic (shared with the new Survey platform's `FormKernel`). This is sufficient for the spreadsheet's skills-lab Yes→a)/No→b) branching and EMR-dependent indicator skips — no new capability needed there, only Phase 2 content authoring.
- Commodities already support Yes/No/N/A per (commodity × department), with N/A excluded from scoring denominators (`CommodityScoringService`). Many commodities are combined multi-size line items (e.g. "Suction catheter sizes 5, 6, 8, 10" as one row) — there is one precedent for un-combining (`AmbuBagCommoditySeeder`), but this is exactly what the spreadsheet requires systematically.
- Human Resources is a hardcoded 5-column-per-cadre matrix (`EditHumanResources.php`) with no way to mark a cell as not applicable — the spreadsheet requires this constantly (e.g. "Maternity theatre anaesthetists" × "IMNCI" = N/A).
- There is no mechanism to hide an entire section, department tab, commodity category, or individual commodity based on a prior answer (e.g. "hide NICU/PICU category if facility has no NICU"). Only individual questions can be conditionally hidden today.
- There is no way to attach reference-checklist content (ORT Corner checklist, Triage requirements, Skills Lab checklist) to a question for on-demand display.
- Every N/A marker found in the spreadsheet lives inside the Health Products (commodity) or Human Resources (cadre) matrices — ordinary yes/no questions never need N/A. So **no new N/A concept is needed on `AssessmentQuestionResponse`.**

Phase 1 builds the missing engine capabilities. It does **not** create the 2026 content (sections, questions, commodities, checklists) — that's Phase 2. It also does not touch visual styling beyond what's needed to prove each capability renders correctly — that's Phase 3.

## Goals

1. Make `AssessmentType` "versions" actually isolated: a 2026 template can exist alongside the 2025 template without either affecting the other's data, history, or reports.
2. Support splitting a combined multi-size/multi-item spreadsheet row into individual, auto-lettered, indented line items — for both `AssessmentQuestion` and `Commodity` — without a proliferation of new tables.
3. Support per-cell "not applicable" in the Human Resources cadre matrix.
4. Support attaching a reference checklist to a question, shown on demand.
5. Support hiding an entire section, department tab, commodity category, or individual commodity based on answers elsewhere in the assessment, reusing the existing conditional-logic evaluator.

## Non-goals

- Building the actual 2026 `AssessmentType` row, its sections/questions/commodities/checklists/cadres (Phase 2).
- Final visual design of indentation, lettering typography, badge styling (Phase 3 — Phase 1 only needs to render correctly, not beautifully).
- Any change to the 2025 assessment's behavior, data, or appearance.
- A generic "N/A" concept on `AssessmentQuestionResponse` — not needed per the audit above.
- Migrating/backfilling old `XAssessment*`/`ManageModuleAssessments` dead files — confirmed unreferenced; out of scope for this work (can be deleted separately, not part of this project).

## Design

### A. Type-scoping master data

Add a nullable `assessment_type_id` FK (`constrained('assessment_types')->nullOnDelete()`) to:

- `commodity_categories`
- `assessment_departments`
- `main_cadres`

Backfill migration sets all existing rows to the current live `STANDARD_FACILITY_ASSESSMENT` type's id, so the 2025 assessment continues to see exactly the categories/departments/cadres it always has. `Commodity` needs no new column — it scopes implicitly through `commodity_category_id → commodity_categories.assessment_type_id`.

All queries that currently load "all active commodity categories / departments / cadres" (`EditHealthProducts.php`, `EditHumanResources.php`, `CommodityScoringService`, `AssessmentExportService`, `AssessmentPdfReportService`) get a `where('assessment_type_id', $assessment->assessment_type_id)` filter added, resolved from the `Assessment` record's own type. This is the only behavioral change to existing 2025 code paths — additive filtering, not a data change.

The 2026 `AssessmentType` row itself (e.g. code `STANDARD_FACILITY_ASSESSMENT_2026`) is created by the Phase 2 seeder, not here — Phase 1 only needs the schema to support it existing.

### B. Line-item splitting, indentation, and auto-lettering

Two new columns, same meaning on both models:

- `assessment_questions.indent_level` (unsignedTinyInteger, default 0)
- `commodities.indent_level` (unsignedTinyInteger, default 0)

And one new grouping column:

- `commodities.group_label` (nullable string) — mirrors the existing `assessment_questions.group` column (already used as "visual sub-section" grouping), so questions need no new column here.

Convention: when a spreadsheet row is a combined line list (e.g. "Suction catheter sizes: a) Fr-6 b) Fr-8 c) Fr-10 d) Fr-12"), Phase 2 content authors it as:

- One row with `indent_level = 0` holding the shared label ("Suction catheter sizes") — non-interactive, not scored, not answerable (rendered as a group header only). For questions this uses a lightweight new `question_type` value `group_header` (label-only, no input, excluded from scoring and from response saving). For commodities, a `Commodity` row is not needed for the header at all — `group_label` on the children is sufficient since commodities are always individually answerable rows; the header is synthesized at render time from `group_label`.
- N children sharing the same `group`/`group_label` and same section/category/department context, each `indent_level = 1`, ordered by the existing `order` column.

A single shared helper — `FormKernel/LineItemGrouper.php` (new, subject-agnostic like the rest of `FormKernel`) — takes an ordered collection of question-or-commodity-like items and yields `(header_label|null, [(letter, item)])` groups: consecutive items sharing the same group/group_label value are clustered, and letters (`a`, `b`, `c`, ...) are assigned by position within the cluster. `DynamicFormBuilder` (questions) and `EditHealthProducts`/`GroupedFieldRenderer` (commodities) both consume this helper instead of duplicating clustering logic.

Rendering: group header is bold, `indent_level = 0`; children render with a left-margin indent proportional to `indent_level` and are prefixed with their computed letter — e.g. "a) Fr-6". This satisfies the spreadsheet's "number them appropriately with a,b like others then also indent any followup question inner than the parent question" instruction generically, for arbitrary nesting depth (though the spreadsheet only ever needs depth 1).

### C. Human Resources per-cell N/A

New nullable JSON column: `main_cadres.na_training_columns` — an array drawn from the five fixed column keys already hardcoded in `EditHumanResources.php` (`etat_plus`, `comprehensive_newborn_care`, `imnci`, `type_1_diabetes`, `essential_newborn_care`).

`EditHumanResources::form()` skips rendering the `TextInput` for any column listed in the cadre's `na_training_columns` (and `mutateFormDataBeforeSave` skips writing that field, leaving it `null` on `HumanResourceResponse` rather than coercing to `0` — a `null` cell in exports must read as "N/A", not "0 trained"). A cadre needing every column N/A (e.g. a facility-level count-only row like "No of TOTs in the facility") simply lists all five keys — the grid area collapses to just the "Total Staff" field, no separate `is_count_only` flag needed.

`AssessmentExportService`/`AssessmentPdfReportService` render `N/A` (not blank, not `0`) for any cell whose column is in the cadre's `na_training_columns` — both already have an `N/A` fallback convention for null values per the audit, so this reuses existing rendering, not new logic.

### D. Checklists

Two new tables:

```
assessment_checklists
  id, assessment_type_id (FK, nullable — scoped like other type-owned master data),
  title, description (nullable), timestamps

assessment_checklist_items
  id, assessment_checklist_id (FK, cascadeOnDelete),
  group_label (nullable string — e.g. "EQUIPMENT" / "STATIONERY" for the Skills Lab checklist;
               null for flat lists like Triage or ORT Corner),
  label, qty (nullable unsignedInteger — populated for ORT Corner's "Min. Qty" column,
              null where the spreadsheet has no quantity, e.g. Triage/Skills Lab),
  order, timestamps
```

Plus a nullable `checklist_id` FK on `assessment_questions`. One checklist can be attached to multiple questions (e.g. both ORT-corner questions — outpatient and inpatient — point at the same "ORT Corner checklist" row); a question has at most one attached checklist.

Rendering: a small icon button (e.g. `heroicon-o-clipboard-document-list`) next to any question with a non-null `checklist_id`, opening a Filament modal listing the checklist's items — grouped under their `group_label` headers where present, with a "Qty" column shown only if any item on that checklist has a non-null `qty` (so ORT Corner shows a Qty column, Triage/Skills Lab don't). This satisfies "checklists we must show them as popups on the side of the questions."

### E. Whole-block conditional visibility

Add a nullable `display_conditions` JSON column, same shape already used on `assessment_questions` (`ConditionalLogicEvaluator`'s AND/OR/single-condition schema, keyed by `question_code`), to:

- `assessment_sections`
- `assessment_departments`
- `commodity_categories`
- `commodities`

No changes to `ConditionalLogicEvaluator` itself — it's already subject-agnostic and already resolves parent-question answers across the whole assessment (not just the current section), which is exactly what's needed for e.g. a commodity's `display_conditions` to reference the Infrastructure section's "Do you have a NICU" question.

Applied at each render/scoring point:

- **Section**: `HasSectionNavigation`/`EditSection` skip a section from the section-navigation list and from `DynamicScoringService::recalculateOverallScore()` when its `display_conditions` evaluates false. (Not required by the current spreadsheet content, but built for consistency and because it's the same one-line integration as the other three.)
- **Department tab**: `EditHealthProducts` filters `AssessmentDepartment::query()` through the evaluator before building tabs — a hidden department contributes nothing to `CommodityScoringService`'s per-department scoring.
- **Commodity category**: within a department's tabs, `EditHealthProducts` filters `CommodityCategory` the same way — this is what hides the entire "NICU/PICU" category block when "Do you have a NICU" = No.
- **Individual commodity**: for commodities like surfactant/midazolam that are individually NICU-gated but live inside a category that isn't wholly NICU-only, `EditHealthProducts` filters at the commodity level too, after category filtering.

`CommodityScoringService` excludes conditionally-hidden departments/categories/commodities from `total_applicable` the same way it already excludes `not_applicable`-flagged responses — hidden-by-condition and marked-N/A both mean "don't count this in the denominator," just via different mechanisms (structural vs. per-response).

## Data model summary (new/changed columns only)

| Table | Change |
|---|---|
| `commodity_categories` | + `assessment_type_id` (FK, nullable), + `display_conditions` (json, nullable) |
| `assessment_departments` | + `assessment_type_id` (FK, nullable), + `display_conditions` (json, nullable) |
| `main_cadres` | + `assessment_type_id` (FK, nullable), + `na_training_columns` (json, nullable) |
| `commodities` | + `indent_level` (unsignedTinyInteger, default 0), + `group_label` (string, nullable), + `display_conditions` (json, nullable) |
| `assessment_questions` | + `indent_level` (unsignedTinyInteger, default 0), + `checklist_id` (FK to `assessment_checklists`, nullable); new `question_type` value `group_header` |
| `assessment_sections` | + `display_conditions` (json, nullable) |
| `assessment_checklists` | new table |
| `assessment_checklist_items` | new table |

All new FK columns are nullable with backfill migrations defaulting existing rows to the current live type, so the 2025 assessment's behavior and data are unaffected.

## Testing

- Feature test: creating a second `AssessmentType` with its own `CommodityCategory`/`AssessmentDepartment`/`MainCadre` rows does not alter what the existing 2025-type assessment sees or scores.
- Unit test: `FormKernel/LineItemGrouper` — clustering and lettering across questions and commodities, including groups of 1 (no lettering needed — a lone child isn't a "list") and adjacent-but-different groups.
- Feature test: `EditHumanResources` — a cadre with `na_training_columns` containing some/all of the five keys renders the correct subset of fields and the saved `HumanResourceResponse` has `null` (not `0`) in those columns; export/PDF render `N/A` for those cells.
- Feature test: a question with a `checklist_id` renders the checklist-icon action and the modal lists items grouped/qty'd correctly.
- Feature test: `display_conditions` on a `CommodityCategory` hides that category's tab-section and excludes it from `CommodityScoringService` output when the condition is false; same for a single `Commodity` and for an `AssessmentDepartment`.
- Regression: full existing assessment test suite (2025 Standard + EmONC types) passes unchanged.

## Open questions carried into Phase 2 (content), not blocking Phase 1

- Exact treatment of the "Quality of care — Select agreed timelines (Neonates 7–28 days)" note (row 283): whether this needs a period-selector field or is purely a reviewer instruction.
- Which specific commodities/categories/departments get `display_conditions` populated, and against which exact question codes (NICU, PICU, NBU, paediatric-unit questions) — a content mapping exercise once the 2026 Infrastructure section's question codes exist.
- Whether the fourth possible checklist implied by rows 394–395 ("Radiant Warmer" / "Suction Machine", appearing right after the Skills Lab checklist with no new title row) is a continuation of the Skills Lab checklist's equipment list or a distinct checklist — needs visual inspection of the original spreadsheet formatting or a question back to the source.
