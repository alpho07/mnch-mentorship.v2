# EmONC Module 2 (Labour Care Guide) Section Content Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Module 2's ("Labour Care Guide (LCG)") mentee case-scenario content with 7 section-based "Practice" narratives extracted from `LCG webinar presentation 2.pptx`, and add matching mentor-only theory + figures per section, without any schema migration.

**Architecture:** A single idempotent seeder (`EmoncLcgModule2SectionContentSeeder`) deletes the old "Grace" case-scenario rows and `updateOrCreate`s new `case_scenario` (mentee) and `mentor_materials` (mentor) rows on `ProgramModule#52`, copying 19 image assets (committed to the repo, extracted from the PPTX ahead of time) into public storage and embedding them as Markdown images. A small, isolated code fix makes the mentor review page loop over all `mentor_materials` rows instead of taking only the first one, mirroring the pattern the mentee page already uses for `case_scenario`.

**Tech Stack:** Laravel 12 seeder + Eloquent (`ProgramModuleContent`), Laravel `Storage` facade (`public` disk), Filament page (`ReviewModuleMentee`), Blade (`Str::markdown()`), PHPUnit `RefreshDatabase` feature test.

**Spec:** `docs/superpowers/specs/2026-08-20-emonc-module2-lcg-section-content-design.md`

## Global Constraints

- No database migration — reuse existing `program_module_contents.type` values `case_scenario` (mentee) and `mentor_materials` (mentor).
- Target only `ProgramModule` where `program_id` = the "Maternal Health (EmONC)" program's id, `name like '%Labour Care Guide%'`, `parent_id` is null (module 2).
- Seeder must be safe to re-run on production: use `updateOrCreate` keyed on `(program_module_id, type, title)`, and check-before-copy for image files.
- Delete only the two specific old rows by exact title (`LCG Simulation Drill — Case Scenario`, `LCG Simulation Drill — Scenario Progression`) — never a blanket delete of all `case_scenario` rows for the module.
- Images are committed to the repo under `database/seeders/assets/emonc-module2-lcg/` and copied into `storage/app/public/program-module-content/emonc-module-2-lcg/` at seed time via `Storage::disk('public')`.
- Content strings are Markdown (rendered via `Str::markdown()` in both the mentee and mentor blades) — not Filament `RichEditor` HTML.

---

## File Structure

- Create: `database/seeders/assets/emonc-module2-lcg/*.png` (19 image files extracted from the PPTX)
- Create: `database/seeders/EmoncLcgModule2SectionContentSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (register the new seeder)
- Create: `tests/Feature/EmoncLcgModule2SectionContentSeederTest.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ReviewModuleMentee.php` (`mentorMaterials` → collection)
- Modify: `resources/views/filament/pages/review-module-mentee.blade.php` (loop over `$mentorMaterials`)

---

### Task 1: Extract and commit the PPTX figures as repo assets

**Files:**
- Create: `database/seeders/assets/emonc-module2-lcg/section-1-fig-a.png` … `appendix-sample-completed-lcg.png` (19 files, see mapping below)

**Interfaces:**
- Produces: 19 PNG files at fixed, descriptive paths under `database/seeders/assets/emonc-module2-lcg/`, consumed by Task 2's seeder via `database_path('seeders/assets/emonc-module2-lcg/<file>.png')`.

This task has no application code — it is a one-time extraction of images embedded in
`LCG webinar presentation 2.pptx` (already present at the repo root) into committed files, using the
system Python (`python3` at `/c/Users/ALPHY/anaconda3/python.exe` on this machine, which has `zipfile`
and `xml.etree.ElementTree` from the standard library — no extra packages needed for this task).

- [ ] **Step 1: Write the extraction script**

Create a throwaway script (not committed) at, e.g.,
`C:\Users\ALPHY\AppData\Local\Temp\claude\extract_lcg_images.py`:

```python
import zipfile
import shutil
import os

PPTX = 'LCG webinar presentation 2.pptx'
OUT_DIR = os.path.join('database', 'seeders', 'assets', 'emonc-module2-lcg')

# media filename (in ppt/media/) -> repo filename
MAPPING = {
    'image27.png': 'section-1-fig-a.png',
    'image28.png': 'section-1-fig-b.png',
    'image29.png': 'section-2-fig-a.png',
    'image30.png': 'section-2-fig-b.png',
    'image31.png': 'section-3-fig-a.png',
    'image32.png': 'section-3-fig-b.png',
    'image33.png': 'section-3-fig-c.png',
    'image34.png': 'section-4-fig-a.png',
    'image35.png': 'section-4-fig-b.png',
    'image36.png': 'section-5-fig-a.png',
    'image37.png': 'section-5-fig-b.png',
    'image38.png': 'section-5-fig-c.png',
    'image39.png': 'section-5-fig-d.png',
    'image40.png': 'section-5-fig-e.png',
    'image41.png': 'section-6-fig-a.png',
    'image42.png': 'section-7-fig-a.png',
    'image43.png': 'section-7-fig-b.png',
    'image44.png': 'section-7-fig-c.png',
    'image45.png': 'appendix-sample-completed-lcg.png',
}

