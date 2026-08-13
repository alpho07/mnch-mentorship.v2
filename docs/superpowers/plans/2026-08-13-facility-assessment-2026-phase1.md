# Facility Readiness Assessment 2026 — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the engine capabilities the 2026 Facility Readiness Assessment content needs — isolated versioning, line-item splitting with auto-lettering/indent, per-cell N/A in Human Resources, attachable checklists, and whole-block conditional visibility — without touching any 2025 assessment data or behavior.

**Architecture:** Additive migrations (new nullable columns/tables) plus targeted query filters in the existing Filament pages and scoring services. No existing table is dropped or renamed; every new column defaults such that untouched 2025 rows behave exactly as before. Two new shared, subject-agnostic classes join the existing `App\Services\FormKernel\*` family (`LineItemGrouper`) and one new small domain (`AssessmentChecklist`/`AssessmentChecklistItem`).

**Tech Stack:** Laravel 12, Filament v3, MySQL, PHPUnit (`php artisan test`).

## Global Constraints

- Every new FK column is nullable with a backfill step — no existing row may end up with an unexpected `NULL` that changes its current behavior.
- Every schema change that adds a `UNIQUE` constraint scoped by `assessment_type_id` must first `dropUnique` the old global-unique index by Laravel's default index name (`{table}_{column}_unique`) in the *same* migration, then add the composite one — never leave both indexes present.
- No task in this plan renames, drops, or mutates any 2025 `AssessmentType`/`AssessmentSection`/`AssessmentQuestion`/`Commodity`/`CommodityCategory`/`AssessmentDepartment`/`MainCadre` row, or the 2025 assessment's rendered form/report output.
- Deviation from the approved design doc (`docs/superpowers/specs/2026-08-13-facility-assessment-2026-phase1-design.md`), discovered while mapping exact files: Part B does **not** introduce a `group_header` question type or a `parent_question_id`/`parent_commodity_id` self-FK. The existing `GroupedFieldRenderer::buildGroupFieldset()` already renders a question's `group` string as the enclosing Fieldset/Section's header with zero extra DB rows — reusing that (plus a new `group_label` column on `Commodity`, which has no equivalent grouping mechanism today) fully delivers the approved "header + lettered indented children" behavior with less schema, so that's what this plan builds. Every other approved capability (A, C, D, E) is implemented exactly as specified.
- Run `php artisan test --filter=Assessment` (and the specific new test classes named below) after every task; run the full suite before the final task's commit.

---

### Task 1: Type-scope master data + fix global-unique collisions

**Files:**
- Create: `database/migrations/2026_08_13_150000_add_assessment_type_id_to_commodity_master_data.php`
- Create: `database/migrations/2026_08_13_150001_scope_section_and_question_codes_to_assessment_type.php`
- Modify: `app/Models/CommodityCategory.php`
- Modify: `app/Models/AssessmentDepartment.php`
- Modify: `app/Models/MainCadre.php`
- Test: `tests/Feature/AssessmentTypeScopingTest.php`

**Interfaces:**
- Produces: `CommodityCategory::assessmentType(): BelongsTo`, `AssessmentDepartment::assessmentType(): BelongsTo`, `MainCadre::assessmentType(): BelongsTo` — Task 2 uses these implicitly via `where('assessment_type_id', ...)` queries.
- Produces: `commodity_categories.assessment_type_id`, `assessment_departments.assessment_type_id`, `assessment_cadres.assessment_type_id` columns — consumed by Task 2 and Task 7.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AssessmentDepartment;
use App\Models\AssessmentType;
use App\Models\CommodityCategory;
use App\Models\MainCadre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTypeScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_commodity_categories_can_share_the_same_slug_across_two_assessment_types(): void
    {
        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_TEST', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_TEST', 'is_active' => true]);

        $cat2025 = CommodityCategory::create(['assessment_type_id' => $type2025->id, 'name' => 'AIRWAY', 'slug' => 'airway']);
        $cat2026 = CommodityCategory::create(['assessment_type_id' => $type2026->id, 'name' => 'AIRWAY', 'slug' => 'airway']);

        $this->assertNotSame($cat2025->id, $cat2026->id);
        $this->assertSame($type2025->id, $cat2025->fresh()->assessment_type_id);
        $this->assertSame($type2026->id, $cat2026->fresh()->assessment_type_id);
    }

    public function test_assessment_departments_can_share_the_same_slug_across_two_assessment_types(): void
    {
        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_TEST2', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_TEST2', 'is_active' => true]);

        $dept2025 = AssessmentDepartment::create(['assessment_type_id' => $type2025->id, 'name' => 'Skills Lab', 'slug' => 'skills-lab']);
        $dept2026 = AssessmentDepartment::create(['assessment_type_id' => $type2026->id, 'name' => 'Skills Lab', 'slug' => 'skills-lab']);

        $this->assertNotSame($dept2025->id, $dept2026->id);
    }

    public function test_assessment_cadres_can_share_the_same_code_across_two_assessment_types(): void
    {
        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_TEST3', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_TEST3', 'is_active' => true]);

        $cadre2025 = MainCadre::create(['assessment_type_id' => $type2025->id, 'name' => 'Neonatologist', 'code' => 'neonatologist']);
        $cadre2026 = MainCadre::create(['assessment_type_id' => $type2026->id, 'name' => 'Neonatologist', 'code' => 'neonatologist']);

        $this->assertNotSame($cadre2025->id, $cadre2026->id);
    }

    public function test_assessment_sections_and_questions_can_share_codes_across_two_assessment_types(): void
    {
        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_TEST4', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_TEST4', 'is_active' => true]);

        $section2025 = \App\Models\AssessmentSection::create([
            'assessment_type_id' => $type2025->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => 'dynamic_questions', 'order' => 1, 'is_active' => true,
        ]);
        $section2026 = \App\Models\AssessmentSection::create([
            'assessment_type_id' => $type2026->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => 'dynamic_questions', 'order' => 1, 'is_active' => true,
        ]);

        $q2025 = \App\Models\AssessmentQuestion::create([
            'assessment_section_id' => $section2025->id, 'question_code' => 'INFRA_Q1',
            'question_text' => 'Test', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        $q2026 = \App\Models\AssessmentQuestion::create([
            'assessment_section_id' => $section2026->id, 'question_code' => 'INFRA_Q1',
            'question_text' => 'Test', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);

        $this->assertNotSame($section2025->id, $section2026->id);
        $this->assertNotSame($q2025->id, $q2026->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentTypeScopingTest`
Expected: FAIL — `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'airway' for key 'commodity_categories_slug_unique'` (and equivalent for the other three tests) because the composite-unique columns/indexes don't exist yet.

- [ ] **Step 3: Write the master-data type-scoping migration**

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
        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->foreignId('assessment_type_id')->nullable()->after('id')->constrained('assessment_types')->nullOnDelete();
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->foreignId('assessment_type_id')->nullable()->after('id')->constrained('assessment_types')->nullOnDelete();
        });
        Schema::table('assessment_cadres', function (Blueprint $table) {
            $table->foreignId('assessment_type_id')->nullable()->after('id')->constrained('assessment_types')->nullOnDelete();
        });

        // Backfill: every existing row in these three tables today belongs
        // to the one live "Standard Facility Assessment" template — the
        // only assessment type that currently consumes commodities,
        // departments, or this cadre list at all.
        $standardTypeId = DB::table('assessment_types')->where('code', 'STANDARD_FACILITY_ASSESSMENT')->value('id');

        if ($standardTypeId) {
            DB::table('commodity_categories')->whereNull('assessment_type_id')->update(['assessment_type_id' => $standardTypeId]);
            DB::table('assessment_departments')->whereNull('assessment_type_id')->update(['assessment_type_id' => $standardTypeId]);
            DB::table('assessment_cadres')->whereNull('assessment_type_id')->update(['assessment_type_id' => $standardTypeId]);
        }

        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->dropUnique('commodity_categories_slug_unique');
            $table->unique(['assessment_type_id', 'slug']);
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->dropUnique('assessment_departments_slug_unique');
            $table->unique(['assessment_type_id', 'slug']);
        });
        Schema::table('assessment_cadres', function (Blueprint $table) {
            $table->dropUnique('assessment_cadres_code_unique');
            $table->unique(['assessment_type_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->dropUnique(['assessment_type_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('assessment_type_id');
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->dropUnique(['assessment_type_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('assessment_type_id');
        });
        Schema::table('assessment_cadres', function (Blueprint $table) {
            $table->dropUnique(['assessment_type_id', 'code']);
            $table->unique('code');
            $table->dropConstrainedForeignId('assessment_type_id');
        });
    }
};
```

- [ ] **Step 4: Write the section/question code-scoping migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // assessment_sections.code and assessment_questions.question_code
        // are already effectively scoped per-template (every section has a
        // required assessment_type_id, every question belongs to a
        // section), but both still carry a leftover GLOBAL unique index
        // from before that scoping existed. A 2026 template reusing the
        // same codes as 2025 (e.g. "infrastructure", "INFRA_Q1" — expected,
        // since they're the same conceptual question) would fail to insert
        // without this fix.
        Schema::table('assessment_sections', function (Blueprint $table) {
            $table->dropUnique('assessment_sections_code_unique');
            $table->unique(['assessment_type_id', 'code']);
        });

        // question_code's scope key is its section's assessment_type_id, one
        // join away — not a column on this table — so a plain composite
        // unique isn't expressible here. Uniqueness of question_code WITHIN
        // a template is enforced at the application level where Phase 2's
        // seeder writes questions (each seeder run scopes its own codes),
        // matching how AssessmentSectionResource/AssessmentQuestionResource
        // already validate section codes without a matching DB constraint
        // for cross-template scoping. The original creation migration
        // already added a plain (non-unique) index on question_code
        // alongside the unique one (see 2025_12_01_064532_create_assessment_questions_table.php:56),
        // so lookups by code stay fast without adding a second index here.
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropUnique('assessment_questions_question_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->unique('question_code');
        });
        Schema::table('assessment_sections', function (Blueprint $table) {
            $table->dropUnique(['assessment_type_id', 'code']);
            $table->unique('code');
        });
    }
};
```

- [ ] **Step 5: Run the migrations**

Run: `php artisan migrate`
Expected: both new migrations run successfully with no errors.

- [ ] **Step 6: Update the three models**

In `app/Models/CommodityCategory.php`, add `'assessment_type_id'` to `$fillable` (after `'name'`) and add the relation:

```php
    public function assessmentType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }
