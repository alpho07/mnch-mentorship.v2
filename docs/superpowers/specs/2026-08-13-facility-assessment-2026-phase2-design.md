# Facility Readiness Assessment 2026 — Phase 2: Content

**Status:** Approved
**Date:** 2026-08-13
**Depends on:** Phase 1 (merged to `main`) — type-scoped master data, line-item lettering/indent, Human Resources per-cell N/A, checklists, whole-block conditional visibility. Design: `docs/superpowers/specs/2026-08-13-facility-assessment-2026-phase1-design.md`.
**Source of truth:** `Assessments. v2.xlsx` (root of repo), sheet "Mentorship questionnaire survey", 395 rows.

## Context

Phase 1 built the engine capabilities. Phase 2 builds the actual 2026 content: a new `AssessmentType`, its sections, questions, commodities, checklists, and cadres — seeded from the spreadsheet, using Phase 1's capabilities. Zero content is shared with the 2025 `STANDARD_FACILITY_ASSESSMENT` type; every row created here is new, type-scoped data.

One additional small engine capability surfaced during this design and is included here as Task 0 (see below): **template parameters**.

## New capability: template parameters (Task 0)

Row 283's header — "Quality of care Select agreed timelines (Neonates 7-28 days): Needs to be set to appear" — turns out to name a general need, not a one-off: an `AssessmentType` should support admin-configured named parameters that appear in section/question titles, set once per template rather than re-entered per assessment.

**Design:**
- Reuse `assessment_types.metadata` (existing `json`, nullable, already cast to `array`, currently unused by any code path — confirmed via Phase 1 audit). Store parameters under a dedicated sub-key to avoid future collisions with any other use of `metadata`: `metadata['parameters'] = ['quality_of_care_timeline' => 'Neonates 7-28 days', ...]`.
- Admin UI: add `Forms\Components\KeyValue::make('metadata.parameters')->label('Template Parameters')->helperText('Reference these in section/question text as {{key}}.')` to `AssessmentTypeResource`'s form.
- Interpolation: new method `AssessmentType::interpolate(?string $text): ?string` — replaces every `{{key}}` occurrence with `$this->metadata['parameters'][$key] ?? "{{key}}"` (unresolved tokens stay visible literally, rather than silently vanishing, so a missing parameter is obvious to whoever's looking at the rendered page, not silently blank). Returns `$text` unchanged (including `null`) if the type has no parameters at all.
- Applied at every point `AssessmentSection`/`AssessmentQuestion` text reaches the UI: `AssessmentSection->name`, `AssessmentSection->description` (in `EditSection::form()`'s `Section::make("{$this->section->name} Assessment")->description(...)` and in `HasSectionNavigation`'s section-chrome data), and `AssessmentQuestion->question_text`/`help_text` (in `DynamicFormBuilder::buildFieldForQuestion()`, before handing off to `QuestionFieldBuilder`/the NBU/mortality special cases — mirrors exactly where Task 3's Phase 1 letter-prefixing already clones and mutates the question).
- 2026 content then uses `{{quality_of_care_timeline}}` in the Quality of Care section's `description`, and the 2026 `AssessmentType`'s `metadata.parameters` sets `quality_of_care_timeline` to a literal transcription of the spreadsheet's own note, e.g. `"Neonates 7–28 days"` — this satisfies "select agreed timelines" as an admin-configured constant (editable per assessment cycle via the KeyValue field) rather than a per-assessment question, and "needs to be set to appear" simply means: the template parameter must be configured for the text to render meaningfully (an unset one visibly shows `{{quality_of_care_timeline}}`, prompting whoever manages the template to fill it in).

No new migration. Backward compatible — a 2025 `AssessmentType` with no `metadata.parameters` renders every `{{...}}`-free string unchanged (2025 content has none).

## AssessmentType

- `code`: `STANDARD_FACILITY_ASSESSMENT_2026`
- `name`: "Standard Facility Readiness Assessment (2026)"
- `version`: `"2026"`
- `category_id`: same `AssessmentTypeCategory` as the 2025 Standard type (facility readiness assessments) — resolved by code/name match at seed time, not hardcoded ID.
- `is_active`: `true`
- `metadata.parameters.quality_of_care_timeline`: `"Neonates 7–28 days"`

## Section list (in order)

Reuses the **exact same section `code` values as 2025** (`facility_profile`, `infrastructure`, `bed_capacity`, `skills_lab`, `human_resources`, `health_products`, `information_systems`, `quality_of_care`) — this is exactly what Phase 1's Task 1 composite-unique fix (`assessment_sections.code` scoped to `assessment_type_id`) was for. Reusing codes means `AssessmentExportService`/`AssessmentPdfReportService`'s hardcoded section-code checks (confirmed in the Phase 1 audit) work for 2026 automatically — no changes needed to either service. One new section code is added for content that didn't exist in 2025.

| Order | Code | Name | `section_type` | Scored | Notes |
|---|---|---|---|---|---|
| 1 | `facility_profile` | Health Facility Profile | `structured_data` | No | Informational, `INFORMATIONAL_CODES` — 0 questions, matches 2025 |
| 2 | `infrastructure` | Infrastructure | `dynamic_questions` | Yes | Infrastructure Yes/No questions **+ all bed-capacity number questions** (see below) |
| 3 | `bed_capacity` | Bed Capacities | `structured_data` | No | Informational placeholder, 0 questions — matches 2025's pattern; real bed-count fields live in `infrastructure` (see rationale below) |
| 4 | `skills_lab` | Skills Lab | `dynamic_questions` | Yes | a)/b) branching via existing `display_conditions` |
| 5 | `human_resources` | Human Resources managing newborns and paediatric patients | `structured_data` | No | Cadre matrix, per-cell N/A |
| 6 | `health_products` | Health Products and Technologies | `commodity_matrix` | Yes | Departments × Categories × Commodities |
| 7 | `information_systems` | Information System and Record Keeping For Monitoring | `dynamic_questions` | Yes | Includes MoH-forms Available/Completeness table |
| 8 | `quality_of_care` | Quality of Care | `dynamic_questions` | Yes | Uses `{{quality_of_care_timeline}}` in its description |
| 9 | `newborn_paediatric_indicators` | Newborn & Paediatric Indicators | `dynamic_questions` | **No** (`is_scored = false`) | New for 2026 — quantitative counts, data-only, never scored (same convention as `mortality_three_month`) |

