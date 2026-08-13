# Facility Readiness Assessment 2026 — Phase 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Seed the actual 2026 Facility Readiness Assessment content (`AssessmentType`, sections, questions, commodities, checklists, cadres) from `Assessments. v2.xlsx`, using Phase 1's engine capabilities. Zero shared data with the 2025 `STANDARD_FACILITY_ASSESSMENT` type.

**Architecture:** One idempotent Laravel seeder class per spreadsheet section under `database/seeders/FacilityAssessment2026/`, orchestrated by `FacilityAssessment2026Seeder`. Every seeder follows the `AmbuBagCommoditySeeder` convention already in the codebase: `firstOrCreate`/`updateOrCreate` on natural keys, one-line `$this->command->info()` summary, safe to re-run.

**Tech Stack:** Laravel 12 seeders, PHPUnit feature tests (`php artisan test`).

## Global Constraints

- Every seeder is idempotent — running `php artisan db:seed --class=FacilityAssessment2026Seeder` twice must not create duplicates or error.
- Every row of content comes from the verified, twice-corrected content mapping in `docs/superpowers/specs/2026-08-13-facility-assessment-2026-phase2-design.md` — that document is the authoritative data source for this plan; deviating from it requires updating the design doc first (as already happened twice during design review), not silently diverging in code.
- No task touches 2025 `STANDARD_FACILITY_ASSESSMENT` data, sections, questions, commodities, or cadres.
- Every seeder task's test asserts (a) exact row/question counts for that section, (b) `question_code`/`response type`/`display_conditions` for every row named explicitly in the design doc's tables (not just a couple of spot-checks — the design doc names specific codes precisely so the test should check all of them), and (c) that 2025 data is untouched.
- Run `php artisan test --filter='FacilityAssessment2026|Assessment'` after every task; run the full `composer test` suite before the final task's commit.
- Reuses the exact 2025 section `code` values (`facility_profile`, `infrastructure`, `bed_capacity`, `skills_lab`, `human_resources`, `health_products`, `information_systems`, `quality_of_care`) plus one new code (`newborn_paediatric_indicators`) — this is what Phase 1's Task 1 composite-unique fix on `assessment_sections.code` was for.

---

### Task 0: Template parameters — `AssessmentType::interpolate()`

**Files:**
- Modify: `app/Models/AssessmentType.php`
- Modify: `app/Services/DynamicFormBuilder.php` (`buildFieldForQuestion`)
- Modify: `app/Filament/Resources/AssessmentResource/Pages/EditSection.php` (`form()`)
- Modify: `app/Filament/Resources/AssessmentResource/Traits/HasSectionNavigation.php` (section-chrome label)
- Modify: `app/Filament/Resources/AssessmentTypeResource.php` (admin form — add `KeyValue::make('metadata.parameters')`)
- Test: `tests/Unit/AssessmentTypeInterpolateTest.php`

**Interfaces:**
- Produces: `AssessmentType::interpolate(?string $text): ?string` — replaces every `{{key}}` with `$this->metadata['parameters'][$key] ?? "{{key}}"`; returns input unchanged if null or containing no tokens.

- [ ] **Step 1: Write the failing unit test**

```php
<?php

namespace Tests\Unit;

use App\Models\AssessmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTypeInterpolateTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_a_set_parameter(): void
    {
        $type = AssessmentType::create([
            'name' => 'Interp Test', 'code' => 'INTERP_TEST', 'is_active' => true,
            'metadata' => ['parameters' => ['timeline' => 'Neonates 7-28 days']],
        ]);

        $this->assertSame('Select agreed timelines: Neonates 7-28 days', $type->interpolate('Select agreed timelines: {{timeline}}'));
    }

    public function test_leaves_an_unset_parameter_token_visible(): void
    {
        $type = AssessmentType::create(['name' => 'Interp Test 2', 'code' => 'INTERP_TEST_2', 'is_active' => true]);

        $this->assertSame('Value: {{missing}}', $type->interpolate('Value: {{missing}}'));
    }

    public function test_passes_through_null_and_plain_text_unchanged(): void
    {
        $type = AssessmentType::create(['name' => 'Interp Test 3', 'code' => 'INTERP_TEST_3', 'is_active' => true]);

        $this->assertNull($type->interpolate(null));
        $this->assertSame('Plain text', $type->interpolate('Plain text'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentTypeInterpolateTest`
Expected: FAIL — `interpolate` method doesn't exist.

- [ ] **Step 3: Implement `interpolate()`**

In `app/Models/AssessmentType.php`, add:

