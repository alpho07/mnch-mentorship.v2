# EmONC Supportive Supervision Assessment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port CHAI's REDCap "Post EmONC Training Supportive Supervision" survey into the MNCH platform's existing dynamic assessment engine as a new, categorized `AssessmentType`, and add a category concept so assessments can be organized as EmONC / Newborn, Infant & Child / General Facility Readiness at creation time.

**Architecture:** One new lookup table (`assessment_type_categories`) plus one new FK column (`assessment_types.category_id`). All ~235 survey fields become `AssessmentSection`/`AssessmentQuestion` rows seeded by a new idempotent seeder — no new content tables. The dynamic engine (`DynamicFormBuilder`, `DynamicScoringService`) gains two small, additive, backward-compatible capabilities: grouped question rendering and an all-or-nothing composite ("group completeness") question type.

**Tech Stack:** Laravel 12, Filament v3, PHPUnit/Pest-style feature tests (this codebase uses PHPUnit `TestCase` classes — see existing `tests/Feature/AssessmentTemplateTest.php`), MySQL.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-11-emonc-supportive-supervision-assessment-design.md` — every task below implements a piece of it; consult it for the full question inventory (§8) if any wording here is ambiguous.
- **Never break existing behavior.** Every engine change (Tasks 3–4) must be additive: sections/questions with `group = null` (100% of existing data) and question types other than `group_completeness` must render/score byte-identical to today. Run `php artisan test --filter=AssessmentTemplateTest` after Tasks 3 and 4 — it must stay 100% green with no changes to that file.
- All ported EmONC questions use `is_required = false`. The source REDCap form marks nearly everything required, but this platform's assessment workflow (draft → in_progress → completed) is built around saving sections incrementally; forcing every field would break that pattern for no benefit here.
- Yes/No questions use `scoring_map: ['Yes' => 1, 'No' => 0]`, `requires_explanation_on: ['Yes', 'No']` (remarks always visible — matches the source form's always-shown Remarks column), `explanation_label: 'Remarks'`.
- New `question_code` values are globally unique (enforced by the existing `assessment_questions.question_code` unique index) — every code below is prefixed `EMONC_`.
- New `AssessmentSection.code` values are globally unique likewise — all prefixed `emonc_`.
- Migration timestamps continue from the latest existing migration (`2026_08_11_110307_...`) — use `2026_08_11_12####`.
- Follow the existing seeder idempotency convention (`database/seeders/AssessmentQuestionConfigSeeder.php`): every write is `updateOrCreate`, safe to re-run.

---

### Task 1: `assessment_type_categories` table + `AssessmentTypeCategory` model

**Files:**
- Create: `database/migrations/2026_08_11_120000_create_assessment_type_categories_table.php`
- Create: `app/Models/AssessmentTypeCategory.php`
- Modify: `app/Models/AssessmentType.php`
- Test: `tests/Feature/AssessmentTypeCategoryTest.php`