```

In `app/Models/AssessmentDepartment.php`, add `'assessment_type_id'` to `$fillable` (after `'name'`) and the same `assessmentType()` relation.

In `app/Models/MainCadre.php`, add `'assessment_type_id'` to `$fillable` (after `'name'`) and the same `assessmentType()` relation.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentTypeScopingTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Run the full assessment test suite to confirm no 2025 regressions**

Run: `php artisan test --filter=Assessment`
Expected: PASS — every existing test still passes unchanged (they all create their own `AssessmentType`/sections/questions per-test via `RefreshDatabase`, so the new nullable columns don't affect them).

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_13_150000_add_assessment_type_id_to_commodity_master_data.php \
        database/migrations/2026_08_13_150001_scope_section_and_question_codes_to_assessment_type.php \
        app/Models/CommodityCategory.php app/Models/AssessmentDepartment.php app/Models/MainCadre.php \
        tests/Feature/AssessmentTypeScopingTest.php
git commit -m "feat: scope commodity/department/cadre master data and section/question codes per assessment type"
```

---

### Task 2: Filter master-data queries by the assessment's own type

**Files:**
- Modify: `app/Filament/Resources/AssessmentResource/Pages/EditHealthProducts.php:69-88`
- Modify: `app/Filament/Resources/AssessmentResource/Pages/EditHumanResources.php:140-147`
- Modify: `app/Services/CommodityScoringService.php:18-34`
- Test: `tests/Feature/AssessmentTypeScopingTest.php` (extend)

**Interfaces:**
- Consumes: `CommodityCategory`, `AssessmentDepartment`, `MainCadre` all now have `assessment_type_id` (Task 1).
- Produces: no new public interface — this task makes Task 1's scoping actually take effect at render/scoring time.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/AssessmentTypeScopingTest.php`:

```php
    public function test_health_products_page_only_shows_the_assessments_own_type_departments_and_categories(): void
    {
        $user = \App\Models\User::factory()->create(['name' => 'Scoping Assessor']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_HP', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_HP', 'is_active' => true]);

        $section2026 = \App\Models\AssessmentSection::create([
            'assessment_type_id' => $type2026->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => \App\Models\AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);

        $dept2025 = AssessmentDepartment::create(['assessment_type_id' => $type2025->id, 'name' => 'Old Dept Only 2025', 'slug' => 'old-dept-2025', 'is_active' => true, 'order' => 1]);
        $dept2026 = AssessmentDepartment::create(['assessment_type_id' => $type2026->id, 'name' => 'New Dept Only 2026', 'slug' => 'new-dept-2026', 'is_active' => true, 'order' => 1]);

        $facility = \App\Models\Facility::factory()->create();
        $assessment = \App\Models\Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type2026->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = \App\Filament\Resources\AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('New Dept Only 2026');
        $response->assertDontSee('Old Dept Only 2025');
    }

    public function test_human_resources_page_only_shows_the_assessments_own_type_cadres(): void
    {
        $user = \App\Models\User::factory()->create(['name' => 'HR Scoping Assessor']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_HR', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_HR', 'is_active' => true]);

        \App\Models\AssessmentSection::create([
            'assessment_type_id' => $type2026->id, 'name' => 'Human Resources', 'code' => 'human_resources',
            'section_type' => \App\Models\AssessmentSection::KIND_HUMAN_RESOURCES, 'order' => 1, 'is_active' => true,
        ]);

        MainCadre::create(['assessment_type_id' => $type2025->id, 'name' => 'Old Cadre Only 2025', 'code' => 'old_cadre_2025', 'is_active' => true, 'order' => 1]);
        MainCadre::create(['assessment_type_id' => $type2026->id, 'name' => 'New Cadre Only 2026', 'code' => 'new_cadre_2026', 'is_active' => true, 'order' => 1]);

        $facility = \App\Models\Facility::factory()->create();
        $assessment = \App\Models\Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type2026->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = \App\Filament\Resources\AssessmentResource::getUrl('edit-human-resources', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('New Cadre Only 2026');
        $response->assertDontSee('Old Cadre Only 2025');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentTypeScopingTest`
Expected: FAIL — both new tests fail their `assertDontSee`, because `EditHealthProducts`/`EditHumanResources` currently load ALL active departments/categories/cadres regardless of the assessment's type.

- [ ] **Step 3: Filter EditHealthProducts by the assessment's type**

In `app/Filament/Resources/AssessmentResource/Pages/EditHealthProducts.php`, replace the `form()` method body (lines 71-75):

```php
    public function form(Form $form): Form
    {
        $departments = AssessmentDepartment::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->orderBy('order')
            ->get();

        $categories = CommodityCategory::where('assessment_type_id', $this->record->assessment_type_id)
            ->orderBy('order')
            ->get();
```

(The rest of `form()` — the `Tabs::make(...)` block — is unchanged.)

- [ ] **Step 4: Filter EditHumanResources by the assessment's type**

In `app/Filament/Resources/AssessmentResource/Pages/EditHumanResources.php`, in `form()` (lines 140-147), add the type filter to the `$cadres` query:

```php
        $cadres = MainCadre::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->when(! empty($excludedIds), fn ($q) => $q->whereNotIn('id', $excludedIds))
            ->orderBy('order')
            ->get();
```

Also add the same `->where('assessment_type_id', $this->record->assessment_type_id)` filter to the `$allCadreIds` query inside `getHeaderActions()`'s `fillForm` closure (line 42) and the `$allCadreIds`/options queries inside its `action` closure (lines 53 and 58) — all three currently read `MainCadre::where('is_active', true)` with no type filter, which would let the "Manage Cadres" modal show/toggle cadres from the wrong template.

- [ ] **Step 5: Filter CommodityScoringService by the assessment's type**

In `app/Services/CommodityScoringService.php`, `recalculateDepartmentScore()` (lines 18-34) currently loads `CommodityCategory::orderBy('order')->get()` with no type filter. Change it to resolve the type from the assessment and filter:

```php
    public function recalculateDepartmentScore(int $assessmentId, int $departmentId): void {
        $department = AssessmentDepartment::find($departmentId);

        if (!$department) {
            return;
        }

        $categories = CommodityCategory::where('assessment_type_id', $department->assessment_type_id)
            ->orderBy('order')
            ->get();

        foreach ($categories as $category) {
            $this->recalculateDepartmentCategoryScore($assessmentId, $departmentId, $category->id);
        }

        app(DynamicScoringService::class)->recalculateOverallScore($assessmentId);
    }
```