```php
    public function interpolate(?string $text): ?string
    {
        if ($text === null || ! str_contains($text, '{{')) {
            return $text;
        }

        $parameters = $this->metadata['parameters'] ?? [];

        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $matches) use ($parameters) {
            return $parameters[$matches[1]] ?? $matches[0];
        }, $text);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentTypeInterpolateTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Wire interpolation into question rendering**

In `app/Services/DynamicFormBuilder.php`, in `buildFieldForQuestion()` (the method Task 3 of Phase 1 already modifies to clone-and-relabel lettered questions), interpolate `question_text` and `help_text` on the (possibly-already-lettered) clone before building the field:

```php
    protected static function buildFieldForQuestion(AssessmentQuestion $question, ?AssessmentQuestionResponse $existingResponse): mixed
    {
        $fieldName = "question_response_{$question->id}";

        $type = $question->section?->assessmentType;
        if ($type && (str_contains($question->question_text ?? '', '{{') || str_contains($question->help_text ?? '', '{{'))) {
            $question = clone $question;
            $question->question_text = $type->interpolate($question->question_text);
            $question->help_text = $type->interpolate($question->help_text);
        }

        // ... existing body unchanged from here ...
```

(`$question->section` requires the `AssessmentQuestion::section()` relation, already present. This runs BEFORE the existing NBU/mortality/QuestionFieldBuilder dispatch, so interpolated text flows through unchanged from there.)

- [ ] **Step 6: Wire interpolation into section title/description rendering**

In `app/Filament/Resources/AssessmentResource/Pages/EditSection.php`, in `form()`:

```php
    public function form(Form $form): Form
    {
        $fields = DynamicFormBuilder::buildForSection($this->section->id, $this->record->id);

        if ($this->section->code === 'emonc_facility_context') {
            $fields = $this->insertManageCadresAction($fields);
        }

        $type = $this->record->assessmentType;
        $sectionName = $type ? $type->interpolate($this->section->name) : $this->section->name;
        $sectionDescription = $type ? $type->interpolate($this->section->description) : $this->section->description;

        return $form->schema([
            Forms\Components\View::make('filament.pages.assessment.section-chrome')
                ->viewData(fn () => [
                    'sections' => $this->getAllSections(),
                    'currentKey' => $this->section->code,
                ])
                ->columnSpanFull(),
            Forms\Components\Section::make("{$sectionName} Assessment")
                ->description($sectionDescription)
                ->icon('heroicon-o-clipboard-document-check')
                ->extraAttributes(['class' => 'aqs'])
                ->schema($fields)
                ->columns(1),
        ]);
    }
```

- [ ] **Step 7: Add the admin KeyValue field**

In `app/Filament/Resources/AssessmentTypeResource.php`, locate the `form()` method's schema array and add, near the existing `description`/`metadata`-adjacent fields:

```php
                Forms\Components\KeyValue::make('metadata.parameters')
                    ->label('Template Parameters')
                    ->helperText('Reference these in section/question text as {{key}}. An unset key stays visible as {{key}} on the rendered page, so a missing parameter is obvious.')
                    ->keyLabel('Parameter')
                    ->valueLabel('Value')
                    ->reorderable(false),
```

- [ ] **Step 8: Run the full assessment test suite**

Run: `php artisan test --filter=Assessment`
Expected: PASS — no 2025 question/section text contains `{{`, so `interpolate()` returns every string unchanged for existing content (the `str_contains($text, '{{')` short-circuit in Step 3 means the method body barely executes for 2025 rows at all).

- [ ] **Step 9: Commit**

```bash
git add app/Models/AssessmentType.php app/Services/DynamicFormBuilder.php \
        app/Filament/Resources/AssessmentResource/Pages/EditSection.php \
        app/Filament/Resources/AssessmentTypeResource.php \
        tests/Unit/AssessmentTypeInterpolateTest.php
git commit -m "feat: support {{parameter}} interpolation in assessment template section/question text"
```

---

### Task 1: Orchestrator + `AssessmentType` + `facility_profile`/`bed_capacity` placeholder sections

**Files:**
- Create: `database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php`
- Create: `database/seeders/FacilityAssessment2026/FacilityProfileSeeder.php`
- Create: `database/seeders/FacilityAssessment2026/BedCapacitySeeder.php`
- Test: `tests/Feature/FacilityAssessment2026/FacilityAssessment2026SeederTest.php`

**Interfaces:**
- Produces: the `STANDARD_FACILITY_ASSESSMENT_2026` `AssessmentType` row, resolvable by `AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->first()` — every subsequent task's seeder resolves the type this way rather than taking a constructor argument, matching how `AmbuBagCommoditySeeder` resolves its category by lookup rather than a passed-in ID.
- Produces: `facility_profile` and `bed_capacity` sections (0 questions each), `infrastructure` (empty section row — Task 3 fills it), `bed_capacity` `code` reused from 2025 per the Global Constraints.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\FacilityAssessment2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityAssessment2026SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_2026_assessment_type_with_quality_of_care_parameter(): void
    {
        $this->seed(FacilityAssessment2026Seeder::class);

        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->first();

        $this->assertNotNull($type);
        $this->assertSame('2026', $type->version);
        $this->assertTrue($type->is_active);
        $this->assertSame('Neonates 7–28 days', $type->metadata['parameters']['quality_of_care_timeline'] ?? null);
    }

    public function test_creates_facility_profile_and_bed_capacity_as_empty_informational_sections(): void
    {
        $this->seed(FacilityAssessment2026Seeder::class);
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->first();

        $profile = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'facility_profile')->first();
        $bedCapacity = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'bed_capacity')->first();

        $this->assertNotNull($profile);
        $this->assertSame(0, $profile->questions()->count());
        $this->assertNotNull($bedCapacity);
        $this->assertSame(0, $bedCapacity->questions()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(FacilityAssessment2026Seeder::class);
        $this->seed(FacilityAssessment2026Seeder::class);

        $this->assertSame(1, AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->count());
    }

    public function test_does_not_touch_the_2025_standard_facility_assessment(): void
    {
        $before = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT')->count();

        $this->seed(FacilityAssessment2026Seeder::class);

        $this->assertSame($before, AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT')->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FacilityAssessment2026SeederTest`
Expected: FAIL — seeder class doesn't exist.

- [ ] **Step 3: Write the orchestrator**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the 2026 revision of the Facility Readiness Assessment, sourced
 * from Assessments. v2.xlsx (root of repo). Every section/question/
 * commodity/checklist/cadre this creates is scoped to a brand-new
 * AssessmentType — nothing here touches the 2025 STANDARD_FACILITY_ASSESSMENT
 * type's data. See docs/superpowers/specs/2026-08-13-facility-assessment-2026-phase2-design.md
 * for the full content mapping this seeder implements.
 *
 * Run with:
 *   php artisan db:seed --class="Database\Seeders\FacilityAssessment2026\FacilityAssessment2026Seeder"
 */
class FacilityAssessment2026Seeder extends Seeder
{
    public function run(): void
    {
        $category = AssessmentTypeCategory::where('name', 'like', '%facility%')->first();

        $type = AssessmentType::firstOrCreate(
            ['code' => 'STANDARD_FACILITY_ASSESSMENT_2026'],
            [
                'name' => 'Standard Facility Readiness Assessment (2026)',
                'version' => '2026',
                'category_id' => $category?->id,
                'is_active' => true,
                'metadata' => ['parameters' => ['quality_of_care_timeline' => 'Neonates 7–28 days']],
            ]
        );

        // Existing rows (a re-run) won't have their metadata re-applied by
        // firstOrCreate — keep the parameter in sync explicitly.
        if ($type->metadata['parameters']['quality_of_care_timeline'] ?? null !== 'Neonates 7–28 days') {
            $type->update(['metadata' => ['parameters' => ['quality_of_care_timeline' => 'Neonates 7–28 days']]]);
        }

        $this->call([
            ChecklistsSeeder::class,
            FacilityProfileSeeder::class,
            InfrastructureSeeder::class,
            BedCapacitySeeder::class,
            SkillsLabSeeder::class,
            HumanResourcesSeeder::class,
            HealthProductsSeeder::class,
            InformationSystemsSeeder::class,
            QualityOfCareSeeder::class,
            IndicatorsSeeder::class,
        ]);

        $this->command->info('✓ Facility Readiness Assessment 2026 seeded.');
    }
}
```

- [ ] **Step 4: Write the two placeholder-section seeders**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class FacilityProfileSeeder extends Seeder
{
    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'facility_profile'],
            [
                'name' => 'Health Facility Profile',
                'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES,
                'is_scored' => false,
                'order' => 1,
                'is_active' => true,
            ]
        );

        $this->command->info('  ✓ facility_profile section (informational).');
    }
}
```

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class BedCapacitySeeder extends Seeder
{
    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'bed_capacity'],
            [
                'name' => 'Bed Capacities',
                'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES,
                'is_scored' => false,
                'order' => 3,
                'is_active' => true,
            ]
        );

        $this->command->info('  ✓ bed_capacity section (informational placeholder — real fields live in infrastructure).');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=FacilityAssessment2026SeederTest`
Expected: still FAIL at this point — `$this->call([...])` references `InfrastructureSeeder`, `SkillsLabSeeder`, etc. which don't exist yet (Tasks 2–9). Temporarily comment out every `::class` entry in the `$this->call([...])` array except `FacilityProfileSeeder::class` and `BedCapacitySeeder::class` to get this task green in isolation; each subsequent task uncomments its own line as it's implemented (Step 3 of Tasks 2–9 each include this).

Run: `php artisan test --filter=FacilityAssessment2026SeederTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php \
        database/seeders/FacilityAssessment2026/FacilityProfileSeeder.php \
        database/seeders/FacilityAssessment2026/BedCapacitySeeder.php \
        tests/Feature/FacilityAssessment2026/FacilityAssessment2026SeederTest.php
git commit -m "feat: seed the 2026 AssessmentType orchestrator + informational placeholder sections"
```

---

### Task 2: `ChecklistsSeeder`

**Files:**
- Create: `database/seeders/FacilityAssessment2026/ChecklistsSeeder.php`
- Test: `tests/Feature/FacilityAssessment2026/ChecklistsSeederTest.php`

**Interfaces:**
- Produces: 3 `AssessmentChecklist` rows resolvable by `AssessmentChecklist::where('assessment_type_id', $type->id)->where('title', '...')->first()` — Tasks 3 and 4 (Infrastructure, Skills Lab) look these up by title to set `checklist_id` on their questions.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\ChecklistsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        return AssessmentType::create(['name' => 'CL Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
    }

    public function test_ort_corner_checklist_has_17_items_with_min_qty(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);

        $checklist = AssessmentChecklist::where('title', 'ORT Corner checklist')->firstOrFail();
        $this->assertCount(17, $checklist->items);
        $this->assertSame(6, $checklist->items->firstWhere('label', 'Clean spoons')?->qty);
        $this->assertNull($checklist->items->firstWhere('label', 'Chlorine for disinfection')?->qty);
    }

    public function test_triage_requirements_has_17_items_no_qty(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);

        $checklist = AssessmentChecklist::where('title', 'Triage requirements')->firstOrFail();
        $this->assertCount(17, $checklist->items);
        $this->assertTrue($checklist->items->every(fn ($i) => $i->qty === null));
    }

    public function test_skills_lab_checklist_has_equipment_and_stationery_groups(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);

        $checklist = AssessmentChecklist::where('title', 'Skills Lab Checklist Requirements')->firstOrFail();
        $equipment = $checklist->items->where('group_label', 'EQUIPMENT');
        $stationery = $checklist->items->where('group_label', 'STATIONERY');

        $this->assertCount(5, $equipment); // 3 manikin/model items + Radiant Warmer + Suction Machine
        $this->assertCount(2, $stationery);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);
        $this->seed(ChecklistsSeeder::class);

        $this->assertSame(3, AssessmentChecklist::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChecklistsSeederTest`
Expected: FAIL — seeder class doesn't exist.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class ChecklistsSeeder extends Seeder
{
    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        $this->seedOrtCorner($type);
        $this->seedTriage($type);
        $this->seedSkillsLab($type);

        $this->command->info('  ✓ 3 checklists seeded (ORT Corner, Triage requirements, Skills Lab).');
    }

    private function seedOrtCorner(AssessmentType $type): void
    {
        $checklist = AssessmentChecklist::firstOrCreate(
            ['assessment_type_id' => $type->id, 'title' => 'ORT Corner checklist']
        );

        $items = [
            ['Clean spoons', 6], ['Plastic buckets (with lids for infection prevention)', 3],
            ['Buckets – for storing cups, spoons,', 1], ['Small plastic cups (50-100ml & 100-200ml & 500mls)', 6],
            ['1 litre Calibrated measuring jars', 2], ['Table Trays', 2], ['Wash Basins', 2],
            ['Water boiling equipment', 1], ['Waste Bin', 1], ['Functinal Wall Clock', 1],
            ['Table- for mixing ORS', 1], ['Benches/chair(s), comfortable seats', 6],
            ['Hand Washing Facility/Point e.g. tippy taps and new technologies and soap', 1],
            ['Safe water source', 1], ['Chlorine for disinfection', null],
            ['Low osmolarity ORS/Zinc copack /Resomal', null], ['ORT monitoring tools (Register, summary sheets etc)', 1],
        ];

        foreach ($items as $order => [$label, $qty]) {
            $checklist->items()->updateOrCreate(
                ['label' => $label],
                ['qty' => $qty, 'order' => $order + 1]
            );
        }
    }

    private function seedTriage(AssessmentType $type): void
    {
        $checklist = AssessmentChecklist::firstOrCreate(
            ['assessment_type_id' => $type->id, 'title' => 'Triage requirements']
        );

        $items = [
            'Table', 'Chairs', 'Paediatric stethoscopes', 'Vital signs monitor', 'Digital thermometer',
            'Handheld pulse oximeter with infant and paediatrics probes',
            'BP machines with a range of cuff sizes (newborns, infants, older children and adolescents)',
            'Weighing scales (infant and older children)', 'Stadiometer', 'Tape measures (MUAC tapes, Breslow tapes)',
            'Examination couch', 'Heating source', 'Computer', 'Storage cabinets', 'Hand washing point',
            'Alcohol-based hand rub (isopropyl alcohol 75%-500ml)', 'Disposable hand towels',
        ];

        foreach ($items as $order => $label) {
            $checklist->items()->updateOrCreate(
                ['label' => $label],
                ['qty' => null, 'order' => $order + 1]
            );
        }
    }

    private function seedSkillsLab(AssessmentType $type): void
    {
        $checklist = AssessmentChecklist::firstOrCreate(
            ['assessment_type_id' => $type->id, 'title' => 'Skills Lab Checklist Requirements']
        );

        $equipment = [
            'neonatal manikin with inflatable lungs',
            'preterm manikin with open nares and mouth for OGT, NGT and CPAP demonstration',
            'mama breast',
            'Radiant Warmer',
            'Suction Machine',
        ];
        $stationery = ['Flip charts', 'White board markers'];

        foreach ($equipment as $order => $label) {
            $checklist->items()->updateOrCreate(
                ['label' => $label, 'group_label' => 'EQUIPMENT'],
                ['qty' => null, 'order' => $order + 1]
            );
        }
        foreach ($stationery as $order => $label) {
            $checklist->items()->updateOrCreate(
                ['label' => $label, 'group_label' => 'STATIONERY'],
                ['qty' => null, 'order' => $order + 10]
            );
        }
    }
}
```

- [ ] **Step 4: Uncomment `ChecklistsSeeder::class` in the orchestrator's `$this->call([...])` list** (Task 1, Step 3)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ChecklistsSeederTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Run FacilityAssessment2026SeederTest to confirm the orchestrator still works with this seeder active**

Run: `php artisan test --filter=FacilityAssessment2026SeederTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/seeders/FacilityAssessment2026/ChecklistsSeeder.php \
        database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php \
        tests/Feature/FacilityAssessment2026/ChecklistsSeederTest.php
git commit -m "feat: seed the 3 Facility Readiness Assessment 2026 checklists"
```

---

### Task 3: `InfrastructureSeeder`

**Files:**
- Create: `database/seeders/FacilityAssessment2026/InfrastructureSeeder.php`
- Test: `tests/Feature/FacilityAssessment2026/InfrastructureSeederTest.php`

**Interfaces:**
- Produces: `infrastructure` section with 14 gating/plain yes_no questions + 16 bed-capacity number questions = 30 questions. Question codes `INFRA_HAS_NBU`, `INFRA_HAS_PAED`, `INFRA_HAS_NICU`, `INFRA_HAS_PICU` are consumed by Task 4 (Skills Lab), Task 6 (Health Products NICU-gating), and Task 9 (Indicators).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\InfrastructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        $type = AssessmentType::create(['name' => 'Infra Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 2, 'is_active' => true,
        ]);

        return $type;
    }

    public function test_seeds_30_questions(): void
    {
        $type = $this->makeType();
        $this->seed(InfrastructureSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'infrastructure')->first();
        $this->assertSame(30, $section->questions()->count());
    }

    public function test_bed_capacity_questions_are_conditionally_gated_on_their_own_unit(): void
    {
        $this->makeType();
        $this->seed(InfrastructureSeeder::class);

        $nbuBeds = AssessmentQuestion::where('question_code', 'INFRA_NBU_GENERAL_FUNCTIONAL')->firstOrFail();
        $this->assertSame(['question_code' => 'INFRA_HAS_NBU', 'operator' => 'equals', 'value' => 'Yes'], $nbuBeds->display_conditions);
        $this->assertSame(1, $nbuBeds->indent_level);
        $this->assertSame('number', $nbuBeds->question_type);

        $picuBeds = AssessmentQuestion::where('question_code', 'INFRA_PICU_FUNCTIONAL')->firstOrFail();
        $this->assertSame(['question_code' => 'INFRA_HAS_PICU', 'operator' => 'equals', 'value' => 'Yes'], $picuBeds->display_conditions);
    }

    public function test_ort_questions_link_the_ort_corner_checklist(): void
    {
        $this->makeType();
        $this->seed(\Database\Seeders\FacilityAssessment2026\ChecklistsSeeder::class);
        $this->seed(InfrastructureSeeder::class);

        $outpatient = AssessmentQuestion::where('question_code', 'INFRA_ORT_OUTPATIENT')->firstOrFail();
        $inpatient = AssessmentQuestion::where('question_code', 'INFRA_ORT_INPATIENT')->firstOrFail();

        $this->assertNotNull($outpatient->checklist_id);
        $this->assertSame($outpatient->checklist_id, $inpatient->checklist_id);
        $this->assertSame('ORT Corner checklist', $outpatient->checklist->title);
    }

    public function test_triage_question_links_the_triage_checklist(): void
    {
        $this->makeType();
        $this->seed(\Database\Seeders\FacilityAssessment2026\ChecklistsSeeder::class);
        $this->seed(InfrastructureSeeder::class);

        $triage = AssessmentQuestion::where('question_code', 'INFRA_TRIAGE')->firstOrFail();
        $this->assertSame('Triage requirements', $triage->checklist->title);
    }

    public function test_no_questions_require_explanation_except_on_no(): void
    {
        $this->makeType();
        $this->seed(InfrastructureSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'INFRA_SEPARATE_NBU_PAED')->firstOrFail();
        $this->assertSame(['No'], $q->requires_explanation_on);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InfrastructureSeederTest`
Expected: FAIL — seeder class doesn't exist.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class InfrastructureSeeder extends Seeder
{
    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'infrastructure')->firstOrFail();
        $ortChecklist = AssessmentChecklist::where('assessment_type_id', $type->id)->where('title', 'ORT Corner checklist')->first();
        $triageChecklist = AssessmentChecklist::where('assessment_type_id', $type->id)->where('title', 'Triage requirements')->first();

        $order = 0;
        $create = function (array $attrs) use ($section, &$order) {
            $order++;
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $attrs['question_code']],
                array_merge([
                    'question_type' => 'yes_no',
                    'is_scored' => true,
                    'scoring_map' => ['Yes' => 1, 'No' => 0],
                    'requires_explanation_on' => ['No'],
                    'order' => $order,
                    'is_active' => true,
                    'indent_level' => 0,
                ], $attrs)
            );
        };

        $create(['question_code' => 'INFRA_HAS_NBU', 'question_text' => 'Do you have a newborn unit (If yes show entry for bed capacity under newborn unit below)']);
        $this->bedCountPair($section, $order, 'INFRA_NBU_GENERAL', 'General NBU beds', 'INFRA_HAS_NBU'); $order += 2;
        $this->bedCountPair($section, $order, 'INFRA_NBU_KMC', 'KMC beds', 'INFRA_HAS_NBU'); $order += 2;

        $create(['question_code' => 'INFRA_HAS_PAED', 'question_text' => 'Do you have a paediatric unit (If yes show entry for bed capacity under pediatric unit below)']);
        $this->bedCountPair($section, $order, 'INFRA_PAED_GENERAL', 'General ward beds', 'INFRA_HAS_PAED'); $order += 2;

        $create(['question_code' => 'INFRA_HAS_NICU', 'question_text' => 'Do you have a NICU (If yes show entry for bed capacity under NICU)']);
        $this->bedCountPair($section, $order, 'INFRA_NICU', 'NICU Beds', 'INFRA_HAS_NICU'); $order += 2;

        $create(['question_code' => 'INFRA_HAS_PICU', 'question_text' => 'Do you have a PICU (If yes show entry for bed capacity under PICU)']);
        $this->bedCountPair($section, $order, 'INFRA_PICU', 'PICU Beds', 'INFRA_HAS_PICU'); $order += 2;

        $create(['question_code' => 'INFRA_SEPARATE_NBU_PAED', 'question_text' => 'Is there a separate newborn and paediatric unit']);
        $create(['question_code' => 'INFRA_SEPARATE_OPD', 'question_text' => 'Are newborns and paediatrics patients seen separately from the adults in the outpatient department']);
        $create(['question_code' => 'INFRA_RESUS_LABOUR', 'question_text' => 'Is there a warm functional newborn resuscitation area in labour ward with: Complete resuscitation tray with an updated checklist, Radiant warmer, suction machine']);
        $create(['question_code' => 'INFRA_RESUS_THEATRE', 'question_text' => 'Is there a warm functional newborn resuscitation area in maternity theater?']);
        $create(['question_code' => 'INFRA_ORT_OUTPATIENT', 'question_text' => 'Is there a functional Oral Rehydration Therapy (ORT) corner in the outpatient department?', 'checklist_id' => $ortChecklist?->id]);
        $create(['question_code' => 'INFRA_ORT_INPATIENT', 'question_text' => 'Is there a functional Oral Rehydration Therapy (ORT) corner in the inpatient department?', 'checklist_id' => $ortChecklist?->id]);
        $create(['question_code' => 'INFRA_ORT_REGISTER', 'question_text' => 'Is there an updated Oral Rehydration Therapy (ORT) corner register?(Observe availability and functionality)?']);
        $create(['question_code' => 'INFRA_NEBULIZATION', 'question_text' => 'Is there a nebulization corner?']);
        $create(['question_code' => 'INFRA_TRIAGE', 'question_text' => 'Is there a triage area in the outpatient department?', 'checklist_id' => $triageChecklist?->id]);

        $this->command->info('  ✓ infrastructure: 30 questions (14 gating/plain + 16 bed-capacity).');
    }

    /**
     * A Functional/Non-Functional pair of number questions, both
     * conditionally visible only when $gatingCode = Yes, both indented
     * under their gating question.
     */
    private function bedCountPair(AssessmentSection $section, int $startOrder, string $codePrefix, string $label, string $gatingCode): void
    {
        foreach (['FUNCTIONAL' => 'Functional', 'NONFUNCTIONAL' => 'Non-Functional'] as $suffix => $variantLabel) {
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => "{$codePrefix}_{$suffix}"],
                [
                    'question_text' => "{$label} ({$variantLabel})",
                    'question_type' => 'number',
                    'is_scored' => false,
                    'display_conditions' => ['question_code' => $gatingCode, 'operator' => 'equals', 'value' => 'Yes'],
                    'indent_level' => 1,
                    'order' => $startOrder + ($suffix === 'FUNCTIONAL' ? 1 : 2),
                    'is_active' => true,
                ]
            );
        }
    }
}
```

- [ ] **Step 4: Uncomment `InfrastructureSeeder::class` in the orchestrator's `$this->call([...])` list**

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=InfrastructureSeederTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Commit**

```bash
git add database/seeders/FacilityAssessment2026/InfrastructureSeeder.php \
        database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php \
        tests/Feature/FacilityAssessment2026/InfrastructureSeederTest.php
