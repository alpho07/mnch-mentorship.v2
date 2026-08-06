# Mentorship Dashboard Gap-Closing Widgets Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close 5 genuine KPI gaps and fix 1 correctness bug in `MentorAnalyticsDashboardService`, per `docs/superpowers/specs/2026-08-07-mentorship-dashboard-gaps-design.md`.

**Architecture:** All changes are additive to one existing service (`app/Services/MentorAnalyticsDashboardService.php`) and its consuming Blade partial (`resources/views/analytics/dashboard/mentor-mode.blade.php`). No new files, no new routes, no schema changes. Existing KPIs/tiles/tests are untouched.

**Tech Stack:** Laravel 12 Eloquent queries, PHPUnit/Laravel test conventions matching this codebase (`RefreshDatabase`, factory + plain `::create()` mix, matching the existing `MentorAnalyticsDashboardServiceExceptionsTest.php` fixture pattern exactly).

## Global Constraints

- Every new KPI that has "no data yet in scope" must return `null`, not `0` — `0` would misleadingly imply "everyone failed" rather than "nobody assessed yet." The Blade layer renders `null` as "No data yet."
- Do not touch any of the 7 existing KPI tiles, their values, or their markup — only append new tiles after them, matching the exact same CSS/markup pattern.
- `CoordinatorExceptionResolver`'s tier-2 items already carry `'tier' => 2` (confirmed at `app/Services/CoordinatorExceptionResolver.php:133`) — reuse that, don't re-derive inactivity logic.

---

### Task 1: Fix the mentor CPD calculation bug

**Files:**
- Modify: `app/Services/MentorAnalyticsDashboardService.php:69-83` (the inline `$cpdCounts`/`$cpdData` block inside `build()`)
- Test: `tests/Unit/Services/MentorAnalyticsDashboardServiceCpdTest.php`

