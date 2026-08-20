# EmONC Module 2 (Labour Care Guide) — Section-Based Mentee/Mentor Content

Date: 2026-08-20

## Background

`Program: Maternal Health (EmONC)` → `ProgramModule#52: Module 2: Labour Care Guide (LCG)` currently has
generic intro/outcome content plus a "Grace" case-scenario simulation (`case_scenario` #36,
`case_scenario_progression` #37). We are replacing the case-scenario content with material sourced from
`LCG webinar presentation 2.pptx` (30 slides), which walks through the WHO Labour Care Guide in 7
numbered sections, each pairing:

- **Theory slides** — WHO recommendations, alert-code definitions, and reference figures (screenshots of
  the LCG form annotated per section).
- **One "Section N - Practice" slide** — a running case narrative for a patient, "Mary Jane," applying
  that section's content.

Content split:
- **Mentee-facing** = only the "Practice" narrative text, per section.
- **Mentor-facing** = everything else (theory text + all reference figures), per section.

## Data model

No schema migration is required. `program_module_contents.type` already distinguishes audiences via
`ProgramModuleContent::audience()` (`mentor_`-prefixed = mentor-only, everything else = mentee-facing):

- Mentee rows: `type = case_scenario`, one per section, `title = "Section N — Practice"` (section 6 & 7
  share one slide in the source, so one combined row), `order_sequence = 1..6`.
- Mentor rows: `type = mentor_materials`, one per section (`order_sequence = 1..7`) plus one appendix row
  ("Sample Completed LCG (Reference)", `order_sequence = 8`).

Existing `introduction` (#34, #35) and `expected_learning_outcome` (#38) rows are untouched — they are
module-level orientation, not section-specific.

## Content mapping (source: PPTX slides, verified by direct extraction)

| Section | Mentee practice row (case_scenario) | Mentor materials row (mentor_materials) | Figures |
|---|---|---|---|
| 1 — Admission | Mary Jane's presenting history (date, GA, parity, history of stillbirth/miscarriage, anaemia, 5cm at 06:00) | No routine pelvimetry; care decisions based on risk factors | image27, image28 ("Fig. 3: How to complete Section 1") |
| 2 — Supportive care | Companion/pain relief/fluids/posture at 06:00 and 07:00 | Companionship/Pain relief/Oral fluid/Posture Y/N/D codes — "all new" | image29, image30 |
| 3 — Well-being of the baby | FHR/decelerations/VE findings at 06:00 and 07:00 | FHR & decel, fetal position, caput (new); WHO 2018 auscultation guidance; amniotic-fluid & moulding alert codes; fetal-position & caput alert codes | image31, image32, image33 |
| 4 — Well-being of the woman | Pulse/BP/temp/urinalysis at 06:00 | Pulse/BP recorded not plotted; recording frequency depends on clinical status | image34, image35 |
| 5 — Labour progress | Contractions/VE/descent at 06:00 and 07:00 | First-stage duration & dilatation-rate guidance, alert-line triage use; second-stage parameters (contractions, pushing, descent); WHO 2nd-stage guidance; phases of 2nd stage | image36, image37, image38, image39, image40 |
| 6 — Medications | (combined with 7 below) | Oxytocin/Medicine/IV-fluid assess-and-record steps | image41 |
| 7 — Shared decision-making | (combined with 6 above, one row: "Sections 6 & 7 — Practice" — no medication given; make an assessment and plan) | Making an assessment; types of decision-making (paternalistic / informed / shared); develop & record plan of care; record initials | image42, image43, image44 |
| Appendix | — | "Sample Completed LCG (Reference)" — fully completed Mary Jane form | image45 |

Exact copy for each row is drafted directly from the extracted slide text (see seeder below); WHO
recommendation quotes are preserved verbatim where the slide quotes WHO 2018/2022 guidance.

## Images

13 distinct figures (image27–image45, excluding duplicates reused across slides) are extracted once from
the PPTX now and committed to the repo as static assets — the seeder does not depend on the PPTX at
deploy/runtime:

- `database/seeders/assets/emonc-module2-lcg/section-1-fig-*.png`, `section-2-fig-*.png`, ... named by
  section and content (not raw `imageNN`), so the mapping is self-documenting in the repo.
- Seeder copies them into `storage/app/public/program-module-content/emonc-module-2-lcg/` via
  `Storage::disk('public')->put()` (idempotent — checks existence, matching how `FileUpload`-managed
  video assets are handled elsewhere), and references them in Markdown as
  `![alt](/storage/program-module-content/emonc-module-2-lcg/<file>.png)`, matching how existing content
  rows are rendered (`Str::markdown($content)`).

## Code change: mentor page must render multiple `mentor_materials` rows

`ReviewModuleMentee.php` (`getViewData()`) currently does:
```php
'mentorMaterials' => $contents->where('type', 'mentor_materials')->first(),
```
This only ever shows one row. Change to pass the full ordered collection:
```php
'mentorMaterials' => $contents->where('type', 'mentor_materials')->sortBy('order_sequence')->values(),
```
`mentor_course_intro` stays `.first()` (no per-section intro content is being introduced).

`resources/views/filament/pages/review-module-mentee.blade.php` (~line 251-256): replace the single
`@if($mentorMaterials)` block with a `@foreach($mentorMaterials as $material)` loop, mirroring the
existing `@foreach($caseScenarios as $scenario)` pattern on the mentee page — each iteration renders its
own titled `<div>` with `{!! Str::markdown($material->content) !!}` (Markdown images resolve to `<img>`
tags automatically, no template change needed for images).

## Seeder

New file `database/seeders/EmoncLcgModule2SectionContentSeeder.php`:
1. Locate `Program::where('name', 'Maternal Health (EmONC)')` and
   `ProgramModule::where('name', 'like', '%Labour Care Guide%')->whereNull('parent_id')`.
2. Delete the existing `case_scenario` (#36) and `case_scenario_progression` (#37) rows for this module
   (`->delete()`, not soft-delete — `ProgramModuleContent` has no `SoftDeletes`).
3. Copy the 13 committed image assets into `storage/app/public/...` if not already present.
4. `updateOrCreate` (keyed on `program_module_id` + `type` + `title`, matching existing seeder
   conventions) the 6 mentee `case_scenario` rows and 8 mentor `mentor_materials` rows described above.
5. Wrap in `DB::transaction()`; log a summary via `$this->command->info()`.

Registered as a new call in `DatabaseSeeder::run()` alongside the other `Emonc*Seeder` calls (not inside
`AphModuleContentSeeder`/`EmoncBatchAContentSeeder` etc. — this is module-2-specific, matching the
one-seeder-per-concern convention already used, e.g. `EmoncAphIntroContentSeeder`,
`EmoncPphModuleContentSeeder`).

Safe to re-run in production: `updateOrCreate` and existence-checked file copy make the seeder
idempotent; the one destructive step (deleting the old Grace scenario rows) is also idempotent since a
second run simply finds nothing to delete.

## Testing

New `tests/Feature/EmoncLcgModule2SectionContentSeederTest.php`:
- Running the seeder creates exactly 6 `case_scenario` rows (titled `Section N — Practice` /
  `Sections 6 & 7 — Practice`) and 8 `mentor_materials` rows for module 52.
- The old Grace `case_scenario`/`case_scenario_progression` rows (by title) are gone after seeding.
- Running the seeder twice does not duplicate rows (idempotency).
- `ProgramModuleContent::audience()` still correctly reports `mentor` for the new `mentor_materials`
  rows and `mentee` for the new `case_scenario` rows (regression guard on the existing model contract).

Manual verification: run `php artisan migrate:fresh --seed` (or run the seeder standalone) locally, then
view the module both as a mentee (`resources/views/mentee/module-detail.blade.php` — case scenario
section should show 6 "Practice" blocks) and as a mentor reviewing a mentee
(`ReviewModuleMentee`/`review-module-mentee.blade.php` — should show 8 mentor-materials sections with
images rendering correctly).

## Out of scope

- No changes to `ContentsRelationManager` (admin CRUD for content) — it already supports
  `mentor_materials`/`case_scenario` types and `RichEditor` content editing; existing rows created by this
  seeder remain editable there.
- No changes to `introduction`, `expected_learning_outcome`, `video`, or the module's `objectives`/
  `content` (workplan) fields.
- No OCR/re-authoring of the LCG form tables as structured data — figures are embedded as images, not
  reconstructed as HTML tables.
