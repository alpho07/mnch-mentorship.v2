# Mentor Dashboard Exceptions "See More" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cap the inline Exceptions list at 5 items and add a "See more" Bootstrap modal showing all exceptions with tier-specific detail columns, per `docs/superpowers/specs/2026-08-07-exceptions-see-more-design.md`.

**Architecture:** Pure template change to `resources/views/analytics/dashboard/mentor-mode.blade.php` — no service/backend changes, `$mentorExceptions` already contains everything needed. Uses Bootstrap 5's native modal (`data-bs-toggle="modal"`), matching the `data-bs-toggle="collapse"` pattern already used elsewhere on this page.

**Tech Stack:** Blade, Bootstrap 5 (already loaded on this page — no new JS/CSS dependency).

## Global Constraints

- Do not modify `CoordinatorExceptionResolver` — its tier logic, thresholds, and `meta` shape are unchanged.
- The inline list's existing row markup must stay pixel-identical for the first 5 items — only the iteration bound changes (`array_slice(..., 0, 5)` instead of the full array).
- The "See more" button/modal must only render when there are more than 5 exceptions — no empty modal trigger when everything fits inline.

---

### Task 1: Cap the inline list and add the "See more" modal

**Files:**
- Modify: `resources/views/analytics/dashboard/mentor-mode.blade.php` (the Exceptions section, lines 128-164)
- Test: `tests/Feature/MentorDashboardExceptionsSeeMoreTest.php`

**Interfaces:**
- Consumes: `$mentorExceptions` (array of `['tier' => int, 'label' => string, 'headline' => string, 'subtext' => string, 'url' => string, 'meta' => array]` items — confirmed shape from reading `CoordinatorExceptionResolver.php` in full: tier 1 `meta` has `completion_pct`/`attendance_pct`, tier 2 has `days_inactive`, tier 3 has `classes_led`).
- Produces: nothing consumed elsewhere — terminal change.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

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

class MentorDashboardExceptionsSeeMoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Renders the mentor-mode view directly with real service data, bypassing
     * AnalyticsDashboardController@index (pre-existing MySQL-only YEAR() gap
     * unrelated to this change — see docs/PHASE1-DISCOVERY-BASELINE.md §9.12),
     * matching the pattern already established in
     * MentorAnalyticsDashboardRenderSmokeTest.
     */
    private function renderMentorMode(array $data): string
    {
        return view('analytics.dashboard.index', [
            'mode' => 'mentor',
            'selectedYear' => null,
            'availableYears' => [],
            'mentorKpis' => $data['kpis'],
            'mentorMatrix' => $data['matrix'],
            'mentorCharts' => $data['chartData'],
            'mentorInsights' => $data['insights'],
            'mentorExceptions' => $data['exceptions'],
            'mentorFilters' => [],
            'mentorPrograms' => collect(),
            'mentorCounties' => collect(),
            'mentorSubcounties' => collect(),
            'mentorFacilities' => collect(),
            'mentorCadres' => collect(),
            'mentorDepartments' => collect(),
            'mentorUsers' => collect(),
        ])->render();
    }

    public function test_more_than_5_exceptions_shows_only_5_inline_plus_a_see_more_button(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $viewer->assignRole('super_admin');
        $this->actingAs($viewer);

        $program = Program::factory()->create(['name' => 'Newborn Care']);

        // 6 mentors, each leading one class with one incomplete module —
        // each becomes a tier-3 "zero CPD" exception (cheapest tier to
        // trigger: no time-based inactivity logic, no facility aggregation).
        for ($i = 0; $i < 6; $i++) {
            $mentor = User::factory()->create();
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
        }

        $data = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertCount(6, $data['exceptions'], 'Fixture sanity check: expected exactly 6 tier-3 exceptions.');

        $html = $this->renderMentorMode($data);

        $this->assertStringContainsString('See all 6 exceptions', $html);
        $this->assertStringContainsString('id="exceptionsModal"', $html);

        // All 6 mentors have identical sort_ts (tier 3's sort key is
        // -classes_led, and every fixture mentor here leads exactly 1 class),
        // so PHP 8's stable sort preserves insertion order — the 6th mentor
        // created is guaranteed to fall outside array_slice(0, 5) and can
        // only appear via the modal's full table.
        $sixthMentorHeadline = $data['exceptions'][5]['headline'];
        $this->assertStringContainsString(e($sixthMentorHeadline), $html);
    }

    public function test_5_or_fewer_exceptions_does_not_show_the_see_more_button(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $viewer->assignRole('super_admin');
        $this->actingAs($viewer);

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $mentor = User::factory()->create();
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

        $data = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertCount(1, $data['exceptions']);

        $html = $this->renderMentorMode($data);

        $this->assertStringNotContainsString('See all', $html);
        $this->assertStringContainsString('0 CPD points', $html);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/MentorDashboardExceptionsSeeMoreTest.php`
Expected: FAIL on the first test — today's template renders all 6 items inline with no "See all" button and no `#exceptionsModal`.

- [ ] **Step 3: Update the Blade template**

Replace the entire Exceptions section in `resources/views/analytics/dashboard/mentor-mode.blade.php` (currently lines 128-164 — the `{{-- ── EXCEPTIONS ── --}}` comment through the closing `@endif`) with:

```blade
{{-- ── EXCEPTIONS ───────────────────────────────────────────────────────────── --}}
@if(!empty($mentorExceptions))
<div class="dash-section" data-aos="fade-up" style="margin-bottom:1.25rem;background:#fff;border:1px solid var(--gray-200);border-radius:12px;overflow:hidden;">
    <div style="padding:.9rem 1.1rem;border-bottom:1px solid var(--gray-100);">
        <h6 style="margin:0;font-weight:700;color:var(--gray-800);font-size:.92rem;">
            <i class="fas fa-triangle-exclamation" style="color:#F59E0B;margin-right:.4rem;"></i>
            {{ count($mentorExceptions) }} exception{{ count($mentorExceptions) !== 1 ? 's' : '' }} need attention
        </h6>
    </div>
    @foreach(array_slice($mentorExceptions, 0, 5) as $item)
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
    @if(count($mentorExceptions) > 5)
        <div style="padding:.75rem 1.1rem;text-align:center;border-top:1px solid var(--gray-100);">
            <button type="button" class="btn btn-link" style="font-size:.82rem;font-weight:700;text-decoration:none;" data-bs-toggle="modal" data-bs-target="#exceptionsModal">
                See all {{ count($mentorExceptions) }} exceptions →
            </button>
        </div>
    @endif
</div>

{{-- ── EXCEPTIONS MODAL (all items) ─────────────────────────────────────────── --}}
<div class="modal fade" id="exceptionsModal" tabindex="-1" aria-labelledby="exceptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exceptionsModalLabel">All {{ count($mentorExceptions) }} Exceptions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Tier</th>
                            <th>Item</th>
                            <th>Detail</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mentorExceptions as $item)
                            @php
                                $tierColor = match($item['tier']) {
                                    1 => '#EF4444',
                                    2 => '#F59E0B',
                                    default => '#3B82F6',
                                };
                                $detail = match($item['tier']) {
                                    1 => ($item['meta']['completion_pct'] ?? '—') . '% completion, ' . ($item['meta']['attendance_pct'] ?? '—') . '% attendance',
                                    2 => ($item['meta']['days_inactive'] ?? '—') . ' days inactive',
                                    3 => ($item['meta']['classes_led'] ?? '—') . ' class(es) led, 0 CPD',
                                    default => '—',
                                };
                            @endphp
                            <tr>
                                <td><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $tierColor }};"></span></td>
                                <td>
                                    <div style="font-size:.85rem;font-weight:700;color:var(--gray-800);">{{ $item['headline'] }}</div>
                                    <div style="font-size:.76rem;color:var(--gray-500);">{{ $item['subtext'] }}</div>
                                </td>
                                <td style="font-size:.82rem;color:var(--gray-600);">{{ $detail }}</td>
                                <td>
                                    <a href="{{ $item['url'] }}" style="padding:.35rem .8rem;border-radius:8px;background:{{ $tierColor }};color:#fff;font-size:.76rem;font-weight:700;text-decoration:none;white-space:nowrap;">
                                        {{ $item['label'] }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@else
<div class="dash-section" data-aos="fade-up" style="margin-bottom:1.25rem;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;padding:.9rem 1.1rem;">
    <span style="font-size:.85rem;font-weight:700;color:#166534;">No exceptions</span>
    <span style="font-size:.82rem;color:#15803D;margin-left:.4rem;">Every facility and mentor in view is healthy.</span>
</div>
@endif
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/MentorDashboardExceptionsSeeMoreTest.php`
Expected: PASS on both.

- [ ] **Step 5: Run the other MentorAnalyticsDashboardService-related tests to confirm no regression**

Run: `php artisan test tests/Unit/Services/MentorAnalyticsDashboardServiceExceptionsTest.php tests/Unit/Services/MentorAnalyticsDashboardServiceCpdTest.php tests/Unit/Services/MentorAnalyticsDashboardServiceGapKpisTest.php tests/Feature/MentorAnalyticsDashboardRenderSmokeTest.php`
Expected: PASS (unchanged).

- [ ] **Step 6: Commit**

```bash
git add resources/views/analytics/dashboard/mentor-mode.blade.php tests/Feature/MentorDashboardExceptionsSeeMoreTest.php
git commit -m "feat: cap mentor dashboard exceptions at 5 inline, add See More modal with full table"
```

---

## Self-Review

**Spec coverage:** Task 1 covers every element of the spec — 5-item cap, conditional "See more" button, Bootstrap modal, full table with tier-specific Detail column via the exact `match()` expressions the spec specified.

**Placeholder scan:** No TBD/TODO. Full replacement Blade code provided verbatim (not "similar to the existing block" — the existing block is quoted and extended in full), verified against the actual current file content read directly.

**Type consistency:** `$item['meta']` keys (`completion_pct`/`attendance_pct`/`days_inactive`/`classes_led`) match exactly what `CoordinatorExceptionResolver.php`'s three tier-building methods actually set (confirmed by reading the file in full during design). Test fixture uses tier 3 (zero-CPD mentors) as the cheapest way to generate 6 exceptions without needing to fabricate 14-day-old activity timestamps or facility-level aggregation — verified `tier3ZeroCpdMentors()`'s logic doesn't skip a mentor with `cpd['total'] === 0` (the `> 0` check only excludes mentors who *have* points).