(Scoping via `$department->assessment_type_id` rather than looking up the `Assessment` again — a department can only legitimately be scored against categories from its own type, and this keeps the method's existing signature/call sites unchanged.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentTypeScopingTest`
Expected: PASS (6 tests total)

- [ ] **Step 7: Run the full assessment test suite**

Run: `php artisan test --filter=Assessment`
Expected: PASS — no regressions to the 2025-shaped tests, since a single-type test fixture is unaffected by adding a `where('assessment_type_id', ...)` clause that already matches every row it created.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/AssessmentResource/Pages/EditHealthProducts.php \
        app/Filament/Resources/AssessmentResource/Pages/EditHumanResources.php \
        app/Services/CommodityScoringService.php tests/Feature/AssessmentTypeScopingTest.php
git commit -m "fix: scope Health Products and Human Resources pages to the assessment's own template"
```

---

### Task 3: Line-item lettering/indent for questions — `LineItemGrouper` + `DynamicFormBuilder`

**Files:**
- Create: `database/migrations/2026_08_13_150002_add_indent_level_to_assessment_questions.php`
- Create: `app/Services/FormKernel/LineItemGrouper.php`
- Modify: `app/Models/AssessmentQuestion.php`
- Modify: `app/Services/DynamicFormBuilder.php:14-76`
- Test: `tests/Unit/FormKernel/LineItemGrouperTest.php`
- Test: `tests/Feature/AssessmentLineItemQuestionsTest.php`

**Interfaces:**
- Produces: `LineItemGrouper::annotate(iterable $items, callable $groupKey, callable $indentLevel): array<int, array{item: object, letter: ?string, group_key: mixed, is_group_start: bool}>` and `LineItemGrouper::letterFor(int $position): string` — consumed by Task 4 (commodities) as well as this task.
- Consumes: `AssessmentQuestion::$group` (existing), new `AssessmentQuestion::$indent_level`.

- [ ] **Step 1: Write the failing unit test for LineItemGrouper**

```php
<?php

namespace Tests\Unit\FormKernel;

use App\Services\FormKernel\LineItemGrouper;
use PHPUnit\Framework\TestCase;

class LineItemGrouperTest extends TestCase
{
    private function item(string $label, ?string $group, int $indent): object
    {
        return (object) ['label' => $label, 'group' => $group, 'indent' => $indent];
    }

    public function test_letters_a_run_of_indented_siblings_sharing_a_group(): void
    {
        $items = [
            $this->item('Fr-6', 'Suction catheter sizes', 1),
            $this->item('Fr-8', 'Suction catheter sizes', 1),
            $this->item('Fr-10', 'Suction catheter sizes', 1),
        ];

        $annotated = LineItemGrouper::annotate($items, fn ($i) => $i->group, fn ($i) => $i->indent);

        $this->assertSame(['a', 'b', 'c'], array_column($annotated, 'letter'));
        $this->assertSame([true, false, false], array_column($annotated, 'is_group_start'));
    }

    public function test_a_lone_indented_item_gets_no_letter(): void
    {
        $items = [$this->item('Only child', 'Solo group', 1)];

        $annotated = LineItemGrouper::annotate($items, fn ($i) => $i->group, fn ($i) => $i->indent);

        $this->assertNull($annotated[0]['letter']);
    }

    public function test_unindented_items_never_get_a_letter_even_if_grouped(): void
    {
        $items = [
            $this->item('A', 'Some group', 0),
            $this->item('B', 'Some group', 0),
        ];

        $annotated = LineItemGrouper::annotate($items, fn ($i) => $i->group, fn ($i) => $i->indent);

        $this->assertSame([null, null], array_column($annotated, 'letter'));
    }

    public function test_two_adjacent_different_groups_letter_independently(): void
    {
        $items = [
            $this->item('26G', 'IV cannula gauges', 1),
            $this->item('24G', 'IV cannula gauges', 1),
            $this->item('2cc', 'Syringe sizes', 1),
            $this->item('5cc', 'Syringe sizes', 1),
        ];

        $annotated = LineItemGrouper::annotate($items, fn ($i) => $i->group, fn ($i) => $i->indent);

        $this->assertSame(['a', 'b', 'a', 'b'], array_column($annotated, 'letter'));
    }

    public function test_letter_for_wraps_past_z_excel_style(): void
    {
        $this->assertSame('a', LineItemGrouper::letterFor(0));
        $this->assertSame('z', LineItemGrouper::letterFor(25));
        $this->assertSame('aa', LineItemGrouper::letterFor(26));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LineItemGrouperTest`
Expected: FAIL — `Class "App\Services\FormKernel\LineItemGrouper" not found`

- [ ] **Step 3: Implement LineItemGrouper**

```php
<?php

namespace App\Services\FormKernel;

/**
 * Pure, model-agnostic clustering of an ordered list into "line item runs":
 * consecutive entries sharing a group key AND an indent level >= 1 are
 * assigned sequential letters (a, b, c, ...); everything else gets no
 * letter. A run of exactly one indented item isn't a list, so it gets no
 * letter either — matches the spreadsheet convention of only lettering
 * genuine multi-item splits (e.g. "Suction catheter sizes: a) Fr-6 b) Fr-8").
 * Shared between AssessmentQuestion (DynamicFormBuilder) and Commodity
 * (EditHealthProducts) — both expose an ordered list, a group key, and an
 * indent level, nothing more is required.
 */
class LineItemGrouper
{
    /**
     * @param  iterable<int, object>  $items  Already in display order.
     * @param  callable(object): mixed  $groupKey
     * @param  callable(object): int  $indentLevel
     * @return array<int, array{item: object, letter: ?string, group_key: mixed, is_group_start: bool}>
     */
    public static function annotate(iterable $items, callable $groupKey, callable $indentLevel): array
    {
        $items = array_values(is_array($items) ? $items : iterator_to_array($items));
        $count = count($items);

        $keys = [];
        $levels = [];
        foreach ($items as $index => $item) {
            $keys[$index] = $groupKey($item);
            $levels[$index] = $indentLevel($item);
        }

        $annotated = [];
        $index = 0;

        while ($index < $count) {
            if ($levels[$index] < 1 || $keys[$index] === null) {
                $annotated[] = [
                    'item' => $items[$index],
                    'letter' => null,
                    'group_key' => $keys[$index],
                    'is_group_start' => false,
                ];
                $index++;

                continue;
            }

            $runStart = $index;
            $runEnd = $index;
            while ($runEnd < $count && $levels[$runEnd] >= 1 && $keys[$runEnd] === $keys[$runStart]) {
                $runEnd++;
            }
            $runLength = $runEnd - $runStart;

            for ($position = $runStart; $position < $runEnd; $position++) {
                $annotated[] = [
                    'item' => $items[$position],
                    'letter' => $runLength >= 2 ? static::letterFor($position - $runStart) : null,
                    'group_key' => $keys[$position],
                    'is_group_start' => $position === $runStart,
                ];
            }

            $index = $runEnd;
        }

        return $annotated;
    }

    /**
     * 0-based position -> letter: 0='a', 1='b', ..., 25='z', 26='aa', ...
     * (base-26, Excel-column style — the spreadsheet never needs past 'h').
     */
    public static function letterFor(int $position): string
    {
        $letter = '';
        $position++;

        while ($position > 0) {
            $position--;
            $letter = chr(97 + ($position % 26)).$letter;
            $position = intdiv($position, 26);
        }

        return $letter;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LineItemGrouperTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Add the migration and model column**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->unsignedTinyInteger('indent_level')->default(0)->after('group');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropColumn('indent_level');
        });
    }
};
```

Run: `php artisan migrate`

In `app/Models/AssessmentQuestion.php`, add `'indent_level'` to `$fillable` (after `'group'`) and `'indent_level' => 'integer'` to `$casts`.

- [ ] **Step 6: Wire letter/indent computation into DynamicFormBuilder::buildForSection**

In `app/Services/DynamicFormBuilder.php`, replace the `foreach ($questions as $question) { ... }` loop body (lines 44-69) — insert the letter-annotation pass right after fetching `$questions` (line 19) and use the annotated `$question` (relabeled when it has a letter) inside the existing loop:

```php
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

        // Letter (a, b, c, ...) any question that's part of a genuine
        // multi-item split (indent_level >= 1, sharing `group` with its
        // run) — see LineItemGrouper. The letter is baked into a CLONED
        // question's question_text before field-building, since the built
        // Filament field's shape varies by question_type (e.g. yes_no
        // returns a Group with no top-level ->label()) and every
        // QuestionFieldBuilder method already reads $question->question_text
        // uniformly.
        $annotated = \App\Services\FormKernel\LineItemGrouper::annotate(
            $questions->all(),
            fn (AssessmentQuestion $q) => $q->group,
            fn (AssessmentQuestion $q) => $q->indent_level,
        );

        // First pass: collapse consecutive same-`group` questions into
        // "runs" — one run per group occurrence, carrying its built fields
        // alongside the raw `group` string (parsed by buildGroupedField()
        // below into either a plain small-group label or a repeating
        // table row — see its docblock for the `group` string convention).
        $runs = [];
        $currentGroup = null;
        $currentRun = null;
        // A question's own `group` can legitimately be null (ungrouped),
        // which is indistinguishable from the "no run started yet"
        // sentinel above by equality alone — this flag disambiguates the
        // very first question so its run always gets initialized.
        $started = false;

        foreach ($annotated as ['item' => $question, 'letter' => $letter]) {
            $existingResponse = null;

            if ($assessmentId) {
                $existingResponse = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                    ->where('assessment_question_id', $question->id)
                    ->first();
            }

            if ($letter !== null) {
                $question = clone $question;
                $question->question_text = "{$letter}) {$question->question_text}";
            }

            $field = static::buildFieldForQuestion($question, $existingResponse);

            if (! $field) {
                continue;
            }

            if ($question->indent_level > 0 && method_exists($field, 'extraAttributes')) {
                $field->extraAttributes(['style' => 'margin-left: 1.5rem;'], merge: true);
            }

            if (! $started || $question->group !== $currentGroup) {
                if ($currentRun !== null) {
                    $runs[] = $currentRun;
                }
                $currentGroup = $question->group;
                $currentRun = ['group' => $currentGroup, 'fields' => []];
                $started = true;
            }

            $currentRun['fields'][] = $field;
        }

        if ($currentRun !== null) {
            $runs[] = $currentRun;
        }

        return static::renderRuns($runs);
```

(The clone uses `$question->id` for `buildFieldForQuestion`'s field-name generation, `question_code` checks, and response lookups — all read from the clone, which carries every original attribute unchanged except `question_text`, so nothing downstream breaks.)

- [ ] **Step 7: Write the failing feature test for rendered lettering**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentLineItemQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_line_item_questions_render_lettered_and_indented(): void
    {
        $user = User::factory()->create(['name' => 'Line Item Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type = AssessmentType::create(['name' => 'Line Item Test', 'code' => 'LINE_ITEM_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_li',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);

        foreach ([['Fr-6', 1], ['Fr-8', 2], ['Fr-10', 3]] as [$size, $order]) {
            AssessmentQuestion::create([
                'assessment_section_id' => $section->id,
                'question_code' => 'SUCTION_'.strtoupper(str_replace('-', '_', $size)),
                'question_text' => $size,
                'question_type' => 'yes_no',
                'group' => 'Suction catheter sizes',
                'indent_level' => 1,
                'order' => $order,
                'is_active' => true,
            ]);
        }

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $section->code]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Suction catheter sizes');
        $response->assertSee('a) Fr-6', false);
        $response->assertSee('b) Fr-8', false);
        $response->assertSee('c) Fr-10', false);
    }
}
```

- [ ] **Step 8: Run test to verify it fails, then passes**

Run: `php artisan test --filter=AssessmentLineItemQuestionsTest`
Expected: FAIL before Step 6's change is in place (letters not present); PASS after.

- [ ] **Step 9: Run the full assessment test suite**

Run: `php artisan test --filter=Assessment`
Expected: PASS — `indent_level` defaults to `0` and `letter` is `null` for every existing question, so `AssessmentTemplateTest`'s groups (none of which set `indent_level`) render exactly as before.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_13_150002_add_indent_level_to_assessment_questions.php \
        app/Services/FormKernel/LineItemGrouper.php app/Models/AssessmentQuestion.php \
        app/Services/DynamicFormBuilder.php \
        tests/Unit/FormKernel/LineItemGrouperTest.php tests/Feature/AssessmentLineItemQuestionsTest.php
git commit -m "feat: auto-letter and indent split line-item questions"
```

---

### Task 4: Line-item lettering/indent for commodities

**Files:**
- Create: `database/migrations/2026_08_13_150003_add_line_item_columns_to_commodities.php`
- Modify: `app/Models/Commodity.php`
- Modify: `app/Filament/Resources/AssessmentResource/Pages/EditHealthProducts.php:90-141`
- Test: `tests/Feature/HealthProductsLineItemTest.php`

**Interfaces:**
- Consumes: `LineItemGrouper::annotate()` (Task 3), new `Commodity::$group_label`, `Commodity::$indent_level`.

- [ ] **Step 1: Add the migration and model columns**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            $table->string('group_label')->nullable()->after('name');
            $table->unsignedTinyInteger('indent_level')->default(0)->after('group_label');
        });
    }

    public function down(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            $table->dropColumn(['group_label', 'indent_level']);
        });
    }
};
```

Run: `php artisan migrate`

In `app/Models/Commodity.php`, add `'group_label'` and `'indent_level'` to `$fillable` (after `'name'`) and add `'indent_level' => 'integer'` to `$casts`.

- [ ] **Step 2: Write the failing feature test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HealthProductsLineItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_line_item_commodities_render_a_group_header_then_lettered_indented_rows(): void
    {
        $user = User::factory()->create(['name' => 'HP Line Item Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type = AssessmentType::create(['name' => 'HP Line Item Test', 'code' => 'HP_LINE_ITEM_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-hp-li', 'is_active' => true, 'order' => 1]);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'AIRWAY', 'slug' => 'airway-hp-li', 'order' => 1]);

        $sizes = ['Fr-6' => 1, 'Fr-8' => 2, 'Fr-10' => 3];
        foreach ($sizes as $size => $order) {
            $commodity = Commodity::create([
                'commodity_category_id' => $category->id,
                'name' => $size,
                'group_label' => 'Suction catheter sizes',
                'indent_level' => 1,
                'order' => $order,
                'is_active' => true,
            ]);
            $commodity->applicableDepartments()->attach($dept->id);
        }

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Suction catheter sizes');
        $response->assertSee('a) Fr-6', false);
        $response->assertSee('b) Fr-8', false);
        $response->assertSee('c) Fr-10', false);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=HealthProductsLineItemTest`
Expected: FAIL — no letters or group header rendered yet.

- [ ] **Step 4: Wire LineItemGrouper into EditHealthProducts::buildCategorySections**

In `app/Filament/Resources/AssessmentResource/Pages/EditHealthProducts.php`, replace the `buildCategorySections()` method (lines 90-141):

```php
    private function buildCategorySections($dept, $categories): array
    {
        return $categories->map(function ($category) use ($dept) {
            $commodities = Commodity::where('commodity_category_id', $category->id)
                ->where('is_active', true)
                ->whereHas('applicableDepartments', function ($q) use ($dept) {
                    $q->where('assessment_department_id', $dept->id);
                })
                ->orderBy('order')
                ->get();

            if ($commodities->isEmpty()) {
                return null;
            }

            $annotated = \App\Services\FormKernel\LineItemGrouper::annotate(
                $commodities->all(),
                fn (Commodity $c) => $c->group_label,
                fn (Commodity $c) => $c->indent_level,
            );

            $rows = [];
            foreach ($annotated as ['item' => $commodity, 'letter' => $letter, 'is_group_start' => $isGroupStart]) {
                if ($isGroupStart) {
                    $rows[] = Forms\Components\Placeholder::make("group_header_{$dept->id}_{$commodity->id}")
                        ->label('')
                        ->content($commodity->group_label)
                        ->extraAttributes(['class' => 'font-semibold'])
                        ->columnSpanFull();
                }

                $label = $letter !== null ? "{$letter}) {$commodity->name}" : $commodity->name;
                $rowStyle = $commodity->indent_level > 0 ? ['style' => 'margin-left: 1.5rem;'] : [];

                $rows[] = Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Placeholder::make("label_{$dept->id}_{$commodity->id}")
                            ->label('')
                            ->content($label)
                            ->extraAttributes($rowStyle)
                            ->columnSpan(1),
                        Forms\Components\ToggleButtons::make("commodities.{$dept->id}.{$commodity->id}")
                            ->label('')
                            ->options([
                                1 => 'Available',
                                0 => 'Not Available',
                                'na' => 'Not Applicable',
                            ])
                            ->colors([
                                1 => 'success',
                                0 => 'danger',
                                'na' => 'gray',
                            ])
                            ->icons([
                                1 => 'heroicon-o-check-circle',
                                0 => 'heroicon-o-x-circle',
                                'na' => 'heroicon-o-minus-circle',
                            ])
                            ->inline()
                            ->columnSpan(1),
                    ])
                    ->columns(2);
            }

            return Forms\Components\Section::make($category->name)
                ->description("({$commodities->count()} items)")
                ->schema([Forms\Components\Grid::make(2)->schema($rows)])
                ->collapsible();
        })->filter()->values()->toArray();
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=HealthProductsLineItemTest`
Expected: PASS

- [ ] **Step 6: Run the full assessment test suite**

Run: `php artisan test --filter=Assessment`
Expected: PASS — every existing `Commodity` row has `group_label = null`, `indent_level = 0`, so `LineItemGrouper::annotate()` returns `is_group_start => false` and `letter => null` for all of them, rendering exactly as before (no header row, unprefixed label).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_13_150003_add_line_item_columns_to_commodities.php \
        app/Models/Commodity.php app/Filament/Resources/AssessmentResource/Pages/EditHealthProducts.php \
        tests/Feature/HealthProductsLineItemTest.php
git commit -m "feat: auto-letter and indent split line-item commodities"
```

---

### Task 5: Human Resources per-cell N/A

**Files:**
- Create: `database/migrations/2026_08_13_150004_add_na_training_columns_to_assessment_cadres.php`
- Create: `database/migrations/2026_08_13_150005_make_human_resource_response_columns_nullable.php`
- Modify: `app/Models/MainCadre.php`
- Modify: `app/Filament/Resources/AssessmentResource/Pages/EditHumanResources.php`
- Modify: `app/Services/AssessmentExportService.php:158-173`
- Modify: `app/Services/AssessmentPdfReportService.php:224-268`
- Test: `tests/Feature/HumanResourcesNaColumnsTest.php`

**Interfaces:**
- Produces: `MainCadre::$na_training_columns` (array of `'total_in_facility'|'etat_plus'|'comprehensive_newborn_care'|'imnci'|'type_1_diabetes'|'essential_newborn_care'`, cast `array`).

- [ ] **Step 1: Add the migrations**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_cadres', function (Blueprint $table) {
            $table->json('na_training_columns')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_cadres', function (Blueprint $table) {
            $table->dropColumn('na_training_columns');
        });
    }
};
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Previously default(0) + NOT NULL — a facility with a cadre that
        // genuinely doesn't get trained in a program (e.g. a maternity
        // theatre anaesthetist trained in "Type 1 Diabetes") needs a real
        // NULL to distinguish "not applicable" from "trained zero staff".
        Schema::table('human_resource_responses', function (Blueprint $table) {
            $table->integer('total_in_facility')->nullable()->default(null)->change();
            $table->integer('etat_plus')->nullable()->default(null)->change();
            $table->integer('comprehensive_newborn_care')->nullable()->default(null)->change();
            $table->integer('imnci')->nullable()->default(null)->change();
            $table->integer('type_1_diabetes')->nullable()->default(null)->change();
            $table->integer('essential_newborn_care')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('human_resource_responses', function (Blueprint $table) {
            $table->integer('total_in_facility')->nullable(false)->default(0)->change();
            $table->integer('etat_plus')->nullable(false)->default(0)->change();
            $table->integer('comprehensive_newborn_care')->nullable(false)->default(0)->change();
            $table->integer('imnci')->nullable(false)->default(0)->change();
            $table->integer('type_1_diabetes')->nullable(false)->default(0)->change();
            $table->integer('essential_newborn_care')->nullable(false)->default(0)->change();
        });
    }
};
```

This `->change()` migration requires `doctrine/dbal`. Run: `composer show doctrine/dbal` — if not installed, run `composer require doctrine/dbal --dev` first (it's a compile-time-only dependency for column alterations, matching the project's existing `2026_08_01_161241_fix_assessment_types_id_column_type.php`-style repair migrations).

Run: `php artisan migrate`

- [ ] **Step 2: Update MainCadre model**

In `app/Models/MainCadre.php`, add `'na_training_columns'` to `$fillable` (after `'code'`) and `'na_training_columns' => 'array'` to `$casts`. Add a helper used by both the form page and the export/report services:

```php
    /**
     * The five fixed training-program columns, in the same order
     * EditHumanResources renders them — used to validate na_training_columns
     * values and by the export/PDF services to know which key means what.
     */
    public const TRAINING_COLUMNS = ['etat_plus', 'comprehensive_newborn_care', 'imnci', 'type_1_diabetes', 'essential_newborn_care'];

    public function isColumnNotApplicable(string $column): bool
    {
        return in_array($column, $this->na_training_columns ?? [], true);
    }