os.makedirs(OUT_DIR, exist_ok=True)

z = zipfile.ZipFile(PPTX)
for src, dest in MAPPING.items():
    with z.open(f'ppt/media/{src}') as fh:
        data = fh.read()
    out_path = os.path.join(OUT_DIR, dest)
    with open(out_path, 'wb') as out:
        out.write(data)
    print(f'{dest}: {len(data)} bytes')

print(f'Extracted {len(MAPPING)} images to {OUT_DIR}')
```

- [ ] **Step 2: Run the script from the repo root**

Run: `"/c/Users/ALPHY/anaconda3/python.exe" "C:\Users\ALPHY\AppData\Local\Temp\claude\extract_lcg_images.py"` (run with cwd = repo root, e.g. via `cd "/c/xampp/htdocs/MNCH-Master.v2" && ...`)

Expected: prints 19 lines like `section-1-fig-a.png: 21019 bytes`, then `Extracted 19 images to database/seeders/assets/emonc-module2-lcg`.

- [ ] **Step 3: Verify all 19 files exist and are non-empty**

Run: `ls -la "database/seeders/assets/emonc-module2-lcg/" | wc -l` (expect 19 data files, i.e. 20+2 lines counting `.`/`..`/total)

Expected: 19 `.png` files present, none 0 bytes.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/assets/emonc-module2-lcg/
git commit -m "chore: add EmONC Module 2 LCG figures extracted from LCG webinar PPTX"
```

---

### Task 2: Write `EmoncLcgModule2SectionContentSeeder`

**Files:**
- Create: `database/seeders/EmoncLcgModule2SectionContentSeeder.php`
- Test: `tests/Feature/EmoncLcgModule2SectionContentSeederTest.php`