**Interfaces:**
- Produces: `AssessmentTypeCategory` model with `name`, `description`, `order`, `is_active` fillable, `scopeActive()`, `scopeOrdered()`. `AssessmentType::category(): BelongsTo`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTypeCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_type_category_can_be_created_and_scoped(): void
    {
        $active = AssessmentTypeCategory::create(['name' => 'EmONC', 'order' => 1, 'is_active' => true]);
        $inactive = AssessmentTypeCategory::create(['name' => 'Retired', 'order' => 2, 'is_active' => false]);

        $activeIds = AssessmentTypeCategory::active()->ordered()->pluck('id')->all();

        $this->assertSame([$active->id], $activeIds);
    }

    public function test_assessment_type_belongs_to_a_category(): void
    {
        $category = AssessmentTypeCategory::create(['name' => 'EmONC', 'order' => 1, 'is_active' => true]);
        $type = AssessmentType::create([
            'name' => 'Test Type',
            'code' => 'CATEGORY_RELATION_TEST',
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($type->fresh()->category->is($category));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentTypeCategoryTest`
Expected: FAIL — class `AssessmentTypeCategory` not found, and/or `category_id` column doesn't exist on `assessment_types` yet (that column arrives in Task 2 — for now this test's second method is expected to fail on the missing column too; that's fine, both failures are addressed together by the end of Task 2. For Task 1 alone, only run the first test method: `php artisan test --filter=test_assessment_type_category_can_be_created_and_scoped`).

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_type_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_type_categories');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentTypeCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'order',
        'is_active',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function assessmentTypes(): HasMany
    {
        return $this->hasMany(AssessmentType::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
```

- [ ] **Step 5: Add the relation to `AssessmentType`**

In `app/Models/AssessmentType.php`, add after the existing `sections()` method:

```php
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AssessmentTypeCategory::class, 'category_id');
    }
```

- [ ] **Step 6: Run migration and the first test**

Run: `php artisan migrate`
Run: `php artisan test --filter=test_assessment_type_category_can_be_created_and_scoped`
Expected: PASS

(The second test method, `test_assessment_type_belongs_to_a_category`, still fails until Task 2 adds the `category_id` column — that's expected and resolved in Task 2's steps, not here.)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_11_120000_create_assessment_type_categories_table.php app/Models/AssessmentTypeCategory.php app/Models/AssessmentType.php tests/Feature/AssessmentTypeCategoryTest.php
git commit -m "feat: add assessment_type_categories table and model"
```

---

### Task 2: `category_id` column on `assessment_types` + backfill

**Files:**
- Create: `database/migrations/2026_08_11_120100_add_category_id_to_assessment_types_table.php`
- Create: `database/migrations/2026_08_11_120200_backfill_general_facility_readiness_category.php`
- Test: `tests/Feature/AssessmentTypeCategoryTest.php` (already has the failing test from Task 1)

**Interfaces:**
- Consumes: `AssessmentTypeCategory` (Task 1).
- Produces: `assessment_types.category_id` column, nullable at the DB level (matching the existing `backfill_standard_facility_assessment_type` pattern of nullable-then-backfilled) but every type in practice has one after this task's backfill runs.

- [ ] **Step 1: Confirm the test still fails for the right reason**

Run: `php artisan test --filter=test_assessment_type_belongs_to_a_category`
Expected: FAIL — `category_id` is not a fillable/column on `assessment_types`.

- [ ] **Step 2: Create the column migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_types', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_types', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('code')
                    ->constrained('assessment_type_categories')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
```

- [ ] **Step 3: Add `category_id` to `AssessmentType`'s fillable**

In `app/Models/AssessmentType.php`, add `'category_id',` to the `$fillable` array (after `'code',`).

- [ ] **Step 4: Create the backfill migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STANDARD_TYPE_CODE = 'STANDARD_FACILITY_ASSESSMENT';

    private const CATEGORY_NAME = 'General Facility Readiness';

    /**
     * Every AssessmentType needs a category going forward. The pre-existing
     * "Standard Facility Assessment" type (itself backfilled by
     * 2026_08_01_161245_backfill_standard_facility_assessment_type) predates
     * categorization entirely, so it's assigned a catch-all category here.
     * Idempotent — safe to run more than once.
     */
    public function up(): void
    {
        $categoryId = DB::table('assessment_type_categories')->where('name', self::CATEGORY_NAME)->value('id');

        if (! $categoryId) {
            $categoryId = DB::table('assessment_type_categories')->insertGetId([
                'name' => self::CATEGORY_NAME,
                'description' => 'Catch-all category for facility assessment templates that predate categorization.',
                'order' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('assessment_types')
            ->where('code', self::STANDARD_TYPE_CODE)
            ->whereNull('category_id')
            ->update(['category_id' => $categoryId]);
    }

    public function down(): void
    {
        DB::table('assessment_types')
            ->where('code', self::STANDARD_TYPE_CODE)
            ->update(['category_id' => null]);
    }
};
```

- [ ] **Step 5: Run migrations and the test**

Run: `php artisan migrate`
Run: `php artisan test --filter=AssessmentTypeCategoryTest`
Expected: PASS (both test methods)

- [ ] **Step 6: Verify no regression on the existing template picker test**

Run: `php artisan test --filter=CreateAssessmentTemplatePreloadTest`
Expected: PASS — the standard type now has a `category_id` set, but that test doesn't touch categories at all, so it must be unaffected.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_11_120100_add_category_id_to_assessment_types_table.php database/migrations/2026_08_11_120200_backfill_general_facility_readiness_category.php app/Models/AssessmentType.php
git commit -m "feat: add category_id to assessment_types and backfill the standard template"
```

---

### Task 3: Grouped question rendering in `DynamicFormBuilder`

**Files:**
- Modify: `app/Services/DynamicFormBuilder.php:14-49` (the `buildForSection` method)
- Test: `tests/Feature/DynamicFormBuilderGroupingTest.php`

**Interfaces:**
- Consumes: `AssessmentQuestion.group` (existing column, currently unused).
- Produces: `DynamicFormBuilder::buildForSection(int $sectionId, ?int $assessmentId = null): array` — same signature as today. Consecutive questions sharing a non-null `group` are now wrapped in one `Forms\Components\Fieldset` per group; `group = null` questions render exactly as before (unwrapped, in original order).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Services\DynamicFormBuilder;
use Filament\Forms\Components\Fieldset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFormBuilderGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSection(string $code): AssessmentSection
    {
        $type = AssessmentType::create(['name' => 'Grouping Test', 'code' => "GROUPING_TEST_{$code}", 'is_active' => true]);

        return AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Grouped Section',
            'code' => $code,
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);
    }

    private function makeYesNo(AssessmentSection $section, string $questionCode, int $order, ?string $group): AssessmentQuestion
    {
        return AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => $questionCode,
            'question_text' => "Question {$questionCode}",
            'question_type' => 'yes_no',
            'group' => $group,
            'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'order' => $order,
            'is_active' => true,
        ]);
    }

    public function test_questions_sharing_a_group_are_wrapped_in_one_fieldset(): void
    {
        $section = $this->makeSection('grouped_section_test');
        $this->makeYesNo($section, 'GRP_Q1', 1, 'Kit A');
        $this->makeYesNo($section, 'GRP_Q2', 2, 'Kit A');
        $this->makeYesNo($section, 'GRP_Q3', 3, null);

        $fields = DynamicFormBuilder::buildForSection($section->id);

        // GRP_Q1 + GRP_Q2 collapse into one Fieldset labeled "Kit A";
        // the ungrouped GRP_Q3 stays a separate top-level field.
        $this->assertCount(2, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        $this->assertSame('Kit A', $fields[0]->getLabel());
        $this->assertNotInstanceOf(Fieldset::class, $fields[1]);
    }

    public function test_ungrouped_questions_render_with_no_fieldset_wrapping_at_all(): void
    {
        $section = $this->makeSection('ungrouped_section_test');
        $this->makeYesNo($section, 'UNGRP_Q1', 1, null);
        $this->makeYesNo($section, 'UNGRP_Q2', 2, null);

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(2, $fields);
        foreach ($fields as $field) {
            $this->assertNotInstanceOf(Fieldset::class, $field);
        }
    }

    public function test_two_separate_groups_produce_two_separate_fieldsets(): void
    {
        $section = $this->makeSection('two_groups_section_test');
        $this->makeYesNo($section, 'TWOGRP_Q1', 1, 'Kit A');
        $this->makeYesNo($section, 'TWOGRP_Q2', 2, 'Kit B');

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(2, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        $this->assertSame('Kit A', $fields[0]->getLabel());
        $this->assertInstanceOf(Fieldset::class, $fields[1]);
        $this->assertSame('Kit B', $fields[1]->getLabel());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DynamicFormBuilderGroupingTest`
Expected: FAIL — today every question renders as a top-level field regardless of `group`, so `assertCount(2, $fields)` for the 3-question first test actually returns 3, and no `Fieldset` ever appears.

- [ ] **Step 3: Implement grouped rendering**

Replace `app/Services/DynamicFormBuilder.php:14-49` (`buildForSection`) with:

```php
    /**
     * Build form fields for a specific section
     */
    public static function buildForSection(int $sectionId, ?int $assessmentId = null): array
    {
        $questions = AssessmentQuestion::where('assessment_section_id', $sectionId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        if ($questions->isEmpty()) {
            return [
                Forms\Components\Placeholder::make('no_questions')
                    ->label('')
                    ->content('No questions configured for this section yet.')
                    ->columnSpanFull(),
            ];
        }

        $fields = [];
        $currentGroup = null;
        $groupBuffer = [];

        $flushGroup = function () use (&$fields, &$groupBuffer, &$currentGroup) {
            if ($groupBuffer === []) {
                return;
            }

            if ($currentGroup === null) {
                array_push($fields, ...$groupBuffer);
            } else {
                $fields[] = Forms\Components\Fieldset::make($currentGroup)
                    ->schema($groupBuffer)
                    ->columns(1)
                    ->columnSpanFull();
            }

            $groupBuffer = [];
        };

        foreach ($questions as $question) {
            $existingResponse = null;

            if ($assessmentId) {
                $existingResponse = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                    ->where('assessment_question_id', $question->id)
                    ->first();
            }

            $field = static::buildFieldForQuestion($question, $existingResponse);

            if (! $field) {
                continue;
            }

            if ($question->group !== $currentGroup) {
                $flushGroup();
                $currentGroup = $question->group;
            }

            $groupBuffer[] = $field;
        }

        $flushGroup();

        return $fields;
    }
```

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=DynamicFormBuilderGroupingTest`
Expected: PASS (all 3 methods)

- [ ] **Step 5: Verify no regression on the existing template test suite**

Run: `php artisan test --filter=AssessmentTemplateTest`
Expected: PASS, unchanged — every question in that file has `group` unset (null), so `buildForSection` must produce the exact same flat array as before.

- [ ] **Step 6: Commit**

```bash
git add app/Services/DynamicFormBuilder.php tests/Feature/DynamicFormBuilderGroupingTest.php
git commit -m "feat: render questions sharing a group in a labeled fieldset"
```

---

### Task 4: `group_completeness` question type (render + score)

**Files:**
- Modify: `app/Services/DynamicFormBuilder.php` (the `buildFieldForQuestion` match statement, plus one new method)
- Modify: `app/Services/DynamicScoringService.php:15-72` (`recalculateSectionScore`), plus one new method
- Test: `tests/Feature/GroupCompletenessTest.php`

**Interfaces:**
- Consumes: `AssessmentQuestion.question_type = 'group_completeness'`, `AssessmentQuestion.group`, `AssessmentQuestionResponse` (existing model).
- Produces: `DynamicFormBuilder` renders `group_completeness` questions as a read-only `Forms\Components\Placeholder` (never submitted, so `saveResponses()` needs no changes — a `Placeholder` field is never in the submitted form `$data`, which `saveResponses()`'s existing `array_key_exists($fieldName, $data)` guard already skips). `DynamicScoringService::recalculateSectionScore()` now resolves and persists `group_completeness` responses before summing.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentSectionScore;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\DynamicFormBuilder;
use App\Services\DynamicScoringService;
use Filament\Forms\Components\Placeholder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GroupCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Completeness Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    private function makeKitTemplate(): array
    {
        $type = AssessmentType::create(['name' => 'Kit Completeness Test', 'code' => 'KIT_COMPLETENESS_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Kit Section',
            'code' => 'kit_completeness_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);
        $item1 = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'KIT_ITEM_1',
            'question_text' => 'Item 1',
            'question_type' => 'yes_no',
            'group' => 'Kit A',
            'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'order' => 1,
            'is_active' => true,
        ]);
        $item2 = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'KIT_ITEM_2',
            'question_text' => 'Item 2',
            'question_type' => 'yes_no',
            'group' => 'Kit A',
            'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'order' => 2,
            'is_active' => true,
        ]);
        $completeness = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'KIT_COMPLETE',
            'question_text' => 'Kit A Completeness',
            'question_type' => 'group_completeness',
            'group' => 'Kit A',
            'is_scored' => true,
            'order' => 3,
            'is_active' => true,
        ]);

        return [$type, $section, $item1, $item2, $completeness];
    }

    public function test_group_completeness_renders_as_a_disabled_placeholder(): void
    {
        [, $section] = $this->makeKitTemplate();

        $fields = DynamicFormBuilder::buildForSection($section->id);

        // All 3 questions share group "Kit A" -> one Fieldset.
        $this->assertCount(1, $fields);
        $fieldset = $fields[0];
        $children = $fieldset->getChildComponents();
        $this->assertInstanceOf(Placeholder::class, end($children));
    }

    public function test_completeness_scores_one_when_every_sibling_is_at_max(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section, $item1, $item2, $completeness] = $this->makeKitTemplate();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item1->id, 'response_value' => 'Yes', 'score' => 1]);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item2->id, 'response_value' => 'Yes', 'score' => 1]);

        DynamicScoringService::recalculateSectionScore($assessment->id, $section->id);

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $completeness->id)
            ->first();

        $this->assertNotNull($response);
        $this->assertSame('Yes', $response->response_value);
        $this->assertEquals(1, $response->score);
    }

    public function test_completeness_scores_zero_when_a_sibling_is_missing_or_no(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section, $item1, $item2, $completeness] = $this->makeKitTemplate();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item1->id, 'response_value' => 'Yes', 'score' => 1]);
        // item2 left unanswered entirely.

        DynamicScoringService::recalculateSectionScore($assessment->id, $section->id);

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $completeness->id)
            ->first();

        $this->assertNotNull($response);
        $this->assertSame('No', $response->response_value);
        $this->assertEquals(0, $response->score);
    }

    public function test_completeness_score_contributes_to_the_section_total(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section, $item1, $item2, $completeness] = $this->makeKitTemplate();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item1->id, 'response_value' => 'Yes', 'score' => 1]);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item2->id, 'response_value' => 'Yes', 'score' => 1]);

        DynamicScoringService::recalculateSectionScore($assessment->id, $section->id);

        $score = AssessmentSectionScore::where('assessment_id', $assessment->id)
            ->where('assessment_section_id', $section->id)
            ->first();

        // 3 scored questions total (item1, item2, completeness), all at max -> 3/3.
        $this->assertEquals(3, $score->max_score);
        $this->assertEquals(3, $score->total_score);
        $this->assertSame(100.0, (float) $score->percentage);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GroupCompletenessTest`
Expected: FAIL — `group_completeness` isn't a recognized `question_type` yet (renders nothing), and `recalculateSectionScore` has no logic to derive its response.

- [ ] **Step 3: Add the placeholder field builder to `DynamicFormBuilder`**

In `app/Services/DynamicFormBuilder.php`, add `'group_completeness' => static::buildGroupCompletenessField($question, $fieldName, $existingResponse),` to the `match` statement inside `buildFieldForQuestion` (after the `'radio'` line, before `'mortality_three_month'`).

Then add this new method right after `buildRadioField()`:

```php
    /**
     * Group-completeness questions aren't user-answerable — their response
     * is derived by DynamicScoringService from sibling questions sharing
     * the same `group`. Rendered as a disabled placeholder; never submitted
     * (no form key), so saveResponses() needs no changes to skip it.
     */
    protected static function buildGroupCompletenessField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        $content = match ($response?->response_value) {
            'Yes' => '✓ Complete',
            'No' => '✗ Incomplete',
            default => 'Not yet calculated — save this section to compute',
        };

        return Forms\Components\Placeholder::make($fieldName)
            ->label($question->question_text)
            ->content($content)
            ->columnSpanFull();
    }