**Rows 328–355 ("REPORTING PROPORTIONAL NEWBORN INDICATORS" / "CHILD INDICATORS" numerator/denominator/data-source tables) are NOT seeded as questions.** They define how the indicator counts above are used to compute proportions elsewhere (a reporting/analytics concern, not a data-entry field — no response column exists for them in the spreadsheet). Out of scope for Phase 2; noted here so it's a documented decision, not an oversight.

### Why bed-capacity fields live inside `infrastructure`, not a dedicated page

The spreadsheet wants 4 independent bed-capacity blocks (Newborn Unit, Paediatric Unit, PIC Unit, NIC Unit), each revealed only if its corresponding Infrastructure question ("Do you have a newborn/paediatric unit/NICU/PICU") = Yes, each with **Functional** and **Non-Functional** counts per bed type (per the repeated note "include Functional/ Non Functional to all (beds)"). The existing `INFRA_NBU`/`INFRA_PAED` special-cased metadata mechanism in `DynamicFormBuilder::buildUnitCapacityField()` only supports 2 combined toggles with a fixed, smaller field set (no functional/non-functional split, no separate NICU/PICU gating) — it was sized for 2025's simpler needs and doesn't fit 2026's.

Rather than extending that special-cased mechanism, 2026 models every bed-capacity number as a **plain `number`-type question** positioned immediately after its gating Yes/No question in the `infrastructure` section, each with `display_conditions` referencing that gating question — Phase 1 required no new capability for this; it's exactly what per-question `display_conditions` (pre-existing) plus the ordinary `number` type already do. `indent_level = 1` on each bed-count question gives it the same visual nesting as any other split line-item, purely cosmetic. The `bed_capacity` section stays a 0-question informational placeholder (matching the 2025 convention `AssessmentSection::INFORMATIONAL_CODES` already encodes) so section-progress/dashboard iteration logic (`Assessment::getCompletionPercentageAttribute()`) continues to work unchanged.