**Interfaces:**
- Consumes: `App\Services\CpdPointsService::batchForMentors(array $mentorIds): array` — already returns `[$mentorId => ['total' => int, 'level' => array, 'completed_modules' => int]]`, the exact shape `$cpdData` already has downstream (confirmed by reading `CpdPointsService.php` in full).
- Produces: `$cpdData` with the same shape as before — `buildMatrix()`, `buildKpis()`, `buildChartData()`, and `CoordinatorExceptionResolver::resolve()` (all called later in `build()`) require no changes.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\CpdPointsService;
use App\Services\MentorAnalyticsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorAnalyticsDashboardServiceCpdTest extends TestCase
{
    use RefreshDatabase;

    public function test_cpd_total_matches_cpd_points_service_even_when_the_class_is_not_completed(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $viewer = User::factory()->create();
        $viewer->assignRole('super_admin');

        $mentor = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        // Class is 'active', not 'completed' — the old inline calc counted this;
        // CpdPointsService::forMentor() correctly does not.
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'completed',
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $dashboardCpd = collect($result['matrix'])->firstWhere('mentor_id', $mentor->id)['cpd_total'] ?? null;

        $realCpd = app(CpdPointsService::class)->forMentor($mentor)['total'];

        $this->assertSame($realCpd, $dashboardCpd);
        $this->assertSame(0, $dashboardCpd, 'Module is completed but the class is not — CpdPointsService correctly awards 0 points here.');
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceCpdTest.php`
Expected: FAIL — the current inline calc counts the completed module regardless of class status, so `$dashboardCpd` is `1`, not `0`.

- [ ] **Step 3: Fix it**

In `app/Services/MentorAnalyticsDashboardService.php`, replace:

```php
        // CPD: 1 pt per completed module (any class status) — computed from already-loaded data
        $trainingMentor = $trainings->pluck('mentor_id', 'id'); // training_id => mentor_id
        $cpdCounts = [];
        foreach ($allClasses as $cls) {
            $mentorId = $trainingMentor->get($cls->training_id);
            if (! $mentorId) continue;
            $cpdCounts[$mentorId] = ($cpdCounts[$mentorId] ?? 0)
                + $cls->classModules->where('status', 'completed')->count();
        }
        $cpdService = app(CpdPointsService::class);
        $cpdData = [];
        foreach ($mentorIds as $mid) {
            $count = $cpdCounts[$mid] ?? 0;
            $cpdData[$mid] = ['total' => $count, 'level' => $cpdService->level($count), 'completed_modules' => $count];
        }
```

with:

```php
        $cpdData = app(CpdPointsService::class)->batchForMentors($mentorIds);
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceCpdTest.php`
Expected: PASS.

- [ ] **Step 5: Run the existing exceptions test to confirm no regression**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceExceptionsTest.php`
Expected: PASS (unchanged).

- [ ] **Step 6: Commit**

```bash
git add app/Services/MentorAnalyticsDashboardService.php tests/Unit/Services/MentorAnalyticsDashboardServiceCpdTest.php
git commit -m "fix: mentor CPD total now matches CpdPointsService (was ignoring class status)"
```

---

### Task 2: Add the 5 new KPIs to `buildKpis()`

**Files:**
- Modify: `app/Services/MentorAnalyticsDashboardService.php` — `build()` (to pass new data into `buildKpis()`) and `buildKpis()` itself
- Test: `tests/Unit/Services/MentorAnalyticsDashboardServiceGapKpisTest.php`

**Interfaces:**
- Consumes: `App\Models\MenteeModuleProgress` (`class_participant_id`, `assessment_score`, `assessment_status` — confirmed fillable), `App\Models\RubricAssessment` (`class_module_id`, `mentor_id`, `mentee_id`, `score`, `passed` — confirmed fillable), `App\Models\ClassParticipant` (`status` — confirmed fillable, `'dropped'` is a real status value used elsewhere in this file's own `$participants` filter).
- Produces: `$kpis['mentor_to_mentee_ratio']`, `$kpis['avg_assessment_score']`, `$kpis['assessment_pass_rate']`, `$kpis['avg_rubric_score']`, `$kpis['rubric_pass_rate']`, `$kpis['inactive_mentors']`, `$kpis['dropped_mentees']` — consumed by Task 3's Blade changes.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\RubricAssessment;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorAnalyticsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorAnalyticsDashboardServiceGapKpisTest extends TestCase
{
    use RefreshDatabase;

    private function makeScopedClass(): array
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $viewer = User::factory()->create();
        $viewer->assignRole('super_admin');

        $mentor = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        return compact('viewer', 'mentor', 'class', 'classModule', 'programModule');
    }

    public function test_mentor_to_mentee_ratio_divides_the_two_existing_kpis(): void
    {
        ['viewer' => $viewer, 'class' => $class] = $this->makeScopedClass();

        $menteeA = User::factory()->create();
        $menteeB = User::factory()->create();
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => $menteeA->id, 'status' => 'active']);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => $menteeB->id, 'status' => 'active']);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);

        $this->assertSame(2.0, $result['kpis']['mentor_to_mentee_ratio']);
    }

    public function test_assessment_score_stats_are_null_with_no_data_and_computed_with_data(): void
    {
        ['viewer' => $viewer, 'class' => $class] = $this->makeScopedClass();

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertNull($result['kpis']['avg_assessment_score']);
        $this->assertNull($result['kpis']['assessment_pass_rate']);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $mentee->id, 'status' => 'active',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $class->classModules()->first()->id,
            'assessment_score' => 80,
            'assessment_status' => 'passed',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $class->classModules()->first()->id,
            'assessment_score' => 40,
            'assessment_status' => 'failed',
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertSame(60.0, $result['kpis']['avg_assessment_score']);
        $this->assertSame(50.0, $result['kpis']['assessment_pass_rate']);
    }

    public function test_rubric_score_stats_are_null_with_no_data_and_computed_with_data(): void
    {
        ['viewer' => $viewer, 'class' => $class, 'mentor' => $mentor, 'classModule' => $classModule, 'programModule' => $programModule] = $this->makeScopedClass();

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertNull($result['kpis']['avg_rubric_score']);
        $this->assertNull($result['kpis']['rubric_pass_rate']);

        $mentee = User::factory()->create();
        $rubric = ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Test Rubric',
            'total_marks' => 100,
            'pass_marks' => 70,
        ]);
        RubricAssessment::create([
            'module_rubric_id' => $rubric->id,
            'class_module_id' => $classModule->id,
            'mentee_id' => $mentee->id,
            'mentor_id' => $mentor->id,
            'score' => 90,
            'passed' => true,
            'assessed_at' => now(),
        ]);
        RubricAssessment::create([
            'module_rubric_id' => $rubric->id,
            'class_module_id' => $classModule->id,
            'mentee_id' => $mentee->id,
            'mentor_id' => $mentor->id,
            'score' => 50,
            'passed' => false,
            'assessed_at' => now(),
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertSame(70.0, $result['kpis']['avg_rubric_score']);
        $this->assertSame(50.0, $result['kpis']['rubric_pass_rate']);
    }

    public function test_dropped_mentees_are_counted_separately_from_the_active_participant_scope(): void
    {
        ['viewer' => $viewer, 'class' => $class] = $this->makeScopedClass();

        $mentee = User::factory()->create();
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $mentee->id, 'status' => 'dropped',
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);

        $this->assertSame(1, $result['kpis']['dropped_mentees']);
        $this->assertSame(0, $result['kpis']['total_mentees'], 'Dropped participants are excluded from total_mentees, per the existing $participants scope.');
    }

    public function test_inactive_mentors_kpi_matches_the_tier_2_exception_count(): void
    {
        ['viewer' => $viewer] = $this->makeScopedClass();

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);

        $expectedTier2Count = collect($result['exceptions'])->where('tier', 2)->count();
        $this->assertSame($expectedTier2Count, $result['kpis']['inactive_mentors']);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceGapKpisTest.php`
Expected: FAIL — none of these array keys exist yet in `buildKpis()`'s return.

- [ ] **Step 3: Implement the new KPIs**

In `app/Services/MentorAnalyticsDashboardService.php`, inside `build()`, right before the `$kpis = $this->buildKpis(...)` line, add:

```php
        $participantIds = $participants->pluck('id');
        $assessmentStats = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
            ->whereNotNull('assessment_score')
            ->selectRaw('AVG(assessment_score) as avg_score, SUM(assessment_status = "passed") as passed, COUNT(*) as total')
            ->first();

        $liveClassModuleIds = $liveClasses->pluck('classModules')->flatten()->pluck('id');
        $rubricStats = RubricAssessment::whereIn('class_module_id', $liveClassModuleIds)
            ->selectRaw('AVG(score) as avg_score, SUM(passed) as passed, COUNT(*) as total')
            ->first();

        $droppedMentees = ClassParticipant::whereIn('mentorship_class_id', $liveClassIds)
            ->where('status', 'dropped')
            ->count();