```

- [ ] **Step 3: Write the failing feature test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Pages\EditHumanResources;
use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\HumanResourceResponse;
use App\Models\MainCadre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HumanResourcesNaColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'HR NA Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    public function test_na_training_column_is_not_rendered_and_saves_as_null(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'HR NA Test', 'code' => 'HR_NA_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Human Resources', 'code' => 'human_resources',
            'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES, 'order' => 1, 'is_active' => true,
        ]);
        $cadre = MainCadre::create([
            'assessment_type_id' => $type->id,
            'name' => 'Maternity theatre anaesthetists',
            'code' => 'maternity_theatre_anaesthetists',
            'is_active' => true,
            'order' => 1,
            'na_training_columns' => ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes'],
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);

        $url = AssessmentResource::getUrl('edit-human-resources', ['record' => $assessment->id]);
        $rendered = $this->get($url);
        $rendered->assertOk();
        $rendered->assertDontSee("hr_{$cadre->id}_comprehensive_newborn_care");

        Livewire::test(EditHumanResources::class, ['record' => $assessment->id])
            ->fillForm([
                "hr_{$cadre->id}_total_in_facility" => 3,
                "hr_{$cadre->id}_etat_plus" => 2,
                "hr_{$cadre->id}_essential_newborn_care" => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $response = HumanResourceResponse::where('assessment_id', $assessment->id)->where('cadre_id', $cadre->id)->first();

        $this->assertSame(3, $response->total_in_facility);
        $this->assertSame(2, $response->etat_plus);
        $this->assertNull($response->comprehensive_newborn_care);
        $this->assertNull($response->imnci);
        $this->assertNull($response->type_1_diabetes);
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test --filter=HumanResourcesNaColumnsTest`
Expected: FAIL — every column still saves as `0`, and the N/A fields still render.

