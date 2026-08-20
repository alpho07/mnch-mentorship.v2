# Assessment Round Comparison Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a user tag a facility assessment with its round (Baseline / Midline / Endline / Other + specify) at creation time, and automatically show a side-by-side comparison across every structured report section when a facility has multiple rounds against the same template.

**Architecture:** Repurpose the vestigial `assessments.assessment_type` column as the round field (converted from enum to string, plus a new `round_label` column for "Other"). A new `AssessmentComparisonService` gathers sibling assessments (same facility + same template, different round) and pivots each report section's data into round-keyed columns, reusing `AssessmentPdfReportService::prepareReportData()` per sibling. The HTML summary (`assessment-html-report.blade.php`) renders every structured section through a shared comparison-table partial when 2+ rounds exist, falling back to a single column otherwise.

**Tech Stack:** Laravel 12, Filament v3, Pest/PHPUnit (`tests/Feature`), SQLite in-memory for tests (`phpunit.xml`), Blade views (server-rendered HTML report, no DomPDF changes in this plan).

**Spec:** `docs/superpowers/specs/2026-08-20-assessment-round-comparison-design.md`

## Global Constraints

- Comparability is scoped to **same `facility_id` + same `assessment_type_id`** only — indicators are template-specific (keyed by `question_code` within a template's sections), so different templates never compare.
- Round values: `baseline`, `midline`, `endline`, `other`. `round_label` is populated only when round = `other`.
- Duplicate guard: at most one `baseline`, one `midline`, one `endline` per (facility, template); unlimited `other` rounds as long as each has a distinct `round_label`. Enforced in application code (`CreateAssessment::beforeCreate()`), not a DB unique index (MySQL treats each `NULL` in a unique index as distinct, which would let duplicate baseline/midline/endline rows through).
- Do not use `Schema::table(...)->change()` on `assessment_type` — avoid a `doctrine/dbal` dependency (not present in `composer.lock`). Use add-copy-drop-rename instead.
- PDF export (`generateExecutiveReport()` / `pdf.assessment-executive-report` view) and CSV export (`AssessmentExportService`) are **out of scope** — only the HTML summary (`reports/assessment-html-report.blade.php`) changes.
- Tests run against SQLite in-memory (`phpunit.xml`); every migration and query must work on SQLite, not just MySQL.

---

### Task 1: Migration — convert `assessment_type` to string, add `round_label`

**Files:**
- Create: `database/migrations/2026_08_20_090000_convert_assessment_type_to_string_and_add_round_label.php`
- Test: `tests/Feature/AssessmentRoundMigrationTest.php`

**Interfaces:**
- Produces: `assessments.assessment_type` becomes a plain `string` column (still holds `baseline|midline|endline|other`, validated at the app layer, not the DB). `assessments.round_label` — new nullable `string` column, `NULL` unless `assessment_type = 'other'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('stores an other round with a free-text round_label', function () {
    $type = AssessmentType::create([
        'name' => 'Test Template',
        'code' => 'TEST_TEMPLATE',
        'version' => '1.0',
        'is_active' => true,
    ]);
    $facility = Facility::factory()->create();

    $assessment = Assessment::create([
        'facility_id' => $facility->id,
        'assessment_type_id' => $type->id,
        'assessment_type' => 'other',
        'round_label' => 'Post-COVID Re-assessment',
        'assessment_date' => now(),
    ]);

    expect($assessment->fresh()->assessment_type)->toBe('other')
        ->and($assessment->fresh()->round_label)->toBe('Post-COVID Re-assessment');
});
```

Place this in `tests/Feature/AssessmentRoundMigrationTest.php` using Pest's functional style (this codebase's `AssessmentTemplateTest.php` uses PHPUnit class style — either works since Pest is available; this test uses Pest to keep it short, matching other single-purpose migration/column tests in this repo's `tests/Feature` directory. If Pest is not configured, write it as a PHPUnit method `test_it_stores_an_other_round_with_a_free_text_round_label` inside a `class AssessmentRoundMigrationTest extends TestCase` instead — check for a `tests/Pest.php` file to decide).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentRoundMigrationTest`
Expected: FAIL — `round_label` column does not exist (or `assessment_type` rejects `'other'` under the old enum/whatever constraint SQLite applied).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert assessment_type from enum('baseline','midline','endline')
        // to a plain string so it can also hold 'other', without a
        // Schema::change() call (would require doctrine/dbal, not a
        // project dependency). Add-copy-drop-rename instead.
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('assessment_type_new', 20)->nullable()->after('assessment_type');
        });

        DB::statement('UPDATE assessments SET assessment_type_new = assessment_type');

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('assessment_type');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->renameColumn('assessment_type_new', 'assessment_type');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->string('round_label')->nullable()->after('assessment_type');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('round_label');
        });

        // Not restoring the original enum constraint on rollback — the
        // column stays a string. Non-destructive; matches the asymmetric
        // down() precedent in 2026_08_14_003423_drop_tots_count_from_assessments.php.
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentRoundMigrationTest`
Expected: PASS

- [ ] **Step 5: Run the full existing Assessment test suite to check for regressions**

Run: `php artisan test --filter=Assessment`
Expected: PASS (existing tests that set `assessment_type => 'baseline'` etc. keep working since the column still accepts those strings).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_20_090000_convert_assessment_type_to_string_and_add_round_label.php tests/Feature/AssessmentRoundMigrationTest.php
git commit -m "feat: convert assessment_type to string and add round_label column"
```

---

### Task 2: `Assessment` model — round fillable, display accessor, sort weight

**Files:**
- Modify: `app/Models/Assessment.php`
- Test: `tests/Unit/AssessmentRoundDisplayTest.php`

**Interfaces:**
- Consumes: `assessment_type` (string: `baseline|midline|endline|other`), `round_label` (nullable string) — from Task 1.
- Produces: `Assessment::round_label` fillable; `$assessment->round_display` accessor (string); `$assessment->roundSortWeight(): int` (0=baseline, 1=midline, 2=endline, 3=other) — consumed by Task 5's `AssessmentComparisonService`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Assessment;
use Tests\TestCase;

class AssessmentRoundDisplayTest extends TestCase
{
    public function test_round_display_returns_ucfirst_label_for_standard_rounds(): void
    {
        $assessment = new Assessment(['assessment_type' => 'midline']);

        $this->assertSame('Midline', $assessment->round_display);
    }

    public function test_round_display_returns_round_label_for_other(): void
    {
        $assessment = new Assessment([
            'assessment_type' => 'other',
            'round_label' => 'Post-COVID Re-assessment',
        ]);

        $this->assertSame('Post-COVID Re-assessment', $assessment->round_display);
    }