git commit -m "feat: seed the 2026 Infrastructure section incl. per-unit bed-capacity questions"
```

---

### Task 4: `SkillsLabSeeder`

**Files:**
- Create: `database/seeders/FacilityAssessment2026/SkillsLabSeeder.php`
- Test: `tests/Feature/FacilityAssessment2026/SkillsLabSeederTest.php`

**Interfaces:**
- Consumes: `INFRA_HAS_NICU` (Task 3), `AssessmentChecklist` "Skills Lab Checklist Requirements" (Task 2).
- Produces: `skills_lab` section, 21 questions (1 gate + 18 "yes" branch + 2 "no" branch).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\ChecklistsSeeder;
use Database\Seeders\FacilityAssessment2026\SkillsLabSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillsLabSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        $type = AssessmentType::create(['name' => 'Skills Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Skills Lab', 'code' => 'skills_lab',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 4, 'is_active' => true,
        ]);
        $this->seed(ChecklistsSeeder::class);

        return $type;
    }

    public function test_seeds_21_questions(): void
    {
        $type = $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'skills_lab')->first();
        $this->assertSame(21, $section->questions()->count());
    }

    public function test_yes_branch_questions_are_gated_on_skills_has_lab(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_POWER_OUTLETS')->firstOrFail();
        $this->assertSame(['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'], $q->display_conditions);
    }

    public function test_manikin_anne_additionally_requires_nicu(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_MANIKIN_ANNE')->firstOrFail();
        $this->assertSame('and', $q->display_conditions['operator']);
        $this->assertCount(2, $q->display_conditions['conditions']);
    }

    public function test_no_branch_questions_are_gated_on_skills_has_lab_no(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_NO_ROOM_SPACE')->firstOrFail();
        $this->assertSame(['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'No'], $q->display_conditions);
    }

    public function test_lockable_store_question_links_the_skills_lab_checklist(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_LOCKABLE_STORE')->firstOrFail();
        $this->assertSame('Skills Lab Checklist Requirements', $q->checklist->title);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SkillsLabSeederTest`