- [ ] **Step 5: Update EditHumanResources form/save/load**

In `app/Filament/Resources/AssessmentResource/Pages/EditHumanResources.php`, replace the `$cadres->map(...)` body inside `form()` (lines 153-215) — build only the visible training-column fields per cadre:

```php
                    $cadres->map(function ($cadre) {
                        $visibleColumns = array_diff(MainCadre::TRAINING_COLUMNS, $cadre->na_training_columns ?? []);
                        $columnLabels = [
                            'etat_plus' => 'ETAT+',
                            'comprehensive_newborn_care' => 'Comprehensive Newborn Care',
                            'imnci' => 'IMNCI',
                            'type_1_diabetes' => 'Type 1 Diabetes',
                            'essential_newborn_care' => 'Essential Newborn Care',
                        ];

                        $schema = [
                            Forms\Components\TextInput::make("hr_{$cadre->id}_total_in_facility")
                                ->label('Total Staff in Facility')
                                ->helperText('Total number of this cadre working at the facility')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->required()
                                ->columnSpanFull(),
                        ];

                        if (! empty($visibleColumns)) {
                            $schema[] = Forms\Components\Placeholder::make("hr_{$cadre->id}_divider")
                                ->label('Trained in '.count($visibleColumns).' Area'.(count($visibleColumns) === 1 ? '' : 's'))
                                ->content('Enter how many of the total staff above are trained in each programme:')
                                ->columnSpanFull();

                            $schema[] = Forms\Components\Grid::make(count($visibleColumns))
                                ->schema(collect($visibleColumns)->map(fn ($column) => Forms\Components\TextInput::make("hr_{$cadre->id}_{$column}")
                                    ->label($columnLabels[$column])
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required())->all())
                                ->columns(count($visibleColumns));
                        }

                        return Forms\Components\Section::make($cadre->name)
                            ->schema($schema)
                            ->collapsible()
                            ->collapsed(false);
                    })->toArray()
```

Replace `mutateFormDataBeforeSave()` (lines 221-261):

```php
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $cadres = MainCadre::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->get();

        foreach ($cadres as $cadre) {
            $prefix = "hr_{$cadre->id}_";

            if (! isset($data["{$prefix}total_in_facility"])) {
                continue;
            }

            HumanResourceResponse::updateOrCreate(
                [
                    'assessment_id' => $this->record->id,
                    'cadre_id' => $cadre->id,
                ],
                [
                    'total_in_facility' => (int) ($data["{$prefix}total_in_facility"] ?? 0),
                    'etat_plus' => $this->trainingColumnValue($cadre, 'etat_plus', $data, $prefix),
                    'comprehensive_newborn_care' => $this->trainingColumnValue($cadre, 'comprehensive_newborn_care', $data, $prefix),
                    'imnci' => $this->trainingColumnValue($cadre, 'imnci', $data, $prefix),
                    'type_1_diabetes' => $this->trainingColumnValue($cadre, 'type_1_diabetes', $data, $prefix),
                    'essential_newborn_care' => $this->trainingColumnValue($cadre, 'essential_newborn_care', $data, $prefix),
                ]
            );
        }

        $progress = $this->record->section_progress ?? [];
        $progress[$this->section->code] = true;
        $this->record->section_progress = $progress;
        $this->record->save();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'hr_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function trainingColumnValue(MainCadre $cadre, string $column, array $data, string $prefix): ?int
    {
        if ($cadre->isColumnNotApplicable($column)) {
            return null;
        }

        return (int) ($data["{$prefix}{$column}"] ?? 0);
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=HumanResourcesNaColumnsTest`
Expected: PASS