```

- [ ] **Step 4: Add the resolution step to `DynamicScoringService`**

In `app/Services/DynamicScoringService.php`, modify `recalculateSectionScore` (lines 15-72) by inserting a call right after the `$questions` collection is fetched (after the `->get();` on line 30, before the "Conditional exclusion" comment block):

```php
        // Resolve any group_completeness questions' responses from their
        // sibling groups before scoring sums them like any other question.
        // No-op for sections without one — i.e. every section that existed
        // before this feature.
        self::resolveGroupCompletenessResponses($assessmentId, $questions);
```

Then add this new private method anywhere in the class (e.g. right after `excludeConditionallyHiddenQuestions`):

```php
    /**
     * A `group_completeness` question's response is derived, not submitted:
     * 1 (Yes) iff every other active, scored sibling sharing its `group`
     * (within the same section's already-loaded $questions collection)
     * currently has a response scored at that sibling's own maximum
     * possible score; 0 (No) otherwise, including when a sibling is
     * unanswered. Upserts the response so the normal sum below picks it up
     * exactly like any other scored question.
     */
    private static function resolveGroupCompletenessResponses(int $assessmentId, $questions): void
    {
        $completenessQuestions = $questions->where('question_type', 'group_completeness');

        if ($completenessQuestions->isEmpty()) {
            return;
        }

        foreach ($completenessQuestions as $completenessQuestion) {
            $siblings = $questions->filter(fn ($q) => $q->group === $completenessQuestion->group
                && $q->id !== $completenessQuestion->id
                && $q->question_type !== 'group_completeness');

            if ($siblings->isEmpty()) {
                continue;
            }

            $siblingResponses = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                ->whereIn('assessment_question_id', $siblings->pluck('id'))
                ->get()
                ->keyBy('assessment_question_id');

            $allComplete = $siblings->every(function ($sibling) use ($siblingResponses) {
                $response = $siblingResponses->get($sibling->id);

                if (! $response || $response->score === null) {
                    return false;
                }

                $maxForSibling = ! empty($sibling->scoring_map) ? max($sibling->scoring_map) : 1;

                return (float) $response->score >= (float) $maxForSibling;
            });

            AssessmentQuestionResponse::updateOrCreate(
                ['assessment_id' => $assessmentId, 'assessment_question_id' => $completenessQuestion->id],
                ['response_value' => $allComplete ? 'Yes' : 'No', 'score' => $allComplete ? 1 : 0]
            );
        }
    }
```

- [ ] **Step 5: Run the test again**

Run: `php artisan test --filter=GroupCompletenessTest`
Expected: PASS (all 4 methods)

- [ ] **Step 6: Verify no regression on existing scoring tests**

Run: `php artisan test --filter=AssessmentTemplateTest`
Expected: PASS, unchanged — none of that file's sections contain a `group_completeness` question, so `resolveGroupCompletenessResponses` is a no-op for every one of them and the rest of `recalculateSectionScore` is untouched.

- [ ] **Step 7: Commit**

```bash
git add app/Services/DynamicFormBuilder.php app/Services/DynamicScoringService.php tests/Feature/GroupCompletenessTest.php
git commit -m "feat: add group_completeness question type for all-or-nothing composite scoring"
```

---

### Task 5: Seeder scaffold — categories, AssessmentType, Section A

**Files:**
- Create: `database/seeders/EmoncSupportiveSupervisionSeeder.php`
- Test: `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`

**Interfaces:**
- Consumes: `AssessmentTypeCategory`, `AssessmentType`, `AssessmentSection`, `AssessmentQuestion` (all existing/Task 1).
- Produces: `EmoncSupportiveSupervisionSeeder::run(): void`. Helper methods reused by every later task: `upsertSection(AssessmentType $type, string $code, string $name, ?string $description, bool $isScored, int $order): AssessmentSection`, `upsertQuestion(AssessmentSection $section, array $attrs): void`, `yesNo(string $code, string $text, int $order, ?string $group = null, ?string $helpText = null): array`, `nextOrder(): int`. Type code constant: `EmoncSupportiveSupervisionSeeder::TYPE_CODE === 'EMONC_SUPPORTIVE_SUPERVISION'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use Database\Seeders\EmoncSupportiveSupervisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmoncSupportiveSupervisionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_categories_and_the_assessment_type(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $this->assertTrue(AssessmentTypeCategory::where('name', 'EmONC')->exists());
        $this->assertTrue(AssessmentTypeCategory::where('name', 'Newborn, Infant & Child')->exists());

        $type = AssessmentType::where('code', 'EMONC_SUPPORTIVE_SUPERVISION')->first();
        $this->assertNotNull($type);
        $this->assertSame('EmONC', $type->category->name);
        $this->assertTrue($type->is_active);
    }

    public function test_section_a_is_seeded_with_29_unscored_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $type = AssessmentType::where('code', 'EMONC_SUPPORTIVE_SUPERVISION')->first();
        $section = $type->sections()->where('code', 'emonc_facility_context')->first();

        $this->assertNotNull($section);
        $this->assertFalse($section->is_scored);
        $this->assertSame(29, $section->questions()->count());
        $this->assertSame(0, $section->questions()->where('is_scored', true)->count());

        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_A_FACILITY_CATEGORY')->exists());
        $facilityCategory = AssessmentQuestion::where('question_code', 'EMONC_A_FACILITY_CATEGORY')->first();
        $this->assertSame(['CEMONC', 'BEMONC'], $facilityCategory->options);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);
        $countBefore = AssessmentQuestion::where('question_code', 'like', 'EMONC_%')->count();

        $this->seed(EmoncSupportiveSupervisionSeeder::class);
        $countAfter = AssessmentQuestion::where('question_code', 'like', 'EMONC_%')->count();

        $this->assertSame($countBefore, $countAfter);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: FAIL — class `Database\Seeders\EmoncSupportiveSupervisionSeeder` doesn't exist.

- [ ] **Step 3: Create the seeder with scaffold + categories + type + Section A**