**Not built in Phase 2 (deferred, non-blocking):** an auto-computed "TOTAL" display (e.g. row 47 `sum(45-46)`) summing sibling bed-count fields live in the form. This is a pure UI nicety with no data-model implication — the total is trivially derivable from the individual Functional/Non-Functional counts whenever needed (export, PDF, or a future Phase 3 polish pass adding a reactive Filament total). Not required for correct data collection.

## Section content, row by row

### Infrastructure (`infrastructure`)

Plain Yes/No questions (rows 28–40), each `requires_explanation_on: ['No']` (matches the sheet-wide rule "whenever an answer is no, provide an area to enter reason" — already-existing `requires_explanation_on` capability, no new work). Question codes use the `INFRA_` prefix, numbered by spreadsheet row order:

- `INFRA_HAS_NBU` — "Do you have a newborn unit" — **gates** the 3 NBU bed-count questions below (`display_conditions: {question_code: INFRA_HAS_NBU, operator: equals, value: Yes}`)
- `INFRA_NBU_GENERAL_FUNCTIONAL` (number) / `INFRA_NBU_GENERAL_NONFUNCTIONAL` (number) — "General NBU beds", `indent_level: 1`, both gated on `INFRA_HAS_NBU`
- `INFRA_NBU_KMC_FUNCTIONAL` (number) / `INFRA_NBU_KMC_NONFUNCTIONAL` (number) — "KMC beds", `indent_level: 1`, gated on `INFRA_HAS_NBU`
- `INFRA_HAS_PAED` — "Do you have a paediatric unit" — gates `INFRA_PAED_GENERAL_FUNCTIONAL`/`INFRA_PAED_GENERAL_NONFUNCTIONAL` (number, "General ward", `indent_level: 1`)
- `INFRA_HAS_NICU` — "Do you have a NICU" — gates `INFRA_NICU_FUNCTIONAL`/`INFRA_NICU_NONFUNCTIONAL` (number, "NICU Beds", `indent_level: 1`). **This is also the question code referenced by every NICU-conditional `display_conditions` in Health Products (see below) and Skills Lab.**
- `INFRA_HAS_PICU` — "Do you have a PICU" — gates `INFRA_PICU_FUNCTIONAL`/`INFRA_PICU_NONFUNCTIONAL` (number, "PICU Beds", `indent_level: 1`)
- `INFRA_SEPARATE_NBU_PAED` — "Is there a separate newborn and paediatric unit"
- `INFRA_SEPARATE_OPD` — "Are newborns and paediatrics patients seen separately from the adults in the outpatient department"
- `INFRA_RESUS_LABOUR` (yes_no) — "Is there a warm functional newborn resuscitation area in labour ward with: Complete resuscitation tray with an updated checklist, Radiant warmer, suction machine" — kept as **one** question (not split into 3 lettered sub-items): unlike the Health Products section's explicit "comma separated items are individual line lists" instruction (which only applies there — see row 105's note, scoped to "Health products and technologies"), this is a single compound yes/no about one physical setup, not a checklist of separately answerable items. Documented assumption.
- `INFRA_RESUS_THEATRE` — "Is there a warm functional newborn resuscitation area in maternity theater?"
- `INFRA_ORT_OUTPATIENT` — "Is there a functional ORT corner in the outpatient department?" — `checklist_id` → **ORT Corner checklist**
- `INFRA_ORT_INPATIENT` — "Is there a functional ORT corner in the inpatient department?" — `checklist_id` → **ORT Corner checklist** (same checklist row, two questions point at it, per Phase 1's design)
- `INFRA_ORT_REGISTER` — "Is there an updated Oral Rehydration Therapy (ORT) corner register?"
- `INFRA_NEBULIZATION` — "Is there a nebulization corner?"
- `INFRA_TRIAGE` — "Is there a triage area in the outpatient department?" — `checklist_id` → **Triage requirements checklist**

### Skills Lab (`skills_lab`)

- `SKILLS_HAS_LAB` (yes_no) — "Is there a functional skills lab?" — gates the "a) If yes" branch
- `SKILLS_YES_POWER_OUTLETS` .. `SKILLS_YES_MONTHLY_REPORTS` (rows 63–72, yes_no each) — `display_conditions: {question_code: SKILLS_HAS_LAB, operator: equals, value: Yes}`. `SKILLS_YES_POWER_BACKUP` includes `options: ['Generator', 'Solar', 'Other']` per its note (row 64) — modeled as `question_type: select` with a following `requires_explanation_on` for "Other". `SKILLS_YES_LOCKABLE_STORE` gets `checklist_id` → **Skills Lab checklist**. `SKILLS_YES_MONTHLY_REPORTS` gets `options: ['Monthly', 'Quarterly', 'Both']` (`select`) per its note (row 72).
- `SKILLS_YES_MANIKIN_CHILD` .. `SKILLS_YES_MANIKIN_ANNE` (rows 73–80, yes_no each) — same `display_conditions` as above. `SKILLS_YES_MANIKIN_ANNE` (Newborn Anne Manikin) additionally requires NICU: `display_conditions: {operator: and, conditions: [{question_code: SKILLS_HAS_LAB, operator: equals, value: Yes}, {question_code: INFRA_HAS_NICU, operator: equals, value: Yes}]}` — per row 80's explicit note "If NICU is available".
- `SKILLS_NO_ROOM_SPACE` (yes_no) — "b) If no: Is there a room/space used for skills teaching and simulation?" — `display_conditions: {question_code: SKILLS_HAS_LAB, operator: equals, value: No}`
- `SKILLS_NO_LOCKABLE_STORAGE` (yes_no) — same `display_conditions` as `SKILLS_NO_ROOM_SPACE`

### Human Resources (`human_resources`)

Cadres seeded to `assessment_cadres` (scoped to the 2026 type), `order` matching spreadsheet row order:

| Cadre | `na_training_columns` |
|---|---|
| Neonatologist | — (all 5 columns applicable) |
| Paediatrician | — |
| Medical officer | — |
| General nurses NBU | `['type_1_diabetes']` (row 92, F92='N/A') |
| Neonatal nurses | `['type_1_diabetes']` (row 93, F93='N/A') |
| General nurses-paediatric | — |
| Paediatric nurses | — |
| Clinical officer paediatric | — |
| Clinical officer | — |
| Maternity theatre anaesthetists | `['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']` (row 98, C/E/F='N/A') |
| Maternity theatre nurses | `['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']` (row 99) |
| Midwives | `['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']` (row 100) |
| Post natal ward nurses | `['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']` (row 101) |

"No of TOTs in the facility" (row 102) is **not** a cadre row — B102='N/A' under the TOTAL IN FACILITY column indicates this is a standalone single-number fact about the facility, not a per-cadre training count. Modeled as its own `dynamic_questions` question in `human_resources`... but `human_resources` is `structured_data` kind (bespoke `EditHumanResources` page), which doesn't render arbitrary `AssessmentQuestion` rows at all. Resolution: add one plain `number` field to `EditHumanResources`'s form directly (outside the per-cadre loop), persisted as a new nullable `assessments.tots_count` column — smallest change that fits the existing bespoke-page pattern (matches how `excluded_cadre_ids` already lives directly on `Assessment`, not as a generic question). This is a small Phase 2 schema addition (one nullable integer column + one form field), not a Phase 1-scale capability.

### Health Products and Technologies (`health_products`)

**Departments** (`assessment_departments`, 2026-scoped): Skills lab, NBU, Maternity, Theatre, Paediatric ward — 5 departments, matching row 106's header columns B/C/D/E/G (column F is blank in the header row and never used as a distinct department in the body — every "F" N/A marker in the dump aligns with the Theatre or a 6th unlabeled slot inconsistently across rows; treated as spreadsheet formatting noise, not a 6th department. Documented assumption).

**Categories** (`commodity_categories`, 2026-scoped, in order): AIRWAY, NICU/PICU, CIRCULATION, DISABILITY, EXPOSURE, INFECTION PREVENTION AND CONTROL (IPC), NUTRITION ASSESSMENT, MEDICINE/DRUGS, OTHERS.

**NICU/PICU category** gets `display_conditions: {question_code: INFRA_HAS_NICU, operator: equals, value: Yes}` (row 113's note: "Hide if facility does not have a NICU").

**Every combined/multi-size commodity row is split** per the sheet-wide rule (row 1 note + row 105 note): one comma-separated cell → N individual `Commodity` rows sharing `group_label` = the row's own label text (stripped of the trailing size list) and `indent_level: 1`, lettered a/b/c... at render time by Phase 1's `LineItemGrouper`. Representative splits (full list continues the same pattern for every remaining multi-item row in AIRWAY/NICU-PICU/CIRCULATION/DISABILITY/MEDICINE-DRUGS):

- "Suction catheters size: Fr-6/Fr-8/Fr-10/Fr12" → 4 commodities, `group_label: "Suction catheters"`
- "Oropharyngeal Airway of appropriate sizes: 00/0/1/2/3/4" → 6 commodities, `group_label: "Oropharyngeal Airway"`
- "ETT (size 2–size 4): 2.5/3.0/3.5/4.0/4.5/5.0/5.5/6.0" → 8 commodities, `group_label: "ETT"`. Sizes 4.5–6.0 (the last 4) additionally get `display_conditions: {question_code: INFRA_HAS_NICU, operator: not_equals, value: Yes}` per row 114's note "N/A in newborn 4.5 to 6" — read as: those larger sizes are for non-newborn/older-child use and are the ones that stay relevant even without a NICU, while 2.5–4.0 are the newborn-relevant sizes always shown. (Documented interpretation — the note is terse; this is the reading that matches "N/A in newborn" literally: the 4.5–6.0 sizes are N/A *specifically for newborn* patients, i.e. always applicable at facilities that see older children regardless of NICU status, so no condition is actually the safer default for 2.5–4.0, and only the interpretation above would ever need a condition. Given the ambiguity, **Phase 2 seeds all 8 ETT sizes with no `display_conditions` at all** — simplest, safest, reversible choice; a facility-specific N/A is already available per-commodity via the existing Available/Not Available/N/A toggle. Flagged for a follow-up content correction if the intended meaning turns out to require the conditional gating.)
- Oxygen source: piped/Cylinder/Concentrator → 3 commodities, `group_label: "Oxygen source"`
- BVM masks' sizes: 00/0/1 → 3 commodities, `group_label: "BVM masks' sizes"`
- IV cannulas-Gauge: 26/24/22 → 3 commodities, `group_label: "IV cannulas"`
- Syringes: 2cc/5cc/10cc/20cc → 4 commodities, `group_label: "Syringes"`
- Needles: G21/G22/G23/G24/G25 → 5 commodities, `group_label: "Needles"`
- Sample bottles: EDTA/Biochemistry/Blood culture bottle/urine/stool/CSF bottles → 6 commodities, `group_label: "Sample bottles"`
- Urinary catheters: 4/6 → 2 commodities, `group_label: "Urinary catheters"`
- NG tube (newborn sizes): 4/5/6 → 3 commodities, `group_label: "NG tube (newborn sizes)"`; NG tube 8/10/12 → separate 3-commodity group, `group_label: "NG tube"`
- Gloves: Clean/Sterile → 2 commodities, `group_label: "Gloves"`
- Colour-coded waste disposal bins: Yellow/Black/Red → 3 commodities, `group_label: "Colour-coded waste disposal bins"`
- Preterm supplements: Multivitamins/Vitamin D 400 IU/Folate tabs/Iron/Calcium → 5 commodities, `group_label: "Preterm supplements"`, each `display_conditions` on `INFRA_HAS_NICU = Yes` (row 187 note "If NICU is available" applies to the whole preterm-supplements group, same as `surfactant`/`midazolam` below)
- Sample bottles/urinary catheters/etc. follow the same pattern; every remaining single-item row (no comma list) becomes exactly one `Commodity`, `group_label: null`, `indent_level: 0` — e.g. "Functional suction machine", "Penguin Sucker", "Magill forceps", "Stethoscope", "Adrenaline", "Vitamin K 2mg", and so on for the rest of AIRWAY/CIRCULATION/DISABILITY/EXPOSURE/IPC/NUTRITION/MEDICINE-DRUGS/OTHERS.

**Individually NICU-gated single commodities** (not part of a category-wide hide, since their category isn't exclusively NICU-only): `surfactant` and `midazolam` (rows 186, 189) — `display_conditions: {question_code: INFRA_HAS_NICU, operator: equals, value: Yes}` per their own "For NICU"/"If NICU is available" notes, same evaluator, applied at the individual-commodity level (Phase 1 Part E).

**Commodity → department applicability**: every commodity in this section is applicable to **all 5 departments** by default (the spreadsheet's department columns B/C/D/E/G are formatting for the response grid, not a per-commodity restriction — no row in the dump narrows a commodity to fewer than all departments). Seeded via `Commodity::applicableDepartments()->attach()` with all 5 department IDs, for every commodity, uniformly.

### Information Systems and Record Keeping (`information_systems`)

- `INFOSYS_DOC_TYPE` (select: Paper based / EMR / Hybrid) — "What type of documentation is the facility using"
- `INFOSYS_PAPER_AVAIL_COMPLETE` (yes_no) — "If paper based/Hybrid ask about the availability and completeness..." — `display_conditions: {question_code: INFOSYS_DOC_TYPE, operator: in, value: ['Paper based', 'Hybrid']}`

**MoH forms Available/Completeness table** (rows 241–264, 22 forms): each form gets **two** `yes_no` questions sharing one `group` value using the existing pipe-delimited table convention (`GroupedFieldRenderer::buildTableFieldset`, already built, no new capability): `group: "Data Collection Tools & Registers|Form|{form name}"` — e.g. `MOH_204A_AVAILABLE` and `MOH_204A_COMPLETE`, both `group: "Data Collection Tools & Registers|Form|MoH 204 A: Out-Patient register"`. All 22 forms follow this identical pattern (NCD Register, MoH 661, MoH 670, Neonatal Child and Adolescent death register, MoH 282, MoH 671, mortality line list, MoH 511, MoH 333, KMC Register, Theatre Maternity register, MoH 373, MoH 301, MoH 378, MoH 379, MoH 377, MoH 711, MoH 661 death notification, D1, B1, Mortality registers, Monthly summary forms ×2).

- `INFOSYS_KHIS_UPLOAD` — "Is data uploaded to KHIS"
- `INFOSYS_KHIS_RESPONSIBLE` — "Is there a person responsible for neonatal data entry into the KHIS Tracker?"
- `INFOSYS_USES_EMR` (yes_no) — "If the facility is using EMR: If Yes" — gates the 6 EMR-report questions below
- `INFOSYS_EMR_REPORT_711` .. `INFOSYS_EMR_REPORT_D1` (6 yes_no, rows 270–274) — each `display_conditions: {question_code: INFOSYS_USES_EMR, operator: equals, value: Yes}`
- `INFOSYS_EMR_ACCESS` (yes_no) — "Does the EMR allow access to the patient records to verify Information" — same `display_conditions`
- `INFOSYS_EMR_KHIS_UPLOAD` (yes_no) — "Is data uploaded to KHIS" (row 276, EMR-context duplicate of `INFOSYS_KHIS_UPLOAD` — kept as a distinct question code since it's asked again specifically about the EMR path) — same `display_conditions`
- `INFOSYS_ATTENDANCE_REGISTER`, `INFOSYS_ASSESSMENT_RECORDS` — rows 277–278, both `help_text` notes "Does Not appear in baseline" transcribed verbatim into `help_text` (informational to the assessor, not a functional gate — the spreadsheet author's own note about these being new for 2026)
- `INFOSYS_FEEDBACK_MECHANISM`, `INFOSYS_MENTORSHIP_DATA_ENTRY`, `INFOSYS_INTERNET` — rows 279–281, plain yes_no

### Quality of Care (`quality_of_care`)

Section `description` set to `"Select agreed timelines: {{quality_of_care_timeline}}"` (interpolated per Task 0).

- `QOC_NEONATAL_AUDITS` — "Are audits conducted to review neonatal deaths?"
- `QOC_NEONATAL_MOH527` — "Are they documented on the Neonatal death review form MoH 527" — `indent_level: 1`, `group: null` (a natural follow-up question, not a comma-separated line-item split — kept ungrouped since it doesn't fit the "list of items needing individual yes/no" rule, just visually indented under its parent via `indent_level` alone, which Phase 1's `LineItemGrouper` correctly leaves unlettered for an indented item with no shared group)
- `QOC_NEONATAL_KHIS_UPLOAD`, `QOC_NEONATAL_ACTION_POINTS` — rows 286–287, `indent_level: 1`
- `QOC_CHILD_AUDITS` — "Are audits conducted to review child deaths at least once a month?"
- `QOC_CHILD_REGISTER` — "Are they documented on the paediatric register" — `indent_level: 1`

### Newborn & Paediatric Indicators (`newborn_paediatric_indicators`, new section, unscored)

All `number` type, `is_scored: false` (mirrors `mortality_three_month`'s "data-only" convention, but as plain integers rather than a 3-month JSON object, since these are single-period counts not a rolling window):

- `IND_NEWBORN_ADMISSIONS` .. `IND_NEWBORN_KMC_DURING_STAY` (rows 292–305, 14 questions) — total admissions, hypothermia, O2 sat taken, RBS taken, head-to-toe exam, birth asphyxia, <34wk admissions/caffeine/corticosteroids, <32wk admissions/CPAP, <2500g KMC, KMC within 2hrs, KMC during stay.
  - `IND_NEWBORN_O2SAT_TAKEN` (row 294) and `IND_NEWBORN_HEADTOTOE` (row 296) both carry the note "if the question to access of EMR is No skip the question" — `display_conditions: {question_code: INFOSYS_EMR_ACCESS, operator: equals, value: Yes}`, referencing the Information Systems section's EMR-access question (Phase 1's cross-section evaluation, already proven working).
- `IND_PAED_ADMISSIONS` .. `IND_PAED_DKA_DEATHS` (rows 308–322, 15 questions) — paediatric O2 sat/hypoxemia/oxygen-started counts (rows 309–311 kept as 3 separate questions, `indent_level: 1` for 310/311 as sub-breakdowns of 309, matching their own "a)"/"b)" lettering already present verbatim in the spreadsheet text itself at rows 310–311), severe pneumonia oxygen/antibiotics/deaths, diarrhoea ORS/hypovolemic-shock, RBS, malnutrition screening ×2 (inpatient/outpatient), Type 1 DM basal-bolus, DKA deaths.

## Checklists

Three checklists, `assessment_type_id` scoped to 2026:

1. **"ORT Corner checklist"** — 17 items (rows 361–377), `group_label: null` (flat list), `qty` populated from the "Min. Qty" column for every row that has one (rows 361–374; rows 375–377 have no Min. Qty value in the dump, `qty: null` for those three — "Chlorine for disinfection", "Low osmolarity ORS/Zinc copack/Resomal", "ORT monitoring tools"). Attached to `INFRA_ORT_OUTPATIENT` and `INFRA_ORT_INPATIENT`.
2. **"Triage requirements"** — one merged cell (row 382) containing a newline-separated list; split into individual items on newlines: Table, Chairs, Paediatric stethoscopes, Vital signs monitor, Digital thermometer, Handheld pulse oximeter with infant and paediatric probes, BP machines with a range of cuff sizes, Weighing scales, Stadiometer, Tape measures, Examination couch, Heating source, Computer, Storage cabinets, Hand washing point, Alcohol-based hand rub, Disposable hand towels — 17 items, `group_label: null`, `qty: null` throughout (no quantities given). Attached to `INFRA_TRIAGE`.
3. **"Skills Lab Checklist Requirements"** — grouped: `group_label: "EQUIPMENT"` for "neonatal manikin with inflatable lungs", "preterm manikin with open nares and mouth for OGT, NGT and CPAP demonstration", "mama breast", **plus** "Radiant Warmer" and "Suction Machine" (rows 394–395) — documented assumption: these two trailing rows have no title row of their own (unlike the other 3 checklists, which each open with a clear bold title before their content), so they're treated as two more EQUIPMENT items appended to this same checklist rather than a 4th, separate checklist. `group_label: "STATIONERY"` for "Flip charts", "White board markers" — 2 items. `qty: null` throughout. Attached to `SKILLS_YES_LOCKABLE_STORE`.

## Non-goals

- Visual/CSS polish of the letter/indent styling beyond what Phase 1 already renders functionally correctly — Phase 3.
- Building the "TOTAL" auto-sum bed-capacity display — deferred, noted above.
- Any change to 2025 `STANDARD_FACILITY_ASSESSMENT` content, scoring, or reports.
- Migrating away from `mortality_three_month`/`INFRA_NBU`/`INFRA_PAED` special cases for 2025 — they stay exactly as-is; 2026 simply doesn't use them for bed capacity (uses plain conditional `number` questions instead, per the rationale above).

## Seeder structure

```
database/seeders/FacilityAssessment2026/
  FacilityAssessment2026Seeder.php   — orchestrator: creates the AssessmentType, calls each seeder in order
  FacilityProfileSeeder.php          — facility_profile section row only (0 questions, matches 2025)
  InfrastructureSeeder.php           — infrastructure section + all bed-capacity number questions
  BedCapacitySeeder.php              — bed_capacity section row only (0 questions, informational placeholder)
  SkillsLabSeeder.php                — skills_lab section + questions
  HumanResourcesSeeder.php           — human_resources section + assessment_cadres rows
  HealthProductsSeeder.php           — health_products section + departments + categories + commodities + applicability
  InformationSystemsSeeder.php       — information_systems section + questions incl. MoH-forms table
  QualityOfCareSeeder.php            — quality_of_care section + questions
  IndicatorsSeeder.php               — newborn_paediatric_indicators section + questions
  ChecklistsSeeder.php               — the 3 AssessmentChecklist + items, called before Infrastructure/SkillsLab (they attach checklist_id by lookup)
```

Each seeder is idempotent (`firstOrCreate`/`updateOrCreate` on natural keys — `question_code` within the section, `code`/`slug` within the type), matching the existing `AmbuBagCommoditySeeder` convention, and prints a one-line `$this->command->info(...)` summary per section so a re-run's output is auditable.

## Testing

- Feature test per seeder: row/question counts match the design doc's tallies exactly, spot-check a handful of representative `question_code`s for exact `question_text`/`question_type`/`display_conditions`, and confirm 2025's `STANDARD_FACILITY_ASSESSMENT` data is completely unaffected (same assertion pattern as Phase 1's `AssessmentTypeScopingTest`).
- One end-to-end test: run the full `FacilityAssessment2026Seeder`, create an `Assessment` against it, walk through Infrastructure → Skills Lab → Health Products → Human Resources, and assert the NICU-gating chain works live (answering `INFRA_HAS_NICU = No` hides the NICU/PICU commodity category and the Newborn Anne Manikin skills-lab question; answering `Yes` shows both) — proves Phase 1's engine and Phase 2's content wire together correctly, not just that content exists in the DB.
- `AssessmentType::interpolate()` unit test (Task 0): resolves a set parameter, leaves an unset one visible as `{{key}}`, passes through `null`/plain text unchanged.
