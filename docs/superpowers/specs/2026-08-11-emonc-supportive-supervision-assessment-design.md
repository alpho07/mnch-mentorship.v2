# EmONC Post-Training Supportive Supervision Assessment — Design

**Date:** 2026-08-11
**Status:** Approved for planning

## 1. Background

CHAI currently runs a "POST EmONC TRAINING SUPPORTIVE SUPERVISION" survey on an
external REDCap instance
(`https://redcap.chaiportal.org/redcap/surveys/?s=8D37MFPRDMM9DXLD`). It assesses
how facilities apply EmONC training in practice: facility readiness,
commodities, emergency kits, referral systems, infection prevention, and
gaps/success stories, producing a per-section and overall score.

Goal: port this survey into the MNCH platform's existing facility-assessment
engine (`AssessmentType` → `AssessmentSection` → `AssessmentQuestion`, scored
by `DynamicScoringService`, rendered by `DynamicFormBuilder`) instead of
REDCap, and introduce a category concept so assessments can be organized as
**EmONC**, **Newborn, Infant & Child**, etc. at creation time.

The full REDCap survey was captured via a headless-browser read of the live
public form (no data submitted) on 2026-08-11 and is transcribed in full in
§7 (Question Inventory) — that transcription is the source of truth for the
implementation seeder.

## 2. Goals

- Assessors can create a new assessment, pick a **category** (EmONC / Newborn,
  Infant & Child / General Facility Readiness / …), then pick the specific
  **template** (`AssessmentType`) within that category — sections/questions
  load automatically exactly as today.
- The EmONC survey's ~232 fields, scoring, and structure are faithfully
  represented as one new `AssessmentType` with ~9 sections and their
  questions — no new database tables for content (categories table is the one
  exception — see §3).
- Section/overall scoring, the assessment dashboard, PDF/CSV export, team
  management, and locking all work for the new template with **zero
  type-specific code** — the same generic machinery that runs the existing
  "Standard Facility Assessment" today.
- The dynamic engine gains two small, additive capabilities (grouped question
  display, all-or-nothing composite scoring) that are reusable by any future
  template, not EmONC-specific hacks.

## 3. Data Model Changes

### New table: `assessment_type_categories`
```
id
name                 -- "EmONC", "Newborn, Infant & Child", "General Facility Readiness"
description          nullable
order                integer, default 0
is_active            boolean, default true
timestamps
```
Model: `AssessmentTypeCategory`. Named `assessment_type_categories` /
`AssessmentTypeCategory` rather than the more obvious `assessment_categories`
/ `AssessmentCategory` — that name is **already taken** by an existing,
unrelated model (`app/Models/AssessmentCategory.php`, table
`assessment_categories`): a mentorship-curriculum concept for training
pre-test/post-test categories (`category_type`, `training_assessment_categories`
pivot, `MenteeAssessmentResult`), nothing to do with facility assessments.
Reusing that name would collide.

Also deliberately **not** the existing curriculum `programs` table — that
table's boundaries are set by mentorship/training curriculum (e.g. "Newborn
Care" and "Infant and Child Care" are two separate programs there), which
don't line up 1:1 with how assessments should be grouped. Kept as its own
small lookup table scoped only to assessment templates, per the earlier
decision.

### `assessment_types` — add column
```
category_id  FK -> assessment_type_categories, required going forward
```
Backfill migration sets the existing "Standard Facility Assessment" type's
`category_id` to the new "General Facility Readiness" category so every
template has a category (matches the pattern of the existing
`backfill_standard_facility_assessment_type` migration).

### No other schema changes
`assessment_questions.question_type` and `.group` are already free-form
`VARCHAR` columns (widened in `2026_08_01_165817_...`), so the new
`group_completeness` question type and the group-label rendering (§5) need no
migration. `requires_explanation_on`, `scoring_map`, `options` are all
already-existing JSON columns, sufficient for every field in the survey.

## 4. Content Structure

New `AssessmentType`: **"EmONC Post-Training Supportive Supervision Survey"**,
category = EmONC, version `1.0`.

| Code | Name | Kind | Scored? | Questions |
|---|---|---|---|---|
| `emonc_facility_context` | A. Facility Profile | dynamic_questions | No | ~29 |
| `emonc_feedback` | B. Feedback to Office & Colleagues | dynamic_questions | Yes | 7 |
| `emonc_capacity_building` | C. Capacity Building | dynamic_questions | Yes | 2 |
| `emonc_key_commodities` | D. Key Commodities | dynamic_questions | Yes | 27 |
| `emonc_emergency_kits` | E. Emergency Preparedness — Kits & SOPs | dynamic_questions | Yes | ~106 |
| `emonc_referrals` | F. Referral Systems | dynamic_questions | Yes | 19 |
| `emonc_ipc` | G. Infection Prevention Control | dynamic_questions | Yes | 6 |
| `emonc_gaps_success` | H. Gaps & Success Stories | dynamic_questions | No | 35 |
| `emonc_notes` | J. Additional Notes | dynamic_questions | No | 1 |