```php
<?php

namespace Database\Seeders;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use Illuminate\Database\Seeder;

/**
 * Ports CHAI's REDCap "Post EmONC Training Supportive Supervision" survey
 * into the platform's dynamic assessment engine as a single categorized
 * AssessmentType. Content transcribed from the live survey on 2026-08-11 —
 * see docs/superpowers/specs/2026-08-11-emonc-supportive-supervision-assessment-design.md
 * §8 for the source-of-truth question inventory.
 *
 * Idempotent — every write is updateOrCreate, safe to re-run.
 *
 * Run with:
 *   php artisan db:seed --class=EmoncSupportiveSupervisionSeeder
 */
class EmoncSupportiveSupervisionSeeder extends Seeder
{
    public const TYPE_CODE = 'EMONC_SUPPORTIVE_SUPERVISION';

    private int $order = 0;

    public function run(): void
    {
        $this->seedCategories();
        $type = $this->seedAssessmentType();
        $this->seedSectionA($type);
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'EmONC', 'description' => 'Emergency Maternal and Newborn Care assessments.', 'order' => 1],
            ['name' => 'Newborn, Infant & Child', 'description' => 'Newborn, infant, and child health assessments.', 'order' => 2],
            ['name' => 'General Facility Readiness', 'description' => 'Catch-all category for facility assessment templates that predate categorization.', 'order' => 0],
        ];

        foreach ($categories as $category) {
            AssessmentTypeCategory::updateOrCreate(
                ['name' => $category['name']],
                array_merge($category, ['is_active' => true])
            );
        }
    }

    private function seedAssessmentType(): AssessmentType
    {
        $category = AssessmentTypeCategory::where('name', 'EmONC')->firstOrFail();

        return AssessmentType::updateOrCreate(
            ['code' => self::TYPE_CODE],
            [
                'name' => 'EmONC Post-Training Supportive Supervision Survey',
                'description' => 'Assesses how facilities apply EmONC training in practice: facility readiness, commodities, emergency kits, referral systems, infection prevention, and gaps/success stories. Ported from CHAI\'s REDCap instrument.',
                'version' => '1.0',
                'is_active' => true,
                'category_id' => $category->id,
            ]
        );
    }

    // ── Shared helpers, used by every seedSectionX() method ────────────────

    private function upsertSection(AssessmentType $type, string $code, string $name, ?string $description, bool $isScored, int $order): AssessmentSection
    {
        $this->order = 0;

        return AssessmentSection::updateOrCreate(
            ['code' => $code],
            [
                'assessment_type_id' => $type->id,
                'name' => $name,
                'description' => $description,
                'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
                'is_scored' => $isScored,
                'order' => $order,
                'is_active' => true,
            ]
        );
    }

    private function nextOrder(): int
    {
        return ++$this->order;
    }

    private function upsertQuestion(AssessmentSection $section, array $attrs): void
    {
        AssessmentQuestion::updateOrCreate(
            ['question_code' => $attrs['code']],
            [
                'assessment_section_id' => $section->id,
                'question_text' => $attrs['text'],
                'help_text' => $attrs['help_text'] ?? null,
                'question_type' => $attrs['type'],
                'options' => $attrs['options'] ?? null,
                'is_required' => false,
                'is_scored' => $attrs['scored'] ?? false,
                'scoring_map' => $attrs['scoring_map'] ?? null,
                'requires_explanation_on' => $attrs['requires_explanation_on'] ?? null,
                'explanation_label' => $attrs['explanation_label'] ?? null,
                'group' => $attrs['group'] ?? null,
                'order' => $attrs['order'],
                'is_active' => true,
            ]
        );
    }

    /** A scored Yes/No question with the survey's standard always-visible-remarks config. */
    private function yesNo(string $code, string $text, int $order, ?string $group = null, ?string $helpText = null): array
    {
        return [
            'code' => $code,
            'text' => $text,
            'type' => 'yes_no',
            'scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'requires_explanation_on' => ['Yes', 'No'],
            'explanation_label' => 'Remarks',
            'order' => $order,
            'group' => $group,
            'help_text' => $helpText,
        ];
    }

    // ── A. Facility Profile (not scored) ───────────────────────────────────

    private function seedSectionA(AssessmentType $type): void
    {
        $section = $this->upsertSection(
            $type,
            'emonc_facility_context',
            'A. Facility Profile',
            'Facility identity, EmONC training coverage, and human resources in the maternity unit. Facility name, MFL code, county, level, and ownership are shown from the selected facility record and not re-collected here.',
            false,
            1
        );

        $this->upsertQuestion($section, [
            'code' => 'EMONC_A_FACILITY_CATEGORY',
            'text' => 'Facility Category',
            'type' => 'select',
            'options' => ['CEMONC', 'BEMONC'],
            'order' => $this->nextOrder(),
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $this->upsertQuestion($section, ['code' => "EMONC_A_SUP{$i}_NAME", 'text' => "Supervisor {$i} — Name", 'type' => 'text', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "EMONC_A_SUP{$i}_TITLE", 'text' => "Supervisor {$i} — Title", 'type' => 'text', 'order' => $this->nextOrder()]);
        }

        $this->upsertQuestion($section, ['code' => 'EMONC_A_RESPONDENT_NAME', 'text' => 'Facility Supervision Respondent — Name', 'type' => 'text', 'order' => $this->nextOrder()]);
        $this->upsertQuestion($section, ['code' => 'EMONC_A_RESPONDENT_CONTACT', 'text' => 'Facility Supervision Respondent — Contact', 'type' => 'text', 'order' => $this->nextOrder()]);
        $this->upsertQuestion($section, ['code' => 'EMONC_A_RESPONDENT_CADRE', 'text' => 'Facility Supervision Respondent — Cadre', 'type' => 'text', 'order' => $this->nextOrder()]);

        $cadres = [
            'EMONC_A_HR_NURSES' => 'Nurses',
            'EMONC_A_HR_CO' => 'Clinical Officers',
            'EMONC_A_HR_MO' => 'Medical Officers',
            'EMONC_A_HR_OB' => 'Obstetricians',
        ];
        foreach ($cadres as $prefix => $label) {
            $this->upsertQuestion($section, ['code' => "{$prefix}_ALLOCATED", 'text' => "{$label} — Number Allocated in Maternity (ANW/Labour Ward/PNW)", 'type' => 'number', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "{$prefix}_TRAINED", 'text' => "{$label} — Number Trained on 5-day EmONC (from 2024 to date)", 'type' => 'number', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "{$prefix}_24HR", 'text' => "{$label} — Number present in the maternity unit in a 24hr shift", 'type' => 'number', 'order' => $this->nextOrder()]);
        }

        $this->upsertQuestion($section, ['code' => 'EMONC_A_EMONC_TRAINED_TOTAL', 'text' => 'Number of EmONC-trained healthcare workers', 'type' => 'number', 'order' => $this->nextOrder()]);

        $departments = ['ANC', 'HRC', 'L/W', 'NBU', 'ANW', 'PNW'];
        foreach ($departments as $dept) {
            $deptCode = str_replace('/', '', $dept);
            $this->upsertQuestion($section, ['code' => "EMONC_A_DIST_{$deptCode}", 'text' => "EmONC-trained healthcare workers — {$dept}", 'type' => 'number', 'order' => $this->nextOrder()]);
        }
    }
}
```

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: PASS (all 3 methods)

- [ ] **Step 5: Commit**

```bash
git add database/seeders/EmoncSupportiveSupervisionSeeder.php tests/Feature/EmoncSupportiveSupervisionSeederTest.php
git commit -m "feat: seed EmONC assessment type categories and Section A (Facility Profile)"
```

---

### Task 6: Seeder — Section B (Feedback) and Section C (Capacity Building)

**Files:**
- Modify: `database/seeders/EmoncSupportiveSupervisionSeeder.php` (`run()` + two new methods)
- Test: `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`

**Interfaces:**
- Consumes: `upsertSection`, `upsertQuestion`, `yesNo`, `nextOrder` (Task 5).
- Produces: sections `emonc_feedback` (10 questions, scored) and `emonc_capacity_building` (2 questions, scored).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`:

```php
    public function test_section_b_is_seeded_with_10_questions_one_scored(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_feedback')->first();

        $this->assertNotNull($section);
        $this->assertTrue($section->is_scored);
        $this->assertSame(10, $section->questions()->count());
        $this->assertSame(1, $section->questions()->where('is_scored', true)->count());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_B_FEEDBACK_MEETING_DONE')->where('is_scored', true)->exists());
    }

    public function test_section_c_is_seeded_with_2_scored_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_capacity_building')->first();

        $this->assertNotNull($section);
        $this->assertSame(2, $section->questions()->count());
        $this->assertSame(2, $section->questions()->where('is_scored', true)->count());
        $cmes = AssessmentQuestion::where('question_code', 'EMONC_C_CMES')->first();
        $this->assertSame('Confirm using the CME register/booklet', $cmes->help_text);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: FAIL — sections `emonc_feedback`/`emonc_capacity_building` don't exist yet.

- [ ] **Step 3: Add the two section methods**

In `database/seeders/EmoncSupportiveSupervisionSeeder.php`, add to `run()` (after `$this->seedSectionA($type);`):

```php
        $this->seedSectionB($type);
        $this->seedSectionC($type);
```

Then add these two methods after `seedSectionA()`:

```php
    // ── B. Feedback to Office & Colleagues (scored) ────────────────────────

    private function seedSectionB(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_feedback', 'B. Feedback to Office & Colleagues', null, true, 2);

        $this->upsertQuestion($section, $this->yesNo('EMONC_B_FEEDBACK_MEETING_DONE', 'Feedback meeting to office held', $this->nextOrder()));

        for ($i = 1; $i <= 3; $i++) {
            $this->upsertQuestion($section, ['code' => "EMONC_B_AP{$i}_TEXT", 'text' => "Action Plan {$i} — Description", 'type' => 'text', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, [
                'code' => "EMONC_B_AP{$i}_STATUS",
                'text' => "Action Plan {$i} — Status",
                'type' => 'select',
                'options' => ['Resolved', 'In Progress', 'Not Addressed'],
                'order' => $this->nextOrder(),
            ]);
            $this->upsertQuestion($section, ['code' => "EMONC_B_AP{$i}_REMARKS", 'text' => "Action Plan {$i} — Remarks", 'type' => 'text', 'order' => $this->nextOrder()]);
        }
    }

    // ── C. Capacity Building (scored) ───────────────────────────────────────

    private function seedSectionC(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_capacity_building', 'C. Capacity Building', 'Sessions for knowledge and skills sharing.', true, 3);

        $helpText = 'Confirm using the CME register/booklet';
        $this->upsertQuestion($section, $this->yesNo('EMONC_C_CMES', 'CMEs held', $this->nextOrder(), null, $helpText));
        $this->upsertQuestion($section, $this->yesNo('EMONC_C_DRILLS', 'Drills held', $this->nextOrder(), null, $helpText));
    }
```

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: PASS (all 5 methods)