- [ ] **Step 7: Render N/A instead of 0 in exports and PDF reports**

In `app/Services/AssessmentExportService.php`, replace `getHumanResourceRows()` (lines 158-173):

```php
    protected function getHumanResourceRows(Assessment $assessment): array {
        $responses = $assessment->humanResourceResponses()->with('cadre')->get();

        $rows = [];

        foreach ($responses as $response) {
            $rows[] = [
                $response->cadre->name,
                $response->total_in_facility ?? 0,
                $this->hrCell($response, 'etat_plus'),
                $this->hrCell($response, 'comprehensive_newborn_care'),
                $this->hrCell($response, 'imnci'),
                $this->hrCell($response, 'type_1_diabetes'),
                $this->hrCell($response, 'essential_newborn_care'),
            ];
        }

        // Add totals row
        if (!empty($rows)) {
            $totals = [
                'TOTAL',
                $responses->sum('total_in_facility'),
                $responses->sum('etat_plus'),
                $responses->sum('comprehensive_newborn_care'),
                $responses->sum('imnci'),
                $responses->sum('type_1_diabetes'),
                $responses->sum('essential_newborn_care'),
            ];
            $rows[] = $totals;
        }

        return $rows;
    }

    private function hrCell($response, string $column) {
        return $response->cadre?->isColumnNotApplicable($column) ? 'N/A' : ($response->{$column} ?? 0);
    }
```

In `app/Services/AssessmentPdfReportService.php`, replace the two `'etat_plus' => $response->etat_plus ?? 0,` blocks (lines 242-246 and 260-264) with `$this->hrCell($response, 'etat_plus')` etc. following the same pattern, and add the identical private `hrCell()` helper method to that class too (both services already duplicate this rendering shape independently — this keeps that established pattern rather than introducing a new shared class for it):

```php
                    'etat_plus' => $this->hrCell($response, 'etat_plus'),
                    'comprehensive_newborn_care' => $this->hrCell($response, 'comprehensive_newborn_care'),
                    'imnci' => $this->hrCell($response, 'imnci'),
                    'type_1_diabetes' => $this->hrCell($response, 'type_1_diabetes'),
                    'essential_newborn_care' => $this->hrCell($response, 'essential_newborn_care'),
```

(applied to both the `'responses'` map at line 238-248 and the `'by_cadre'` map at line 256-266 — the `'total_*'` sum fields at lines 251-255 stay `?? 0`-summed as-is, since `sum()` already treats `null` as `0`, which is the correct aggregate behavior across a mix of applicable/N/A cadres.)

Add to `AssessmentPdfReportService`:

```php
    private function hrCell($response, string $column) {
        return $response->cadre?->isColumnNotApplicable($column) ? 'N/A' : ($response->{$column} ?? 0);
    }
```

- [ ] **Step 8: Run the full assessment test suite**

Run: `php artisan test --filter=Assessment`
Expected: PASS — every existing cadre has `na_training_columns = null` (empty array default via `?? []` in `isColumnNotApplicable`), so `hrCell()` always falls through to the original `$response->{$column} ?? 0` behavior for 2025 data.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_13_150004_add_na_training_columns_to_assessment_cadres.php \
        database/migrations/2026_08_13_150005_make_human_resource_response_columns_nullable.php \
        app/Models/MainCadre.php app/Filament/Resources/AssessmentResource/Pages/EditHumanResources.php \
        app/Services/AssessmentExportService.php app/Services/AssessmentPdfReportService.php \
        tests/Feature/HumanResourcesNaColumnsTest.php
git commit -m "feat: support per-cell N/A in the Human Resources training matrix"
```

---

### Task 6: Checklists attachable to questions

**Files:**
- Create: `database/migrations/2026_08_13_150006_create_assessment_checklists_tables.php`
- Create: `database/migrations/2026_08_13_150007_add_checklist_id_to_assessment_questions.php`
- Create: `app/Models/AssessmentChecklist.php`
- Create: `app/Models/AssessmentChecklistItem.php`
- Create: `resources/views/filament/assessment/checklist-modal.blade.php`
- Modify: `app/Models/AssessmentQuestion.php`
- Modify: `app/Services/FormKernel/QuestionFieldBuilder.php:64-101`
- Test: `tests/Feature/AssessmentChecklistTest.php`

**Interfaces:**
- Produces: `AssessmentChecklist::items(): HasMany`, `AssessmentQuestion::checklist(): BelongsTo`.

- [ ] **Step 1: Create the tables**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_type_id')->nullable()->constrained('assessment_types')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_checklist_id')->constrained('assessment_checklists')->cascadeOnDelete();
            $table->string('group_label')->nullable();
            $table->string('label');
            $table->unsignedInteger('qty')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['assessment_checklist_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_checklist_items');
        Schema::dropIfExists('assessment_checklists');
    }
};
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->foreignId('checklist_id')->nullable()->after('help_text')->constrained('assessment_checklists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_id');
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 2: Create the models**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentChecklist extends Model
{
    protected $fillable = ['assessment_type_id', 'title', 'description'];

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentChecklistItem::class)->orderBy('order');
    }
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentChecklistItem extends Model
{
    protected $fillable = ['assessment_checklist_id', 'group_label', 'label', 'qty', 'order'];

    protected $casts = [
        'qty' => 'integer',
        'order' => 'integer',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(AssessmentChecklist::class, 'assessment_checklist_id');
    }
}
```

In `app/Models/AssessmentQuestion.php`, add `'checklist_id'` to `$fillable` (after `'help_text'`) and add:

```php
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(AssessmentChecklist::class, 'checklist_id');
    }
```

