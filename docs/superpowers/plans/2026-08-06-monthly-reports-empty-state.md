# Monthly Reports Empty-State Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the blocking empty `report_template_id` dropdown on the Monthly Report create form — seed one real, usable `ReportTemplate` linked to all 44 existing indicators, and add defensive helper text for if the dropdown is ever empty again.

**Architecture:** A standalone seeder (not wired into `DatabaseSeeder`'s already-broken call chain — see spec) creates one `ReportTemplate` and attaches all current `Indicator` rows via the `report_template_indicators` pivot, idempotently. A small conditional `->helperText()` addition to the existing `report_template_id` Select field in `MonthlyReportResource` covers the case where the dropdown is empty for any reason.

**Tech Stack:** Laravel 12 seeder, Filament v3 form field, PHPUnit/Livewire testing (matching this codebase's existing conventions).

## Global Constraints

- Do not add `ReportTemplateSeeder` to `DatabaseSeeder`'s active call list — that list is broken by `MenteeSeeder`'s hardcoded, non-existent CSV path (see `docs/PHASE1-DISCOVERY-BASELINE.md` and project memory), so nothing in it currently runs via a plain `php artisan db:seed`. Run this seeder standalone, same as the EmONC seeders earlier this session.
- The seeder must be safe to run more than once (idempotent) — use `firstOrCreate`/`syncWithoutDetaching`, not `create`/`attach`.
- No changes to `report_template_id`'s existing behavior when at least one active `ReportTemplate` exists — the helper text must only appear in the zero-template case.

---

### Task 1: `ReportTemplateSeeder`

**Files:**
- Create: `database/seeders/ReportTemplateSeeder.php`
- Test: `tests/Unit/ReportTemplateSeederTest.php`

**Interfaces:**
- Consumes: `App\Models\ReportTemplate` (`app/Models/ReportTemplate.php` — `indicators(): BelongsToMany` via `report_template_indicators` pivot with `sort_order`/`is_required` columns), `App\Models\Indicator` (has its own `sort_order` column, confirmed via `SHOW COLUMNS FROM indicators`).
- Produces: one `ReportTemplate` row (`code: MONTHLY_FACILITY_INDICATORS`) with all `Indicator` rows attached — consumed by Task 2's manual verification and by anyone creating a Monthly Report going forward.

**Pre-existing wrinkle found while writing this plan (not caused by, or fixed by, this plan):** there are **two** `Indicator` model classes — `App\Models\Indicator` (older, `$fillable` doesn't match the live table: no `group_id`, `indicator_type`, or `category`, all of which are real required columns) and `App\Models\Indicators\Indicator` (newer, `$fillable` matches the live schema). `ReportTemplate::indicators()` targets the **older** one (unqualified `Indicator::class` resolves to `App\Models\Indicator` since `ReportTemplate` itself lives in `App\Models`). Both classes map to the same real `indicators` table (neither overrides `$table`), so read-only queries work fine regardless of which one is used — this plan's seeder only reads `id`/`sort_order` from existing rows, never mass-assigns through either model, so it is unaffected. Test fixtures below use raw `DB::table()->insert()` instead of either `Indicator` model, specifically to sidestep this mismatch rather than accidentally depend on it. **Not fixed here** — resolving which model is canonical (and updating every reference) is a separate, real decision worth its own investigation; flag for `docs/PHASE1-DISCOVERY-BASELINE.md` §9 as a follow-up finding, don't fix as a drive-by.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\ReportTemplate;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Raw inserts, not the Indicator model — see this task's "Pre-existing
     * wrinkle" note above. Builds the minimal valid row chain
     * (indicator_report_types -> indicator_groups -> indicators).
     */
    private function makeIndicator(string $code): int
    {
        $reportTypeId = DB::table('indicator_report_types')->insertGetId([
            'code' => 'test_type_' . $code,
            'name' => 'Test Type',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('indicator_groups')->insertGetId([
            'report_type_id' => $reportTypeId,
            'name' => 'Test Group',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('indicators')->insertGetId([
            'group_id' => $groupId,
            'code' => $code,
            'name' => "Indicator $code",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_creates_one_general_report_template_with_all_indicators_attached(): void
    {
        $this->makeIndicator('IND-1');
        $this->makeIndicator('IND-2');

        (new ReportTemplateSeeder())->run();

        $template = ReportTemplate::where('code', 'MONTHLY_FACILITY_INDICATORS')->first();

        $this->assertNotNull($template);
        $this->assertSame('general', $template->report_type);
        $this->assertSame('monthly', $template->frequency);
        $this->assertTrue($template->is_active);
        $this->assertCount(2, $template->indicators);
    }

    public function test_running_it_twice_does_not_duplicate_the_template_or_indicator_links(): void
    {
        $this->makeIndicator('IND-1');

        (new ReportTemplateSeeder())->run();
        (new ReportTemplateSeeder())->run();

        $this->assertSame(1, ReportTemplate::where('code', 'MONTHLY_FACILITY_INDICATORS')->count());
        $template = ReportTemplate::where('code', 'MONTHLY_FACILITY_INDICATORS')->first();
        $this->assertCount(1, $template->indicators);
    }

    public function test_running_it_again_after_a_new_indicator_is_added_attaches_the_new_one_too(): void
    {
        $this->makeIndicator('IND-1');
        (new ReportTemplateSeeder())->run();

        $this->makeIndicator('IND-2');
        (new ReportTemplateSeeder())->run();

        $template = ReportTemplate::where('code', 'MONTHLY_FACILITY_INDICATORS')->first();
        $this->assertCount(2, $template->indicators);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Unit/ReportTemplateSeederTest.php`
Expected: FAIL — `database/seeders/ReportTemplateSeeder.php` doesn't exist yet (class not found).

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

class ReportTemplateSeeder extends Seeder
{
    private const CODE = 'MONTHLY_FACILITY_INDICATORS';

    public function run(): void
    {
        $template = ReportTemplate::firstOrCreate(
            ['code' => self::CODE],
            [
                'name' => 'Monthly Facility Indicators Report',
                'description' => 'Monthly facility-level report covering all current indicators (newborn and pediatric/child care modules).',
                'report_type' => 'general',
                'frequency' => 'monthly',
                'is_active' => true,
            ]
        );

        $indicators = Indicator::orderBy('sort_order')->orderBy('id')->get();

        $syncData = $indicators->mapWithKeys(fn (Indicator $indicator, int $index) => [
            $indicator->id => ['sort_order' => $index, 'is_required' => true],
        ])->toArray();

        $template->indicators()->syncWithoutDetaching($syncData);

        $this->command?->info(sprintf(
            'Monthly Facility Indicators Report template ready with %d indicator(s) attached.',
            $indicators->count()
        ));
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/ReportTemplateSeederTest.php`
Expected: PASS on all three tests.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/ReportTemplateSeeder.php tests/Unit/ReportTemplateSeederTest.php
git commit -m "feat: add ReportTemplateSeeder for a real, usable starter Monthly Report template"
```

---

### Task 2: Defensive empty-state helper text on `report_template_id`

**Files:**
- Modify: `app/Filament/Resources/MonthlyReportResource.php:40-46` (the existing `Select::make('report_template_id')` field — add `->helperText()` only, no other change)
- Test: `tests/Feature/MonthlyReportEmptyStateTest.php`

**Interfaces:**
- Consumes: `App\Models\ReportTemplate::active()` (existing `scopeActive` on the model — confirmed present), `App\Filament\Resources\ReportTemplateResource::getUrl('create')` (confirmed route exists: `app/Filament/Resources/ReportTemplateResource.php:146`).
- Produces: nothing consumed by later tasks — this is the terminal change for this plan.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\MonthlyReportResource\Pages\CreateMonthlyReport;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MonthlyReportEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Admin']);
        Permission::firstOrCreate(['name' => 'create_monthly::report', 'guard_name' => 'web']);
        $user->givePermissionTo('create_monthly::report');
        $this->actingAs($user);

        return $user;
    }

    public function test_no_helper_text_is_shown_when_a_report_template_already_exists(): void
    {
        $this->actingAsAdmin();
        ReportTemplate::create(['name' => 'T', 'code' => 'T1', 'report_type' => 'general', 'is_active' => true]);

        Livewire::test(CreateMonthlyReport::class)
            ->assertSuccessful()
            ->assertDontSee('No report templates yet');
    }

    public function test_helper_text_with_a_link_is_shown_when_no_report_template_exists(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateMonthlyReport::class)
            ->assertSuccessful()
            ->assertSee('No report templates yet');
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/MonthlyReportEmptyStateTest.php`
Expected: the second test (`test_helper_text_with_a_link_is_shown_when_no_report_template_exists`) FAILS — no helper text exists yet. The first test may pass trivially (nothing to see either way) — that's fine, it becomes a real regression guard once Step 3 lands.

- [ ] **Step 3: Add the helper text**

In `app/Filament/Resources/MonthlyReportResource.php`, change:

```php
                                Forms\Components\Select::make('report_template_id')
                                ->label('Report Template')
                                ->relationship('reportTemplate', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn(string $operation): bool => $operation === 'edit')
                                ->live()
```

to:

```php
                                Forms\Components\Select::make('report_template_id')
                                ->label('Report Template')
                                ->relationship('reportTemplate', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn(string $operation): bool => $operation === 'edit')
                                ->helperText(fn (): ?\Illuminate\Support\HtmlString => \App\Models\ReportTemplate::active()->count() > 0
                                    ? null
                                    : new \Illuminate\Support\HtmlString(
                                        'No report templates yet — <a href="' . \App\Filament\Resources\ReportTemplateResource::getUrl('create') . '" class="underline">create one first</a>.'
                                    ))
                                ->live()
```

(Only the `->helperText(...)` line is new — every other line in this block is unchanged, copied verbatim to show exact placement.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/MonthlyReportEmptyStateTest.php`
Expected: PASS on both.

- [ ] **Step 5: Run the full suite to confirm nothing else broke**

Run: `php artisan test`
Expected: same pass/risky counts as before this plan, plus the new tests — 0 new failures.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MonthlyReportResource.php tests/Feature/MonthlyReportEmptyStateTest.php
git commit -m "feat: show empty-state guidance on Monthly Report create form when no report templates exist"
```

---

## Deferred / not in this plan

- `createOptionForm()` inline template creation — explicitly out of scope per the approved spec.
- Any other Monthly Reports UX polish beyond this specific problem.
- Running the new `ReportTemplateSeeder` against the real `mnch-feb` database — a separate, explicit step after this plan's tests are merged (same backup-first pattern used for every other real-DB change this session), not assumed as part of plan execution.

## Self-Review

**Spec coverage:** Task 1 → spec's "Seed one real starter ReportTemplate" section, including the corrected `general`/44-indicator framing. Task 2 → spec's "Defensive empty-state helper text" section, explicitly excluding `createOptionForm()` as the spec required.

**Placeholder scan:** No TBD/TODO. Seeder code and helper-text diff are both complete, verified against real current file content (`report_template_id` field block read directly from `MonthlyReportResource.php:40-47`, `ReportTemplateResource::getUrl` route confirmed at line 146, `Indicator.sort_order` column confirmed via `SHOW COLUMNS`).

**Type consistency:** `ReportTemplate::active()` scope name matches the model's actual `scopeActive()` method (confirmed in Phase 1 discovery — `app/Models/ReportTemplate.php`). `report_template_indicators` pivot column names (`sort_order`, `is_required`) match both the guarded migration written earlier this session and the model's `withPivot(['sort_order', 'is_required'])` call.