- [ ] **Step 5: Commit**

```bash
git add database/seeders/EmoncSupportiveSupervisionSeeder.php tests/Feature/EmoncSupportiveSupervisionSeederTest.php
git commit -m "feat: seed EmONC Section B (Feedback) and Section C (Capacity Building)"
```

---

### Task 7: Seeder — Section D (Key Commodities)

**Files:**
- Modify: `database/seeders/EmoncSupportiveSupervisionSeeder.php` (`run()` + one new method)
- Test: `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`

**Interfaces:**
- Produces: section `emonc_key_commodities` (27 scored yes/no questions).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`:

```php
    public function test_section_d_is_seeded_with_27_scored_commodity_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_key_commodities')->first();

        $this->assertNotNull($section);
        $this->assertSame(27, $section->questions()->count());
        $this->assertSame(27, $section->questions()->where('is_scored', true)->count());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_D_1')->exists());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_D_27')->exists());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: FAIL — `emonc_key_commodities` doesn't exist yet.

- [ ] **Step 3: Add Section D**

In `database/seeders/EmoncSupportiveSupervisionSeeder.php`, add `$this->seedSectionD($type);` to `run()` after `seedSectionC`, then add:

```php
    // ── D. Key Commodities (scored) ─────────────────────────────────────────

    private function seedSectionD(AssessmentType $type): void
    {
        $section = $this->upsertSection(
            $type,
            'emonc_key_commodities',
            'D. Key Commodities',
            'Available and functional in quantity sufficient for one month\'s caseload in the maternity department. Does not refer to other departments.',
            true,
            4
        );

        $items = [
            'Assorted IV cannulas/branulas',
            'Assorted disposable syringes with needles',
            'Elbow gloves/gynaecological gloves',
            'Sterile surgical gloves',
            'Assorted suture material',
            'Blood pressure measurement equipment (Digital BP machine or sphygmomanometer + stethoscope)',
            'Delivery Kit (5 Green towels, 1 Tray 10×14, 2 straight artery forceps 8", cord scissors, episiotomy scissors, 2 needle holders 7", 2 large kidney dishes 10", cord clamps, 1 Gallipot — randomly check 1 kit for contents)',
            'Ambu bag (280ml) with neonatal pre-term (size 0) masks',
            'Ambu bag (280ml) with neonatal term (size 1) masks',
            'Ambu bag (1.5L) with adult masks',
            'Fetoscope/handheld fetal heart monitor/digital fetoscope',
            'Portable examination lamp',
            'Assorted speculums (small/medium/large)',
            'Functional suction machines and catheters or penguin suction',
            'Functional Infant Resuscitation Unit/Radiant Warmer/Resuscitaire',
            'Oxygen set (portable cylinder or central wall supply with mask/nasal cannula + flow meter) or concentrator',
            'Patella hammer',
            'Thermometer',
            'Non-Pneumatic Antishock Garment (NASG)',
            'Oropharyngeal airway for adults',
            'Urine strips (proteinuria and sugar dip sticks) in labour ward and lab',
            'Functioning refrigerator for cold-chain drugs/lab reagents, powered 24/7 (excludes KEPI fridges)',
            'Blood/blood products currently stored with blood-giving/transfusion sets',
            'Haemoglobin meter with reagents',
            'Blood grouping & cross-matching kit (water bath, centrifuge, reagents, cold-chain blood carriers)',
            'Functioning refrigerator available for storing blood, powered 24/7',
            "IV fluids assorted (Normal saline / Ringer's lactate / Half-strength Darrow's) with IV administration set",
        ];

        foreach ($items as $i => $text) {
            $this->upsertQuestion($section, $this->yesNo('EMONC_D_'.($i + 1), $text, $this->nextOrder()));
        }
    }
```

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: PASS (all 6 methods)

- [ ] **Step 5: Commit**

```bash
git add database/seeders/EmoncSupportiveSupervisionSeeder.php tests/Feature/EmoncSupportiveSupervisionSeederTest.php
git commit -m "feat: seed EmONC Section D (Key Commodities)"
```

---

### Task 8: Seeder — Section E, kits 1–3 (Obstetric Hemorrhage, Neonatal Resuscitation, PET/Eclampsia)

**Files:**
- Modify: `database/seeders/EmoncSupportiveSupervisionSeeder.php` (`run()` + one new method + one new helper)
- Test: `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`

**Interfaces:**
- Produces: section `emonc_emergency_kits` created (partially populated — kits 1–3 only; Task 9 completes it). New helper: `seedKit(AssessmentSection $section, string $codePrefix, string $groupLabel, string $parentText, array $items): void` — creates one parent yes/no, N sub-item yes/nos (all sharing `group = $groupLabel`), and one `group_completeness` question, consuming `nextOrder()` for every row so ordering stays continuous across the whole section as more kits are added in Task 9.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`:

```php
    public function test_section_e_kit_1_obstetric_hemorrhage_is_fully_seeded(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_emergency_kits')->first();
        $this->assertNotNull($section);

        $kit1Questions = $section->questions()->where('group', '1. Obstetric Hemorrhage Kit')->get();
        // 1 parent + 14 sub-items + 1 completeness = 16
        $this->assertCount(16, $kit1Questions);

        $completeness = AssessmentQuestion::where('question_code', 'EMONC_E_K1_COMPLETE')->first();
        $this->assertNotNull($completeness);
        $this->assertSame('group_completeness', $completeness->question_type);
        $this->assertSame('1. Obstetric Hemorrhage Kit', $completeness->group);

        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_E_K1_PARENT')->exists());
        $this->assertSame(14, AssessmentQuestion::where('question_code', 'like', 'EMONC_E_K1_%')->where('question_type', 'yes_no')->where('question_code', '!=', 'EMONC_E_K1_PARENT')->count());
    }

    public function test_section_e_kits_2_and_3_are_seeded(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_emergency_kits')->first();

        $this->assertCount(20, $section->questions()->where('group', '2. Neonatal Resuscitation Kit')->get()); // 1 + 18 + 1
        $this->assertCount(20, $section->questions()->where('group', '3. PET/Eclampsia Kit')->get()); // 1 + 18 + 1
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: FAIL — `emonc_emergency_kits` section and its kit questions don't exist yet.

- [ ] **Step 3: Add Section E (kits 1–3) and the `seedKit` helper**

In `database/seeders/EmoncSupportiveSupervisionSeeder.php`, add `$this->seedSectionE($type);` to `run()` after `seedSectionD`, then add:

```php
    // ── E. Emergency Preparedness — Kits & SOPs (scored) ────────────────────

    private function seedSectionE(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_emergency_kits', 'E. Emergency Preparedness — Kits & SOPs', 'Kits with checklists, followed by SOPs/job aids.', true, 5);

        $this->seedKit($section, 'EMONC_E_K1', '1. Obstetric Hemorrhage Kit', 'Obstetric Hemorrhage Kit', [
            'Large bore cannulas',
            'Oxytocin',
            'Tranexamic acid',
            'Misoprostol',
            'Balloon tamponade (UBT or condom)',
            'IV fluids',
            'Giving sets',
            '2-way Foleys catheters',
            'Gynecological gloves',
            'Specimen bottles',
            'NASG',
            'Blood loss monitoring chart',
            'Calibrated drapes',
            'MEOWS chart',
        ]);

        $this->seedKit($section, 'EMONC_E_K2', '2. Neonatal Resuscitation Kit', 'Neonatal Resuscitation Kit', [
            'Resuscitation table with radiant warmer',
            'Ambu bag (280ml, neonatal pre-term size 1/0)',
            'Penguin sucker',
            'Oral pharyngeal airway',
            'Oxygen source',
            'Non-rebreather mask',
            'Suction catheter size 8 (preterm)',
            'Suction catheter size 10 (all)',
            'Suction catheter size 12 (meconium)',
            'Assorted syringes & needles',
            'Cannulas',
            'Pulse oximeter',
            'Stethoscope',
            'Thermal blanket / plastic wrap for preterm',
            'Cap to prevent heat loss',
            'Dextrose solution (50%)',
            'Adrenalin injection',
            'Neonatal nasal prongs',
        ]);

        $this->seedKit($section, 'EMONC_E_K3', '3. PET/Eclampsia Kit', 'PET/Eclampsia Kit', [
            'Magnesium sulphate 50% (3 ampoules)',
            'Calcium gluconate',
            'Patella hammer',
            '20cc syringes',
            '10cc syringes',
            'Labetalol (oral and injectable)',
            'Methyldopa',
            'Nifedipine',
            'Inj. hydralazine',
            'Water for injection',
            'Inj. lignocaine 2%',
            '2-way Foleys catheter',
            'Urine bag',
            'Cannulas',
            'Specimen bottles',
            'Gloves',
            'Nasal prongs',
            'Magnesium Sulphate Toxicity Monitoring Chart',
        ]);
    }

    /**
     * Seeds one kit: a parent "kit available" yes/no, each of its sub-items
     * (all sharing $groupLabel so DynamicFormBuilder renders them as one
     * fieldset), and a trailing group_completeness question that
     * DynamicScoringService derives from every other question in the group.
     */
    private function seedKit(AssessmentSection $section, string $codePrefix, string $groupLabel, string $parentText, array $items): void
    {
        $this->upsertQuestion($section, $this->yesNo("{$codePrefix}_PARENT", $parentText, $this->nextOrder(), $groupLabel));

        foreach ($items as $i => $itemText) {
            $this->upsertQuestion($section, $this->yesNo($codePrefix.'_'.($i + 1), $itemText, $this->nextOrder(), $groupLabel));
        }

        $this->upsertQuestion($section, [
            'code' => "{$codePrefix}_COMPLETE",
            'text' => "{$parentText} Completeness",
            'type' => 'group_completeness',
            'scored' => true,
            'group' => $groupLabel,
            'order' => $this->nextOrder(),
        ]);
    }
```

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: PASS (all 8 methods)

- [ ] **Step 5: Commit**

```bash
git add database/seeders/EmoncSupportiveSupervisionSeeder.php tests/Feature/EmoncSupportiveSupervisionSeederTest.php
git commit -m "feat: seed EmONC Section E kits 1-3 (Hemorrhage, Neonatal Resus, PET/Eclampsia)"
```

---

### Task 9: Seeder — Section E, kits 4–6 + SOPs/Job Aids

**Files:**
- Modify: `database/seeders/EmoncSupportiveSupervisionSeeder.php` (`seedSectionE()` body only — append calls)
- Test: `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`

**Interfaces:**
- Consumes: `seedKit()` (Task 8).
- Produces: `emonc_emergency_kits` section fully populated at 106 questions (88 kit rows across 6 kits + 12 SOP/job-aid items + — wait, 6 kits × (1 parent + items + 1 completeness): kit sizes 14/18/18/14/11/7 sub-items → totals per kit 16/20/20/16/13/9 = 94, plus 12 SOPs = 106).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`:

```php
    public function test_section_e_is_fully_seeded_with_106_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_emergency_kits')->first();

        $this->assertSame(106, $section->questions()->count());
        $this->assertSame(6, $section->questions()->where('question_type', 'group_completeness')->count());

        $this->assertCount(16, $section->questions()->where('group', '4. Maternal Resuscitation Kit')->get()); // 1 + 14 + 1
        $this->assertCount(13, $section->questions()->where('group', '5. Delivery Kit')->get()); // 1 + 11 + 1
        $this->assertCount(9, $section->questions()->where('group', '6. Assisted Vacuum Delivery Kit (AVD/Kiwi kit)')->get()); // 1 + 7 + 1

        $sopQuestions = $section->questions()->where('group', 'SOPs / Job Aids')->get();
        $this->assertCount(12, $sopQuestions);
        $this->assertTrue($sopQuestions->every(fn ($q) => $q->question_type === 'yes_no'));
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_E_SOP_1')->exists());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_E_SOP_12')->exists());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: FAIL — kits 4-6 and SOPs don't exist yet (Section E currently at 60 questions from Task 8: 16+20+20 for kits 1-3, wait actually 16+20+20=56... regardless, count is short of 106).