- [ ] **Step 3: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentChecklistItem;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Services\FormKernel\QuestionFieldBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_question_with_a_checklist_gets_a_hint_action(): void
    {
        $type = AssessmentType::create(['name' => 'Checklist Test', 'code' => 'CHECKLIST_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_cl',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $checklist = AssessmentChecklist::create(['assessment_type_id' => $type->id, 'title' => 'ORT Corner checklist']);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'label' => 'Clean spoons', 'qty' => 6, 'order' => 1]);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'label' => 'Plastic buckets', 'qty' => 3, 'order' => 2]);

        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'ORT_OUTPATIENT',
            'question_text' => 'Is there a functional ORT corner in the outpatient department?',
            'question_type' => 'yes_no',
            'checklist_id' => $checklist->id,
            'order' => 1,
            'is_active' => true,
        ]);

        $field = QuestionFieldBuilder::buildField($question->fresh(), null);
        $radio = $field->getChildComponents()[0];

        $this->assertCount(1, $radio->getHintActions());
        $this->assertArrayHasKey('checklist_'.$question->id, $radio->getHintActions());
    }

    public function test_a_question_without_a_checklist_has_no_hint_action(): void
    {
        $type = AssessmentType::create(['name' => 'Checklist Test 2', 'code' => 'CHECKLIST_TEST_2', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_cl2',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'NO_CHECKLIST_Q',
            'question_text' => 'Plain question',
            'question_type' => 'yes_no',
            'order' => 1,
            'is_active' => true,
        ]);

        $field = QuestionFieldBuilder::buildField($question->fresh(), null);
        $radio = $field->getChildComponents()[0];

        $this->assertCount(0, $radio->getHintActions());
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentChecklistTest`
Expected: FAIL — `getHintActions()` returns an empty array for both (no hint action wired up yet), so the first test's `assertCount(1, ...)` fails.

- [ ] **Step 5: Wire the hint action into buildYesNoPartialField**

In `app/Services/FormKernel/QuestionFieldBuilder.php`, modify `buildYesNoPartialField()` (lines 64-101) — insert the hint action right after building `$field` and before the `if ($question->help_text)` check:

```php
        $field = Forms\Components\Radio::make($fieldName)
            ->label($question->question_text)
            ->options(array_combine($options, $options))
            ->required($question->is_required)
            ->inline()
            ->live()
            ->default($response?->response_value);

        if ($question instanceof \App\Models\AssessmentQuestion && $question->checklist_id) {
            $field->hintAction(
                Forms\Components\Actions\Action::make("checklist_{$question->id}")
                    ->label('View checklist')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->modalHeading($question->checklist?->title ?? 'Checklist')
                    ->modalContent(fn () => view('filament.assessment.checklist-modal', [
                        'checklist' => $question->checklist,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
            );
        }

        if ($question->help_text) {
            $field->helperText($question->help_text);
        }
```

(Gated on `AssessmentQuestion` specifically — `SurveyQuestion` has no `checklist_id`/`checklist` relation, and this kernel method is shared between both per its class docblock.)

- [ ] **Step 6: Create the modal view**

```blade
<div class="space-y-4">
    @if($checklist->description)
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $checklist->description }}</p>
    @endif

    @php $hasQty = $checklist->items->contains(fn ($item) => $item->qty !== null); @endphp

    @foreach($checklist->items->groupBy('group_label') as $groupLabel => $items)
        @if($groupLabel)
            <h4 class="font-semibold text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $groupLabel }}</h4>
        @endif
        <table class="w-full text-sm">
            <tbody>
                @foreach($items as $item)
                    <tr class="border-b border-gray-100 dark:border-gray-700">
                        <td class="py-1">{{ $item->label }}</td>
                        @if($hasQty)
                            <td class="py-1 text-right text-gray-500 dark:text-gray-400">{{ $item->qty }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</div>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentChecklistTest`
Expected: PASS (2 tests)

- [ ] **Step 8: Run the full assessment test suite**

Run: `php artisan test --filter=Assessment`
Expected: PASS — every existing question has `checklist_id = null`, so the new `if` block is a no-op for all 2025 content.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_13_150006_create_assessment_checklists_tables.php \
        database/migrations/2026_08_13_150007_add_checklist_id_to_assessment_questions.php \
        app/Models/AssessmentChecklist.php app/Models/AssessmentChecklistItem.php app/Models/AssessmentQuestion.php \
        app/Services/FormKernel/QuestionFieldBuilder.php \
        resources/views/filament/assessment/checklist-modal.blade.php \
        tests/Feature/AssessmentChecklistTest.php
git commit -m "feat: attach reference checklists to yes/no questions, shown via an on-demand modal"
```

---

### Task 7: Whole-block conditional visibility (sections, departments, categories, commodities)

**Files:**
- Create: `database/migrations/2026_08_13_150008_add_display_conditions_to_assessment_blocks.php`
- Modify: `app/Models/AssessmentSection.php`
- Modify: `app/Models/AssessmentDepartment.php`
- Modify: `app/Models/CommodityCategory.php`
- Modify: `app/Models/Commodity.php`
- Modify: `app/Filament/Resources/AssessmentResource/Traits/HasSectionNavigation.php:14-48`
- Modify: `app/Filament/Resources/AssessmentResource/Pages/EditHealthProducts.php`
- Modify: `app/Services/CommodityScoringService.php`
- Test: `tests/Feature/AssessmentDisplayConditionsBlockTest.php`

**Interfaces:**
- Consumes: `ConditionalLogicEvaluator::isVisible(array $conditions, callable $valueResolver): bool` (existing, unchanged).
- Produces: `display_conditions` (array, nullable) on all four models.

- [ ] **Step 1: Add the migration and model casts**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_sections', function (Blueprint $table) {
            $table->json('display_conditions')->nullable()->after('is_active');
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->json('display_conditions')->nullable()->after('is_active');
        });
        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->json('display_conditions')->nullable()->after('description');
        });
        Schema::table('commodities', function (Blueprint $table) {
            $table->json('display_conditions')->nullable()->after('indent_level');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_sections', function (Blueprint $table) {
            $table->dropColumn('display_conditions');
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->dropColumn('display_conditions');
        });
        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->dropColumn('display_conditions');
        });
        Schema::table('commodities', function (Blueprint $table) {
            $table->dropColumn('display_conditions');
        });
    }
};
```

Run: `php artisan migrate`

Add `'display_conditions'` to `$fillable` and `'display_conditions' => 'array'` to `$casts` in `app/Models/AssessmentSection.php`, `app/Models/AssessmentDepartment.php`, and `app/Models/CommodityCategory.php` (which currently has no `$casts` array — add one). In `app/Models/Commodity.php`, add `'display_conditions'` to `$fillable` and `'display_conditions' => 'array'` to `$casts`.

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Facility;
use App\Models\User;
use App\Services\CommodityScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentDisplayConditionsBlockTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Block Skip Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    public function test_commodity_category_is_hidden_from_health_products_page_when_its_condition_is_false(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'NICU Skip Test', 'code' => 'NICU_SKIP_TEST', 'is_active' => true]);

        $infraSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_nicu',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $hasNicuQuestion = AssessmentQuestion::create([
            'assessment_section_id' => $infraSection->id, 'question_code' => 'HAS_NICU',
            'question_text' => 'Do you have a NICU?', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products_nicu',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 2, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-nicu-skip', 'is_active' => true, 'order' => 1]);
        $nicuCategory = CommodityCategory::create([
            'assessment_type_id' => $type->id, 'name' => 'NICU/PICU', 'slug' => 'nicu-picu-skip', 'order' => 1,
            'display_conditions' => ['question_code' => 'HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'],
        ]);
        $commodity = Commodity::create(['commodity_category_id' => $nicuCategory->id, 'name' => 'Surfactant', 'order' => 1, 'is_active' => true]);
        $commodity->applicableDepartments()->attach($dept->id);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $hasNicuQuestion->id, 'response_value' => 'No',
        ]);

        $url = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);
        $response->assertOk();
        $response->assertDontSee('NICU/PICU');
        $response->assertDontSee('Surfactant');

        // Scoring must also exclude the hidden category from the denominator.
        app(CommodityScoringService::class)->recalculateDepartmentScore($assessment->id, $dept->id);
        $score = \App\Models\AssessmentDepartmentScore::where('assessment_id', $assessment->id)
            ->where('assessment_department_id', $dept->id)
            ->where('commodity_category_id', $nicuCategory->id)
            ->first();
        $this->assertNull($score, 'A hidden category should not produce a department score row.');
    }

    public function test_commodity_category_is_shown_when_its_condition_is_true(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'NICU Show Test', 'code' => 'NICU_SHOW_TEST', 'is_active' => true]);

        $infraSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_nicu2',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $hasNicuQuestion = AssessmentQuestion::create([
            'assessment_section_id' => $infraSection->id, 'question_code' => 'HAS_NICU',
            'question_text' => 'Do you have a NICU?', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products_nicu2',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 2, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-nicu-show', 'is_active' => true, 'order' => 1]);
        $nicuCategory = CommodityCategory::create([
            'assessment_type_id' => $type->id, 'name' => 'NICU/PICU', 'slug' => 'nicu-picu-show', 'order' => 1,
            'display_conditions' => ['question_code' => 'HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'],
        ]);
        $commodity = Commodity::create(['commodity_category_id' => $nicuCategory->id, 'name' => 'Surfactant', 'order' => 1, 'is_active' => true]);
        $commodity->applicableDepartments()->attach($dept->id);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $hasNicuQuestion->id, 'response_value' => 'Yes',
        ]);

        $url = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);
        $response->assertOk();
        $response->assertSee('NICU/PICU');
        $response->assertSee('Surfactant');
    }

    public function test_section_with_a_false_condition_is_excluded_from_section_navigation(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Section Skip Test', 'code' => 'SECTION_SKIP_TEST', 'is_active' => true]);

        $gateSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Gate', 'code' => 'gate_section',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $gateQuestion = AssessmentQuestion::create([
            'assessment_section_id' => $gateSection->id, 'question_code' => 'GATE_Q',
            'question_text' => 'Enable extra section?', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        $hiddenSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Extra', 'code' => 'extra_section',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 2, 'is_active' => true,
            'display_conditions' => ['question_code' => 'GATE_Q', 'operator' => 'equals', 'value' => 'Yes'],
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $hiddenSection->id, 'question_code' => 'EXTRA_Q',
            'question_text' => 'Extra question', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $gateQuestion->id, 'response_value' => 'No',
        ]);

        $page = new \App\Filament\Resources\AssessmentResource\Pages\EditSection;
        $page->record = $assessment;
        $page->mount($assessment->id);

        $sections = (new \ReflectionMethod($page, 'getAllSections'))->invoke($page);

        $this->assertArrayHasKey('gate_section', $sections);
        $this->assertArrayNotHasKey('extra_section', $sections);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentDisplayConditionsBlockTest`
Expected: FAIL — all three tests fail (category/commodity still render regardless of condition; the section still appears in navigation; the department score row still gets created).

- [ ] **Step 4: Apply conditions in HasSectionNavigation**

In `app/Filament/Resources/AssessmentResource/Traits/HasSectionNavigation.php`, replace `getAllSections()` (lines 14-48):

