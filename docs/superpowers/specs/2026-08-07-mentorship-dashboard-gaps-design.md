# Mentorship Dashboard Gap-Closing Widgets — Design Spec

**Status:** Approved, ready for implementation plan
**Phase:** Production-Safe System Audit, Phase 4 (Dashboard Design)
**Related:** `docs/MENTORSHIP-DASHBOARD-KPI-CATALOGUE.md` (the discovery pass this design closes gaps from)

## Problem

The KPI catalogue found 5 genuine gaps in `MentorAnalyticsDashboardService` (the coordinator-facing cross-mentor analytics dashboard, `resources/views/analytics/dashboard/mentor-mode.blade.php`), all buildable from data that already exists with zero new migrations, plus one correctness bug in the same file.

## Design

All six changes land in `app/Services/MentorAnalyticsDashboardService.php` and its consuming Blade partial — additive to the existing `buildKpis()` method and KPI strip, not a new page. Existing KPIs/tiles are untouched.

### 1. Fix the CPD calculation bug (correctness, not a new KPI)

`build()` (lines 69-83) currently re-derives mentor CPD inline, counting completed `ClassModule`s across classes of **any** status. `CpdPointsService::forMentor()`/`batchForMentors()` — used everywhere else CPD is shown — requires the class itself to be `status='completed'` too. Replace the entire inline block with:

```php
$cpdData = app(CpdPointsService::class)->batchForMentors($mentorIds);
```

`batchForMentors()` already returns the exact same shape (`['total', 'level', 'completed_modules']`) the rest of the file consumes — this is a same-shape drop-in replacement, not a refactor of downstream code.

### 2. Mentor-to-mentee ratio (KPI #14)

New line in `buildKpis()`:

```php
'mentor_to_mentee_ratio' => $mentors->count() > 0
    ? round($participants->pluck('user_id')->unique()->count() / $mentors->count(), 1)
    : 0,
```

Both operands are numbers `buildKpis()` already computes separately (`total_mentors`, `total_mentees`) — this just also returns their ratio.

### 3. Assessment scores (KPI #6)

Sourced from `mentee_module_progress.assessment_score` / `assessment_status`, scoped to the same `$participants` collection already loaded in `build()` (their `class_participant_id`s):

```php
$participantIds = $participants->pluck('id');
$assessmentStats = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
    ->whereNotNull('assessment_score')
    ->selectRaw('AVG(assessment_score) as avg_score, SUM(assessment_status = "passed") as passed, COUNT(*) as total')
    ->first();

'avg_assessment_score' => $assessmentStats && $assessmentStats->total > 0 ? round($assessmentStats->avg_score, 1) : null,
'assessment_pass_rate' => $assessmentStats && $assessmentStats->total > 0 ? round(($assessmentStats->passed / $assessmentStats->total) * 100, 1) : null,
```

`null` (not `0`) when there's no assessment data yet in scope — the Blade tile shows "No data yet" rather than a misleading 0%, since 0% would imply everyone failed rather than nobody having been assessed.

### 4. Practical skills / rubric scores (KPI #8)

Sourced from `rubric_assessments`, scoped to `class_module_id`s belonging to the live classes already loaded (`$liveClasses`):

```php
$liveClassModuleIds = $liveClasses->pluck('classModules')->flatten()->pluck('id');
$rubricStats = RubricAssessment::whereIn('class_module_id', $liveClassModuleIds)
    ->selectRaw('AVG(score) as avg_score, SUM(passed) as passed, COUNT(*) as total')
    ->first();

'avg_rubric_score' => $rubricStats && $rubricStats->total > 0 ? round($rubricStats->avg_score, 1) : null,
'rubric_pass_rate' => $rubricStats && $rubricStats->total > 0 ? round(($rubricStats->passed / $rubricStats->total) * 100, 1) : null,
```

`avg_rubric_score` is the raw point average (not a %, since rubrics have different `total_marks` per rubric — a cross-rubric average of raw scores isn't strictly comparable, but is still a directionally useful "how are mentees doing on practical assessments" signal; a true normalized %-average would need a per-row `scorePercentage()` call, deferred as unnecessary complexity for a first pass).

### 5. Inactive participants aggregate (KPI #15)

Two counts, both cheap given data already loaded/computable in `build()`:

```php
'inactive_mentors' => collect($exceptions)->where('tier', 2)->count(), // reuses CoordinatorExceptionResolver's already-computed tier-2 (inactive mentor) list
'dropped_mentees'  => ClassParticipant::whereIn('mentorship_class_id', $liveClassIds)->where('status', 'dropped')->count(),
```

`inactive_mentors` reuses `$exceptions` (already computed by `CoordinatorExceptionResolver::resolve()` earlier in `build()`) rather than re-deriving the 14-day-inactivity logic a second time — confirmed each item carries a literal `'tier' => 2` for inactive-mentor entries (`app/Services/CoordinatorExceptionResolver.php:133`), matching the `where('tier', 2)` above exactly.

`dropped_mentees` is a new, small query — `$participants` (loaded earlier in `build()`) explicitly excludes `status='dropped'` (`whereIn('status', ['enrolled', 'active', 'completed'])`), so dropped mentees need a separate count rather than reuse.

## Blade changes

`resources/views/analytics/dashboard/mentor-mode.blade.php` — append new `<div class="kpi-card">` blocks after the existing 7 (lines ~169-201), following the exact same markup pattern (icon + `counter-animate` value + label), for: mentor-to-mentee ratio, avg assessment score (+ pass rate as a `kpi-trend`-style sub-line), avg rubric score (+ pass rate), inactive mentors, dropped mentees. When a stat is `null` (no data yet), render "No data yet" instead of `0` in the value slot — this is the one behavioral branch new to this Blade file, everything else follows the established tile pattern exactly.

## Testing

- Feature/Unit test on `MentorAnalyticsDashboardService::build()`: seed mentees with known `assessment_score`/`assessment_status` and `rubric_assessments` rows, assert the new KPI values compute correctly (including the `null`-when-empty case).
- Regression test locking in the CPD bug fix: build a scenario where the old inline calc and `CpdPointsService::forMentor()` would have disagreed (a completed module in a non-completed class) and assert the dashboard's CPD number now matches `CpdPointsService`'s.
- Test `mentor_to_mentee_ratio`, `inactive_mentors`, `dropped_mentees` with known fixture data.

## Out of scope (explicitly deferred, per the KPI catalogue)

- True coverage % (#1) — check `CoverageDashboard.php` first, may already exist.
- Competency progress (#7) and follow-up sessions (#13) — both need new data modeling, not just a widget.
- Learning-gaps heatmap (#10) and overdue-activity due-date tracking (#11) — both depend on this round's items (assessment/rubric scores) being live first, and need their own design pass.
- Normalizing `avg_rubric_score` to a true cross-rubric percentage (noted above as a simplification).