- [ ] **Step 3: Append kits 4–6 and SOPs to `seedSectionE`**

In `database/seeders/EmoncSupportiveSupervisionSeeder.php`, in the `seedSectionE()` method, add after the kit 3 (`PET/Eclampsia Kit`) block, still inside the same method:

```php
        $this->seedKit($section, 'EMONC_E_K4', '4. Maternal Resuscitation Kit', 'Maternal Resuscitation Kit', [
            'Ambu bag (1.5L, adult)',
            'Oropharyngeal airway (different sizes)',
            'Foleys catheter with urine bag',
            'Oxygen tubing & mask (NRM)',
            'IV fluids',
            'Large bore cannulas',
            'Specimen bottles',
            'NASG',
            'Patella hammer',
            'Fetoscope',
            'Stethoscope',
            'BP machine',
            'Thermometer',
            'Blood loss monitoring chart',
        ]);

        $this->seedKit($section, 'EMONC_E_K5', '5. Delivery Kit', 'Delivery Kit', [
            '6 green towels',
            '1 Tray 10×14',
            '2 straight artery forceps 8"',
            'Cord scissors',
            'Episiotomy scissors',
            '2 needle holders 7"',
            '2 large kidney dishes 10"',
            'Cord clamps',
            '1 Gallipot',
            'Sims speculum (small/medium/large)',
            'Cusco speculum (small/medium/large)',
        ]);

        $this->seedKit($section, 'EMONC_E_K6', '6. Assisted Vacuum Delivery Kit (AVD/Kiwi kit)', 'Assisted Vacuum Delivery Kit (AVD/Kiwi kit)', [
            'Vacuum extractor (Omni Cap/Pro Cap)',
            'Syringes',
            'Needles',
            'Foleys catheter',
            'Fetoscope',
            'V-drape',
            'Lubricant (e.g. K-Y jelly)',
        ]);

        $sopHelpText = 'Confirm physically that the job aid is available — laminated chart, wall chart, poster, or leaflet — appropriately placed in a visible location.';
        $sops = [
            'EMOTIVE',
            'PET/Eclampsia',
            'Breech Delivery',
            'Shoulder Dystocia',
            'Maternal Resuscitation',
            'Neonatal Resuscitation',
            'Maternal Shock',
            'PPH',
            'NASG Application',
            'Assisted Vacuum Delivery',
            'Heat Stable Carbetocin',
            'AMTSL Job Aid',
        ];
        foreach ($sops as $i => $text) {
            $this->upsertQuestion($section, $this->yesNo('EMONC_E_SOP_'.($i + 1), $text, $this->nextOrder(), 'SOPs / Job Aids', $sopHelpText));
        }
```

(This is appended immediately before the closing `}` of `seedSectionE()`.)

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: PASS (all 9 methods)

- [ ] **Step 5: Commit**

```bash
git add database/seeders/EmoncSupportiveSupervisionSeeder.php tests/Feature/EmoncSupportiveSupervisionSeederTest.php
git commit -m "feat: seed EmONC Section E kits 4-6 and SOPs/Job Aids"
```

---

### Task 10: Seeder — Section F (Referrals) and Section G (Infection Prevention Control)

**Files:**
- Modify: `database/seeders/EmoncSupportiveSupervisionSeeder.php` (`run()` + two new methods)
- Test: `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`

**Interfaces:**
- Produces: sections `emonc_referrals` (19 questions, 4 scored) and `emonc_ipc` (6 scored questions).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`:

```php
    public function test_section_f_is_seeded_with_19_questions_4_scored(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_referrals')->first();

        $this->assertNotNull($section);
        $this->assertSame(19, $section->questions()->count());
        $this->assertSame(4, $section->questions()->where('is_scored', true)->count());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_F_REF_JAN2025')->exists());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_F_REF_MAR2026')->exists());
    }

    public function test_section_g_is_seeded_with_6_scored_ipc_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_ipc')->first();

        $this->assertNotNull($section);
        $this->assertSame(6, $section->questions()->count());
        $this->assertSame(6, $section->questions()->where('is_scored', true)->count());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: FAIL — sections `emonc_referrals`/`emonc_ipc` don't exist yet.

- [ ] **Step 3: Add Section F and Section G**

In `database/seeders/EmoncSupportiveSupervisionSeeder.php`, add to `run()` (after `seedSectionE`):

```php
        $this->seedSectionF($type);
        $this->seedSectionG($type);
```

Then add:

```php
    // ── F. Referral Systems (scored) ─────────────────────────────────────

    private function seedSectionF(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_referrals', 'F. Referral Systems', 'Confirm using the referral form or referral register, where available.', true, 6);

        $questions = [
            'Notified of, or notified about, a referral before the patient arrived in the receiving facility (most recent referral)',
            'Does the maternity unit have access to a functional phone?',
            'Do you have access to ambulance services available 24/7 for maternity referrals to higher-level facilities?',
            'Was the most recent referral accompanied by a skilled health personnel?',
        ];
        foreach ($questions as $i => $text) {
            $this->upsertQuestion($section, $this->yesNo('EMONC_F_'.($i + 1), $text, $this->nextOrder()));
        }

        $months = [
            'JAN2025' => 'Jan 2025', 'FEB2025' => 'Feb 2025', 'MAR2025' => 'Mar 2025', 'APR2025' => 'Apr 2025',
            'MAY2025' => 'May 2025', 'JUN2025' => 'Jun 2025', 'JUL2025' => 'Jul 2025', 'AUG2025' => 'Aug 2025',
            'SEP2025' => 'Sep 2025', 'OCT2025' => 'Oct 2025', 'NOV2025' => 'Nov 2025', 'DEC2025' => 'Dec 2025',
            'JAN2026' => 'Jan 2026', 'FEB2026' => 'Feb 2026', 'MAR2026' => 'Mar 2026',
        ];
        foreach ($months as $code => $label) {
            $this->upsertQuestion($section, ['code' => "EMONC_F_REF_{$code}", 'text' => "Referrals out — {$label}", 'type' => 'number', 'order' => $this->nextOrder()]);
        }
    }

    // ── G. Infection Prevention Control (scored) ────────────────────────────

    private function seedSectionG(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_ipc', 'G. Infection Prevention Control', null, true, 7);

        $questions = [
            'Is there clean running water/soap?',
            'Is the waste segregated? (color-coded bins and liners)',
            'Are antiseptics available?',
            'Are there alcohol hand rubs?',
            'Are disinfectants available?',
            'Is there a functional facility for sterilization?',
        ];
        foreach ($questions as $i => $text) {
            $this->upsertQuestion($section, $this->yesNo('EMONC_G_'.($i + 1), $text, $this->nextOrder()));
        }
    }
```

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: PASS (all 11 methods)