Expected: FAIL — seeder class doesn't exist.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class SkillsLabSeeder extends Seeder
{
    private const YES_ITEMS = [
        ['SKILLS_YES_POWER_OUTLETS', 'Does the Skills lab have at least 4 power outlets?', 'yes_no'],
        ['SKILLS_YES_POWER_BACKUP', 'Is there a power back up system? If yes Specify', 'select'],
        ['SKILLS_YES_HANDWASH_SINK', 'Does the skills lab have at least 1 hand washing sink with running water and soap', 'yes_no'],
        ['SKILLS_YES_VENTILATED', 'Is the space well ventilated?', 'yes_no'],
        ['SKILLS_YES_WELL_LIT', 'Is the space well lit?', 'yes_no'],
        ['SKILLS_YES_LOCKABLE_STORE', 'Does the skills lab have a lockable store or cabinet to safely maintain the skills lab essential supplies and equipment?', 'yes_no'],
        ['SKILLS_YES_FIRE_EXITS', 'Are there clearly marked fire exits and fire extinguishers?', 'yes_no'],
        ['SKILLS_YES_OFFICER_IN_CHARGE', 'Is there an officer in charge of the skills lab?', 'yes_no'],
        ['SKILLS_YES_BIOMED_MAINTENANCE', 'Is there a biomed assigned to do planned preventive maintainance and corrective maintainance?', 'yes_no'],
        ['SKILLS_YES_MONTHLY_REPORTS', 'Are there upto date monthly/quaterly reports showing activities/events held in the skills lab?', 'select'],
        ['SKILLS_YES_MANIKIN_CHILD', 'One child manikin with lungs that fill up when a BVM is used and feedback mechanism', 'yes_no'],
        ['SKILLS_YES_MANIKIN_INFANT', 'One infant manikin with lungs that fill up when a BVM is used?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_NEONATE', 'One neonate manikin with lungs that fill up when a BVM is used?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_PREMATURE', 'Premature mannikin - with open nose to aid in NGT insertion and is used to demonstrate use of plastic wraps and phototherapy', 'yes_no'],
        ['SKILLS_YES_MANIKIN_CPAP', 'One CPAP baby - has an open nose and mouth to aid in insertion of CPAP prongs and OGT?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_BREAST', 'Breast model able to simulate breast milk expression(mama breast)', 'yes_no'],
        ['SKILLS_YES_AIR_DEVICE', 'AIR device', 'yes_no'],
        // SKILLS_YES_MANIKIN_ANNE handled separately below (extra NICU condition).
    ];

    private const NO_ITEMS = [
        ['SKILLS_NO_ROOM_SPACE', 'Is there a room/space used for skills teaching and simulation?'],
        ['SKILLS_NO_LOCKABLE_STORAGE', 'Is there a lockable storage area for the equipment to be used in skills teaching and simulation?'],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'skills_lab')->firstOrFail();
        $checklist = AssessmentChecklist::where('assessment_type_id', $type->id)->where('title', 'Skills Lab Checklist Requirements')->first();

        $order = 1;

        AssessmentQuestion::updateOrCreate(
            ['assessment_section_id' => $section->id, 'question_code' => 'SKILLS_HAS_LAB'],
            ['question_text' => 'Is there a functional skills lab?', 'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0], 'order' => $order++, 'is_active' => true]
        );

        $yesCondition = ['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'];

        foreach (self::YES_ITEMS as [$code, $text, $questionType]) {
            $attrs = [
                'question_text' => $text,
                'question_type' => $questionType,
                'is_scored' => true,
                'scoring_map' => $questionType === 'yes_no' ? ['Yes' => 1, 'No' => 0] : null,
                'display_conditions' => $yesCondition,
                'order' => $order++,
                'is_active' => true,
            ];
            if ($questionType === 'select') {
                $attrs['options'] = $code === 'SKILLS_YES_POWER_BACKUP' ? ['Generator', 'Solar', 'Other'] : ['Monthly', 'Quarterly', 'Both'];
            }
            if ($code === 'SKILLS_YES_LOCKABLE_STORE') {
                $attrs['checklist_id'] = $checklist?->id;
            }

            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $code],
                $attrs
            );
        }

        AssessmentQuestion::updateOrCreate(
            ['assessment_section_id' => $section->id, 'question_code' => 'SKILLS_YES_MANIKIN_ANNE'],
            [
                'question_text' => 'Newborn Anne Manikin that can be intubated and has an umbilicus for UVC insertion',
                'question_type' => 'yes_no',
                'is_scored' => true,
                'scoring_map' => ['Yes' => 1, 'No' => 0],
                'display_conditions' => [
                    'operator' => 'and',
                    'conditions' => [
                        $yesCondition,
                        ['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'],
                    ],
                ],
                'order' => $order++,
                'is_active' => true,
            ]
        );

        $noCondition = ['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'No'];

        foreach (self::NO_ITEMS as [$code, $text]) {
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $code],
                [
                    'question_text' => $text,
                    'question_type' => 'yes_no',
                    'is_scored' => true,
                    'scoring_map' => ['Yes' => 1, 'No' => 0],
                    'display_conditions' => $noCondition,
                    'order' => $order++,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('  ✓ skills_lab: 21 questions (1 gate + 18 yes-branch + 2 no-branch).');
    }
}
```

- [ ] **Step 4: Uncomment `SkillsLabSeeder::class` in the orchestrator's `$this->call([...])` list**

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SkillsLabSeederTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Commit**

```bash
git add database/seeders/FacilityAssessment2026/SkillsLabSeeder.php \
        database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php \
        tests/Feature/FacilityAssessment2026/SkillsLabSeederTest.php
git commit -m "feat: seed the 2026 Skills Lab section with a)/b) branching"
```

---

### Task 5: `HumanResourcesSeeder` + `tots_count`

**Files:**
- Create: `database/migrations/2026_08_13_160000_add_tots_count_to_assessments.php`
- Create: `database/seeders/FacilityAssessment2026/HumanResourcesSeeder.php`
- Modify: `app/Models/Assessment.php` (fillable)
- Modify: `app/Filament/Resources/AssessmentResource/Pages/EditHumanResources.php` (add TOTs field, outside the per-cadre loop)
- Test: `tests/Feature/FacilityAssessment2026/HumanResourcesSeederTest.php`

**Interfaces:**
- Produces: `human_resources` section (0 questions — `structured_data` kind, matches 2025), 13 `MainCadre` rows type-scoped to 2026, `assessments.tots_count` nullable integer column.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentType;
use App\Models\MainCadre;
use Database\Seeders\FacilityAssessment2026\HumanResourcesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HumanResourcesSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        return AssessmentType::create(['name' => 'HR Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
    }

    public function test_seeds_13_cadres(): void
    {
        $type = $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $this->assertSame(13, MainCadre::where('assessment_type_id', $type->id)->count());
    }

    public function test_general_nurses_nbu_has_type_1_diabetes_na(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'General nurses NBU')->firstOrFail();
        $this->assertSame(['type_1_diabetes'], $cadre->na_training_columns);
    }

    public function test_maternity_theatre_anaesthetists_has_three_na_columns(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'Maternity theatre anaesthetists')->firstOrFail();
        $this->assertEqualsCanonicalizing(['comprehensive_newborn_care', 'imnci', 'type_1_diabetes'], $cadre->na_training_columns);
    }

    public function test_neonatologist_has_no_na_columns(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'Neonatologist')->firstOrFail();
        $this->assertEmpty($cadre->na_training_columns ?? []);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HumanResourcesSeederTest`
Expected: FAIL — seeder class doesn't exist.