```

Then change the `$kpis = $this->buildKpis(...)` call to pass these three through, and update `buildKpis()`'s signature and return array:

```php
        $kpis      = $this->buildKpis($mentors, $allClasses, $liveClasses, $participants, $cpdData, $assessmentStats, $rubricStats, $droppedMentees, $exceptions);
```

Note: `$exceptions` must be computed *before* this call — move the existing `$exceptions = app(CoordinatorExceptionResolver::class)->resolve(...)` line (currently after `$kpis`/`$chartData`/`$insights` in `build()`) to right after `$matrix` is built, before `$kpis` is computed. `buildInsights()` already only consumes `$kpis` and `$matrix`, not `$exceptions`, so this reordering doesn't affect it.

Update `buildKpis()`:

```php
    private function buildKpis($mentors, $allClasses, $liveClasses, $participants, array $cpdData, $assessmentStats, $rubricStats, int $droppedMentees, array $exceptions): array
    {
        $totalCpd          = array_sum(array_column($cpdData, 'total'));
        $avgCpd            = count($cpdData) > 0 ? round($totalCpd / count($cpdData), 1) : 0;
        $modulesFacilitated = $liveClasses->sum(fn ($c) => $c->classModules->count());
        $totalMentors      = $mentors->count();
        $totalMentees      = $participants->pluck('user_id')->unique()->count();

        return [
            'total_mentors'       => $totalMentors,
            'active_classes'      => $liveClasses->count(),
            'completed_classes'   => $allClasses->where('status', 'completed')->count(),
            'modules_facilitated' => $modulesFacilitated,
            'total_mentees'       => $totalMentees,
            'certified_mentees'      => $participants->whereNotNull('head_drmh_approved_at')->count(),
            'total_cpd_awarded'   => $totalCpd,
            'avg_cpd_per_mentor'  => $avgCpd,
            'mentor_to_mentee_ratio' => $totalMentors > 0 ? round($totalMentees / $totalMentors, 1) : 0,
            'avg_assessment_score' => $assessmentStats && $assessmentStats->total > 0 ? round((float) $assessmentStats->avg_score, 1) : null,
            'assessment_pass_rate' => $assessmentStats && $assessmentStats->total > 0 ? round(($assessmentStats->passed / $assessmentStats->total) * 100, 1) : null,
            'avg_rubric_score' => $rubricStats && $rubricStats->total > 0 ? round((float) $rubricStats->avg_score, 1) : null,
            'rubric_pass_rate' => $rubricStats && $rubricStats->total > 0 ? round(($rubricStats->passed / $rubricStats->total) * 100, 1) : null,
            'inactive_mentors' => collect($exceptions)->where('tier', 2)->count(),
            'dropped_mentees' => $droppedMentees,
        ];
    }
```

Add `use App\Models\MenteeModuleProgress;` and `use App\Models\RubricAssessment;` to the file's imports (alongside the existing `use App\Models\ClassParticipant;` etc.).

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceGapKpisTest.php`
Expected: PASS on all 5.