- [ ] **Step 5: Commit**

```bash
git add database/seeders/EmoncSupportiveSupervisionSeeder.php tests/Feature/EmoncSupportiveSupervisionSeederTest.php
git commit -m "feat: seed EmONC Section F (Referrals) and Section G (Infection Prevention Control)"
```

---

### Task 11: Seeder — Section H (Gaps & Success Stories) and Section J (Additional Notes)

**Files:**
- Modify: `database/seeders/EmoncSupportiveSupervisionSeeder.php` (`run()` + two new methods)
- Test: `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`

**Interfaces:**
- Produces: sections `emonc_gaps_success` (35 unscored questions) and `emonc_notes` (1 unscored question). This is the final content task — after this, the full seeder produces 9 sections and 235 questions.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EmoncSupportiveSupervisionSeederTest.php`:

```php
    public function test_section_h_is_seeded_with_35_unscored_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_gaps_success')->first();

        $this->assertNotNull($section);
        $this->assertFalse($section->is_scored);
        $this->assertSame(35, $section->questions()->count());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_H_GAP1_GAP')->exists());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_H_GAP5_WHEN')->exists());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_H_SUCCESS1_WHAT')->exists());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_H_SUCCESS5_IMPACT')->exists());
    }

    public function test_section_j_is_seeded_with_1_optional_question(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_notes')->first();

        $this->assertNotNull($section);
        $this->assertSame(1, $section->questions()->count());
        $question = $section->questions()->first();
        $this->assertFalse($question->is_required);
    }

    public function test_full_seeder_produces_9_sections_and_235_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $type = AssessmentType::where('code', 'EMONC_SUPPORTIVE_SUPERVISION')->first();

        $this->assertSame(9, $type->sections()->count());
        $this->assertSame(235, AssessmentQuestion::whereIn('assessment_section_id', $type->sections()->pluck('id'))->count());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: FAIL — sections `emonc_gaps_success`/`emonc_notes` don't exist yet.

- [ ] **Step 3: Add Section H and Section J**

In `database/seeders/EmoncSupportiveSupervisionSeeder.php`, add to `run()` (after `seedSectionG`):

```php
        $this->seedSectionH($type);
        $this->seedSectionJ($type);
```

Then add:

```php
    // ── H. Key Gaps, Recommendations & Success Stories (not scored) ────────

    private function seedSectionH(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_gaps_success', 'H. Gaps & Success Stories', null, false, 8);

        for ($i = 1; $i <= 5; $i++) {
            $this->upsertQuestion($section, ['code' => "EMONC_H_GAP{$i}_GAP", 'text' => "Gap {$i}", 'type' => 'text', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "EMONC_H_GAP{$i}_ACTION", 'text' => "Gap {$i} — Action", 'type' => 'text', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "EMONC_H_GAP{$i}_WHO", 'text' => "Gap {$i} — Who", 'type' => 'text', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "EMONC_H_GAP{$i}_WHEN", 'text' => "Gap {$i} — When", 'type' => 'text', 'order' => $this->nextOrder()]);
        }

        for ($i = 1; $i <= 5; $i++) {
            $this->upsertQuestion($section, ['code' => "EMONC_H_SUCCESS{$i}_WHAT", 'text' => "Success Story {$i} — What Happened", 'type' => 'text', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "EMONC_H_SUCCESS{$i}_HOW", 'text' => "Success Story {$i} — How It Was Achieved", 'type' => 'text', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "EMONC_H_SUCCESS{$i}_IMPACT", 'text' => "Success Story {$i} — Impact on Patient Care", 'type' => 'text', 'order' => $this->nextOrder()]);
        }
    }

    // ── J. Additional Notes (not scored) ────────────────────────────────────

    private function seedSectionJ(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_notes', 'J. Additional Notes', null, false, 9);

        $this->upsertQuestion($section, [
            'code' => 'EMONC_J_COMMENTS',
            'text' => 'Additional comments',
            'help_text' => 'Optional',
            'type' => 'text',
            'order' => $this->nextOrder(),
        ]);
    }
```

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=EmoncSupportiveSupervisionSeederTest`
Expected: PASS (all 14 methods)

- [ ] **Step 5: Commit**

```bash
git add database/seeders/EmoncSupportiveSupervisionSeeder.php tests/Feature/EmoncSupportiveSupervisionSeederTest.php
git commit -m "feat: seed EmONC Section H (Gaps & Success Stories) and Section J (Notes)"
```

---

### Task 12: `CreateAssessment` — category select filters the template select

**Files:**
- Modify: `app/Filament/Resources/AssessmentResource/Pages/CreateAssessment.php:58-76` (`form()`, "Assessment Details" section)
- Test: `tests/Feature/CreateAssessmentCategoryFilterTest.php`

**Interfaces:**
- Consumes: `AssessmentTypeCategory::active()->ordered()` (Task 1).
- Produces: no signature change to `CreateAssessment` — same Livewire page. New optional `category_filter` form field (dehydrated false, like the existing `county_filter`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\CreateAssessment;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CreateAssessmentCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_a_category_narrows_the_template_options(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_assessment', 'create_assessment']);
        $this->actingAs($user);

        $catA = AssessmentTypeCategory::create(['name' => 'Filter Test Cat A', 'order' => 1, 'is_active' => true]);
        $catB = AssessmentTypeCategory::create(['name' => 'Filter Test Cat B', 'order' => 2, 'is_active' => true]);
        $typeA = AssessmentType::create(['name' => 'Filter Test Type A', 'code' => 'FILTER_TEST_TYPE_A', 'is_active' => true, 'category_id' => $catA->id]);
        $typeB = AssessmentType::create(['name' => 'Filter Test Type B', 'code' => 'FILTER_TEST_TYPE_B', 'is_active' => true, 'category_id' => $catB->id]);

        $component = Livewire::test(CreateAssessment::class);

        // No category picked yet -> both templates visible (mirrors the
        // existing county_filter -> facility_id UX elsewhere on this form).
        $optionsBefore = $component->instance()->form->getComponent('data.assessment_type_id')->getOptions();
        $this->assertArrayHasKey($typeA->id, $optionsBefore);
        $this->assertArrayHasKey($typeB->id, $optionsBefore);

        $component->fillForm(['category_filter' => $catA->id]);

        $optionsAfter = $component->instance()->form->getComponent('data.assessment_type_id')->getOptions();
        $this->assertArrayHasKey($typeA->id, $optionsAfter);
        $this->assertArrayNotHasKey($typeB->id, $optionsAfter);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CreateAssessmentCategoryFilterTest`
Expected: FAIL — there's no `category_filter` field yet, and `assessment_type_id`'s options aren't filtered by it.

- [ ] **Step 3: Add the category select and wire the filter**

In `app/Filament/Resources/AssessmentResource/Pages/CreateAssessment.php`, replace the `Forms\Components\Section::make('Assessment Details')` block (lines 58-76) with:

```php
                Forms\Components\Section::make('Assessment Details')
                    ->schema([
                        Forms\Components\Select::make('category_filter')
                            ->label('Category')
                            ->helperText('Optional — narrows the Assessment list below to templates in this category.')
                            ->options(fn () => \App\Models\AssessmentTypeCategory::active()->ordered()->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->dehydrated(false),
                        Forms\Components\Select::make('assessment_type_id')
                            ->label('Assessment')
                            ->helperText('Pick the assessment template to use — its sections and questions load automatically.')
                            ->relationship(
                                name: 'assessmentType',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Forms\Get $get, $query) => $query
                                    ->where('is_active', true)
                                    ->when($get('category_filter'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Forms\Components\DatePicker::make('assessment_date')
                            ->label('Assessment Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->columns(2),
```

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=CreateAssessmentCategoryFilterTest`
Expected: PASS

- [ ] **Step 5: Verify no regression on existing CreateAssessment tests**

Run: `php artisan test --filter=CreateAssessmentTemplatePreloadTest`
Run: `php artisan test --filter=AssessmentTemplateTest`
Expected: both PASS unchanged — neither fills `category_filter`, so `assessment_type_id` continues showing every active template exactly as before.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AssessmentResource/Pages/CreateAssessment.php tests/Feature/CreateAssessmentCategoryFilterTest.php
git commit -m "feat: add category filter to the CreateAssessment template picker"
```

---

### Task 13: `ListAssessments` — category column + filter

**Files:**
- Modify: `app/Filament/Resources/AssessmentResource/Pages/ListAssessments.php:113-127` (columns), `:202-206` (filters)
- Test: `tests/Feature/ListAssessmentsCategoryFilterTest.php`