- [ ] **Step 3: Add the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedInteger('tots_count')->nullable()->after('excluded_cadre_ids');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('tots_count');
        });
    }
};
```

Run: `php artisan migrate`

In `app/Models/Assessment.php`, add `'tots_count'` to `$fillable` (after `'excluded_cadre_ids'`).

- [ ] **Step 4: Write the seeder**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentType;
use App\Models\MainCadre;
use Illuminate\Database\Seeder;

class HumanResourcesSeeder extends Seeder
{
    private const CADRES = [
        ['Neonatologist', []],
        ['Paediatrician', []],
        ['Medical officer', []],
        ['General nurses NBU', ['type_1_diabetes']],
        ['Neonatal nurses', ['type_1_diabetes']],
        ['General nurses-paediatric', []],
        ['Paediatric nurses', []],
        ['Clinical officer paediatric', []],
        ['Clinical officer', []],
        ['Maternity theatre anaesthetists', ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']],
        ['Maternity theatre nurses', ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']],
        ['Midwives', ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']],
        ['Post natal ward nurses', ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        foreach (self::CADRES as $order => [$name, $naColumns]) {
            MainCadre::updateOrCreate(
                ['assessment_type_id' => $type->id, 'name' => $name],
                ['order' => $order + 1, 'is_active' => true, 'na_training_columns' => $naColumns ?: null]
            );
        }

        $this->command->info('  ✓ human_resources: 13 cadres seeded (tots_count captured on the Assessment record directly).');
    }
}
```

- [ ] **Step 5: Add the TOTs field to `EditHumanResources`**

In `app/Filament/Resources/AssessmentResource/Pages/EditHumanResources.php`, in `form()`, add a standalone field before the `Section::make('Human Resources Assessment')` block:

```php
        return $form->schema([
            Forms\Components\TextInput::make('tots_count')
                ->label('No of TOTs in the facility')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default($this->record->tots_count)
                ->columnSpanFull(),
            Forms\Components\Section::make('Human Resources Assessment')
```

