# Coordinator Journey Phase 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the coordinator's mentor-analytics view (`/analytics/dashboard?mode=mentor`) around a ranked facility/mentor exception list, replacing the zero-CPD-mentor count with a real drill-down list and adding inactive-mentor detection — a concept that doesn't exist anywhere in the codebase today.

**Architecture:** A new stateless service, `CoordinatorExceptionResolver`, takes the collections `MentorAnalyticsDashboardService::build()` already loads (`$trainings`, `$liveClasses`, `$participants`, `$cpdData`) and returns a ranked list across three tiers, rolled up to facility/mentor granularity. `build()` adds one call and one new `exceptions` return key. `AnalyticsDashboardController@index` passes it through; the `mentor-mode` Blade partial gets a new exceptions card above the existing content.

**Tech Stack:** Laravel 12, PHPUnit (SQLite in-memory, `RefreshDatabase` per test class).

## Global Constraints

- Reuse the drill-down mechanism already built into this view: link every exception item back to `/analytics/dashboard?mode=mentor&facility_id=X` or `&mentor_id=X` (the existing filter dropdowns already support these query params) via `route('analytics.dashboard.index', [...])` — do not invent a new page.
- A mentor qualifying for both Tier 2 (inactive) and Tier 3 (zero CPD) appears once, at Tier 2.
- Every new PHP class needs a test using `Illuminate\Foundation\Testing\RefreshDatabase` on SQLite in-memory, following the pattern in `tests/Unit/Services/MentorPriorityQueueResolverTest.php`.
- **Deviation from the approved spec, decided during plan-writing:** the spec called for collapsing the existing KPI strip/leaderboards/matrix below the fold, matching Phases 1-2's pattern. This view's leaderboard section renders Chart.js canvases — initializing a chart inside a closed `<details>` element computes a 0×0 canvas size, producing broken/invisible charts when later expanded. To avoid that, this plan only **adds** the exceptions card above the existing content and leaves the KPI strip, insights, leaderboards, and matrix exactly as they render today (visible, uncollapsed). The "exceptions first" principle is satisfied by what leads the page, not by hiding the rest.
- Preserve all existing computed dashboard data (`kpis`, `matrix`, `chartData`, `insights`) — this plan only adds the `exceptions` key, never removes or reshapes the others.
- The three orphaned Filament pages (`CoverageDashboard`, `CoverageOverview`, `TrainingCoverageDashboard`) are confirmed dead code (`shouldRegisterNavigation() => false`) — do not touch them.

---

### Task 1: `CoordinatorExceptionResolver` service

**Files:**
- Create: `app/Services/CoordinatorExceptionResolver.php`
- Test: `tests/Unit/Services/CoordinatorExceptionResolverTest.php`