```php
    protected function getAllSections(): array
    {
        $progress = $this->record->section_progress ?? [];
        $sections = $this->record->assessmentType
            ?->sections()
            ->where('is_active', true)
            ->orderBy('order')
            ->get() ?? collect();

        $responsesByCode = \App\Models\AssessmentQuestionResponse::query()
            ->where('assessment_id', $this->record->id)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
            ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
            ->all();

        $sections = $sections->filter(function (\App\Models\AssessmentSection $section) use ($responsesByCode) {
            if (empty($section->display_conditions)) {
                return true;
            }

            return \App\Services\ConditionalLogicEvaluator::isVisible(
                $section->display_conditions,
                fn (string $code) => $responsesByCode[$code] ?? null
            );
        });

        $result = [];

        foreach ($sections as $section) {
            $route = match ($section->resolvedKind()) {
                'question_group' => AssessmentResource::getUrl('edit-section', [
                    'record' => $this->record->id,
                    'sectionCode' => $section->code,
                ]),
                'human_resources' => AssessmentResource::getUrl('edit-human-resources', ['record' => $this->record->id]),
                'commodity_matrix' => AssessmentResource::getUrl('edit-health-products', ['record' => $this->record->id]),
                default => null, // informational — not an editable section
            };

            if ($route === null) {
                continue;
            }

            $result[$section->code] = [
                'label' => $section->name,
                'done' => $progress[$section->code] ?? false,
                'route' => $route,
            ];
        }

        return $result;
    }
```

- [ ] **Step 5: Apply conditions in EditHealthProducts**

In `app/Filament/Resources/AssessmentResource/Pages/EditHealthProducts.php`, add a private helper and use it to filter departments, categories, and (inside `buildCategorySections()`) commodities:

```php
    public function form(Form $form): Form
    {
        $responsesByCode = $this->responsesByQuestionCode();

        $departments = AssessmentDepartment::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->orderBy('order')
            ->get()
            ->filter(fn (AssessmentDepartment $dept) => $this->isBlockVisible($dept->display_conditions, $responsesByCode));

        $categories = CommodityCategory::where('assessment_type_id', $this->record->assessment_type_id)
            ->orderBy('order')
            ->get()
            ->filter(fn (CommodityCategory $cat) => $this->isBlockVisible($cat->display_conditions, $responsesByCode));

        return $form->schema([
            Forms\Components\Tabs::make('Departments')
                ->tabs(
                    $departments->map(function ($dept) use ($categories, $responsesByCode) {
                        return Forms\Components\Tabs\Tab::make($dept->name)
                            ->schema($this->buildCategorySections($dept, $categories, $responsesByCode));
                    })->toArray()
                )
                ->columnSpanFull()
                ->contained(false),
        ]);
    }

    private function responsesByQuestionCode(): array
    {
        return \App\Models\AssessmentQuestionResponse::query()
            ->where('assessment_id', $this->record->id)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
            ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
            ->all();
    }

    private function isBlockVisible(?array $conditions, array $responsesByCode): bool
    {
        if (empty($conditions)) {
            return true;
        }

        return \App\Services\ConditionalLogicEvaluator::isVisible(
            $conditions,
            fn (string $code) => $responsesByCode[$code] ?? null
        );
    }
```

Update `buildCategorySections()`'s signature and the commodity query (the method built in Task 4) to accept and apply `$responsesByCode`:

```php
    private function buildCategorySections($dept, $categories, array $responsesByCode): array
    {
        return $categories->map(function ($category) use ($dept, $responsesByCode) {
            $commodities = Commodity::where('commodity_category_id', $category->id)
                ->where('is_active', true)
                ->whereHas('applicableDepartments', function ($q) use ($dept) {
                    $q->where('assessment_department_id', $dept->id);
                })
                ->orderBy('order')
                ->get()
                ->filter(fn (Commodity $c) => $this->isBlockVisible($c->display_conditions, $responsesByCode))
                ->values();

            if ($commodities->isEmpty()) {
                return null;
            }

            // ... unchanged from Task 4 (LineItemGrouper::annotate(...) through the closing return) ...
```

(The rest of the method body — from `$annotated = ...` through the final `return Forms\Components\Section::make(...)` — is unchanged from Task 4.)

- [ ] **Step 6: Exclude hidden departments/categories/commodities from scoring**

In `app/Services/CommodityScoringService.php`, replace `recalculateDepartmentCategoryScore()` (lines 39-90) and add the two new private helpers:

```php
    public function recalculateDepartmentCategoryScore(int $assessmentId, int $departmentId, int $categoryId): void {
        $department = AssessmentDepartment::find($departmentId);
        $category = CommodityCategory::find($categoryId);
        $responsesByCode = $this->responsesByQuestionCode($assessmentId);

        if (!$department || !$category
            || !$this->isBlockVisible($department->display_conditions, $responsesByCode)
            || !$this->isBlockVisible($category->display_conditions, $responsesByCode)) {
            // A block that's now hidden (or was deleted) must not leave a
            // stale score row behind — getDepartmentSummary()/
            // getHealthProductsSummary() read AssessmentDepartmentScore
            // directly and would otherwise keep reporting a percentage for
            // a section the assessor can no longer even see.
            AssessmentDepartmentScore::where('assessment_id', $assessmentId)
                ->where('assessment_department_id', $departmentId)
                ->where('commodity_category_id', $categoryId)
                ->delete();

            return;
        }

        // Get all applicable commodities for this department and category
        $applicableCommodities = Commodity::where('commodity_category_id', $categoryId)
                ->where('is_active', true)
                ->whereHas('applicableDepartments', function ($query) use ($departmentId) {
                    $query->where('assessment_department_id', $departmentId);
                })
                ->get()
                ->filter(fn (Commodity $c) => $this->isBlockVisible($c->display_conditions, $responsesByCode))
                ->pluck('id');

        if ($applicableCommodities->isEmpty()) {
            return;
        }

        // Get responses for these commodities
        $responses = AssessmentCommodityResponse::where('assessment_id', $assessmentId)
                ->where('assessment_department_id', $departmentId)
                ->whereIn('commodity_id', $applicableCommodities)
                ->get();

        // Calculate counts — commodities the assessor marked "not
        // applicable" for this facility are excluded from both the
        // numerator and the denominator entirely, rather than counted as
        // unavailable.
        $naCount = $responses->where('not_applicable', true)->count();
        $totalApplicable = $applicableCommodities->count() - $naCount;
        $availableCount = $responses->where('not_applicable', false)->where('available', true)->count();

        // Calculate percentage
        $percentage = $totalApplicable > 0 ? ($availableCount / $totalApplicable) * 100 : 0;

        // Determine grade
        $grade = match (true) {
            $percentage >= 80 => 'green',
            $percentage >= 50 => 'yellow',
            default => 'red',
        };

        // Update or create department score
        AssessmentDepartmentScore::updateOrCreate(
                [
                    'assessment_id' => $assessmentId,
                    'assessment_department_id' => $departmentId,
                    'commodity_category_id' => $categoryId,
                ],
                [
                    'available_count' => $availableCount,
                    'total_applicable' => $totalApplicable,
                    'percentage' => round($percentage, 2),
                    'grade' => $percentage > 0 ? $grade : null,
                ]
        );
    }

    private function responsesByQuestionCode(int $assessmentId): array {
        return \App\Models\AssessmentQuestionResponse::query()
            ->where('assessment_id', $assessmentId)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
            ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
            ->all();
    }

    private function isBlockVisible(?array $conditions, array $responsesByCode): bool {
        if (empty($conditions)) {
            return true;
        }

        return \App\Services\ConditionalLogicEvaluator::isVisible(
            $conditions,
            fn (string $code) => $responsesByCode[$code] ?? null
        );
    }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentDisplayConditionsBlockTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Run the full assessment test suite**

Run: `php artisan test --filter=Assessment`
Expected: PASS — every existing section/department/category/commodity has `display_conditions = null`, so `isBlockVisible()`/the section filter always return `true`, matching current behavior exactly.

- [ ] **Step 9: Run the entire project test suite**

Run: `composer test`
Expected: PASS — full regression check across the whole app, not just assessment-tagged tests, since Task 5's `->change()` migration and Task 1's dropped/recreated unique indexes touch tables other features may also read.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_13_150008_add_display_conditions_to_assessment_blocks.php \
        app/Models/AssessmentSection.php app/Models/AssessmentDepartment.php app/Models/CommodityCategory.php app/Models/Commodity.php \
        app/Filament/Resources/AssessmentResource/Traits/HasSectionNavigation.php \
        app/Filament/Resources/AssessmentResource/Pages/EditHealthProducts.php \
        app/Services/CommodityScoringService.php \
        tests/Feature/AssessmentDisplayConditionsBlockTest.php
git commit -m "feat: support whole-block conditional visibility for sections, departments, categories, and commodities"
```