In `loadSavedResponses()`, add `'tots_count' => $this->record->tots_count,` to the returned array (merge, don't replace, the existing per-cadre `hr_*` keys). In `mutateFormDataBeforeSave()`, persist and strip it:

```php
        $this->record->update(['tots_count' => $data['tots_count'] ?? null]);
        unset($data['tots_count']);
```

(placed alongside the existing `section_progress` update, before the `foreach ($data as $key => $value)` cleanup loop).

- [ ] **Step 6: Uncomment `HumanResourcesSeeder::class` in the orchestrator's `$this->call([...])` list**

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=HumanResourcesSeederTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Run the existing EditHumanResources tests to confirm the new field doesn't break anything**

Run: `php artisan test --filter=HumanResources`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_13_160000_add_tots_count_to_assessments.php \
        database/seeders/FacilityAssessment2026/HumanResourcesSeeder.php \
        database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php \
        app/Models/Assessment.php app/Filament/Resources/AssessmentResource/Pages/EditHumanResources.php \
        tests/Feature/FacilityAssessment2026/HumanResourcesSeederTest.php
git commit -m "feat: seed the 2026 Human Resources cadre list with per-cell N/A + capture TOTs count"
```

---

### Task 6: `HealthProductsSeeder`

**Files:**
- Create: `database/seeders/FacilityAssessment2026/HealthProductsSeeder.php`
- Test: `tests/Feature/FacilityAssessment2026/HealthProductsSeederTest.php`

**Interfaces:**
- Consumes: `INFRA_HAS_NICU` (Task 3).
- Produces: `health_products` section, 5 `AssessmentDepartment`, 8 `CommodityCategory`, 156 `Commodity` rows (40 AIRWAY + 33 CIRCULATION + 8 DISABILITY + 4 EXPOSURE + 12 IPC + 4 NUTRITION + 45 MEDICINE/DRUGS + 14 OTHERS — recounted directly against the `CATEGORIES` array below, one commodity per split-list item), every commodity attached to all 5 departments.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentDepartment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use Database\Seeders\FacilityAssessment2026\HealthProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthProductsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        $type = AssessmentType::create(['name' => 'HP Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
        $infraSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 2, 'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $infraSection->id, 'question_code' => 'INFRA_HAS_NICU',
            'question_text' => 'Do you have a NICU', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 6, 'is_active' => true,
        ]);

        return $type;
    }

    public function test_seeds_5_departments_and_8_categories(): void
    {
        $type = $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $this->assertSame(5, AssessmentDepartment::where('assessment_type_id', $type->id)->count());
        $this->assertSame(8, CommodityCategory::where('assessment_type_id', $type->id)->count());
        $this->assertFalse(CommodityCategory::where('assessment_type_id', $type->id)->where('name', 'NICU/PICU')->exists());
    }

    public function test_airway_category_has_40_commodities(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $category = CommodityCategory::where('name', 'AIRWAY')->firstOrFail();
        $this->assertSame(40, Commodity::where('commodity_category_id', $category->id)->count());
    }

    public function test_suction_catheters_split_into_4_lettered_commodities(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $items = Commodity::where('group_label', 'Suction catheters size')->orderBy('order')->get();
        $this->assertCount(4, $items);
        $this->assertTrue($items->every(fn ($c) => $c->indent_level === 1));
        $this->assertEqualsCanonicalizing(['Fr-6', 'Fr-8', 'Fr-10', 'Fr12'], $items->pluck('name')->all());
    }

    public function test_ett_sizes_are_gated_on_nicu(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $items = Commodity::where('group_label', 'ETT')->get();
        $this->assertCount(8, $items);
        $this->assertTrue($items->every(fn ($c) => $c->display_conditions === ['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes']));
    }

    public function test_magill_forceps_is_individually_nicu_gated_not_category_gated(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $magill = Commodity::where('name', 'Magill forceps')->firstOrFail();
        $this->assertSame(['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'], $magill->display_conditions);
        $this->assertSame('AIRWAY', $magill->category->name);
    }

    public function test_preterm_supplements_has_no_display_conditions(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $items = Commodity::where('group_label', 'Preterm supplements')->get();
        $this->assertCount(5, $items);
        $this->assertTrue($items->every(fn ($c) => $c->display_conditions === null));
    }

    public function test_surfactant_and_midazolam_are_nicu_gated(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        foreach (['surfactant', 'Midazolam'] as $name) {
            $c = Commodity::where('name', $name)->firstOrFail();
            $this->assertSame(['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'], $c->display_conditions);
        }
    }

    public function test_every_commodity_is_applicable_to_all_5_departments(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $commodity = Commodity::where('name', 'Stethoscope')->first();
        $this->assertSame(5, $commodity->applicableDepartments()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);
        $countBefore = Commodity::count();
        $this->seed(HealthProductsSeeder::class);

        $this->assertSame($countBefore, Commodity::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HealthProductsSeederTest`
Expected: FAIL — seeder class doesn't exist.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentDepartment;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HealthProductsSeeder extends Seeder
{
    private const DEPARTMENTS = ['Skills lab', 'NBU', 'Maternity', 'Theatre', 'Paediatric ward'];

    private const NICU_GATE = ['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'];

    /**
     * Category => ordered list of rows. A row is either:
     *   - a plain string (single commodity, no split)
     *   - [groupLabel, [item, item, ...]] (split into lettered/indented commodities)
     *   - [name, 'nicu'] (single commodity, individually NICU-gated)
     *   - [groupLabel, [item, ...], 'nicu'] (split AND NICU-gated on every item)
     */
    private const CATEGORIES = [
        'AIRWAY' => [
            'Functional suction machine (including ability for pressure adjustment)',
            ['Suction catheters size', ['Fr-6', 'Fr-8', 'Fr-10', 'Fr12']],
            'Penguin Sucker',
            ['Oropharyngeal Airway of appropriate sizes', ['00', '0', '1', '2', '3', '4']],
            ['ETT', ['2.5', '3.0', '3.5', '4.0', '4.5', '5.0', '5.5', '6.0'], 'nicu'],
            ['Magill forceps', 'nicu'],
            ['Umbilical vein catheters', 'nicu'],
            ['Umbilical artery catheters', 'nicu'],
            ['Oxygen source', ['piped', 'Cylinder', 'Concentrator']],
            'Can each child receive oxygen individually. If no, are there Oxygen splitters',
            'BVM device 200-300mls',
            'BVM device 500ml and 750ml',
            ["BVM masks' sizes", ['00', '0', '1']],
            'neonatal non rebreather mask',
            'Pulse oximeter with neonatal probes and paediatric probes',
            'Neonatal nasal prongs',
            'Paediatric non rebreather masks',
            'Do you have CPAP. If yes: Number of complete functional CPAP units, Are accessories available',
            'Metered Dose Inhaler',
            'Spacer and mask',
            'Paediatric Nebulising Kit',
        ],
        'CIRCULATION' => [
            'Stethoscope',
            'Patient monitor with neonatal cuffs',
            'Cardiac monitor with paediatric BP cuffs',
            ['IV cannulas-Gauge', ['26', '24', '22']],
            ['Syringes', ['2cc', '5cc', '10cc', '20cc']],
            ['Needles', ['G21', 'G22', 'G23', 'G24', 'G25']],
            'Intraosseuos needle or bone marrow needle 15-18G',
            '3-way stop cock',
            'Solusets',
            'giving sets',
            'blood transfusion set',
            'Perfuser lines',
            ['Sample bottles', ['EDTA', 'Biochemistry', 'Blood culture bottle', 'urine', 'stool', 'CSF bottles']],
            'IV line dressing (transparent)',
            'Medical adhesive',
            ['Urinary catheters', ['4', '6']],
            'Urine bag',
            'Stethoscope',
        ],
        'DISABILITY' => [
            'Functional glucometer with strips',
            'Lancets',
            ['NG tube (newborn sizes)', ['4', '5', '6']],
            ['NG tube', ['8', '10', '12']],
        ],
        'EXPOSURE' => [
            'Digital Thermometer',
            'Radiant warmer/rescuscitaire with a temperature probe',
            '2 dry baby wraps/towel',
            'Plastic wrap',
        ],
        'INFECTION PREVENTION AND CONTROL (IPC)' => [
            'Hand washing station with clean running water and liquid soap',
            ['Gloves', ['Clean', 'Sterile']],
            'Alcohol Hand Rub (at least 70% alcohol)',
            'Surgical spirit',
            'Alcohol Swabs',
            'Sharps box',
            ['Colour-coded waste disposal bins with appropriate liners. At least', ['Yellow', 'Black', 'Red']],
            'Are handwashing audits done (verify using report)',
            'Are decontamination buckets well labelled with date and time indicated(observe)',
        ],
        'NUTRITION ASSESSMENT' => [
            'MUAC Tape',
            'Weighing Scale',
            'Infantometer and Stadiometer',
            'Tape measure',
        ],
        'MEDICINE/DRUGS' => [
            'Adrenaline',
            'Vitamin K 2mg',
            'TEO',
            'Chlorhexidine digluconate 7.1%',
            'Caffeine citrate',
            ['surfactant', 'nicu'],
            ['Preterm supplements', ['Multivitamins', 'Vitamin D 400 IU', 'Folate tabs', 'Iron', 'Calcium']],
            'Phenobarbital',
            ['Midazolam', 'nicu'],
            'Diazepam',
            'Leviteracetam',
            'Phenytoin',
            'Artesunate',
            'Crystalline penicilln',
            'Gentamycin',
            'Ceftriaxone',
            'Ceftazidime/cefepime/cefotaxime',
            'Amoxicillin DT',
            'Metronidazole',
            'Amikacin',
            'AL tablets',
            'Paracetamol',
            'Lasix',
            '10%Dextrose',
            'Ringer lactate',
            '50% Dextrose',
            'Normal saline',
            'Water for injection',
            'KCl',
            'Resomal',
            'Zinc sulphate/ORS-copack',
            ['Therapeutic feeds', ['F75', 'F100', 'RUTF']],
            ['Insulin', ['Soluble insulin', 'long acting insulin']],
            ['Salbutamol respirator solution', ['Salbutamol inhaler']],
            'Prednisone',
            'Budesonide inhaler',
            'Ipratropium bromide',
            'Distilled water',
        ],
        'OTHERS' => [
            'Room thermometer',
            'Wall clock',
            'Pen Torch',
            'Reference material (Guidelines, Drug index)',
            'complete MOH newborn inpatient file (EMR/ Physical)',
            'Calibrated cup and saucer',
            'Nifty cup',
            'flannels',
            'Space heater for warmth',
            'Phototherapy machine with a light meter',
            'Procedure tray',
            'Kidney dish',
            'Bed/ couch and Linen',
            'Food colour- red and green',
        ],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        $departments = collect(self::DEPARTMENTS)->map(fn ($name, $order) => AssessmentDepartment::updateOrCreate(
            ['assessment_type_id' => $type->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'order' => $order + 1, 'is_active' => true]
        ));
        $departmentIds = $departments->pluck('id')->all();

        $categoryOrder = 0;
        foreach (self::CATEGORIES as $categoryName => $rows) {
            $categoryOrder++;
            $category = CommodityCategory::updateOrCreate(
                ['assessment_type_id' => $type->id, 'slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'order' => $categoryOrder]
            );

            $order = 0;
            foreach ($rows as $row) {
                $order = $this->seedRow($category, $row, $order, $departmentIds);
            }
        }

        $this->command->info('  ✓ health_products: 5 departments, 8 categories, '.Commodity::whereIn('commodity_category_id', CommodityCategory::where('assessment_type_id', $type->id)->pluck('id'))->count().' commodities.');
    }

    private function seedRow(CommodityCategory $category, mixed $row, int $order, array $departmentIds): int
    {
        // Single item, plain: 'Name'
        if (is_string($row)) {
            $this->createCommodity($category, $row, null, 0, ++$order, null, $departmentIds);

            return $order;
        }

        // Single item, NICU-gated: ['Name', 'nicu']
        if (count($row) === 2 && $row[1] === 'nicu') {
            $this->createCommodity($category, $row[0], null, 0, ++$order, self::NICU_GATE, $departmentIds);

            return $order;
        }

        // Split group: [groupLabel, [items]] or [groupLabel, [items], 'nicu']
        [$groupLabel, $items] = $row;
        $nicuGated = ($row[2] ?? null) === 'nicu';

        foreach ($items as $item) {
            $this->createCommodity($category, $item, $groupLabel, 1, ++$order, $nicuGated ? self::NICU_GATE : null, $departmentIds);
        }

        return $order;
    }

    private function createCommodity(CommodityCategory $category, string $name, ?string $groupLabel, int $indentLevel, int $order, ?array $displayConditions, array $departmentIds): void
    {
        $commodity = Commodity::updateOrCreate(
            ['commodity_category_id' => $category->id, 'name' => $name, 'group_label' => $groupLabel],
            [
                'indent_level' => $indentLevel,
                'order' => $order,
                'is_active' => true,
                'display_conditions' => $displayConditions,
            ]
        );

        $commodity->applicableDepartments()->sync($departmentIds);
    }
}
```

- [ ] **Step 4: Uncomment `HealthProductsSeeder::class` in the orchestrator's `$this->call([...])` list**

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=HealthProductsSeederTest`
Expected: PASS (8 tests). If a per-category count assertion fails, recount that category's `CATEGORIES` array entries against the design doc's row-by-row list (`docs/superpowers/specs/2026-08-13-facility-assessment-2026-phase2-design.md`) rather than adjusting the test to match unverified seeder output.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/FacilityAssessment2026/HealthProductsSeeder.php \
        database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php \
        tests/Feature/FacilityAssessment2026/HealthProductsSeederTest.php
git commit -m "feat: seed the 2026 Health Products departments, categories, and commodities"
```

---

### Task 7: `InformationSystemsSeeder`

**Files:**
- Create: `database/seeders/FacilityAssessment2026/InformationSystemsSeeder.php`
- Test: `tests/Feature/FacilityAssessment2026/InformationSystemsSeederTest.php`

**Interfaces:**
- Produces: `information_systems` section, 61 questions: 2 (doc type + paper-based availability) + 44 (22 MoH forms × Available/Complete) + 3 (KHIS upload, KHIS responsible, uses-EMR gate) + 7 (5 EMR reports + EMR access + EMR KHIS upload) + 5 (attendance register, assessment records, feedback mechanism, mentorship data entry, internet).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\InformationSystemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformationSystemsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        $type = AssessmentType::create(['name' => 'InfoSys Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Information Systems', 'code' => 'information_systems',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 7, 'is_active' => true,
        ]);

        return $type;
    }

    public function test_seeds_61_questions(): void
    {
        $type = $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'information_systems')->first();
        $this->assertSame(61, $section->questions()->count());
    }

    public function test_moh_form_pair_shares_a_table_group(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $available = AssessmentQuestion::where('question_code', 'MOH_204A_AVAILABLE')->firstOrFail();
        $complete = AssessmentQuestion::where('question_code', 'MOH_204A_COMPLETE')->firstOrFail();

        $this->assertSame($available->group, $complete->group);
        $this->assertCount(3, explode('|', $available->group));
    }

    public function test_emr_report_questions_are_gated_on_uses_emr(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'INFOSYS_EMR_REPORT_711')->firstOrFail();
        $this->assertSame(['question_code' => 'INFOSYS_USES_EMR', 'operator' => 'equals', 'value' => 'Yes'], $q->display_conditions);
    }

    public function test_attendance_register_help_text_notes_it_is_new_for_2026(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'INFOSYS_ATTENDANCE_REGISTER')->firstOrFail();
        $this->assertStringContainsString('Does Not appear in baseline', $q->help_text);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InformationSystemsSeederTest`
Expected: FAIL — seeder class doesn't exist.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class InformationSystemsSeeder extends Seeder
{
    private const MOH_FORMS = [
        ['MOH_204A', 'MoH 204 A: Out- Patient register'],
        ['NCD_REGISTER', 'NCD Register'],
        ['MOH_661_NEONATAL', 'MoH 661 Neonatal death notification'],
        ['MOH_670', 'MoH 670 Child and Adolescent death notification'],
        ['NEONATAL_CHILD_DEATH_REGISTER', 'Neonatal Child and Adolescent death register'],
        ['MOH_282', 'MoH 282 ORT Corner register'],
        ['MOH_671', 'MoH 671 child and adolescent death review forms'],
        ['MORTALITY_LINE_LIST', 'Neonatal Child and Adolescent mortality line list'],
        ['MOH_511', 'MoH 511 CWC register'],
        ['MOH_333', 'MoH 333: Maternity Register'],
        ['KMC_REGISTER', 'KMC Register'],
        ['THEATRE_MATERNITY_REGISTER', 'Theatre Maternity register'],
        ['MOH_373', 'MoH 373: Neonatal Inpatient register'],
        ['MOH_301', 'MoH 301: In-Patient admission Register'],
        ['MOH_378', 'MoH 378 Neonatal admission file'],
        ['MOH_379', 'MoH 379 Paediatric admision file'],
        ['MOH_377', 'MoH 377: Paediatric Admission register'],
        ['MOH_711', 'MoH 711 Integrated summary Tool'],
        ['MOH_661_DEATH_NOTIFICATION', 'MoH 661: Neonatal death notification form'],
        ['D1_DEATH_NOTIFICATION', 'D1 -Death Notification'],
        ['B1_BIRTH_NOTIFICATION', 'B1 -Birth Notification'],
        ['MORTALITY_REGISTER', 'Mortality registers/record for mortalities (Death register)'],
        ['MONTHLY_SUMMARY_NEWBORNS', 'Montlhy summary forms for Newborns'],
        ['MONTHLY_SUMMARY_PAEDIATRICS', 'Monthly summary forms for paediatrics'],
    ];

    private const EMR_REPORTS = [
        ['INFOSYS_EMR_REPORT_711', 'MoH 711 Integrated summary Tool'],
        ['INFOSYS_EMR_REPORT_NEONATAL_MORTALITY', 'Neonatal Mortality Summary'],
        ['INFOSYS_EMR_REPORT_MORTALITY_LINE_LIST', 'Neonatal Child and Adolescent mortality line list'],
        ['INFOSYS_EMR_REPORT_B1', 'B1 -Birth Notification'],
        ['INFOSYS_EMR_REPORT_D1', 'D1 -Death Notification'],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'information_systems')->firstOrFail();

        $order = 0;
        $create = function (array $attrs) use ($section, &$order) {
            $order++;
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $attrs['question_code']],
                array_merge(['question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0], 'requires_explanation_on' => ['No'], 'order' => $order, 'is_active' => true], $attrs)
            );
        };

        $create(['question_code' => 'INFOSYS_DOC_TYPE', 'question_text' => 'What type of documentation is the facility using', 'question_type' => 'select', 'options' => ['Paper based', 'EMR', 'Hybrid'], 'scoring_map' => null]);
        $create(['question_code' => 'INFOSYS_PAPER_AVAIL_COMPLETE', 'question_text' => 'If paper based/Hybrid ask about the availability and completeness of standardized data collection and summary tools', 'display_conditions' => ['question_code' => 'INFOSYS_DOC_TYPE', 'operator' => 'in', 'value' => ['Paper based', 'Hybrid']]]);

        foreach (self::MOH_FORMS as [$codePrefix, $formName]) {
            $group = "Data Collection Tools & Registers|Form|{$formName}";
            $create(['question_code' => "{$codePrefix}_AVAILABLE", 'question_text' => 'Available', 'group' => $group]);
            $create(['question_code' => "{$codePrefix}_COMPLETE", 'question_text' => 'Complete', 'group' => $group]);
        }

        $create(['question_code' => 'INFOSYS_KHIS_UPLOAD', 'question_text' => 'Is data uploaded to KHIS']);
        $create(['question_code' => 'INFOSYS_KHIS_RESPONSIBLE', 'question_text' => 'Is there a person responsible for neonatal data entry into the KHIS Tracker?']);
        $create(['question_code' => 'INFOSYS_USES_EMR', 'question_text' => 'If the facility is using EMR: If Yes']);

        $emrCondition = ['question_code' => 'INFOSYS_USES_EMR', 'operator' => 'equals', 'value' => 'Yes'];
        foreach (self::EMR_REPORTS as [$code, $reportName]) {
            $create(['question_code' => $code, 'question_text' => "Does the EMR generate the following Reports: {$reportName}", 'display_conditions' => $emrCondition]);
        }
        $create(['question_code' => 'INFOSYS_EMR_ACCESS', 'question_text' => 'Does the EMR allow access to the patient records to verify Information', 'display_conditions' => $emrCondition]);
        $create(['question_code' => 'INFOSYS_EMR_KHIS_UPLOAD', 'question_text' => 'Is data uploaded to KHIS', 'display_conditions' => $emrCondition]);

        $create(['question_code' => 'INFOSYS_ATTENDANCE_REGISTER', 'question_text' => 'Is there an upto date attendance register showing the date, time, mentees name & contact, and skills to be taught? (check)', 'help_text' => 'Does Not appear in baseline']);
        $create(['question_code' => 'INFOSYS_ASSESSMENT_RECORDS', 'question_text' => 'Is there an upto date record of all assessments done - which mentees, which area of assessment, by whom, recommendations after assessments (check)', 'help_text' => 'Does Not appear in baseline']);
        $create(['question_code' => 'INFOSYS_FEEDBACK_MECHANISM', 'question_text' => 'Is there a mechanism to collect feedback on the mentorship program(feedback forms,compliment / complaint register)']);
        $create(['question_code' => 'INFOSYS_MENTORSHIP_DATA_ENTRY', 'question_text' => 'Is there a person responsible for mentorship data entry into the electronic platform?']);
        $create(['question_code' => 'INFOSYS_INTERNET', 'question_text' => 'Is there internet Availability?']);

        $this->command->info("  ✓ information_systems: {$order} questions (incl. 22 MoH-form Available/Complete pairs).");
    }
}
```

- [ ] **Step 4: Uncomment `InformationSystemsSeeder::class` in the orchestrator's `$this->call([...])` list**

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=InformationSystemsSeederTest`
Expected: PASS (4 tests) — 61 questions total (see the Interfaces line above for the breakdown).

- [ ] **Step 6: Commit**

```bash
git add database/seeders/FacilityAssessment2026/InformationSystemsSeeder.php \
        database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php \
        tests/Feature/FacilityAssessment2026/InformationSystemsSeederTest.php
git commit -m "feat: seed the 2026 Information Systems section incl. MoH-forms table"
```

---

### Task 8: `QualityOfCareSeeder`

**Files:**
- Create: `database/seeders/FacilityAssessment2026/QualityOfCareSeeder.php`
- Test: `tests/Feature/FacilityAssessment2026/QualityOfCareSeederTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\QualityOfCareSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityOfCareSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        $type = AssessmentType::create([
            'name' => 'QoC Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true,
            'metadata' => ['parameters' => ['quality_of_care_timeline' => 'Neonates 7–28 days']],
        ]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Quality of Care', 'code' => 'quality_of_care',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 8, 'is_active' => true,
        ]);

        return $type;
    }

    public function test_seeds_6_questions(): void
    {
        $type = $this->makeType();
        $this->seed(QualityOfCareSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'quality_of_care')->first();
        $this->assertSame(6, $section->questions()->count());
        $this->assertSame('Select agreed timelines: {{quality_of_care_timeline}}', $section->description);
        $this->assertSame('Select agreed timelines: Neonates 7–28 days', $type->interpolate($section->description));
    }

    public function test_moh527_is_indented_under_neonatal_audits(): void
    {
        $this->makeType();
        $this->seed(QualityOfCareSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'QOC_NEONATAL_MOH527')->firstOrFail();
        $this->assertSame(1, $q->indent_level);
        $this->assertNull($q->group);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QualityOfCareSeederTest`
Expected: FAIL — seeder class doesn't exist.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class QualityOfCareSeeder extends Seeder
{
    private const QUESTIONS = [
        ['QOC_NEONATAL_AUDITS', 'Are audits conducted to review neonatal deaths (Verify using audit minutes)', 0],
        ['QOC_NEONATAL_MOH527', 'Are they documented on the Neonatal death review form MoH 527', 1],
        ['QOC_NEONATAL_KHIS_UPLOAD', 'Is the Neonatal death audit form uploaded to KHIS', 1],
        ['QOC_NEONATAL_ACTION_POINTS', 'Were the action points from the audit Implemented', 1],
        ['QOC_CHILD_AUDITS', 'Are audits conducted to review child deaths at least once a month (Verify using audit minutes)', 0],
        ['QOC_CHILD_REGISTER', 'Are they documented on the paediatric register', 1],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'quality_of_care')->firstOrFail();

        $section->update(['description' => 'Select agreed timelines: {{quality_of_care_timeline}}']);

        foreach (self::QUESTIONS as $order => [$code, $text, $indent]) {
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $code],
                [
                    'question_text' => $text,
                    'question_type' => 'yes_no',
                    'is_scored' => true,
                    'scoring_map' => ['Yes' => 1, 'No' => 0],
                    'requires_explanation_on' => ['No'],
                    'indent_level' => $indent,
                    'order' => $order + 1,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('  ✓ quality_of_care: 6 questions.');
    }
}
```

- [ ] **Step 4: Uncomment `QualityOfCareSeeder::class` in the orchestrator's `$this->call([...])` list**

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=QualityOfCareSeederTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/seeders/FacilityAssessment2026/QualityOfCareSeeder.php \
        database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php \
        tests/Feature/FacilityAssessment2026/QualityOfCareSeederTest.php
git commit -m "feat: seed the 2026 Quality of Care section using the interpolated timeline parameter"
```

---

### Task 9: `IndicatorsSeeder`

**Files:**
- Create: `database/seeders/FacilityAssessment2026/IndicatorsSeeder.php`
- Test: `tests/Feature/FacilityAssessment2026/IndicatorsSeederTest.php`

**Interfaces:**
- Consumes: `INFOSYS_EMR_ACCESS` (Task 7).
- Produces: `newborn_paediatric_indicators` section (new, `is_scored: false`), 29 `number` questions.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\IndicatorsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicatorsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        return AssessmentType::create(['name' => 'Ind Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
    }

    public function test_creates_the_section_unscored(): void
    {
        $type = $this->makeType();
        $this->seed(IndicatorsSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'newborn_paediatric_indicators')->firstOrFail();
        $this->assertFalse($section->is_scored);
        $this->assertSame(29, $section->questions()->count());
    }

    public function test_all_questions_are_number_type_and_unscored(): void
    {
        $this->makeType();
        $this->seed(IndicatorsSeeder::class);

        $questions = AssessmentQuestion::whereHas('section', fn ($q) => $q->where('code', 'newborn_paediatric_indicators'))->get();
        $this->assertTrue($questions->every(fn ($q) => $q->question_type === 'number' && $q->is_scored === false));
    }

    public function test_o2sat_and_headtotoe_are_gated_on_emr_access(): void
    {
        $this->makeType();
        $this->seed(IndicatorsSeeder::class);

        foreach (['IND_NEWBORN_O2SAT_TAKEN', 'IND_NEWBORN_HEADTOTOE'] as $code) {
            $q = AssessmentQuestion::where('question_code', $code)->firstOrFail();
            $this->assertSame(['question_code' => 'INFOSYS_EMR_ACCESS', 'operator' => 'equals', 'value' => 'Yes'], $q->display_conditions);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=IndicatorsSeederTest`
Expected: FAIL — seeder class and section don't exist.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class IndicatorsSeeder extends Seeder
{
    private const EMR_GATE = ['question_code' => 'INFOSYS_EMR_ACCESS', 'operator' => 'equals', 'value' => 'Yes'];

    private const NEWBORN = [
        ['IND_NEWBORN_ADMISSIONS', 'Total number of newborn admissions for the last Quarter (Current agreed period)', 0],
        ['IND_NEWBORN_HYPOTHERMIA', 'Total number of newborns with hypothermia at admission (temp <36.5)', 0],
        ['IND_NEWBORN_O2SAT_TAKEN', 'Total number of newborns who had their oxygen saturation taken at admission (sample 30 newborn files)', 1],
        ['IND_NEWBORN_RBS_TAKEN', 'Total number of admitted newborns who had their RBS taken at admission (sample 30 newborn files)', 0],
        ['IND_NEWBORN_HEADTOTOE', 'Total number of newborns who had a head to toe exam done and recorded in the newborn file(sample 30 newborn files)', 1],
        ['IND_NEWBORN_BIRTH_ASPHYXIA', 'Total number of newborns who had a diagnosis of birth asphyxia (Total admissions for the month)', 0],
        ['IND_NEWBORN_LT34_ADMISSIONS', 'Total number of newborns <34 weeks admitted in the last complete month?', 0],
        ['IND_NEWBORN_LT34_CAFFEINE', 'Total number of newborns <34 weeks initiated on caffeine citrate last complete month?', 0],
        ['IND_NEWBORN_ANTENATAL_CORTICOSTEROIDS', 'Total number of mothers with preterm newborns <34 weeks gestation who received at least one dose of antenatal corticosteroids (Baby\'s admission notes)', 0],
        ['IND_NEWBORN_LT32_ADMISSIONS', 'Total number of newborns <32 weeks admitted in the last complete month', 0],
        ['IND_NEWBORN_LT32_CPAP', 'Total number of newborns <32 weeks initiated on CPAP last complete month (inpatient newborn register)', 0],
        ['IND_NEWBORN_LT2500G_KMC', 'Total number of newborns < 2500g initiated on KMC last complete month (KMC register)', 0],
        ['IND_NEWBORN_KMC_WITHIN_2HRS', 'Total number of newborns initiated on KMC within 2 hours after birth last complete month (KMC register)', 0],
        ['IND_NEWBORN_KMC_DURING_STAY', 'Total number of newborns initiated on KMC during their hospital stay during the last complete month (KMC register)', 0],
    ];

    private const PAEDIATRIC = [
        ['IND_PAED_ADMISSIONS', 'Total number of paediatric admissions for the last complete month) (Paediatric admission file)', 0],
        ['IND_PAED_O2SAT_TAKEN', 'How many had an oxygen saturation taken at admission?', 0],
        ['IND_PAED_HYPOXEMIA', 'Of these, how many had an oxygen saturation less than <90%?', 1],
        ['IND_PAED_HYPOXEMIA_OXYGEN_STARTED', 'Of the ones with SPO2 <90%, how many were started on oxygen?', 1],
        ['IND_PAED_SEVERE_PNEUMONIA_OXYGEN', 'Total Number of children under 5 years with severe pneumonia initiated on oxygen', 0],
        ['IND_PAED_SEVERE_PNEUMONIA_ANTIBIOTICS', 'Total Number of children under 5 years with severe pneumonia initiated on Benzyl Penicillin and Gentamicin', 0],
        ['IND_PAED_PNEUMONIA_AMOXICILLIN', 'Total Number of children under 5 years with pneumonia initiated on Amoxicillin DT', 0],
        ['IND_PAED_SEVERE_PNEUMONIA_DEATHS', 'Total Number of children under 5 years with severe pneumonia who died', 0],
        ['IND_PAED_DIARRHOEA_ORS', 'Total Number of children under 5 years with diarrhoea treated with ORS/Zinc co-pack( MoH 204A)', 0],
        ['IND_PAED_HYPOVOLEMIC_SHOCK', 'Total Number of children under 5 years with hypovolemic shock due to diarrhoea treated with correct volume of isotonic fluid (Paediatric admission file)', 0],
        ['IND_PAED_RBS', 'Total Number of children under 5 years admitted with an RBS measurement (Paediatric admission file)', 0],
        ['IND_PAED_MALNUTRITION_INPATIENT', 'Total Number of children under 5 years screened for malnutrition (MUAC/WHZ/nutritional oedema) in the inpatient department (Paediatric admission file)', 0],
        ['IND_PAED_MALNUTRITION_OUTPATIENT', 'Total Number of children under 5 years screened for malnutrition (MUAC/WHZ/nutritional oedema) in the outpatient department (MoH 511 CWC)', 0],
        ['IND_PAED_T1DM_BASAL_BOLUS', 'Total Number of patients aged 0-18 years with type 1 DM on basal bolus regimen (NCD register)', 0],
        ['IND_PAED_DKA_DEATHS', 'Total Number of childen aged 0-18 years admitted with DKA who died (Paediatric admission file) and Inpatient admission file', 0],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        $section = AssessmentSection::updateOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'newborn_paediatric_indicators'],
            ['name' => 'Newborn & Paediatric Indicators', 'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => false, 'order' => 9, 'is_active' => true]
        );

        $order = 0;
        foreach ([...self::NEWBORN, ...self::PAEDIATRIC] as [$code, $text, $indent]) {
            $order++;
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $code],
                [
                    'question_text' => $text,
                    'question_type' => 'number',
                    'is_scored' => false,
                    'indent_level' => $indent,
                    'display_conditions' => in_array($code, ['IND_NEWBORN_O2SAT_TAKEN', 'IND_NEWBORN_HEADTOTOE'], true) ? self::EMR_GATE : null,
                    'order' => $order,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info("  ✓ newborn_paediatric_indicators: {$order} questions (unscored).");
    }
}
```

- [ ] **Step 4: Uncomment `IndicatorsSeeder::class` in the orchestrator's `$this->call([...])` list — the orchestrator's full list should now include every seeder, in the order shown in Task 1 Step 3**

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=IndicatorsSeederTest`
Expected: PASS (3 tests). 14 newborn + 15 paediatric = 29, matching the section-count assertion.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/FacilityAssessment2026/IndicatorsSeeder.php \
        database/seeders/FacilityAssessment2026/FacilityAssessment2026Seeder.php \
        tests/Feature/FacilityAssessment2026/IndicatorsSeederTest.php
git commit -m "feat: seed the new 2026 Newborn & Paediatric Indicators section"
```

---

### Task 10: End-to-end integration test

**Files:**
- Test: `tests/Feature/FacilityAssessment2026/FacilityAssessment2026EndToEndTest.php`

**Interfaces:**
- Consumes: everything from Tasks 0–9.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Database\Seeders\FacilityAssessment2026\FacilityAssessment2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacilityAssessment2026EndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'E2E 2026 Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    public function test_full_seeder_run_produces_a_working_assessment_with_live_nicu_gating(): void
    {
        $this->seed(FacilityAssessment2026Seeder::class);
        $assessor = $this->makeAssessor();
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);

        // Infrastructure renders and NICU/PICU-gated bed-capacity fields are
        // hidden before HAS_NICU is answered.
        $infraUrl = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => 'infrastructure']);
        $this->get($infraUrl)->assertOk();

        $hasNicu = AssessmentQuestion::where('question_code', 'INFRA_HAS_NICU')->firstOrFail();
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $hasNicu->id, 'response_value' => 'No',
        ]);

        // Skills Lab: Newborn Anne Manikin question requires NICU=Yes AND
        // skills-lab=Yes — with NICU=No it must be excluded from scoring
        // regardless of the skills-lab answer.
        $hasLab = AssessmentQuestion::where('question_code', 'SKILLS_HAS_LAB')->firstOrFail();
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $hasLab->id, 'response_value' => 'Yes', 'score' => 1,
        ]);
        \App\Services\DynamicScoringService::recalculateSectionScore($assessment->id, $hasLab->section_id);
        $manikinAnne = AssessmentQuestion::where('question_code', 'SKILLS_YES_MANIKIN_ANNE')->firstOrFail();
        $this->assertNull(AssessmentQuestionResponse::where('assessment_id', $assessment->id)->where('assessment_question_id', $manikinAnne->id)->first());

        // Health Products: the NICU-gated AIRWAY items (ETT, Magill forceps,
        // UVC, UAC) don't render while HAS_NICU=No.
        $hpUrl = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $hpResponse = $this->get($hpUrl);
        $hpResponse->assertOk();
        $hpResponse->assertDontSee('Magill forceps');

        // Flip HAS_NICU to Yes — the same page must now show it.
        AssessmentQuestionResponse::where('assessment_id', $assessment->id)->where('assessment_question_id', $hasNicu->id)->update(['response_value' => 'Yes']);
        $hpResponseAfter = $this->get($hpUrl);
        $hpResponseAfter->assertOk();
        $hpResponseAfter->assertSee('Magill forceps');
    }
}
```

- [ ] **Step 2: Run and fix until green**

Run: `php artisan test --filter=FacilityAssessment2026EndToEndTest`
Expected: PASS. If any assertion fails, the failure identifies exactly which piece (Task 3's `display_conditions`, Task 4's AND-condition, or Task 6's per-commodity gating) isn't wired correctly — fix the seeder in question, not this test.

- [ ] **Step 3: Run the complete Phase 2 + Phase 1 + full project test suite**

Run: `php artisan test --filter='FacilityAssessment2026|Assessment'`
Expected: PASS

Run: `composer test`
Expected: PASS — full regression check, matching the discipline used at the end of Phase 1.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/FacilityAssessment2026/FacilityAssessment2026EndToEndTest.php
git commit -m "test: end-to-end coverage for the 2026 Facility Readiness Assessment content + NICU gating chain"
```