Deliberate simplifications from the REDCap source:
- **Facility identity fields dropped** (name, MFL code, county, subcounty,
  facility level, ownership, lat/long, person-in-charge name/contact) — all
  already exist on the `Facility` model and are shown from `facility_id`
  (the same pattern the existing engine already uses for the `facility_profile`
  informational section).
- **Assessment date** — already the existing `assessments.assessment_date`
  column, not re-asked.
- **"I. Final Scores"** section is not ported as content — it's a pure
  rollup, and `DynamicScoringService` + the assessment dashboard already
  compute per-section and overall scores/percentages/grades generically.

## 5. Engine Enhancements (additive only)

Both changes are strictly additive — verified against current usage that
neither `group` (unused anywhere today) nor any code path assumes
`question_type` is a closed set (already proven false by the existing
`mortality_three_month` type). Existing templates/sections/questions get
zero behavior change from either.

### 5.1 Grouped question display (`DynamicFormBuilder`)
Section E has 6 kits × many sub-items; presenting ~106 questions as a flat
list is unusable. `DynamicFormBuilder` will watch for `group` changing
between consecutive ordered questions in a section and wrap same-group runs
in a `Forms\Components\Fieldset` labeled with the group name (e.g. "1.
Obstetric Hemorrhage Kit"). Each question within still goes through the
exact same per-type field builder as today. When `group` is null (every
existing question, today), no fieldset wraps anything — identical rendering
to current behavior.

### 5.2 `group_completeness` question type
REDCap auto-computes a binary "Kit Completeness" per kit (1 iff every item in
the kit = Yes, else 0). Rather than hardcode this for EmONC, add a generic,
reusable composite scoring primitive:

- A question with `question_type = 'group_completeness'` and `group = "<Kit
  Name>"` is not user-answerable.
- In `DynamicFormBuilder`, it renders as a disabled `Forms\Components\Placeholder`
  showing a live-computed "✓ Complete" / "✗ Incomplete (n/m)" based on
  current sibling answers.
- In `DynamicScoringService::recalculateSectionScore()`, a new first step
  finds active `group_completeness` questions in the section and, for each,
  upserts its `AssessmentQuestionResponse` (`response_value` Yes/No, `score`
  1/0) based on whether every sibling question sharing its `group` (active,
  scored, in the same section) currently has a response scored at that
  question's max possible value. This step is a no-op for any section with
  no `group_completeness` question — i.e. every section that exists today.
  The existing sum/percentage/grade logic that follows is unmodified.
- Reusable by any future template that needs "all sub-items present" scoring
  — not special-cased to EmONC kit names.

Section E gets one `group_completeness` question per kit (6 total, folded
into the ~106 question count above), positioned last within its kit's group.

## 6. UI Changes

- `CreateAssessment` form: new **Category** select (`assessment_type_categories`,
  active + ordered) placed above the existing **Assessment (template)**
  select. Selecting a category filters the template select's options to that
  category (`assessmentType.category_id`). Category is not stored on the
  `Assessment` row — it's implied via `assessmentType.category`.
- `ListAssessments`: new category column + filter, alongside the existing
  template filter.
- `AssessmentDashboard`: category shown alongside template name in the
  header; no other change — section navigation/progress/scoring already work
  generically for any template.
- No change to `EditSection`, `ViewAssessmentSummary`,
  `AssessmentPdfReportService`, `AssessmentExportService`, team management,
  or locking — all already template-agnostic.

## 7. Build Mechanism

A new idempotent seeder, `EmoncSupportiveSupervisionSeeder` (follows the
`updateOrCreate`-throughout convention of the existing
`AssessmentQuestionConfigSeeder`), creates:
1. The 3 initial `assessment_type_categories` rows (EmONC, Newborn/Infant/Child,
   General Facility Readiness).
2. A migration backfilling `category_id = General Facility Readiness` on the
   existing "Standard Facility Assessment" type.
3. The new `AssessmentType`, its 9 `AssessmentSection` rows, and all ~232
   `AssessmentQuestion` rows (content transcribed in §8), from a structured
   PHP array in the seeder itself.

Run once via `php artisan db:seed --class=EmoncSupportiveSupervisionSeeder`.
Safe to re-run (no duplicate rows, updates existing ones by unique code).

## 8. Question Inventory (source of truth for the seeder)

Transcribed from the live REDCap survey on 2026-08-11. All yes/no items use
`scoring_map: {"Yes": 1, "No": 0}` and `requires_explanation_on: ["Yes",
"No"]` (remarks always visible, matching the source form) unless noted.

### A. Facility Profile (not scored)
- Facility Category — select, options: CEMONC, BEMONC
- Supervisor 1/2/3 — Name (text), Title (text) — 3 pairs
- Facility Supervision Respondent — Name, Contact, Cadre (text)
- Human Resources in Maternity Unit — for each of Nurses / Clinical Officers /
  Medical Officers / Obstetricians: Number Allocated in Maternity (number),
  Number Trained on 5-day EmONC from 2024 to date (number), Number present in
  a 24hr shift (number) — 12 fields
- Number of EmONC-trained healthcare workers (number)
- Distribution of EmONC-trained healthcare workers per department: ANC, HRC,
  L/W, NBU, ANW, PNW (number × 6)

### B. Feedback to Office & Colleagues (scored)
- Feedback meeting to office held — yes/no, **scored**
- Action Plan 1/2/3 — Action Plan description (text, not scored), Status
  (select: Resolved / In Progress / Not Addressed, not scored;
  `requires_explanation_on: ["Resolved", "In Progress", "Not Addressed"]` so
  remarks are always visible, matching the source form's always-shown Remarks
  column — same "always visible" intent as the yes/no default above, just
  spelled out with this field's own option values instead of Yes/No)

### C. Capacity Building (scored)
- CMEs held — yes/no, scored, help text "Confirm using the CME
  register/booklet"
- Drills held — yes/no, scored, same help text

### D. Key Commodities (scored, 27 items, all yes/no, "available and
functional", scope = maternity department, one-month caseload)
1. Assorted IV cannulas/branulas
2. Assorted disposable syringes with needles
3. Elbow gloves/gynaecological gloves
4. Sterile surgical gloves
5. Assorted suture material
6. Blood pressure measurement equipment (Digital BP machine or
   sphygmomanometer + stethoscope)
7. Delivery Kit (5 Green towels, 1 Tray 10×14, 2 straight artery forceps 8",
   cord scissors, episiotomy scissors, 2 needle holders 7", 2 large kidney
   dishes 10", cord clamps, 1 Gallipot — randomly check 1 kit for contents)
8. Ambu bag (280ml) with neonatal pre-term (size 0) masks
9. Ambu bag (280ml) with neonatal term (size 1) masks
10. Ambu bag (1.5L) with adult masks
11. Fetoscope / handheld fetal heart monitor / digital fetoscope
12. Portable examination lamp
13. Assorted speculums (small/medium/large)
14. Functional suction machines and catheters or penguin suction
15. Functional Infant Resuscitation Unit / Radiant Warmer / Resuscitaire
16. Oxygen set (portable cylinder or central wall supply with mask/nasal
    cannula + flow meter) or concentrator
17. Patella hammer
18. Thermometer
19. Non-Pneumatic Antishock Garment (NASG)
20. Oropharyngeal airway for adults
21. Urine strips (proteinuria and sugar dip sticks) in labour ward and lab
22. Functioning refrigerator for cold-chain drugs/lab reagents, powered 24/7
    (excludes KEPI fridges)
23. Blood/blood products currently stored with blood-giving/transfusion sets
24. Haemoglobin meter with reagents
25. Blood grouping & cross-matching kit (water bath, centrifuge, reagents,
    cold-chain blood carriers)
26. Functioning refrigerator available for storing blood, powered 24/7
27. IV fluids assorted (Normal saline / Ringer's lactate / Half-strength
    Darrow's) with IV administration set

### E. Emergency Preparedness — Kits & SOPs (scored)
Each kit: a parent "kit available" yes/no, then its listed sub-items
(yes/no, `group` = kit name), then one `group_completeness` question.

1. **Obstetric Hemorrhage Kit** — Large bore cannulas, Oxytocin, Tranexamic
   acid, Misoprostol, Balloon tamponade (UBT or condom), IV fluids, Giving
   sets, 2-way Foleys catheters, Gynecological gloves, Specimen bottles,
   NASG, Blood loss monitoring chart, Calibrated drapes, MEOWS chart
2. **Neonatal Resuscitation Kit** — Resuscitation table with radiant warmer,
   Ambu bag (280ml, neonatal pre-term size 1/0), Penguin sucker, Oral
   pharyngeal airway, Oxygen source, Non-rebreather mask, Suction catheter
   size 8 (preterm), Suction catheter size 10 (all), Suction catheter size 12
   (meconium), Assorted syringes & needles, Cannulas, Pulse oximeter,
   Stethoscope, Thermal blanket/plastic wrap for preterm, Cap to prevent heat
   loss, Dextrose solution (50%), Adrenalin injection, Neonatal nasal prongs
3. **PET/Eclampsia Kit** — Magnesium sulphate 50% (3 ampoules), Calcium
   gluconate, Patella hammer, 20cc syringes, 10cc syringes, Labetalol (oral
   and injectable), Methyldopa, Nifedipine, Inj. hydralazine, Water for
   injection, Inj. lignocaine 2%, 2-way Foleys catheter, Urine bag, Cannulas,
   Specimen bottles, Gloves, Nasal prongs, Magnesium Sulphate Toxicity
   Monitoring Chart
4. **Maternal Resuscitation Kit** — Ambu bag (1.5L, adult), Oropharyngeal
   airway (different sizes), Foleys catheter with urine bag, Oxygen tubing &
   mask (NRM), IV fluids, Large bore cannulas, Specimen bottles, NASG,
   Patella hammer, Fetoscope, Stethoscope, BP machine, Thermometer, Blood
   loss monitoring chart
5. **Delivery Kit** — 6 green towels, 1 Tray 10×14, 2 straight artery
   forceps 8", Cord scissors, Episiotomy scissors, 2 needle holders 7", 2
   large kidney dishes 10", Cord clamps, 1 Gallipot, Sims speculum
   (S/M/L), Cusco speculum (S/M/L)
6. **Assisted Vacuum Delivery Kit (AVD/Kiwi kit)** — Vacuum extractor (Omni
   Cap/Pro Cap), Syringes, Needles, Foleys catheter, Fetoscope, V-drape,
   Lubricant (e.g. K-Y jelly)

**SOPs / Job Aids** (own group, 12 yes/no items, help text: "Confirm
physically that the job aids listed are available — laminated charts, wall
charts, posters, leaflets — appropriately placed in a visible location"):
EMOTIVE, PET/Eclampsia, Breech Delivery, Shoulder Dystocia, Maternal
Resuscitation, Neonatal Resuscitation, Maternal Shock, PPH, NASG
Application, Assisted Vacuum Delivery, Heat Stable Carbetocin, AMTSL Job Aid

### F. Referral Systems (scored)
- Notified of/notified a referral before patient arrival — yes/no, scored
- Maternity unit has access to a functional phone — yes/no, scored
- Access to ambulance services 24/7 for maternity referrals — yes/no, scored
- Most recent referral accompanied by skilled health personnel — yes/no,
  scored
- Monthly referrals out, Jan 2025 – Mar 2026 (15 number fields, not scored)
- Help text: "Confirm using the referral form or referral register, where
  available"

### G. Infection Prevention Control (scored, 6 yes/no items)
Clean running water/soap; Waste segregated (color-coded bins/liners);
Antiseptic available; Alcohol hand rubs available; Disinfectants available;
Functional sterilization facility.

### H. Gaps & Success Stories (not scored)
- Key gaps/recommendations — 5 rows × (Gap, Action, Who, When) — all text
- Success stories — 5 rows × (What happened, How it was achieved, Impact on
  patient care) — all text

### J. Additional Notes (not scored)
- Additional comments — text, optional

## 9. Testing Plan

- Feature test: seeder creates the category, type, sections, and correct
  question counts per section; re-running is idempotent (no duplicate rows,
  no changed IDs).
- Feature test: `CreateAssessment` — category select filters template
  options correctly; creating an EmONC assessment initializes
  `section_progress` for all 9 new sections.
- Feature test: filling Section E — grouped fieldsets render per kit;
  `group_completeness` question is read-only in the form; completing all
  items in one kit's group yields a stored `Yes`/score `1` completeness
  response, leaving one item unanswered yields `No`/`0`.
- Feature test: `DynamicScoringService` — existing "Standard Facility
  Assessment" section/overall scores are byte-identical before/after this
  change (regression guard for the additive claim in §5).
- Feature test: PDF/CSV export and `ViewAssessmentSummary` render an EmONC
  assessment without error (generic path, no EmONC-specific code expected).

## 10. Open Items For The Implementation Plan
- Exact Filament icon/color choices per section (cosmetic, low-risk, decide
  during implementation).
- Whether `assessment_type_categories` needs its own Filament resource for
  admin CRUD, or is seed-only for now (lean towards seed-only + a simple
  read-only list, since only 3 rows exist initially).