**Interfaces:**
- Consumes: `App\Models\Program`, `App\Models\ProgramModule`, `App\Models\ProgramModuleContent` (existing models, no changes); `Illuminate\Support\Facades\Storage::disk('public')`; the 19 files from Task 1 at `database_path('seeders/assets/emonc-module2-lcg/<file>.png')`.
- Produces: for `ProgramModule` "Module 2: Labour Care Guide (LCG)" (`program_id` = "Maternal Health (EmONC)"'s id, `parent_id` null) — 6 `ProgramModuleContent` rows of `type = 'case_scenario'` and 8 rows of `type = 'mentor_materials'`; deletes the 2 rows titled `LCG Simulation Drill — Case Scenario` / `LCG Simulation Drill — Scenario Progression`. Class name `Database\Seeders\EmoncLcgModule2SectionContentSeeder`, single public method `run(): void`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/EmoncLcgModule2SectionContentSeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use Database\Seeders\EmoncBatchAContentSeeder;
use Database\Seeders\EmoncLcgModule2SectionContentSeeder;
use Database\Seeders\EmoncProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmoncLcgModule2SectionContentSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedBaseline(): ProgramModule
    {
        Storage::fake('public');

        $this->seed(EmoncProgramSeeder::class);
        $this->seed(EmoncBatchAContentSeeder::class);

        return ProgramModule::where('name', 'like', '%Labour Care Guide%')
            ->whereNull('parent_id')
            ->firstOrFail();
    }

    public function test_seeder_replaces_grace_scenario_with_seven_section_practice_rows(): void
    {
        $module = $this->seedBaseline();

        $this->assertTrue(
            ProgramModuleContent::where('program_module_id', $module->id)
                ->where('title', 'LCG Simulation Drill — Case Scenario')
                ->exists()
        );

        $this->seed(EmoncLcgModule2SectionContentSeeder::class);

        $this->assertFalse(
            ProgramModuleContent::where('program_module_id', $module->id)
                ->whereIn('title', [
                    'LCG Simulation Drill — Case Scenario',
                    'LCG Simulation Drill — Scenario Progression',
                ])
                ->exists()
        );

        $practiceRows = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'case_scenario')
            ->orderBy('order_sequence')
            ->get();

        $this->assertCount(6, $practiceRows);
        $this->assertSame('Section 1 — Practice', $practiceRows[0]->title);
        $this->assertSame('Sections 6 & 7 — Practice', $practiceRows[5]->title);
        $this->assertStringContainsString('Mary Jane', $practiceRows[0]->content);
    }

    public function test_seeder_creates_eight_mentor_materials_rows_with_images(): void
    {
        $module = $this->seedBaseline();

        $this->seed(EmoncLcgModule2SectionContentSeeder::class);

        $mentorRows = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'mentor_materials')
            ->orderBy('order_sequence')
            ->get();

        $this->assertCount(8, $mentorRows);
        $this->assertSame('Section 1 — Admission: Identifying Information and Labour Characteristics', $mentorRows[0]->title);
        $this->assertSame('Sample Completed LCG (Reference)', $mentorRows[7]->title);
        $this->assertStringContainsString('program-module-content/emonc-module-2-lcg/section-1-fig-a.png', $mentorRows[0]->content);

        Storage::disk('public')->assertExists('program-module-content/emonc-module-2-lcg/section-1-fig-a.png');
        Storage::disk('public')->assertExists('program-module-content/emonc-module-2-lcg/appendix-sample-completed-lcg.png');
    }

    public function test_content_type_audience_is_correct(): void
    {
        $module = $this->seedBaseline();
        $this->seed(EmoncLcgModule2SectionContentSeeder::class);

        $practiceRow = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'case_scenario')
            ->firstOrFail();
        $mentorRow = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'mentor_materials')
            ->firstOrFail();

        $this->assertSame('mentee', $practiceRow->audience());
        $this->assertSame('mentor', $mentorRow->audience());
    }

    public function test_seeder_is_idempotent(): void
    {
        $module = $this->seedBaseline();

        $this->seed(EmoncLcgModule2SectionContentSeeder::class);
        $countBefore = ProgramModuleContent::where('program_module_id', $module->id)->count();

        $this->seed(EmoncLcgModule2SectionContentSeeder::class);
        $countAfter = ProgramModuleContent::where('program_module_id', $module->id)->count();

        $this->assertSame($countBefore, $countAfter);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmoncLcgModule2SectionContentSeederTest`
Expected: FAIL — `Class "Database\Seeders\EmoncLcgModule2SectionContentSeeder" not found`.

- [ ] **Step 3: Write the seeder**

Create `database/seeders/EmoncLcgModule2SectionContentSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Replaces Module 2's ("Labour Care Guide") mentee case-scenario content with 7
 * section-based "Practice" narratives (patient "Mary Jane") and adds matching
 * mentor-only theory + figures per section, sourced from
 * "LCG webinar presentation 2.pptx". See
 * docs/superpowers/specs/2026-08-20-emonc-module2-lcg-section-content-design.md.
 */
class EmoncLcgModule2SectionContentSeeder extends Seeder
{
    private const ASSET_DIR = 'program-module-content/emonc-module-2-lcg';

    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $module = ProgramModule::where('program_id', $program->id)
                ->where('name', 'like', '%Labour Care Guide%')
                ->whereNull('parent_id')
                ->firstOrFail();

            ProgramModuleContent::where('program_module_id', $module->id)
                ->whereIn('title', [
                    'LCG Simulation Drill — Case Scenario',
                    'LCG Simulation Drill — Scenario Progression',
                ])
                ->delete();

            $img = $this->publishImages();

            $this->seedMenteePracticeRows($module, $img);
            $this->seedMentorMaterialsRows($module, $img);
        });

        $this->command->info('EmONC Module 2 (LCG) section-based mentee/mentor content seeded successfully.');
    }

    /**
     * Copy the committed figures into public storage (skips files that already
     * exist so the seeder stays idempotent) and return a map of
     * filename => public URL.
     */
    private function publishImages(): array
    {
        $files = [
            'section-1-fig-a.png', 'section-1-fig-b.png',
            'section-2-fig-a.png', 'section-2-fig-b.png',
            'section-3-fig-a.png', 'section-3-fig-b.png', 'section-3-fig-c.png',
            'section-4-fig-a.png', 'section-4-fig-b.png',
            'section-5-fig-a.png', 'section-5-fig-b.png', 'section-5-fig-c.png', 'section-5-fig-d.png', 'section-5-fig-e.png',
            'section-6-fig-a.png',
            'section-7-fig-a.png', 'section-7-fig-b.png', 'section-7-fig-c.png',
            'appendix-sample-completed-lcg.png',
        ];

        $disk = Storage::disk('public');
        $urls = [];

        foreach ($files as $file) {
            $storagePath = self::ASSET_DIR.'/'.$file;

            if (! $disk->exists($storagePath)) {
                $sourcePath = database_path('seeders/assets/emonc-module2-lcg/'.$file);
                $disk->put($storagePath, file_get_contents($sourcePath));
            }

            $urls[$file] = $disk->url($storagePath);
        }

        return $urls;
    }

    private function seedMenteePracticeRows(ProgramModule $module, array $img): void
    {
        $rows = [
            [
                'title' => 'Section 1 — Practice',
                'content' => 'Date: June 07, 2020. Time: 06:00. Mary Jane presented with contractions and reports '
                    .'that she has experienced leakage of fluid from the vagina for the last hour. Her gestational '
                    .'age is 38 weeks. This is her fourth pregnancy. She previously had two births, one of a live '
                    .'baby and one of a stillbirth at term. She also had a miscarriage. She is taking oral iron to '
                    .'treat anaemia. Cervical dilatation was 5 cm at 06:00 am.',
            ],
            [
                'title' => 'Section 2 — Practice',
                'content' => "Time 06:00. Mary Jane is not accompanied by a relative or someone from her social "
                    ."network. She reports feeling significant pain due to the uterine contractions, and requests "
                    ."pain relief. She drank a fruit juice and is walking.\n\n"
                    .'Time 07:00. Mary Jane is with her sister and using relaxation techniques for pain relief with '
                    .'good results. She has been drinking water when thirsty, and Mary Jane is now lying in bed in '
                    .'a supine position.',
            ],
            [
                'title' => 'Section 3 — Practice',
                'content' => "Time 06:00. The baby moves during monitoring and has a heart rate of 140 beats per "
                    ."minute (bpm). There are no decelerations. Vaginal examination shows 5 cm cervical dilatation, "
                    ."cephalic presentation. There is no caput or moulding and the fetal position is occiput "
                    ."posterior. Amniotic fluid is clear.\n\n"
                    .'Time 07:00. FHR 132 bpm. There are variable decelerations.',
            ],
            [
                'title' => 'Section 4 — Practice',
                'content' => "Time 06:00. Mary Jane's pulse rate is 88 bpm, with blood pressure of 120/80 mmHg. "
                    .'Her temperature is 36.5°C. She passed urine at admission, without proteinuria or acetone.',
            ],
            [
                'title' => 'Section 5 — Practice',
                'content' => "Time 06:00. At the time of admission, Mary Jane presented with three uterine "
                    ."contractions every 10 minutes, lasting 40 seconds. Vaginal examination shows 5 cm cervical "
                    ."dilatation. Fetal descent is 4/5.\n\n"
                    .'Time 07:00. Three contractions in 10 minutes, lasting 40 seconds.',
            ],
            [
                'title' => 'Sections 6 & 7 — Practice',
                'content' => "Section 6: Medication. There was no medication given.\n\n"
                    .'Section 7: Shared decision-making. Make an assessment and plan based on the findings.',
            ],
        ];

        foreach ($rows as $order => $row) {
            ProgramModuleContent::updateOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => $row['title'],
                ],
                [
                    'content' => $row['content'],
                    'order_sequence' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedMentorMaterialsRows(ProgramModule $module, array $img): void
    {
        $rows = [
            [
                'title' => 'Section 1 — Admission: Identifying Information and Labour Characteristics',
                'content' => "**New recommendation:** do not conduct routine clinical pelvimetry on admission of "
                    ."healthy pregnant women in labour.\n\n"
                    ."Decisions on place of birth, level of care provision, the type of provider who should lead "
                    ."management of her care, and the frequency of monitoring should be based on risk factors and "
                    ."their potential impact on birth outcome.\n\n"
                    ."![Fig. 2: Section 1 admission fields on the WHO Labour Care Guide]({$img['section-1-fig-a.png']})\n\n"
                    ."![Fig. 3: How to complete Section 1]({$img['section-1-fig-b.png']})",
            ],
            [
                'title' => 'Section 2 — Supportive Care',
                'content' => "Supportive-care parameters, recorded Y/N/D (Yes/No/Woman declines) at each "
                    ."assessment:\n\n"
                    ."- **Companionship** — Y = Yes, N = No, D = Woman declines\n"
                    ."- **Pain relief** — Y = Yes, N = No, D = Woman declines\n"
                    ."- **Oral fluid** — Y = Yes, N = No, D = Woman declines\n"
                    ."- **Posture** — SP = Supine, MO = Mobile\n\n"
                    ."All four of these supportive-care parameters are new to the Labour Care Guide.\n\n"
                    ."![Fig. 4: Section 2 supportive-care fields]({$img['section-2-fig-a.png']})\n\n"
                    ."![Fig. 5: Section 2 supportive care, completed example]({$img['section-2-fig-b.png']})",
            ],
            [
                'title' => 'Section 3 — Well-being of the Baby',
                'content' => "Baseline fetal heart rate (FHR) and decelerations, fetal position, and caput are all "
                    ."new fields on the Labour Care Guide.\n\n"
                    ."**WHO 2018 recommendations for intrapartum care:**\n"
                    ."> \"Intermittent auscultation of the fetal heart rate with either a Doppler ultrasound or "
                    ."Pinard stethoscope is recommended for healthy pregnant women.\"\n"
                    ."> \"Continuous cardiotocography is not recommended in healthy women undergoing spontaneous "
                    ."labour.\"\n\n"
                    ."If it is difficult to assess FHR in the woman's chosen position, explain why she needs to "
                    ."change position, assist her to change position and assess FHR, then assist her back into her "
                    ."chosen position.\n\n"
                    ."**Amniotic fluid** (alert!): I = Intact membranes; C = Membranes ruptured, clear fluid; "
                    ."M = Meconium-stained fluid — record + (non-significant), ++ (medium), +++ (thick); "
                    ."B = Blood-stained meconium.\n\n"
                    ."**Moulding** (alert!): 0 = None; + = Sutures apposed; ++ = Sutures overlapped but reducible; "
                    ."+++ = Sutures overlapped and not reducible.\n\n"
                    ."**Fetal position** (alert!): A = Any occiput anterior position; P = Any occiput posterior "
                    ."position; T = Any occiput transverse position.\n\n"
                    ."**Caput** (alert!): 0 = None; + ; ++ ; +++ = Marked.\n\n"
                    ."Assess all parameters that require a vaginal examination at the same time.\n\n"
                    ."![Fig. 6: Section 3 well-being of the baby fields]({$img['section-3-fig-a.png']})\n\n"
                    ."![Fig. 7: Amniotic fluid and moulding alert codes]({$img['section-3-fig-b.png']})\n\n"
                    ."![Fig. 8: Section 3, completed example]({$img['section-3-fig-c.png']})",
            ],
            [
                'title' => 'Section 4 — Well-being of the Woman',
                'content' => "Pulse and blood pressure values are recorded as numbers rather than being plotted on "
                    ."a graph.\n\n"
                    ."The frequency of recording maternal well-being on the Labour Care Guide depends on the "
                    ."woman's clinical status.\n\n"
                    ."![Fig. 9: Section 4 well-being of the woman fields]({$img['section-4-fig-a.png']})\n\n"
                    ."![Fig. 10: Section 4, completed example]({$img['section-4-fig-b.png']})",
            ],
            [
                'title' => 'Section 5 — Labour Progress (First & Second Stage)',
                'content' => "**Key WHO recommendations — first stage:**\n\n"
                    ."Women should be informed that a standard duration of the latent first stage has not been "
                    ."established and can vary widely from one woman to another.\n\n"
                    ."Women should be informed that the duration of the active first stage (from 5 cm until full "
                    ."cervical dilatation) usually does not extend beyond 12 hours in first labours and usually "
                    ."does not extend beyond 10 hours in subsequent labours.\n\n"
                    ."Labour may not naturally accelerate until a cervical dilatation threshold of 5 cm is "
                    ."reached.\n\n"
                    ."For women with spontaneous labour onset, a cervical dilatation rate threshold of 1 cm/hour "
                    ."during the active first stage is inaccurate for identifying women at risk of adverse birth "
                    ."outcomes and is therefore not recommended. A slower than 1 cm/hour dilatation rate alone "
                    ."should not be an indication for obstetric intervention — first carefully evaluate to exclude "
                    ."developing complications (e.g. cephalo-pelvic disproportion) and to determine whether the "
                    ."woman's emotional, psychological and physical needs in labour are being met. In facilities "
                    ."where interventions such as augmentation and caesarean section cannot be performed and "
                    ."referral is difficult, the alert line can still be used for triaging women who may require "
                    ."additional care.\n\n"
                    ."**Second stage — parameters recorded:** contractions (duration and frequency per 10 min), "
                    ."pushing, and descent.\n\n"
                    ."**Key WHO recommendations — second stage:**\n\n"
                    ."Women should be informed that in first labours, birth is usually completed within 3 hours, "
                    ."whereas in subsequent labours birth is usually completed within 2 hours.\n\n"
                    ."Encouraging the adoption of a birth position of the woman's own choice, including upright "
                    ."positions, is recommended.\n\n"
                    ."Women in the expulsive phase of the second stage should be encouraged and supported to "
                    ."follow their own urge to push. Health care providers should avoid imposing directed pushing, "
                    ."as there is no evidence of benefit from this technique.\n\n"
                    ."**Phases of the second stage:** the early (non-expulsive) phase is when the cervix is 10 cm "
                    ."but the woman does not yet have the urge to push; the late (expulsive) phase is when the "
                    ."presenting part of the fetus reaches the pelvic floor and the woman has the urge to push.\n\n"
                    ."![Fig. 11: Section 5 first-stage labour progress fields]({$img['section-5-fig-a.png']})\n\n"
                    ."![Fig. 12: Section 5 first-stage, completed example]({$img['section-5-fig-b.png']})\n\n"
                    ."![Fig. 13: Section 5 second-stage labour progress fields]({$img['section-5-fig-c.png']})\n\n"
                    ."![Fig. 14: Section 5 second-stage, completed example]({$img['section-5-fig-d.png']})\n\n"
                    ."![Fig. 15: Section 5, full completed example]({$img['section-5-fig-e.png']})",
            ],
            [
                'title' => 'Section 6 — Medications',
                'content' => "**Oxytocin** — is oxytocin currently being administered to the woman? If not, "
                    ."record N = No. If it is, record the amount in units per litre (U/L) and drops per minute "
                    ."(drops/min), and record the amount administered every 60 minutes.\n\n"
                    ."**Medicine** — if no other medication is being administered, record N = No. Otherwise record "
                    ."the name, dose and route of administration of any additional medication given during active "
                    ."first or second stage of labour (e.g. 50 mg pethidine, intramuscular (IM)).\n\n"
                    ."**IV fluid** — is the woman on IV fluids? Record Y = Yes or N = No. The routine "
                    ."administration of IV fluids for all women in labour is not recommended, as it reduces the "
                    ."woman's mobility and unnecessarily increases costs. Low-risk women should be encouraged to "
                    ."drink oral fluids and should receive IV fluids only if indicated.\n\n"
                    ."![Fig. 16: Section 6 medications fields]({$img['section-6-fig-a.png']})",
            ],
            [
                'title' => 'Section 7 — Shared Decision-Making',
                'content' => "**Making an assessment and developing the plan of care** are the final steps of "
                    ."each Labour Care Guide cycle.\n\n"
                    ."**Types of decision-making:**\n"
                    ."- *Paternalistic* — information & recommendations only.\n"
                    ."- *Informed medical decision-making* — information & recommendations, with evidence-based "
                    ."medicine as the basis for optimal patient care.\n"
                    ."- *Shared decision-making* — information & options for care, combined with the woman's "
                    ."values, preferences and concerns, achieved through patient-centred communication between "
                    ."clinician and patient.\n\n"
                    ."Develop and record the plan of care based on the assessment.\n\n"
                    ."**Record your initials** — the provider initials the findings, assessment, and plan they "
                    ."have recorded.\n\n"
                    ."![Fig. 17: Types of decision-making]({$img['section-7-fig-a.png']})\n\n"
                    ."![Fig. 18: Recording provider initials]({$img['section-7-fig-b.png']})\n\n"
                    ."![Fig. 19: Section 6 & 7, completed example]({$img['section-7-fig-c.png']})",
            ],
            [
                'title' => 'Sample Completed LCG (Reference)',
                'content' => "A fully completed Labour Care Guide for the case used throughout this module, "
                    ."showing every section filled in from admission through to birth.\n\n"
                    ."![Sample completed LCG — Mary Jane]({$img['appendix-sample-completed-lcg.png']})",
            ],
        ];

        foreach ($rows as $order => $row) {
            ProgramModuleContent::updateOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'mentor_materials',
                    'title' => $row['title'],
                ],
                [
                    'content' => $row['content'],
                    'order_sequence' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=EmoncLcgModule2SectionContentSeederTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/EmoncLcgModule2SectionContentSeeder.php tests/Feature/EmoncLcgModule2SectionContentSeederTest.php
git commit -m "feat: seed EmONC Module 2 (LCG) section-based mentee/mentor content"
```

---

### Task 3: Register the seeder in `DatabaseSeeder`

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: `Database\Seeders\EmoncLcgModule2SectionContentSeeder` from Task 2.

- [ ] **Step 1: Add the seeder call after `EmoncBatchAContentSeeder::class`**

In `database/seeders/DatabaseSeeder.php`, change:

```php
            EmoncBatchAContentSeeder::class,   // Modules 2,3,4,6 — content + quiz
            EmoncBatchBContentSeeder::class,   // Modules 7,8,9 — content + quiz
```

to:

```php
            EmoncBatchAContentSeeder::class,   // Modules 2,3,4,6 — content + quiz
            EmoncLcgModule2SectionContentSeeder::class, // Module 2 — replaces case scenario with 7 section-based mentee/mentor rows from the LCG webinar PPTX
            EmoncBatchBContentSeeder::class,   // Modules 7,8,9 — content + quiz
```

- [ ] **Step 2: Verify the full seeder chain still runs cleanly**

Run: `php artisan migrate:fresh --seed --env=testing` if a testing DB/env is configured, otherwise run
against a local dev database you're prepared to reset:
`php artisan migrate:fresh --seed`

Expected: seeding completes without error and prints
`EmONC Module 2 (LCG) section-based mentee/mentor content seeded successfully.` among the other seeder
output lines.

- [ ] **Step 3: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "chore: register EmoncLcgModule2SectionContentSeeder in DatabaseSeeder"
```

---

### Task 4: Make the mentor review page render all `mentor_materials` rows

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ReviewModuleMentee.php:81-82`
- Modify: `resources/views/filament/pages/review-module-mentee.blade.php:238-256`
- Test: `tests/Feature/ReviewModuleMenteeMentorMaterialsTest.php`

**Interfaces:**
- Consumes: `ProgramModuleContent` rows of `type = 'mentor_materials'` seeded by Task 2 (module 52, 8 rows, titles listed in Task 2).
- Produces: `getViewData()['mentorMaterials']` becomes an `Illuminate\Support\Collection` of `ProgramModuleContent` (was previously `?ProgramModuleContent`); the blade renders one block per row instead of one block total.

- [ ] **Step 1: Write the failing test**

`ProgramModuleContent` has no dedicated factory class (only `Program`, `ProgramModule`, `Training`,
`MentorshipClass`, `ClassModule`, `ClassParticipant` do — verified via `ls database/factories/`), so
create rows with plain `::create()`. `getViewData()` is `protected`, and the established pattern in
`tests/Feature/ReviewModuleMenteeUnlockTest.php` is to instantiate the page directly
(`new ReviewModuleMentee()` + `->mount(...)`) rather than through Livewire — reuse that exact pattern and
call `getViewData()` via reflection. Create `tests/Feature/ReviewModuleMenteeMentorMaterialsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ReviewModuleMentee;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ReviewModuleMenteeMentorMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_exposes_all_mentor_materials_rows_not_just_the_first(): void
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);

        ProgramModuleContent::create([
            'program_module_id' => $programModule->id,
            'type' => 'mentor_materials',
            'title' => 'Section 1 — Admission',
            'content' => 'First section content.',
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        ProgramModuleContent::create([
            'program_module_id' => $programModule->id,
            'type' => 'mentor_materials',
            'title' => 'Section 2 — Supportive Care',
            'content' => 'Second section content.',
            'order_sequence' => 2,
            'is_active' => true,
        ]);

        $mentor = User::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);

        Auth::login($mentor);
        $page = new ReviewModuleMentee();
        $page->mount($training, $class, $classModule, $participant);

        $getViewData = new \ReflectionMethod($page, 'getViewData');
        $getViewData->setAccessible(true);
        $viewData = $getViewData->invoke($page);
        $mentorMaterials = $viewData['mentorMaterials'];

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $mentorMaterials);
        $this->assertCount(2, $mentorMaterials);
        $this->assertSame('Section 1 — Admission', $mentorMaterials->first()->title);
        $this->assertSame('Section 2 — Supportive Care', $mentorMaterials->last()->title);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReviewModuleMenteeMentorMaterialsTest`
Expected: FAIL — `$mentorMaterials` is a single model or null, `assertInstanceOf(Collection::class, ...)`
fails, or `assertCount(2, ...)` fails because `.first()` only returns one row.

- [ ] **Step 3: Fix `ReviewModuleMentee::getViewData()`**

In `app/Filament/Resources/MentorshipResource/Pages/ReviewModuleMentee.php`, change:

```php
            'mentorCourseIntro' => $contents->where('type', 'mentor_course_intro')->first(),
            'mentorMaterials' => $contents->where('type', 'mentor_materials')->first(),
```

to:

```php
            'mentorCourseIntro' => $contents->where('type', 'mentor_course_intro')->first(),
            'mentorMaterials' => $contents->where('type', 'mentor_materials')->sortBy('order_sequence')->values(),
```

- [ ] **Step 4: Update the blade — drop `mentorMaterials` from the compact grid, give it its own section**

The existing "Course Information" card (lines 237-279) is a compact `auto-fit,minmax(240px,1fr)` grid
meant for short items (course intro blurb, equipment chips, debrief bullet list). With 8 full
theory-plus-images sections, `mentorMaterials` no longer belongs inside that grid — it needs a dedicated
full-width section below it. Make two changes:

**4a.** Remove `mentorMaterials` from the Course Information card. Change (lines 238 and 251-256):

```blade
    @if($mentorCourseIntro || $mentorMaterials || $moduleRubric?->debrief_questions || $moduleRubric?->equipment_supplies)
```

to:

```blade
    @if($mentorCourseIntro || $moduleRubric?->debrief_questions || $moduleRubric?->equipment_supplies)
```

and delete this block entirely (lines 251-256):

```blade
            @if($mentorMaterials)
                <div>
                    <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 6px;">Materials Needed for the Course</p>
                    <div class="prose prose-sm max-w-none" style="font-size:13px;color:#374151;">{!! Str::markdown($mentorMaterials->content) !!}</div>
                </div>
            @endif
```

**4b.** Immediately after the Course Information card's closing (currently lines 278-280:
`</div>\n    @endif\n\n    {{-- ═══ QUICK STATS ROW ...`), insert a new full-width card that loops over
every `mentorMaterials` row:

```blade
    {{-- ═══ MENTOR MATERIALS (mentor-facing, per LCG section) ═══════════════════ --}}
    @if($mentorMaterials->isNotEmpty())
    <div class="rv-animate" style="animation-delay:0.05s;margin-bottom:20px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#4f46e5" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
            <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0;">Mentor Materials — By Section</h3>
        </div>
        <div style="padding:18px 20px;">
            @foreach($mentorMaterials as $material)
                <div class="{{ $loop->first ? '' : 'mt-5 pt-5' }}" style="{{ $loop->first ? '' : 'border-top:1px solid #f1f5f9;' }}">
                    <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 6px;">{{ $material->title }}</p>
                    <div class="prose prose-sm max-w-none" style="font-size:13px;color:#374151;">{!! Str::markdown($material->content) !!}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ═══ QUICK STATS ROW ════════════════════════════════════════════════════ --}}
```

Before editing, confirm the exact current line numbers with:
`sed -n '235,282p' resources/views/filament/pages/review-module-mentee.blade.php` — the plan's line
numbers assume the file is unchanged since this plan was written; re-locate by content
(`@if($mentorCourseIntro`, `QUICK STATS ROW`) if line numbers have drifted.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ReviewModuleMenteeMentorMaterialsTest`
Expected: PASS.

- [ ] **Step 6: Run the full mentorship review test suite to check for regressions**

Run: `php artisan test --filter=ReviewModuleMentee`
Expected: all pre-existing tests touching this page (e.g. `HeadDrmhReviewMenteeTest`,
`ReviewModuleMenteeUnlockTest`) still PASS — confirms the `.first()` → collection change didn't break
`mentorCourseIntro` (untouched) or any other view-data consumer.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/ReviewModuleMentee.php resources/views/filament/pages/review-module-mentee.blade.php tests/Feature/ReviewModuleMenteeMentorMaterialsTest.php
git commit -m "fix: render all mentor_materials rows on the mentor review page, not just the first"
```

---

### Task 5: End-to-end manual verification against the seeded module

**Files:** none (verification only)

**Interfaces:**
- Consumes: everything from Tasks 1-4.

- [ ] **Step 1: Seed a local/staging database**

Run: `php artisan migrate:fresh --seed` against a local dev DB (never production directly — this step is
a rehearsal for the production seed run).

Expected: no errors; final seeder confirmation lines appear, including
`EmONC Module 2 (LCG) section-based mentee/mentor content seeded successfully.`

- [ ] **Step 2: Verify mentee-facing content**

As a mentee assigned to a class using Module 2, open the module detail page
(`resources/views/mentee/module-detail.blade.php`'s route — via the mentee dashboard). Confirm the "Case
Scenario" section shows exactly 6 blocks titled `Section 1 — Practice` through `Sections 6 & 7 —
Practice`, each containing only the Mary Jane narrative text (no WHO theory text, no images).

- [ ] **Step 3: Verify mentor-facing content**

As a mentor reviewing a mentee's progress on Module 2 (`ReviewModuleMentee` page), confirm 8
mentor-materials sections render in order (Section 1 through Section 7, then "Sample Completed LCG
(Reference)"), each with its theory text and embedded figures displaying correctly (no broken image
icons — confirms `Storage::disk('public')->url()` paths resolve, which requires `php artisan
storage:link` to already exist in the environment; this project already relies on that symlink for
`video_path` uploads, so no new setup step is expected, but verify it if images 404).

- [ ] **Step 4: Run the full test suite one more time**

Run: `php artisan test`
Expected: full suite PASS — confirms no regression outside the specifically-targeted tests.

- [ ] **Step 5: Deploy to production**

On the production server, pull the merged changes and run:
```bash
php artisan db:seed --class=EmoncLcgModule2SectionContentSeeder
```
This is safe to run standalone (doesn't require `migrate:fresh`) since the seeder only touches
`ProgramModuleContent` rows for the existing Module 2 record and copies image files — no schema changes.
Confirm the command prints the success message and re-check the mentee/mentor views in production.