- [ ] **Step 5: Run the other two MentorAnalyticsDashboardService tests to confirm no regression**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceExceptionsTest.php tests/Unit/Services/MentorAnalyticsDashboardServiceCpdTest.php`
Expected: PASS (unchanged).

- [ ] **Step 6: Commit**

```bash
git add app/Services/MentorAnalyticsDashboardService.php tests/Unit/Services/MentorAnalyticsDashboardServiceGapKpisTest.php
git commit -m "feat: add mentor-to-mentee ratio, assessment/rubric scores, inactive/dropped counts to MentorAnalyticsDashboardService"
```

---

### Task 3: Add the new KPI tiles to the Blade view

**Files:**
- Modify: `resources/views/analytics/dashboard/mentor-mode.blade.php` (append after the existing 7 `<div class="kpi-card">` blocks, lines ~169-201)

**Interfaces:**
- Consumes: `$mentorKpis['mentor_to_mentee_ratio']`, `$mentorKpis['avg_assessment_score']`, `$mentorKpis['assessment_pass_rate']`, `$mentorKpis['avg_rubric_score']`, `$mentorKpis['rubric_pass_rate']`, `$mentorKpis['inactive_mentors']`, `$mentorKpis['dropped_mentees']` — all produced by Task 2.
- Produces: nothing consumed by later tasks — terminal step.

- [ ] **Step 1: Add the new tiles**

Immediately after the existing "Avg CPD / Mentor" `<div class="kpi-card">` block (the 7th tile) and before the closing `</div></div>` of `.kpi-strip`/`.kpi-strip-wrap`, add:

```blade
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="kpi-value">{{ $mentorKpis['mentor_to_mentee_ratio'] }}</div>
            <div class="kpi-label">Mentees per Mentor</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-clipboard-question"></i></div>
            <div class="kpi-value">
                {{ $mentorKpis['avg_assessment_score'] !== null ? $mentorKpis['avg_assessment_score'] . '%' : 'No data yet' }}
            </div>
            <div class="kpi-label">Avg Assessment Score</div>
            @if($mentorKpis['assessment_pass_rate'] !== null)
                <span class="kpi-trend {{ $mentorKpis['assessment_pass_rate'] >= 70 ? 'up' : 'down' }}">
                    {{ $mentorKpis['assessment_pass_rate'] }}% pass rate
                </span>
            @endif
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-hand-holding-medical"></i></div>
            <div class="kpi-value">
                {{ $mentorKpis['avg_rubric_score'] !== null ? $mentorKpis['avg_rubric_score'] : 'No data yet' }}
            </div>
            <div class="kpi-label">Avg Practical Skills Score</div>
            @if($mentorKpis['rubric_pass_rate'] !== null)
                <span class="kpi-trend {{ $mentorKpis['rubric_pass_rate'] >= 70 ? 'up' : 'down' }}">
                    {{ $mentorKpis['rubric_pass_rate'] }}% pass rate
                </span>
            @endif
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-user-clock"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['inactive_mentors'] }}">{{ $mentorKpis['inactive_mentors'] }}</div>
            <div class="kpi-label">Inactive Mentors</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-user-slash"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['dropped_mentees'] }}">{{ $mentorKpis['dropped_mentees'] }}</div>
            <div class="kpi-label">Dropped Mentees</div>
        </div>
```

- [ ] **Step 2: Manually verify the page renders without error**

Run: `php artisan route:list --path=analytics/dashboard` to confirm the route name, then either (a) if the Playwright browser tool is available, navigate to `/analytics/dashboard?mode=mentor` while authenticated and screenshot it, or (b) if not, run a quick `$this->actingAs(...)->get('/analytics/dashboard?mode=mentor')->assertSuccessful()` smoke check in a throwaway test (not committed) to confirm no Blade syntax errors.

- [ ] **Step 3: Commit**

```bash
git add resources/views/analytics/dashboard/mentor-mode.blade.php
git commit -m "feat: display the 5 new mentorship KPIs on the mentor analytics dashboard"
```

---

## Self-Review

**Spec coverage:** Task 1 → spec's "Fix the CPD calculation bug" section. Task 2 → spec's sections 2-5 (ratio, assessment scores, rubric scores, inactive/dropped counts), implemented together since they're all additions to the same `buildKpis()` call. Task 3 → spec's "Blade changes" section.

**Placeholder scan:** No TBD/TODO. All field names, relationships, and existing code verified by reading the actual files (`CpdPointsService.php`, `MentorAnalyticsDashboardService.php` in full, `MenteeModuleProgress.php`, `RubricAssessment.php`, `ModuleRubric.php`, `ClassParticipant.php`, `CoordinatorExceptionResolver.php`, `mentor-mode.blade.php`) rather than assumed.

**Type consistency:** `buildKpis()`'s new signature (adding `$assessmentStats`, `$rubricStats`, `int $droppedMentees`, `array $exceptions` parameters) is reflected consistently in both the call site change and the method definition in Task 2 — verified they match. The `$exceptions`-before-`$kpis` reordering in `build()` is called out explicitly since it's the one non-obvious structural change (existing code computes `$exceptions` last; this plan moves it earlier) — confirmed `buildInsights()` (which runs between them) doesn't consume `$exceptions`, so the reorder is safe.