**Interfaces:**
- Produces: `CoordinatorExceptionResolver::resolve(Collection $trainings, Collection $liveClasses, Collection $participants, array $cpdData): array` returning a list of:
  ```php
  [
      'tier' => int,        // 1-3
      'label' => string,
      'headline' => string,
      'subtext' => string,
      'url' => string,
      'meta' => array,      // e.g. ['completion_pct' => 32], ['days_inactive' => 19], ['classes_led' => 2]
  ]
  ```
  `$trainings` must have `facility` and `mentor` eager-loaded; `$liveClasses` must have `classModules` eager-loaded. Consumed by Task 2 (`MentorAnalyticsDashboardService::build()`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/CoordinatorExceptionResolverTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\CoordinatorExceptionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinatorExceptionResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrainingWithClass(?User $mentor = null): array
    {
        $mentor = $mentor ?? User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'Test Module']);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        return compact('mentor', 'program', 'facility', 'training', 'class', 'programModule', 'classModule');
    }

    private function loadForResolver(array $env): array
    {
        // Fresh fetches, not ->load() on the already-instantiated $env models — ->load() only
        // refreshes the named relation, not the base model's own attributes (e.g. a backdated
        // created_at written via a query-builder update() after $env was built would otherwise
        // never be seen here).
        return [
            collect([Training::with('facility', 'mentor')->find($env['training']->id)]),
            collect([MentorshipClass::with('classModules')->find($env['class']->id)]),
        ];
    }

    public function test_facility_below_completion_threshold_is_tier_one(): void
    {
        $env = $this->makeTrainingWithClass();
        // Second module, neither completed => 0% completion, well under 40%
        ClassModule::factory()->create([
            'mentorship_class_id' => $env['class']->id,
            'program_module_id' => $env['programModule']->id,
            'status' => 'in_progress',
        ]);
        [$trainings, $liveClasses] = $this->loadForResolver($env);

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), []);

        $tier1 = collect($result)->firstWhere('tier', 1);
        $this->assertNotNull($tier1);
    }

    public function test_facility_at_or_above_thresholds_is_not_tier_one(): void
    {
        $env = $this->makeTrainingWithClass();
        $env['classModule']->update(['status' => 'completed']);
        // Single module, completed => 100% completion; no participants => attendance check skipped
        [$trainings, $liveClasses] = $this->loadForResolver($env);

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), []);

        $this->assertNull(collect($result)->firstWhere('tier', 1));
    }

    public function test_mentor_with_no_activity_and_stale_class_is_tier_two(): void
    {
        $env = $this->makeTrainingWithClass();
        // Backdate the class so the "no activity at all" fallback baseline is 20 days ago, not "now".
        MentorshipClass::where('id', $env['class']->id)->update(['created_at' => now()->subDays(20)]);
        [$trainings, $liveClasses] = $this->loadForResolver($env);

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), []);

        $tier2 = collect($result)->firstWhere('tier', 2);
        $this->assertNotNull($tier2);
    }

    public function test_mentor_with_recent_attendance_mark_is_not_tier_two(): void
    {
        $env = $this->makeTrainingWithClass();
        MentorshipClass::where('id', $env['class']->id)->update(['created_at' => now()->subDays(20)]);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $env['class']->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        ClassAttendance::create([
            'class_id' => $env['class']->id,
            'class_module_id' => $env['classModule']->id,
            'user_id' => $mentee->id,
            'marked_by' => $env['mentor']->id,
            'marked_at' => now(),
            'source' => 'manual',
        ]);
        [$trainings, $liveClasses] = $this->loadForResolver($env);
        $participants = collect([$participant]);

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, $participants, []);

        $this->assertNull(collect($result)->firstWhere('tier', 2));
    }

    public function test_mentor_with_zero_live_classes_is_never_tier_two(): void
    {
        $env = $this->makeTrainingWithClass();
        MentorshipClass::where('id', $env['class']->id)->update(['created_at' => now()->subDays(60)]);
        $trainings = collect([$env['training']->load('facility', 'mentor')]);
        // Empty $liveClasses — this mentor has no active class in scope.
        $liveClasses = collect();

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), []);

        $this->assertSame([], $result);
    }

    public function test_zero_cpd_mentor_is_tier_three(): void
    {
        $env = $this->makeTrainingWithClass();
        [$trainings, $liveClasses] = $this->loadForResolver($env);
        $cpdData = [$env['mentor']->id => ['total' => 0]];

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), $cpdData);

        $tier3 = collect($result)->firstWhere('tier', 3);
        $this->assertNotNull($tier3);
    }

    public function test_mentor_with_cpd_is_not_tier_three(): void
    {
        $env = $this->makeTrainingWithClass();
        [$trainings, $liveClasses] = $this->loadForResolver($env);
        $cpdData = [$env['mentor']->id => ['total' => 5]];

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), $cpdData);

        $this->assertNull(collect($result)->firstWhere('tier', 3));
    }

    public function test_mentor_qualifying_for_tier_two_and_three_appears_once_at_tier_two(): void
    {
        $env = $this->makeTrainingWithClass();
        MentorshipClass::where('id', $env['class']->id)->update(['created_at' => now()->subDays(20)]);
        // Module completed so this facility's completion rate is 100% — isolates this test to the
        // Tier 2/Tier 3 mentor dedup, rather than also incidentally tripping Tier 1 (facility-level,
        // independent of mentor dedup) since the single module would otherwise sit at 0% complete.
        $env['classModule']->update(['status' => 'completed']);
        [$trainings, $liveClasses] = $this->loadForResolver($env);
        $cpdData = [$env['mentor']->id => ['total' => 0]];

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), $cpdData);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['tier']);
    }

    public function test_empty_trainings_returns_empty_array(): void
    {
        $result = (new CoordinatorExceptionResolver())->resolve(collect(), collect(), collect(), []);

        $this->assertSame([], $result);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/CoordinatorExceptionResolverTest.php`
Expected: FAIL — `App\Services\CoordinatorExceptionResolver` does not exist.

- [ ] **Step 3: Write the implementation**

Create `app/Services/CoordinatorExceptionResolver.php`:

```php
<?php

namespace App\Services;

use App\Models\ClassAttendance;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CoordinatorExceptionResolver
{
    private const FACILITY_COMPLETION_THRESHOLD_PCT = 40;

    private const FACILITY_ATTENDANCE_THRESHOLD = 0.6;

    private const MENTOR_INACTIVE_DAYS = 14;

    public function resolve(Collection $trainings, Collection $liveClasses, Collection $participants, array $cpdData): array
    {
        $seenMentorIds = [];
        $items = collect();

        $items = $items->merge($this->tier1FacilitiesFallingBehind($trainings, $liveClasses, $participants));
        $items = $items->merge($this->tier2InactiveMentors($trainings, $liveClasses, $seenMentorIds));
        $items = $items->merge($this->tier3ZeroCpdMentors($trainings, $liveClasses, $cpdData, $seenMentorIds));

        return $items->sort(function (array $a, array $b) {
            if ($a['tier'] !== $b['tier']) {
                return $a['tier'] <=> $b['tier'];
            }

            return $a['sort_ts'] <=> $b['sort_ts'];
        })->map(function (array $item) {
            unset($item['sort_ts']);

            return $item;
        })->values()->toArray();
    }

    private function tier1FacilitiesFallingBehind(Collection $trainings, Collection $liveClasses, Collection $participants): array
    {
        $trainingsById = $trainings->keyBy('id');
        $classesByFacility = $liveClasses->groupBy(
            fn ($class) => $trainingsById->get($class->training_id)?->facility_id
        );

        $items = [];

        foreach ($classesByFacility as $facilityId => $classes) {
            if (! $facilityId) {
                continue;
            }

            $training = $trainingsById->get($classes->first()->training_id);
            $facility = $training?->facility;

            $modulesTotal = $classes->sum(fn ($c) => $c->classModules->count());
            $modulesCompleted = $classes->sum(fn ($c) => $c->classModules->where('status', 'completed')->count());
            $completionPct = $modulesTotal > 0 ? (int) round(($modulesCompleted / $modulesTotal) * 100) : 0;

            $classIds = $classes->pluck('id');
            $enrolled = $participants->whereIn('mentorship_class_id', $classIds)->count();
            $confirmed = ClassAttendance::whereIn('class_id', $classIds)->count();
            $attendanceRate = $enrolled > 0 ? $confirmed / $enrolled : 1.0;

            $failsCompletion = $modulesTotal > 0 && $completionPct < self::FACILITY_COMPLETION_THRESHOLD_PCT;
            $failsAttendance = $enrolled > 0 && $attendanceRate < self::FACILITY_ATTENDANCE_THRESHOLD;

            if (! $failsCompletion && ! $failsAttendance) {
                continue;
            }

            $items[] = [
                'tier' => 1,
                'label' => 'Review Facility',
                'headline' => ($facility?->name ?? 'A facility')." — {$completionPct}% avg completion across {$classes->count()} class(es)",
                'subtext' => 'Confirmed attendance: '.((int) round($attendanceRate * 100)).'%',
                'url' => route('analytics.dashboard.index', ['mode' => 'mentor', 'facility_id' => $facilityId]),
                'meta' => ['completion_pct' => $completionPct, 'attendance_pct' => (int) round($attendanceRate * 100)],
                'sort_ts' => $completionPct,
            ];
        }

        return $items;
    }

    private function tier2InactiveMentors(Collection $trainings, Collection $liveClasses, array &$seenMentorIds): array
    {
        $trainingsById = $trainings->keyBy('id');
        $classesByMentor = $liveClasses->groupBy(
            fn ($class) => $trainingsById->get($class->training_id)?->mentor_id
        );

        $items = [];

        foreach ($classesByMentor as $mentorId => $classes) {
            if (! $mentorId || isset($seenMentorIds[$mentorId])) {
                continue;
            }

            $mentor = $trainingsById->get($classes->first()->training_id)?->mentor;
            $classIds = $classes->pluck('id');
            $participantIds = ClassParticipant::whereIn('mentorship_class_id', $classIds)->pluck('id');

            $lastAttendance = ClassAttendance::whereIn('class_id', $classIds)
                ->where('marked_by', $mentorId)
                ->max('marked_at');
            $lastVideoReview = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
                ->max('video_reviewed_at');
            $lastRecommendation = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
                ->max('recommendation_written_at');

            $lastActivity = collect([$lastAttendance, $lastVideoReview, $lastRecommendation])
                ->filter()
                ->map(fn ($d) => Carbon::parse($d))
                ->sortByDesc(fn ($d) => $d->timestamp)
                ->first();

            if (! $lastActivity) {
                $earliestClass = $classes->sortBy('created_at')->first();
                $lastActivity = $earliestClass?->created_at
                    ? Carbon::parse($earliestClass->created_at)
                    : now();
            }

            $daysInactive = (int) $lastActivity->diffInDays(now());
            if ($daysInactive < self::MENTOR_INACTIVE_DAYS) {
                continue;
            }

            $items[] = [
                'tier' => 2,
                'label' => 'Follow Up',
                'headline' => ($mentor?->name ?? 'A mentor')." — inactive {$daysInactive} days",
                'subtext' => 'No reviews, attendance marks, or feedback recorded',
                'url' => route('analytics.dashboard.index', ['mode' => 'mentor', 'mentor_id' => $mentorId]),
                'meta' => ['days_inactive' => $daysInactive],
                'sort_ts' => -$daysInactive,
            ];
            $seenMentorIds[$mentorId] = true;
        }

        return $items;
    }

    private function tier3ZeroCpdMentors(Collection $trainings, Collection $liveClasses, array $cpdData, array &$seenMentorIds): array
    {
        $trainingsById = $trainings->keyBy('id');
        $classesByMentor = $liveClasses->groupBy(
            fn ($class) => $trainingsById->get($class->training_id)?->mentor_id
        );

        $items = [];

        foreach ($classesByMentor as $mentorId => $classes) {
            if (! $mentorId || isset($seenMentorIds[$mentorId])) {
                continue;
            }

            $cpd = $cpdData[$mentorId] ?? null;
            if (! $cpd || ($cpd['total'] ?? 0) > 0) {
                continue;
            }

            $mentor = $trainingsById->get($classes->first()->training_id)?->mentor;
            $classesLed = $classes->count();

            $items[] = [
                'tier' => 3,
                'label' => 'View Mentor',
                'headline' => ($mentor?->name ?? 'A mentor').' — 0 CPD points',
                'subtext' => "{$classesLed} class(es) led, no modules completed",
                'url' => route('analytics.dashboard.index', ['mode' => 'mentor', 'mentor_id' => $mentorId]),
                'meta' => ['classes_led' => $classesLed],
                'sort_ts' => -$classesLed,
            ];
            $seenMentorIds[$mentorId] = true;
        }

        return $items;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Services/CoordinatorExceptionResolverTest.php`
Expected: PASS — all 9 tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Services/CoordinatorExceptionResolver.php tests/Unit/Services/CoordinatorExceptionResolverTest.php
git commit -m "feat: add CoordinatorExceptionResolver for facility/mentor exception list"
```

---

### Task 2: Wire the resolver into `MentorAnalyticsDashboardService`

**Files:**
- Modify: `app/Services/MentorAnalyticsDashboardService.php`
- Test: `tests/Unit/Services/MentorAnalyticsDashboardServiceExceptionsTest.php`

**Interfaces:**
- Consumes: `CoordinatorExceptionResolver::resolve(Collection $trainings, Collection $liveClasses, Collection $participants, array $cpdData): array` (Task 1)
- Produces: `MentorAnalyticsDashboardService::build()`'s returned array gains an `'exceptions'` key, consumed by Task 3 (`AnalyticsDashboardController@index`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Services/MentorAnalyticsDashboardServiceExceptionsTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\ClassModule;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorAnalyticsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorAnalyticsDashboardServiceExceptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_result_includes_exceptions_key_alongside_existing_keys(): void
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
        ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);

        $this->assertArrayHasKey('kpis', $result);
        $this->assertArrayHasKey('matrix', $result);
        $this->assertArrayHasKey('chartData', $result);
        $this->assertArrayHasKey('insights', $result);
        $this->assertArrayHasKey('exceptions', $result);
        $this->assertIsArray($result['exceptions']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceExceptionsTest.php`
Expected: FAIL — `exceptions` key missing from the returned array.

- [ ] **Step 3: Wire the resolver**

In `app/Services/MentorAnalyticsDashboardService.php`, the `build()` method currently ends with:

```php
        $kpis      = $this->buildKpis($mentors, $allClasses, $liveClasses, $participants, $cpdData);
        $chartData = $this->buildChartData($matrix, $allClasses, $mentors, $trainings, $cpdData);
        $insights  = $this->buildInsights($kpis, $matrix);

        return compact('kpis', 'matrix', 'chartData', 'insights');
    }
```

Replace with:

```php
        $kpis      = $this->buildKpis($mentors, $allClasses, $liveClasses, $participants, $cpdData);
        $chartData = $this->buildChartData($matrix, $allClasses, $mentors, $trainings, $cpdData);
        $insights  = $this->buildInsights($kpis, $matrix);
        $exceptions = app(CoordinatorExceptionResolver::class)->resolve($trainings, $liveClasses, $participants, $cpdData);

        return compact('kpis', 'matrix', 'chartData', 'insights', 'exceptions');
    }
```

(`CoordinatorExceptionResolver` is in the same `App\Services` namespace, so no new `use` import is needed.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceExceptionsTest.php`
Expected: PASS

- [ ] **Step 5: Run the full resolver + service test group to check for regressions**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceExceptionsTest.php tests/Unit/Services/CoordinatorExceptionResolverTest.php`
Expected: PASS, all tests green.

- [ ] **Step 6: Commit**

```bash
git add app/Services/MentorAnalyticsDashboardService.php tests/Unit/Services/MentorAnalyticsDashboardServiceExceptionsTest.php
git commit -m "feat: wire CoordinatorExceptionResolver into MentorAnalyticsDashboardService"
```

---

### Task 3: Exceptions card in the mentor-mode analytics view

**Files:**
- Modify: `app/Http/Controllers/AnalyticsDashboardController.php`
- Modify: `resources/views/analytics/dashboard/mentor-mode.blade.php`

**Interfaces:**
- Consumes: `$data['exceptions']` from `MentorAnalyticsDashboardService::build()` (Task 2), passed to the view as `$mentorExceptions`.

No automated test for this task — it's routed through a plain controller (not a Livewire component), so there's no equivalent to `Livewire::test()`'s `assertSee()` without a full HTTP feature test hitting a real authenticated session and real geographic/role fixtures matching this controller's auto-scoping logic. Given the view logic here is a straightforward conditional render of already-tested data (Task 1/2 cover the data correctness), this task is verified by the manual browser check in Step 3 instead.

- [ ] **Step 1: Pass the data through the controller**

In `app/Http/Controllers/AnalyticsDashboardController.php`, inside the `if ($mode === 'mentor')` block, find:

```php
            $data           = app(MentorAnalyticsDashboardService::class)->build(auth()->user(), $mentorFilters);
            $mentorKpis     = $data['kpis'];
            $mentorMatrix   = $data['matrix'];
            $mentorCharts   = $data['chartData'];
            $mentorInsights = $data['insights'];
```

Replace with:

```php
            $data            = app(MentorAnalyticsDashboardService::class)->build(auth()->user(), $mentorFilters);
            $mentorKpis      = $data['kpis'];
            $mentorMatrix    = $data['matrix'];
            $mentorCharts    = $data['chartData'];
            $mentorInsights  = $data['insights'];
            $mentorExceptions = $data['exceptions'];
```

Find the `return view('analytics.dashboard.index', compact(...))` call in the same `if` block:

```php
            return view('analytics.dashboard.index', compact(
                'mode', 'selectedYear', 'availableYears',
                'mentorKpis', 'mentorMatrix', 'mentorCharts', 'mentorInsights',
                'mentorFilters', 'mentorPrograms', 'mentorCounties', 'mentorSubcounties',
                'mentorFacilities', 'mentorCadres', 'mentorDepartments', 'mentorUsers'
            ));
```

Replace with:

```php
            return view('analytics.dashboard.index', compact(
                'mode', 'selectedYear', 'availableYears',
                'mentorKpis', 'mentorMatrix', 'mentorCharts', 'mentorInsights', 'mentorExceptions',
                'mentorFilters', 'mentorPrograms', 'mentorCounties', 'mentorSubcounties',
                'mentorFacilities', 'mentorCadres', 'mentorDepartments', 'mentorUsers'
            ));
```

- [ ] **Step 2: Add the exceptions card to the Blade view**

In `resources/views/analytics/dashboard/mentor-mode.blade.php`, find the boundary between the filter form and the KPI strip:

```blade
    </div>
</form>

{{-- ── KPI STRIP ────────────────────────────────────────────────────────────── --}}
<div class="kpi-strip-wrap mentor-kpi-strip" data-aos="fade-up">
```

Replace with:

```blade
    </div>
</form>

{{-- ── EXCEPTIONS ───────────────────────────────────────────────────────────── --}}
@if(!empty($mentorExceptions))
<div class="dash-section" data-aos="fade-up" style="margin-bottom:1.25rem;background:#fff;border:1px solid var(--gray-200);border-radius:12px;overflow:hidden;">
    <div style="padding:.9rem 1.1rem;border-bottom:1px solid var(--gray-100);">
        <h6 style="margin:0;font-weight:700;color:var(--gray-800);font-size:.92rem;">
            <i class="fas fa-triangle-exclamation" style="color:#F59E0B;margin-right:.4rem;"></i>
            {{ count($mentorExceptions) }} exception{{ count($mentorExceptions) !== 1 ? 's' : '' }} need attention
        </h6>
    </div>
    @foreach($mentorExceptions as $item)
        @php
            $tierColor = match($item['tier']) {
                1 => '#EF4444',
                2 => '#F59E0B',
                default => '#3B82F6',
            };
        @endphp
        <div style="padding:.75rem 1.1rem;{{ !$loop->last ? 'border-bottom:1px solid var(--gray-100);' : '' }}display:flex;align-items:center;justify-content:space-between;gap:1rem;">
            <div style="display:flex;align-items:center;gap:.65rem;min-width:0;">
                <span style="width:8px;height:8px;border-radius:50%;background:{{ $tierColor }};flex-shrink:0;"></span>
                <div style="min-width:0;">
                    <div style="font-size:.85rem;font-weight:700;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item['headline'] }}</div>
                    <div style="font-size:.76rem;color:var(--gray-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item['subtext'] }}</div>
                </div>
            </div>
            <a href="{{ $item['url'] }}" style="flex-shrink:0;padding:.4rem .9rem;border-radius:8px;background:{{ $tierColor }};color:#fff;font-size:.78rem;font-weight:700;text-decoration:none;">
                {{ $item['label'] }}
            </a>
        </div>
    @endforeach
</div>
@else
<div class="dash-section" data-aos="fade-up" style="margin-bottom:1.25rem;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;padding:.9rem 1.1rem;">
    <span style="font-size:.85rem;font-weight:700;color:#166534;">No exceptions</span>
    <span style="font-size:.82rem;color:#15803D;margin-left:.4rem;">Every facility and mentor in view is healthy.</span>
</div>
@endif

{{-- ── KPI STRIP ────────────────────────────────────────────────────────────── --}}
<div class="kpi-strip-wrap mentor-kpi-strip" data-aos="fade-up">
```

- [ ] **Step 3: Manually verify in browser**

Log in as a coordinator-tier user (or `super_admin`, which sees all scope) with a mix of healthy and flagged facilities/mentors and confirm: the exceptions card renders above the KPI strip with correctly ranked/colored items; each item's link correctly filters the same page by `facility_id` or `mentor_id`; the "No exceptions" empty state renders when nothing qualifies; the existing filter bar, KPI strip, insights row, leaderboards, and matrix table are all unchanged and still fully visible (not collapsed, per the Global Constraints deviation); charts still render correctly (they were never touched, so this should be a non-issue, but confirm regardless since this task modifies the same Blade file).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AnalyticsDashboardController.php resources/views/analytics/dashboard/mentor-mode.blade.php
git commit -m "feat: add facility/mentor exceptions card to coordinator analytics view"
```

---

## Self-Review Notes

- **Spec coverage:** Section 3 (resolver + all 3 tiers) → Task 1. Section 4 (integration) → Task 2. Section 5 (view layer) → Task 3, with an explicit, disclosed deviation (no collapsing of chart-bearing sections, to avoid a real Chart.js-in-hidden-container rendering bug). Section 6 (edge cases: divide-by-zero guards, mentor-with-no-live-classes exclusion, null facility/mentor defensiveness) → covered by Task 1's tests and the `?->` null-safe access used throughout the resolver. Section 7 (testing) → each listed scenario has a corresponding test in Task 1 or Task 2.
- **Placeholder scan:** no TBD/TODO markers; every step has real, complete code. Task 3 explicitly documents why it has no automated test rather than silently omitting one.
- **Type consistency:** the exception item shape (`tier`, `label`, `headline`, `subtext`, `url`, `meta`) is identical across Task 1's resolver, Task 2's integration test assertions, and Task 3's Blade consumption. `CoordinatorExceptionResolver::resolve()`'s four-parameter signature matches between its definition (Task 1) and its call site (Task 2).