**Interfaces:**
- Produces: no signature change. New table column `assessmentType.category.name`, new filter `assessment_type_category_id`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\ListAssessments;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ListAssessmentsCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_filter_narrows_the_assessments_table(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $this->actingAs($user);

        $catA = AssessmentTypeCategory::create(['name' => 'List Filter Cat A', 'order' => 1, 'is_active' => true]);
        $catB = AssessmentTypeCategory::create(['name' => 'List Filter Cat B', 'order' => 2, 'is_active' => true]);
        $typeA = AssessmentType::create(['name' => 'List Filter Type A', 'code' => 'LIST_FILTER_TYPE_A', 'is_active' => true, 'category_id' => $catA->id]);
        $typeB = AssessmentType::create(['name' => 'List Filter Type B', 'code' => 'LIST_FILTER_TYPE_B', 'is_active' => true, 'category_id' => $catB->id]);

        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $assessmentA = Assessment::create(['facility_id' => $facilityA->id, 'assessment_type_id' => $typeA->id, 'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id]);
        $assessmentB = Assessment::create(['facility_id' => $facilityB->id, 'assessment_type_id' => $typeB->id, 'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id]);

        Livewire::test(ListAssessments::class)
            ->filterTable('assessment_type_category_id', $catA->id)
            ->assertCanSeeTableRecords([$assessmentA])
            ->assertCanNotSeeTableRecords([$assessmentB]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ListAssessmentsCategoryFilterTest`
Expected: FAIL — no `assessment_type_category_id` filter exists yet.

- [ ] **Step 3: Add the column**

In `app/Filament/Resources/AssessmentResource/Pages/ListAssessments.php`, add right after the `assessmentType.name` column (after line 127, i.e. right after its closing `,`):

```php
                Tables\Columns\TextColumn::make('assessmentType.category.name')
                    ->label('Category')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
```

- [ ] **Step 4: Add the filter**

In the same file, add right after the `assessment_type_id` filter (after line 206, i.e. right after its closing `,`):

```php
                Tables\Filters\SelectFilter::make('assessment_type_category_id')
                    ->label('Category')
                    ->options(fn () => \App\Models\AssessmentTypeCategory::ordered()->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $categoryId) => $q->whereHas(
                            'assessmentType',
                            fn ($sq) => $sq->where('category_id', $categoryId)
                        )
                    )),
```

- [ ] **Step 5: Run the test again**

Run: `php artisan test --filter=ListAssessmentsCategoryFilterTest`
Expected: PASS

- [ ] **Step 6: Verify no regression**

Run: `php artisan test --filter=AssessmentTableFiltersTest`
Expected: PASS unchanged.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/AssessmentResource/Pages/ListAssessments.php tests/Feature/ListAssessmentsCategoryFilterTest.php
git commit -m "feat: add category column and filter to the assessments list"
```

---

### Task 14: `AssessmentDashboard` — show the template's category

**Files:**
- Modify: `app/Filament/Resources/AssessmentResource/Pages/AssessmentDashboard.php:66-75` (`getInfolist`)
- Test: `tests/Feature/AssessmentDashboardCategoryTest.php`

**Interfaces:**
- Produces: no signature change. Infolist gains a `assessmentType.category.name` `TextEntry`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentDashboardCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_the_templates_category(): void
    {
        $user = User::factory()->create(['name' => 'Dashboard Category Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $category = AssessmentTypeCategory::create(['name' => 'Dashboard Category Test', 'order' => 1, 'is_active' => true]);
        $type = AssessmentType::create(['name' => 'Dashboard Category Type', 'code' => 'DASHBOARD_CATEGORY_TEST', 'is_active' => true, 'category_id' => $category->id]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Section',
            'code' => 'dashboard_category_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $user->id,
        ]);

        $url = AssessmentResource::getUrl('dashboard', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Dashboard Category Test');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentDashboardCategoryTest`
Expected: FAIL — the dashboard's infolist doesn't show category yet.

- [ ] **Step 3: Add the category entry to the infolist**

In `app/Filament/Resources/AssessmentResource/Pages/AssessmentDashboard.php`, replace lines 66-75 (`getInfolist`) with:

```php
    public function getInfolist(string $name): ?Infolist
    {
        if ($name !== 'assessment_summary') {
            return null;
        }

        return Infolist::make()
            ->record($this->record)
            ->schema([
                Section::make('Assessment Details')
                    ->schema([
                        TextEntry::make('facility.name')->label('Facility'),
                        TextEntry::make('assessmentType.category.name')->label('Category')->placeholder('—'),
                        TextEntry::make('assessment_type')->label('Type'),
                        TextEntry::make('assessment_date')->label('Date')->date(),
                        TextEntry::make('assessor.name')->label('Assessor'),
                    ])
                    ->columns(2),
            ]);
    }
```

- [ ] **Step 4: Run the test again**

Run: `php artisan test --filter=AssessmentDashboardCategoryTest`
Expected: PASS

- [ ] **Step 5: Verify no regression**

Run: `php artisan test --filter=AssessmentDashboardTeamTest`
Expected: PASS unchanged.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AssessmentResource/Pages/AssessmentDashboard.php tests/Feature/AssessmentDashboardCategoryTest.php
git commit -m "feat: show the template's category on the assessment dashboard"
```

---

### Task 15: End-to-end integration test

**Files:**
- Create: `tests/Feature/EmoncSupportiveSupervisionEndToEndTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–14.
- Produces: nothing new — this task only adds a regression-guarding integration test tying the whole feature together.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSectionScore;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\AssessmentPdfReportService;
use App\Services\DynamicFormBuilder;
use Database\Seeders\EmoncSupportiveSupervisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmoncSupportiveSupervisionEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_emonc_seeder_produces_a_working_assessment_end_to_end(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $type = AssessmentType::where('code', EmoncSupportiveSupervisionSeeder::TYPE_CODE)->firstOrFail();
        $this->assertSame(9, $type->sections()->count());
        $this->assertSame('EmONC', $type->category->name);

        $user = User::factory()->create(['name' => 'E2E Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $user->id,
        ]);

        // Fill every real (non-completeness) question in Kit 1 with "Yes" —
        // exercises grouped rendering, saveResponses(), and the
        // group_completeness derivation together.
        $kitSection = $type->sections()->where('code', 'emonc_emergency_kits')->firstOrFail();
        $kit1Questions = $kitSection->questions()
            ->where('group', '1. Obstetric Hemorrhage Kit')
            ->where('question_type', '!=', 'group_completeness')
            ->get();

        $data = [];
        foreach ($kit1Questions as $question) {
            $data["question_response_{$question->id}"] = 'Yes';
        }

        DynamicFormBuilder::saveResponses($assessment->id, $kitSection->id, $data);

        $completenessQuestion = $kitSection->questions()->where('question_code', 'EMONC_E_K1_COMPLETE')->firstOrFail();
        $completenessResponse = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $completenessQuestion->id)
            ->first();

        $this->assertNotNull($completenessResponse);
        $this->assertSame('Yes', $completenessResponse->response_value);
        $this->assertEquals(1, $completenessResponse->score);

        $sectionScore = AssessmentSectionScore::where('assessment_id', $assessment->id)
            ->where('assessment_section_id', $kitSection->id)
            ->first();
        $this->assertNotNull($sectionScore);
        $this->assertGreaterThan(0, $sectionScore->total_score);

        // A second, unscored section (Facility Profile) must also save
        // cleanly through the same generic path.
        $facilitySection = $type->sections()->where('code', 'emonc_facility_context')->firstOrFail();
        $categoryQuestion = $facilitySection->questions()->where('question_code', 'EMONC_A_FACILITY_CATEGORY')->firstOrFail();
        DynamicFormBuilder::saveResponses($assessment->id, $facilitySection->id, [
            "question_response_{$categoryQuestion->id}" => 'CEMONC',
        ]);
        $savedCategoryResponse = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $categoryQuestion->id)
            ->first();
        $this->assertSame('CEMONC', $savedCategoryResponse->response_value);

        // Generic PDF export must not throw for this template.
        $pdf = app(AssessmentPdfReportService::class)->generateExecutiveReport($assessment->fresh());
        $this->assertNotNull($pdf);

        // Generic CSV export must not throw either.
        $csv = app(\App\Services\AssessmentExportService::class)->exportAssessmentToCSV($assessment->fresh());
        $this->assertIsString($csv);
        $this->assertNotSame('', $csv);

        // The read-only summary page must render without error for this template.
        $summaryUrl = \App\Filament\Resources\AssessmentResource::getUrl('summary', ['record' => $assessment->id]);
        $this->get($summaryUrl)->assertOk();
    }
}
```

- [ ] **Step 2: Run the test**

Run: `php artisan test --filter=EmoncSupportiveSupervisionEndToEndTest`
Expected: PASS. If it fails, the failure will point at whichever earlier task's piece is broken — fix that task's code, not this test.

- [ ] **Step 3: Run the entire assessment-related test suite as a final regression check**

Run: `php artisan test --filter=Assessment`
Expected: 100% PASS — this covers every existing Assessment* test file plus all the new ones from this plan.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/EmoncSupportiveSupervisionEndToEndTest.php
git commit -m "test: add end-to-end coverage for the EmONC assessment feature"
```