    public function test_round_sort_weight_orders_baseline_before_midline_before_endline_before_other(): void
    {
        $baseline = new Assessment(['assessment_type' => 'baseline']);
        $midline = new Assessment(['assessment_type' => 'midline']);
        $endline = new Assessment(['assessment_type' => 'endline']);
        $other = new Assessment(['assessment_type' => 'other']);

        $this->assertSame(0, $baseline->roundSortWeight());
        $this->assertSame(1, $midline->roundSortWeight());
        $this->assertSame(2, $endline->roundSortWeight());
        $this->assertSame(3, $other->roundSortWeight());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentRoundDisplayTest`
Expected: FAIL — `round_display` / `roundSortWeight()` undefined.

- [ ] **Step 3: Implement**

In `app/Models/Assessment.php`, add `'round_label'` to the `$fillable` array (next to the existing `'assessment_type'` entry, around line 15-43), then add these methods near the other accessor methods (e.g. after `getCompletionPercentageAttribute()`, around line 260):

```php
public function getRoundDisplayAttribute(): string
{
    if ($this->assessment_type === 'other') {
        return $this->round_label ?: 'Other';
    }

    return ucfirst($this->assessment_type ?: 'baseline');
}

public function roundSortWeight(): int
{
    return match ($this->assessment_type) {
        'baseline' => 0,
        'midline' => 1,
        'endline' => 2,
        default => 3,
    };
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentRoundDisplayTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Assessment.php tests/Unit/AssessmentRoundDisplayTest.php
git commit -m "feat: add round_display accessor and roundSortWeight to Assessment"
```

---

### Task 3: Create form — Assessment Round field + duplicate guard update

**Files:**
- Modify: `app/Filament/Resources/AssessmentResource/Pages/CreateAssessment.php`
- Modify: `tests/Feature/AssessmentTemplateTest.php` (extend the existing duplicate-guard test)
- Test: `tests/Feature/CreateAssessmentRoundTest.php`

**Interfaces:**
- Consumes: `Assessment::roundSortWeight()`, `round_display` (Task 2) — not directly used here, but this task's data (`assessment_type`, `round_label`) is what Task 2's accessor reads.
- Produces: form now submits `assessment_type` (round) and `round_label`; `beforeCreate()` duplicate guard now keys on `facility_id + assessment_type_id + assessment_type (+ round_label for 'other')`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/CreateAssessmentRoundTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\CreateAssessment;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateAssessmentRoundTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create();

        $role = Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        foreach ([
            'view_any_assessment',
            'view_any_assessment::type',
            'update_assessment::type',
            'create_assessment::type',
            'view_any_assessment::question',
        ] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_create_assessment_stores_the_selected_round(): void
    {
        $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'assessment_type' => 'midline',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('midline', Assessment::where('facility_id', $facility->id)->value('assessment_type'));
    }

    public function test_create_assessment_requires_round_label_when_round_is_other(): void
    {
        $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'assessment_type' => 'other',
                'round_label' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['round_label' => 'required']);
    }

    public function test_create_assessment_allows_baseline_and_midline_for_same_facility_and_template(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD3', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'assessment_type' => 'midline',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Assessment::where('facility_id', $facility->id)->where('assessment_type_id', $type->id)->count());
    }

    public function test_create_assessment_blocks_a_duplicate_round_for_the_same_facility_and_template(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD4', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'assessment_type' => 'baseline',
            ])
            ->call('create');

        $this->assertSame(1, Assessment::where('facility_id', $facility->id)->where('assessment_type_id', $type->id)->count());
    }

    public function test_create_assessment_allows_two_distinctly_labeled_other_rounds(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD5', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'other',
            'round_label' => 'Ad-hoc Review 1',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'assessment_type' => 'other',
                'round_label' => 'Ad-hoc Review 2',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Assessment::where('facility_id', $facility->id)->where('assessment_type_id', $type->id)->count());
    }
}
```

Also add one method to the existing `tests/Feature/AssessmentTemplateTest.php` (after `test_create_assessment_blocks_a_duplicate_for_the_same_template_and_facility`, around line 429) to confirm the pre-existing test still names its scenario correctly now that the guard also checks round — it already only submits `facility_id`/`assessment_type_id`/`assessment_date` with no `assessment_type` in `fillForm`, which will use the field's form default (`baseline`, wired in Step 3 below), so this existing test keeps passing unmodified. No edit needed there; this step is a note, not a code change — confirmed by running the full file in Step 4.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CreateAssessmentRoundTest`
Expected: FAIL — no `assessment_type` / `round_label` fields on the form yet, so `fillForm` silently no-ops those keys and the round isn't persisted as submitted; the "requires round_label" test fails because no such validation exists yet.

- [ ] **Step 3: Implement the form fields**

In `app/Filament/Resources/AssessmentResource/Pages/CreateAssessment.php`, inside the `'Assessment Details'` section's `schema([...])` array (lines 59-85), add two fields after the `assessment_type_id` select and before `assessment_date`:

```php
                        Forms\Components\Select::make('assessment_type')
                            ->label('Assessment Round')
                            ->helperText('Which round of this template is this?')
                            ->options([
                                'baseline' => 'Baseline',
                                'midline' => 'Midline',
                                'endline' => 'Endline',
                                'other' => 'Other',
                            ])
                            ->default('baseline')
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('round_label')
                            ->label('Specify Round')
                            ->helperText('Required when round is "Other" — e.g. "Post-COVID Re-assessment".')
                            ->maxLength(100)
                            ->visible(fn (Forms\Get $get) => $get('assessment_type') === 'other')
                            ->required(fn (Forms\Get $get) => $get('assessment_type') === 'other'),
```

- [ ] **Step 4: Implement the duplicate-guard update**

Replace the `beforeCreate()` method (lines 131-154) with:

```php
    protected function beforeCreate(): void
    {
        $facilityId = $this->data['facility_id'] ?? null;
        $assessmentTypeId = $this->data['assessment_type_id'] ?? null;
        $round = $this->data['assessment_type'] ?? null;
        $roundLabel = $round === 'other' ? ($this->data['round_label'] ?? null) : null;

        if ($facilityId && $assessmentTypeId && $round) {
            $query = Assessment::where('facility_id', $facilityId)
                ->where('assessment_type_id', $assessmentTypeId)
                ->where('assessment_type', $round)
                ->whereNull('deleted_at');

            if ($round === 'other') {
                $query->where('round_label', $roundLabel);
            }

            if ($query->exists()) {
                $typeName = AssessmentType::find($assessmentTypeId)?->name ?? 'This';
                $roundName = $round === 'other' ? $roundLabel : ucfirst($round);
                Notification::make()
                    ->danger()
                    ->title('Duplicate Assessment')
                    ->body("A \"{$roundName}\" \"{$typeName}\" assessment already exists for this facility.")
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }
    }
```

Update the docblock above it (lines 124-130) to reflect the new key:

```php
    /**
     * Block duplicate assessments: one per facility, per template, per
     * round — baseline/midline/endline each allowed once; "other" rounds
     * are distinguished by their free-text round_label, so any number of
     * distinctly-labeled "other" assessments are allowed.
     */
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=CreateAssessmentRoundTest`
Expected: PASS

Run: `php artisan test --filter=AssessmentTemplateTest`
Expected: PASS (existing duplicate-guard test still passes — it now also implicitly checks `assessment_type = 'baseline'` on both sides since that's the field default).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AssessmentResource/Pages/CreateAssessment.php tests/Feature/CreateAssessmentRoundTest.php
git commit -m "feat: add Assessment Round field to create form and round-aware duplicate guard"
```

---

### Task 4: Expose `AssessmentPdfReportService::prepareReportData()` for reuse

**Files:**
- Modify: `app/Services/AssessmentPdfReportService.php:42`
- Test: `tests/Feature/AssessmentPdfReportServicePublicDataTest.php`

**Interfaces:**
- Produces: `AssessmentPdfReportService::prepareReportData(Assessment $assessment): array` becomes `public` (was `protected`) — consumed by Task 5's `AssessmentComparisonService`, which needs each sibling assessment's full section data without duplicating the query logic.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentPdfReportServicePublicDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_report_data_is_publicly_callable(): void
    {
        $type = AssessmentType::create(['name' => 'Template', 'code' => 'PUB1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);

        $data = app(AssessmentPdfReportService::class)->prepareReportData($assessment);

        $this->assertArrayHasKey('facilityInfo', $data);
        $this->assertArrayHasKey('humanResourcesDetails', $data);
        $this->assertArrayHasKey('healthProductsDetails', $data);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentPdfReportServicePublicDataTest`
Expected: FAIL — `Call to protected method App\Services\AssessmentPdfReportService::prepareReportData() from scope Tests\Feature\AssessmentPdfReportServicePublicDataTest`.

- [ ] **Step 3: Implement**

In `app/Services/AssessmentPdfReportService.php:42`, change:

```php
    protected function prepareReportData(Assessment $assessment) {
```

to:

```php
    public function prepareReportData(Assessment $assessment): array {
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentPdfReportServicePublicDataTest`
Expected: PASS

- [ ] **Step 5: Run the full report-service test suite to check for regressions**

Run: `php artisan test --filter=AssessmentPdfReportService`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/AssessmentPdfReportService.php tests/Feature/AssessmentPdfReportServicePublicDataTest.php
git commit -m "refactor: make AssessmentPdfReportService::prepareReportData public"
```

---

### Task 5: `AssessmentComparisonService` — sibling lookup + per-section merge

**Files:**
- Create: `app/Services/AssessmentComparisonService.php`
- Test: `tests/Feature/AssessmentComparisonServiceTest.php`

**Interfaces:**
- Consumes: `Assessment::roundSortWeight()`, `round_display` (Task 2); `AssessmentPdfReportService::prepareReportData(Assessment $assessment): array` (Task 4).
- Produces:
  - `AssessmentComparisonService::getComparableAssessments(Assessment $assessment): \Illuminate\Support\Collection` — all assessments sharing `facility_id` + `assessment_type_id`, ordered baseline→midline→endline→other-by-date, includes the given assessment itself.
  - `AssessmentComparisonService::prepareComparisonData(Assessment $assessment): ?array` — `null` if fewer than 2 comparable assessments exist; otherwise an array with keys `rounds`, `overallScore`, `humanResources`, `infrastructure`, `infrastructureBeds`, `skillsLab`, `informationSystems`, `informationSystemsDataTools`, `qualityYesNo`, `qualitySelect`, `qualityNewbornStats`, `qualityPaedStats`, `healthProducts`. Consumed by Task 6/7/8/9/10's blade changes.
  - Each non-`healthProducts`/`overallScore` key is a list of rows shaped `['label' => string, 'values' => [assessmentId => [...fields...]]]`.
  - `rounds` is a list of `['id' => int, 'label' => string]` in comparison-column order.
  - `overallScore` is `[assessmentId => ['score' => ..., 'max_score' => ..., 'percentage' => ..., 'grade' => ..., 'grade_color' => ...]]`.
  - `healthProducts` is `[departmentName => ['categories' => [['name' => string, 'items' => [['name' => string, 'values' => [assessmentId => bool]]]]]]]`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_only_one_assessment_exists(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'CMP1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($assessment);

        $this->assertNull($result);
    }

    public function test_orders_rounds_baseline_before_midline_before_endline(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'CMP2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        $endline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'endline', 'assessment_date' => now(),
        ]);
        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now()->subMonths(6),
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'midline', 'assessment_date' => now()->subMonths(3),
        ]);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($baseline);

        $this->assertSame(
            [$baseline->id, $midline->id, $endline->id],
            array_column($result['rounds'], 'id')
        );
        $this->assertSame(['Baseline', 'Midline', 'Endline'], array_column($result['rounds'], 'label'));
    }

    public function test_merges_human_resources_rows_by_cadre_across_rounds_with_dash_for_missing(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'CMP3', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $cadre = \App\Models\Cadre::create(['name' => 'Nurse']);

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now()->subMonth(),
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'midline', 'assessment_date' => now(),
        ]);

        \App\Models\HumanResourceResponse::create([
            'assessment_id' => $baseline->id, 'cadre_id' => $cadre->id, 'total_in_facility' => 5,
        ]);
        \App\Models\HumanResourceResponse::create([
            'assessment_id' => $midline->id, 'cadre_id' => $cadre->id, 'total_in_facility' => 8,
        ]);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($baseline);

        $nurseRow = collect($result['humanResources'])->firstWhere('label', 'Nurse');
        $this->assertNotNull($nurseRow);
        $this->assertSame(5, $nurseRow['values'][$baseline->id]['total_in_facility']);
        $this->assertSame(8, $nurseRow['values'][$midline->id]['total_in_facility']);
    }
}
```

Note: this test uses `App\Models\Cadre` and `App\Models\HumanResourceResponse` — confirm these exact class names/fillable fields by reading `app/Models/Cadre.php` and `app/Models/HumanResourceResponse.php` before running; adjust field names (e.g. `cadre_id`, `total_in_facility`) to match if they differ, since these weren't part of the earlier research and must be verified against the live model files.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentComparisonServiceTest`
Expected: FAIL — class `App\Services\AssessmentComparisonService` does not exist.

- [ ] **Step 3: Implement**

Create `app/Services/AssessmentComparisonService.php`:

```php
<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Collection;

class AssessmentComparisonService
{
    public function __construct(private AssessmentPdfReportService $reportService)
    {
    }

    public function getComparableAssessments(Assessment $assessment): Collection
    {
        return Assessment::where('facility_id', $assessment->facility_id)
            ->where('assessment_type_id', $assessment->assessment_type_id)
            ->whereNull('deleted_at')
            ->get()
            ->sortBy(fn (Assessment $a) => [$a->roundSortWeight(), $a->assessment_date?->timestamp ?? 0])
            ->values();
    }

    public function prepareComparisonData(Assessment $assessment): ?array
    {
        $siblings = $this->getComparableAssessments($assessment);

        if ($siblings->count() < 2) {
            return null;
        }

        $rounds = $siblings->map(fn (Assessment $a) => [
            'id' => $a->id,
            'label' => $a->round_display,
        ])->values()->toArray();

        $perAssessmentData = $siblings->mapWithKeys(
            fn (Assessment $a) => [$a->id => $this->reportService->prepareReportData($a)]
        );

        return [
            'rounds' => $rounds,
            'overallScore' => $this->mergeSimpleValues($perAssessmentData, $rounds, 'overallScore'),
            'humanResources' => $this->mergeByKey($perAssessmentData, $rounds, 'humanResourcesDetails.responses', 'cadre', [
                'total_in_facility', 'etat_plus', 'comprehensive_newborn_care', 'imnci', 'type_1_diabetes', 'essential_newborn_care',
            ]),
            'infrastructure' => $this->mergeByKey($perAssessmentData, $rounds, 'infrastructureDetails.responses', 'question', ['response']),
            'infrastructureBeds' => $this->mergeByKey($perAssessmentData, $rounds, 'infrastructureDetails.beds_table', 'unit', ['functional', 'non_functional', 'total']),
            'skillsLab' => $this->mergeByKey($perAssessmentData, $rounds, 'skillsLabDetails.responses', 'question', ['response']),
            'informationSystems' => $this->mergeByKey($perAssessmentData, $rounds, 'informationSystemsDetails.responses', 'question', ['response']),
            'informationSystemsDataTools' => $this->mergeByKey($perAssessmentData, $rounds, 'informationSystemsDetails.data_tools_table', 'form', ['available', 'completeness']),
            'qualityYesNo' => $this->mergeByKey($perAssessmentData, $rounds, 'qualityOfCareDetails.yes_no_array', 'question', ['response']),
            'qualitySelect' => $this->mergeByKey($perAssessmentData, $rounds, 'qualityOfCareDetails.select_array', 'question', ['response']),
            'qualityNewbornStats' => $this->mergeByKey($perAssessmentData, $rounds, 'qualityOfCareDetails.newborn_stats_array', 'question', ['response']),
            'qualityPaedStats' => $this->mergeByKey($perAssessmentData, $rounds, 'qualityOfCareDetails.paed_stats_array', 'question', ['response']),
            'healthProducts' => $this->mergeHealthProducts($perAssessmentData, $rounds),
        ];
    }

    /**
     * @param  array<int, array{id: int, label: string}>  $rounds
     */
    private function mergeByKey(Collection $perAssessmentData, array $rounds, string $dataPath, string $keyField, array $valueFields): array
    {
        $rows = [];
        $order = [];

        foreach ($rounds as $round) {
            $list = data_get($perAssessmentData[$round['id']], $dataPath, []);

            foreach ($list as $item) {
                $key = $item[$keyField] ?? '-';

                if (! isset($rows[$key])) {
                    $rows[$key] = ['label' => $key, 'values' => []];
                    $order[] = $key;
                }

                $rows[$key]['values'][$round['id']] = array_intersect_key($item, array_flip($valueFields));
            }
        }

        return array_values(array_map(fn ($key) => $rows[$key], $order));
    }

    private function mergeSimpleValues(Collection $perAssessmentData, array $rounds, string $dataPath): array
    {
        $values = [];

        foreach ($rounds as $round) {
            $values[$round['id']] = data_get($perAssessmentData[$round['id']], $dataPath, []);
        }

        return $values;
    }

    private function mergeHealthProducts(Collection $perAssessmentData, array $rounds): array
    {
        $departments = [];
        $deptOrder = [];

        foreach ($rounds as $round) {
            $data = data_get($perAssessmentData[$round['id']], 'healthProductsDetails', []);

            foreach ($data as $departmentName => $dept) {
                if (! isset($departments[$departmentName])) {
                    $departments[$departmentName] = ['categories' => [], 'categoryOrder' => []];
                    $deptOrder[] = $departmentName;
                }

                foreach ($dept['categories'] as $category) {
                    $catName = $category['name'];

                    if (! isset($departments[$departmentName]['categories'][$catName])) {
                        $departments[$departmentName]['categories'][$catName] = ['name' => $catName, 'items' => [], 'itemOrder' => []];
                        $departments[$departmentName]['categoryOrder'][] = $catName;
                    }

                    foreach ($category['items'] as $item) {
                        $itemName = $item['name'];

                        if (! isset($departments[$departmentName]['categories'][$catName]['items'][$itemName])) {
                            $departments[$departmentName]['categories'][$catName]['items'][$itemName] = ['name' => $itemName, 'values' => []];
                            $departments[$departmentName]['categories'][$catName]['itemOrder'][] = $itemName;
                        }

                        $departments[$departmentName]['categories'][$catName]['items'][$itemName]['values'][$round['id']] = $item['available'];
                    }
                }
            }
        }

        $result = [];

        foreach ($deptOrder as $departmentName) {
            $dept = $departments[$departmentName];
            $categories = [];

            foreach ($dept['categoryOrder'] as $catName) {
                $cat = $dept['categories'][$catName];
                $items = [];

                foreach ($cat['itemOrder'] as $itemName) {
                    $items[] = $cat['items'][$itemName];
                }

                $categories[] = ['name' => $catName, 'items' => $items];
            }

            $result[$departmentName] = ['categories' => $categories];
        }

        return $result;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentComparisonServiceTest`
Expected: PASS (adjust the `Cadre`/`HumanResourceResponse` field names per Step 1's note if the live models differ, then re-run).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AssessmentComparisonService.php tests/Feature/AssessmentComparisonServiceTest.php
git commit -m "feat: add AssessmentComparisonService for cross-round report comparison"
```

---

### Task 6: Wire comparison data into the HTML report

**Files:**
- Modify: `app/Services/AssessmentPdfReportService.php` (`generateHtmlReport()`, lines 33-37)
- Test: `tests/Feature/AssessmentHtmlReportComparisonWiringTest.php`

**Interfaces:**
- Consumes: `AssessmentComparisonService::prepareComparisonData(Assessment $assessment): ?array` (Task 5).
- Produces: the `reports.assessment-html-report` view now receives a `$comparison` variable (`null` when only one assessment exists, otherwise the array from Task 5) — consumed by Tasks 7-10's blade changes.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class AssessmentHtmlReportComparisonWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_report_passes_null_comparison_for_a_single_assessment(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'WIRE1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(),
        ]);

        View::shouldReceive('render')->never();

        $shared = null;
        View::composer('reports.assessment-html-report', function ($view) use (&$shared) {
            $shared = $view->getData();
        });

        app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertArrayHasKey('comparison', $shared);
        $this->assertNull($shared['comparison']);
    }

    public function test_html_report_passes_comparison_array_for_two_assessments(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'WIRE2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now()->subMonth(),
        ]);
        Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'midline', 'assessment_date' => now(),
        ]);

        $shared = null;
        View::composer('reports.assessment-html-report', function ($view) use (&$shared) {
            $shared = $view->getData();
        });

        app(AssessmentPdfReportService::class)->generateHtmlReport($baseline);

        $this->assertArrayHasKey('comparison', $shared);
        $this->assertIsArray($shared['comparison']);
        $this->assertCount(2, $shared['comparison']['rounds']);
    }
}
```

Note: the first test's `View::shouldReceive('render')->never()` expectation is wrong (the method under test does render the view) — remove that line; it was left in by mistake. The composer-based assertion is the real check. Corrected first test body should NOT include that `shouldReceive` line — just the composer registration and the two assertions after calling `generateHtmlReport()`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentHtmlReportComparisonWiringTest`
Expected: FAIL — `$shared` has no `comparison` key.

- [ ] **Step 3: Implement**

In `app/Services/AssessmentPdfReportService.php`, update `generateHtmlReport()` (lines 33-37):

```php
    /**
     * Generate HTML report for web display
     */
    public function generateHtmlReport(Assessment $assessment): string {
        $data = $this->prepareReportData($assessment);
        $data['comparison'] = app(\App\Services\AssessmentComparisonService::class)->prepareComparisonData($assessment);

        return view('reports.assessment-html-report', $data)->render();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentHtmlReportComparisonWiringTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/AssessmentPdfReportService.php tests/Feature/AssessmentHtmlReportComparisonWiringTest.php
git commit -m "feat: pass comparison data into the assessment HTML report view"
```

---

### Task 7: Comparison-table Blade partial + Infrastructure/Skills Lab/Information Systems sections

**Files:**
- Create: `resources/views/reports/partials/comparison-rows.blade.php`
- Modify: `resources/views/reports/assessment-html-report.blade.php:90-232` (Infrastructure, Skills Lab, Information Systems sections)
- Test: `tests/Feature/AssessmentHtmlReportComparisonRenderTest.php`

**Interfaces:**
- Consumes: `$comparison['infrastructure']`, `$comparison['infrastructureBeds']`, `$comparison['skillsLab']`, `$comparison['informationSystems']`, `$comparison['informationSystemsDataTools']`, `$comparison['rounds']` (Task 5/6) — each a list of `['label' => string, 'values' => [assessmentId => [...]]]` or `null`.
- Produces: partial `reports.partials.comparison-rows` reusable by Task 8 for the Quality of Care and Indicators sections too. Expects: `$rows` (list as above), `$rounds` (list of `['id','label']`), `$field` (string, which key inside each row's per-round array to display), `$badge` (bool, default false — colorizes `Yes`/`No`), `$labelHeader` (string, default `'Question'`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentHtmlReportComparisonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_infrastructure_section_renders_one_column_per_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'REND1', 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'INFRA_TEST',
            'question_text' => 'Has power backup?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $facility = Facility::factory()->create();

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now()->subMonth(),
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'midline', 'assessment_date' => now(),
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $baseline->id, 'assessment_question_id' => $question->id, 'response_value' => 'No']);
        AssessmentQuestionResponse::create(['assessment_id' => $midline->id, 'assessment_question_id' => $question->id, 'response_value' => 'Yes']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($baseline);

        $this->assertStringContainsString('Has power backup?', $html);
        $this->assertStringContainsString('Baseline', $html);
        $this->assertStringContainsString('Midline', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentHtmlReportComparisonRenderTest`
Expected: FAIL — the current Infrastructure section renders no round labels at all (single fixed "Response" column).

- [ ] **Step 3: Create the partial**

Create `resources/views/reports/partials/comparison-rows.blade.php`:

```blade
@php
    $displayRounds = $rounds ?? [['id' => $assessment->id, 'label' => $assessment->round_display]];
    $displayField = $field ?? 'response';
    $useBadge = $badge ?? false;
    $header = $labelHeader ?? 'Question';
@endphp
<table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 24px;">
    <thead>
        <tr>
            <th style="background: #f3f4f6; padding: 12px; text-align: left; border: 1px solid #d1d5db;">{{ $header }}</th>
            @foreach($displayRounds as $round)
                <th style="background: #f3f4f6; padding: 12px; text-align: center; border: 1px solid #d1d5db;">{{ $round['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $row['label'] }}</td>
                @foreach($displayRounds as $round)
                    @php $value = $row['values'][$round['id']][$displayField] ?? null; @endphp
                    <td style="padding: 10px 12px; border: 1px solid #e5e7eb; text-align: center;">
                        @if($value === null)
                            <span style="color:#9ca3af;">&mdash;</span>
                        @elseif($useBadge)
                            <span class="badge badge-{{ $value === 'Yes' ? 'green' : 'red' }}">{{ $value }}</span>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
```

- [ ] **Step 4: Rework the Infrastructure, Skills Lab, Information Systems sections**

Replace `resources/views/reports/assessment-html-report.blade.php:90-232` (from `{{-- Infrastructure Details --}}` through the Information Systems section's closing `@endif`) with:

```blade
    @php
        $comparisonRounds = $comparison['rounds'] ?? [['id' => $assessment->id, 'label' => $assessment->round_display]];

        $infraRows = $comparison['infrastructure'] ?? collect($infrastructureDetails['responses'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $infraBedsRows = $comparison['infrastructureBeds'] ?? collect($infrastructureDetails['beds_table'] ?? [])
            ->map(fn ($d) => ['label' => $d['unit'], 'values' => [$assessment->id => $d]])->all();
    @endphp

    {{-- Infrastructure Details --}}
    @if(!empty($infraRows) || !empty($infraBedsRows))
        <div class="section" style="margin-bottom: 32px; page-break-inside: avoid;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Infrastructure</h2>

            @if(!empty($infraRows))
                @include('reports.partials.comparison-rows', ['rows' => $infraRows, 'rounds' => $comparisonRounds, 'field' => 'response', 'badge' => true])
            @endif

            @if(!empty($infraBedsRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Bed Capacity</h3>
                @include('reports.partials.comparison-rows', ['rows' => $infraBedsRows, 'rounds' => $comparisonRounds, 'field' => 'total', 'labelHeader' => 'Unit'])
            @endif
        </div>
    @endif

    @php
        $skillsLabRows = $comparison['skillsLab'] ?? collect($skillsLabDetails['responses'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
    @endphp

    {{-- Skills Lab Details --}}
    @if(!empty($skillsLabRows))
        <div class="section" style="margin-bottom: 32px; page-break-inside: avoid;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Skills Lab</h2>
            @include('reports.partials.comparison-rows', ['rows' => $skillsLabRows, 'rounds' => $comparisonRounds, 'field' => 'response', 'badge' => true, 'labelHeader' => 'Equipment/Item'])
        </div>
    @endif

    @php
        $infoSysRows = $comparison['informationSystems'] ?? collect($informationSystemsDetails['responses'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $infoSysToolsRows = $comparison['informationSystemsDataTools'] ?? collect($informationSystemsDetails['data_tools_table'] ?? [])
            ->map(fn ($d) => ['label' => $d['form'], 'values' => [$assessment->id => $d]])->all();
    @endphp

    {{-- Information Systems Details --}}
    @if(!empty($infoSysRows) || !empty($infoSysToolsRows))
        <div class="section" style="margin-bottom: 32px; page-break-inside: avoid;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Information Systems</h2>

            @if(!empty($infoSysRows))
                @include('reports.partials.comparison-rows', ['rows' => $infoSysRows, 'rounds' => $comparisonRounds, 'field' => 'response', 'badge' => true])
            @endif

            @if(!empty($infoSysToolsRows))
                <h3 style="color: #374151; margin-top: 20px; margin-bottom: 12px;">Data Collection Tools & Registers — Availability</h3>
                @include('reports.partials.comparison-rows', ['rows' => $infoSysToolsRows, 'rounds' => $comparisonRounds, 'field' => 'available', 'badge' => true, 'labelHeader' => 'Form / Register'])
            @endif
        </div>
    @endif
```

Note: the "Complete" column from the old `data_tools_table` (both `available` and `completeness` per form) is now only shown as `available` per round in the comparison partial, since the generic partial displays one field at a time. This is a scope simplification versus the original single-assessment view (which showed both). Acceptable given the spec's focus on side-by-side comparability; flag to the user as a follow-up if both fields are needed per round.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentHtmlReportComparisonRenderTest`
Expected: PASS

- [ ] **Step 6: Run the full report/blade-related test suite to check for regressions**

Run: `php artisan test --filter=Assessment`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add resources/views/reports/partials/comparison-rows.blade.php resources/views/reports/assessment-html-report.blade.php tests/Feature/AssessmentHtmlReportComparisonRenderTest.php
git commit -m "feat: render Infrastructure/Skills Lab/Information Systems as side-by-side round comparisons"
```

---

### Task 8: Quality of Care and Indicators sections — comparison rendering

**Files:**
- Modify: `resources/views/reports/assessment-html-report.blade.php:392-488` (Quality of Care, Newborn & Paediatric Indicators sections)
- Test: `tests/Feature/AssessmentHtmlReportQualityComparisonRenderTest.php`

**Interfaces:**
- Consumes: `$comparison['qualityYesNo']`, `$comparison['qualitySelect']`, `$comparison['qualityNewbornStats']`, `$comparison['qualityPaedStats']`, `$comparison['rounds']` (Task 5/6); `reports.partials.comparison-rows` (Task 7).
- Produces: no new interfaces — this task only consumes.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentHtmlReportQualityComparisonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_quality_of_care_section_renders_one_column_per_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'QREND1', 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Quality of Care', 'code' => 'quality_of_care',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'QOC_AUDIT_TEST',
            'question_text' => 'Mortality audits conducted?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $facility = Facility::factory()->create();

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now()->subMonth(),
        ]);
        $endline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'endline', 'assessment_date' => now(),
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $baseline->id, 'assessment_question_id' => $question->id, 'response_value' => 'No']);
        AssessmentQuestionResponse::create(['assessment_id' => $endline->id, 'assessment_question_id' => $question->id, 'response_value' => 'Yes']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($baseline);

        $this->assertStringContainsString('Mortality audits conducted?', $html);
        $this->assertStringContainsString('Baseline', $html);
        $this->assertStringContainsString('Endline', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentHtmlReportQualityComparisonRenderTest`
Expected: FAIL — Quality of Care section still renders the old fixed single-column table with no round labels.

- [ ] **Step 3: Implement**

Replace `resources/views/reports/assessment-html-report.blade.php:392-429` (the `{{-- Quality of Care --}}` block) with:

```blade
    @php
        $qualityYesNoRows = $comparison['qualityYesNo'] ?? collect($qualityOfCareDetails['yes_no_array'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $qualitySelectRows = $comparison['qualitySelect'] ?? collect($qualityOfCareDetails['select_array'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $qualityNewbornStatsRows = $comparison['qualityNewbornStats'] ?? collect($qualityOfCareDetails['newborn_stats_array'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $qualityPaedStatsRows = $comparison['qualityPaedStats'] ?? collect($qualityOfCareDetails['paed_stats_array'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
    @endphp

    {{-- Quality of Care --}}
    @if(!empty($qualityYesNoRows) || !empty($qualitySelectRows) || !empty($qualityNewbornStatsRows) || !empty($qualityPaedStatsRows))
        <div class="section" style="margin-bottom: 32px;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Quality of Care</h2>

            @if(!empty($qualityYesNoRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Audit & Process Compliance</h3>
                @include('reports.partials.comparison-rows', ['rows' => $qualityYesNoRows, 'rounds' => $comparisonRounds, 'field' => 'response', 'badge' => true])
            @endif

            @if(!empty($qualitySelectRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Other Assessments</h3>
                @include('reports.partials.comparison-rows', ['rows' => $qualitySelectRows, 'rounds' => $comparisonRounds, 'field' => 'response'])
            @endif

            @if(!empty($qualityNewbornStatsRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Newborn Care Statistics</h3>
                @include('reports.partials.comparison-rows', ['rows' => $qualityNewbornStatsRows, 'rounds' => $comparisonRounds, 'field' => 'response'])
            @endif

            @if(!empty($qualityPaedStatsRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Paediatric Care Statistics</h3>
                @include('reports.partials.comparison-rows', ['rows' => $qualityPaedStatsRows, 'rounds' => $comparisonRounds, 'field' => 'response'])
            @endif
        </div>
    @endif
```

Note: this replaces the "card grid" layout for statistics (`newborn_stats_array`/`paed_stats_array`) with a table, since the card layout has no natural place for multiple round values per stat. This is a deliberate visual simplification for comparability — flag to the user if the card look must be preserved for the single-round case.

Leave the `{{-- Newborn & Paediatric Indicators --}}` block (lines 431-488, `$indicatorsDetails`) unchanged — `AssessmentComparisonService::prepareComparisonData()` does not currently cover `indicatorsDetails` (proportions/newborn/paediatric stat cards), so this section keeps rendering single-assessment only. This is a deliberate scope cut to keep this plan's task count bounded; note it to the user as a candidate follow-up rather than silently dropping it — it was not explicitly called out in the spec's per-section list, which named Infrastructure, Skills Lab, Human Resources, Health Products, Information Systems, and Quality of Care.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentHtmlReportQualityComparisonRenderTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/reports/assessment-html-report.blade.php tests/Feature/AssessmentHtmlReportQualityComparisonRenderTest.php
git commit -m "feat: render Quality of Care section as side-by-side round comparison"
```

---

### Task 9: Human Resources section — multi-round comparison table

**Files:**
- Modify: `resources/views/reports/assessment-html-report.blade.php:234-366` (Human Resources section)
- Test: `tests/Feature/AssessmentHtmlReportHumanResourcesComparisonRenderTest.php`

**Interfaces:**
- Consumes: `$comparison['humanResources']`, `$comparison['rounds']` (Task 5/6).
- Produces: no new interfaces.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Cadre;
use App\Models\Facility;
use App\Models\HumanResourceResponse;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentHtmlReportHumanResourcesComparisonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_human_resources_section_renders_one_column_group_per_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HREND1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $cadre = Cadre::create(['name' => 'Nurse']);

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now()->subMonth(),
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'midline', 'assessment_date' => now(),
        ]);

        HumanResourceResponse::create(['assessment_id' => $baseline->id, 'cadre_id' => $cadre->id, 'total_in_facility' => 5]);
        HumanResourceResponse::create(['assessment_id' => $midline->id, 'cadre_id' => $cadre->id, 'total_in_facility' => 8]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($baseline);

        $this->assertStringContainsString('Nurse', $html);
        $this->assertStringContainsString('Baseline', $html);
        $this->assertStringContainsString('Midline', $html);
    }
}
```

Verify `Cadre` and `HumanResourceResponse` field names against the live models (`app/Models/Cadre.php`, `app/Models/HumanResourceResponse.php`) before running, same caveat as Task 5.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentHtmlReportHumanResourcesComparisonRenderTest`
Expected: FAIL — the current Human Resources table has fixed columns (Available/ETAT+/Comp. NB/IMNCI/Diabetes/Ess. NB) with no round grouping or round labels.

- [ ] **Step 3: Implement**

Replace `resources/views/reports/assessment-html-report.blade.php:234-366` (the `{{-- Human Resources --}}` block) with:

```blade
    @php
        $hrRows = $comparison['humanResources'] ?? collect($humanResourcesDetails['responses'] ?? [])
            ->map(fn ($d) => ['label' => $d['cadre'], 'values' => [$assessment->id => $d]])->all();
        $hrColumns = [
            'total_in_facility' => 'Available',
            'etat_plus' => 'ETAT+',
            'comprehensive_newborn_care' => 'Comp. NB',
            'imnci' => 'IMNCI',
            'type_1_diabetes' => 'Diabetes',
            'essential_newborn_care' => 'Ess. NB',
        ];
    @endphp

    {{-- Human Resources --}}
    @if(!empty($hrRows))
        <div class="section" style="margin-bottom: 32px; page-break-inside: avoid;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Human Resources</h2>

            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr>
                        <th rowspan="2" style="background:#f3f4f6;padding:12px;text-align:left;border:1px solid #d1d5db;vertical-align:bottom;">Cadre</th>
                        @foreach($comparisonRounds as $round)
                            <th colspan="{{ count($hrColumns) }}" style="background:#e5e7eb;padding:8px;text-align:center;border:1px solid #d1d5db;">{{ $round['label'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($comparisonRounds as $round)
                            @foreach($hrColumns as $label)
                                <th style="background:#f3f4f6;padding:8px;text-align:center;border:1px solid #d1d5db;font-size:12px;">{{ $label }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($hrRows as $row)
                        <tr>
                            <td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:600;">{{ $row['label'] ?? '-' }}</td>
                            @foreach($comparisonRounds as $round)
                                @foreach($hrColumns as $field => $label)
                                    @php $value = $row['values'][$round['id']][$field] ?? null; @endphp
                                    <td style="padding:10px 12px;border:1px solid #e5e7eb;text-align:center;">
                                        {{ $value === null ? '—' : $value }}
                                    </td>
                                @endforeach
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
```

Note: this drops the previous "TOTAL" footer row (summed across cadres) — summing across both cadres and rounds in a single footer row would need per-round subtotals, which the merged row structure doesn't carry directly here. Flag to the user as a candidate follow-up if per-round totals are wanted; the per-cadre, per-round detail is preserved, which is the core ask.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentHtmlReportHumanResourcesComparisonRenderTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/reports/assessment-html-report.blade.php tests/Feature/AssessmentHtmlReportHumanResourcesComparisonRenderTest.php
git commit -m "feat: render Human Resources section as side-by-side round comparison"
```

---

### Task 10: Health Products section + Overall Score comparison row

**Files:**
- Modify: `resources/views/reports/assessment-html-report.blade.php:74-88` (Overall Score) and `:368-390` (Health Products)
- Test: `tests/Feature/AssessmentHtmlReportHealthProductsAndScoreComparisonRenderTest.php`

**Interfaces:**
- Consumes: `$comparison['healthProducts']`, `$comparison['overallScore']`, `$comparison['rounds']` (Task 5/6).
- Produces: none — final task in this plan.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCommodityResponse;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Department;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentHtmlReportHealthProductsAndScoreComparisonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_products_section_renders_one_column_per_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HPREND1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $department = Department::create(['name' => 'Pharmacy']);
        $category = CommodityCategory::create(['name' => 'Antibiotics']);
        $commodity = Commodity::create(['name' => 'Amoxicillin', 'category_id' => $category->id]);

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now()->subMonth(),
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'midline', 'assessment_date' => now(),
        ]);

        AssessmentCommodityResponse::create([
            'assessment_id' => $baseline->id, 'commodity_id' => $commodity->id, 'department_id' => $department->id,
            'available' => false, 'not_applicable' => false,
        ]);
        AssessmentCommodityResponse::create([
            'assessment_id' => $midline->id, 'commodity_id' => $commodity->id, 'department_id' => $department->id,
            'available' => true, 'not_applicable' => false,
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($baseline);

        $this->assertStringContainsString('Amoxicillin', $html);
        $this->assertStringContainsString('Baseline', $html);
        $this->assertStringContainsString('Midline', $html);
    }
}
```

Verify `Commodity`, `CommodityCategory`, `Department`, `AssessmentCommodityResponse` field names against the live models before running, same caveat as Task 5.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentHtmlReportHealthProductsAndScoreComparisonRenderTest`
Expected: FAIL — the current Health Products section renders each item as a single red/green badge with no per-round distinction.

- [ ] **Step 3: Implement**

Replace `resources/views/reports/assessment-html-report.blade.php:74-88` (the `{{-- Overall Score Summary --}}` block) with:

```blade
    @php
        // Defined here (not only in Task 7's later @php block at line ~90)
        // because this Overall Score block renders *before* that block in
        // the file — Blade has no block scoping, but top-to-bottom order
        // still matters for when a variable first exists.
        $comparisonRounds = $comparison['rounds'] ?? [['id' => $assessment->id, 'label' => $assessment->round_display]];
    @endphp

    {{-- Overall Score Summary --}}
    <div class="overall-score" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 8px; margin-bottom: 24px;">
        @if($comparison)
            <h3 style="margin: 0 0 16px 0; font-size: 16px; opacity: 0.9;">Overall Score by Round</h3>
            <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                @foreach($comparisonRounds as $round)
                    @php $roundScore = $comparison['overallScore'][$round['id']] ?? null; @endphp
                    <div>
                        <p style="margin: 0; font-size: 13px; opacity: 0.85;">{{ $round['label'] }}</p>
                        <p style="font-size: 28px; font-weight: bold; margin: 4px 0;">{{ number_format($roundScore['percentage'] ?? 0, 1) }}%</p>
                        <span class="badge badge-{{ $roundScore['grade_color'] ?? 'gray' }}" style="font-size: 14px; padding: 4px 12px;">
                            {{ strtoupper($roundScore['grade'] ?? 'N/A') }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 16px; opacity: 0.9;">Overall Score</h3>
                    <p style="font-size: 36px; font-weight: bold; margin: 8px 0;"> {{number_format($percentage/4,1) }}%</p>
                </div>
                <div style="text-align: right;">
                    <span class="badge badge-{{ $color }}" style="font-size: 24px; padding: 8px 20px;">
                        {{ strtoupper($color) }}
                    </span>
                </div>
            </div>
        @endif
    </div>
```

Replace `resources/views/reports/assessment-html-report.blade.php:368-390` (the `{{-- Health Products Summary --}}` block) with:

```blade
    @php
        $healthProductsData = $comparison['healthProducts'] ?? $healthProductsDetails;
    @endphp

    {{-- Health Products Summary --}}
    @if(!empty($healthProductsData))
        <div class="section" style="margin-bottom: 32px;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Health Products & Commodities</h2>
            @foreach($healthProductsData as $departmentName => $dept)
                <h3 style="color: #374151; margin-top: 24px; margin-bottom: 12px;">{{ $departmentName }}</h3>
                @foreach($dept['categories'] as $category)
                    <h4 style="color: #4b5563; font-size: 14px; margin-bottom: 8px;">{{ $category['name'] }}</h4>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 16px;">
                        <thead>
                            <tr>
                                <th style="background: #f3f4f6; padding: 8px 12px; text-align: left; border: 1px solid #d1d5db;">Item</th>
                                @foreach($comparisonRounds as $round)
                                    <th style="background: #f3f4f6; padding: 8px 12px; text-align: center; border: 1px solid #d1d5db;">{{ $round['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($category['items'] as $item)
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $item['name'] }}</td>
                                    @foreach($comparisonRounds as $round)
                                        @php
                                            $available = $comparison
                                                ? ($item['values'][$round['id']] ?? null)
                                                : $item['available'];
                                        @endphp
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; text-align: center;">
                                            @if($available === null)
                                                <span style="color:#9ca3af;">&mdash;</span>
                                            @else
                                                <span class="badge badge-{{ $available ? 'green' : 'red' }}" style="font-size: 12px;">
                                                    {{ $available ? 'Yes' : 'No' }}
                                                </span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endforeach
        </div>
    @endif
```

Note: the single-assessment fallback path (`$healthProductsData = $healthProductsDetails`) still has each item shaped `['name' => ..., 'available' => bool, 'not_applicable' => bool]` (no `values` key) — the `$comparison ? ... : $item['available']` ternary in the template branches correctly between the two shapes since `$comparison` is `null` in that case. This preserves the original single-assessment badge-grid look content-wise (now as a table instead of inline badges, to fit the round-column layout consistently with every other section).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentHtmlReportHealthProductsAndScoreComparisonRenderTest`
Expected: PASS

- [ ] **Step 5: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: PASS (or only pre-existing unrelated failures — compare against a baseline run before this plan started if any are unclear).

- [ ] **Step 6: Commit**

```bash
git add resources/views/reports/assessment-html-report.blade.php tests/Feature/AssessmentHtmlReportHealthProductsAndScoreComparisonRenderTest.php
git commit -m "feat: render Health Products and Overall Score as side-by-side round comparisons"
```

---

## Deferred / Out of Scope (confirm with user before treating as done)

- PDF export (`generateExecutiveReport()`, `pdf.assessment-executive-report` view) and CSV export (`AssessmentExportService`) are unchanged — comparison is HTML-summary-only per the spec.
- The "Newborn & Paediatric Indicators" section (`$indicatorsDetails` — proportions and stat cards) is not wired into `AssessmentComparisonService` and keeps rendering single-assessment only (Task 8 note).
- The Information Systems "Data Collection Tools & Registers" table only shows the `available` field per round in comparison mode, dropping the `completeness` column that the single-assessment view had (Task 7 note).
- The Human Resources comparison table drops the per-cadre "TOTAL" footer row (Task 9 note).
