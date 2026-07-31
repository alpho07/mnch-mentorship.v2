# Coordinator Journey Phase 3 — Facility & Mentor Exception List

> Date: 2026-07-31
> Project: MNCH Mentorship Platform
> Scope: Phase 3 of the design overhaul described in `mnch_mentorship_agentic_development_guide.txt` — the coordinator-facing mentor-analytics view only.

---

## 1. Goal

Rebuild the coordinator's mentor-analytics view around the guide's core principle for coordinators (§7.3, §9, §10): **"which facilities are falling behind, which mentors are inactive" — exceptions first, not totals.** Today's view leads with aggregate KPIs and CPD leaderboards; its one exception-shaped signal (`buildInsights()`'s "X mentors have 0 CPD points") is a count with no drill-down, identical to the gap Phase 2 closed for mentors — except here it needs to be rolled up to mentor/facility granularity, not per-mentee.

---

## 2. Context — what already exists, and what's genuinely dead

- `AnalyticsDashboardController@index` (route `/analytics/dashboard?mode=mentor`) is the **actively-used** coordinator view — it has real role-based geographic scoping for `county_mentor_lead`/`subcounty_mentor_lead` (auto-fills county/subcounty filters from the viewer's assignment) and is backed by `MentorAnalyticsDashboardService::build()`.
- `CoverageDashboard`, `CoverageOverview`, and `TrainingCoverageDashboard` (Filament pages) all have `shouldRegisterNavigation() => false` — confirmed dead/unreachable code. Left untouched per explicit decision; not in scope for this phase.
- `MentorAnalyticsDashboardService::build()` already loads everything this phase needs: `$trainings` (with `mentor`, `facility.subcounty.county`, `program` eager-loaded), `$liveClasses` (active classes only, with `classModules.programModule`), `$participants` (enrolled/active/completed in live classes), and `$cpdData` (keyed by mentor ID, `['total' => int, 'level' => array, 'completed_modules' => int]`). This phase's resolver consumes these directly rather than re-querying the coordinator's scope from scratch.
- `buildInsights()` already computes a zero-CPD mentor **count** (`$zeroCpd` in the method) — this phase replaces that with a real per-mentor list from the new resolver; the other two informational blurbs in `buildInsights()` (no-active-classes notice, the "X live classes across Y mentors" summary) stay as-is since they're context, not actionable exceptions.

---

## 3. `CoordinatorExceptionResolver`

New service, `app/Services/CoordinatorExceptionResolver.php`.

### Signature
```php
CoordinatorExceptionResolver::resolve(
    Collection $trainings,    // Training models, with mentor/facility/program eager-loaded
    Collection $liveClasses,  // MentorshipClass models, active only, with classModules.programModule
    Collection $participants, // ClassParticipant models, enrolled in live classes
    array $cpdData            // keyed by mentor_id => ['total' => int, ...], from MentorAnalyticsDashboardService
): array
```
Returns a ranked list of items shaped identically to prior phases: `tier`, `label`, `headline`, `subtext`, `url`, `meta`.

### Tiers

1. **Facility falling behind** — group `$liveClasses` by facility (via each class's `training_id` → `$trainings` → `facility_id`). For each facility with at least one live class: compute average module-completion % across its classes (`classModules` completed / total) and confirmed-attendance rate (`ClassAttendance` count / enrolled participants, queried fresh — not pre-loaded on `$liveClasses`). Flag if completion < 40% or attendance < 60%.
2. **Inactive mentor** — for each mentor in `$trainings` with at least one live class: compute the latest of three signals scoped to that mentor's mentees/classes — `ClassAttendance.marked_at` where `marked_by` = the mentor, `MenteeModuleProgress.video_reviewed_at`, and `MenteeModuleProgress.recommendation_written_at`. If the latest such timestamp (or the mentor has none at all) is 14+ days in the past, flag as inactive. A mentor with zero live classes is excluded entirely — nothing to be inactive on.
3. **Zero-CPD mentor** — any mentor in `$cpdData` with `total === 0`, using `$trainings` to derive how many classes they lead for the display text.

Dedup: a mentor qualifying for both Tier 2 and Tier 3 appears once, at Tier 2 (facilities are a separate dimension from mentors, so a facility item and a mentor item for people at that facility can coexist — dedup is per-mentor and per-facility independently, not cross-dimension).

Sort: tier ascending; within Tier 1, lowest completion % first; within Tier 2, longest-inactive first; within Tier 3, most classes led first (a mentor leading several classes with zero CPD is a bigger concern than one leading a single class).

---

## 4. Integration into `MentorAnalyticsDashboardService`

`build()` gains one additional call after `$insights` is computed:
```php
$exceptions = app(CoordinatorExceptionResolver::class)->resolve($trainings, $liveClasses, $participants, $cpdData);

return compact('kpis', 'matrix', 'chartData', 'insights', 'exceptions');
```
No existing return keys change shape or are removed.

---

## 5. View layer

`resources/views/analytics/dashboard/index.blade.php` (mentor mode section) gets:
1. A new exceptions card — same visual pattern as the Phase 1/2 queue cards — placed above the existing KPI/leaderboard/chart content, driven by `$mentorExceptions` (passed from `AnalyticsDashboardController@index` alongside the existing `$mentorKpis`/`$mentorMatrix`/`$mentorCharts`/`$mentorInsights`).
2. The existing KPI strip, charts, insights row, and matrix table wrapped in a collapsed `<details>` section below it, matching the pattern from Phases 1 and 2.
3. Empty state ("No exceptions — every facility and mentor in view is healthy") when the resolver returns nothing.

`AnalyticsDashboardController@index`'s mentor-mode branch gains one line passing `$data['exceptions']` through to the view as `$mentorExceptions`.

---

## 6. Business rules / edge cases

- A coordinator with zero trainings in scope (`$trainings` empty) — the resolver returns an empty array immediately; the view's existing "no mentors found" insight already covers this case, no separate handling needed.
- Facility rollup must not divide by zero — a facility is only considered if it has at least one live class with at least one enrolled participant (module-completion) or at least one live class (attendance denominator uses enrolled count, already guarded the same way Phase 2 guarded `$enrolled === 0`).
- Inactive-mentor detection only runs for mentors with ≥1 live class — consistent with Phase 2's rule that a mentor between classes isn't "inactive."
- The resolver must not throw for a training with a null `facility_id` or null `mentor_id` — both are already filtered out upstream in `MentorAnalyticsDashboardService::build()` (`whereNotNull('mentor_id')`), but the resolver should not assume `facility` is always present on every training it's handed (defensive: skip a class's facility grouping if `facility_id` is null rather than erroring).

---

## 7. Testing

- Test: a facility with all live classes below 40% completion appears as a Tier 1 item; a facility at or above 40% (and attendance ≥ 60%) does not.
- Test: a mentor with a live class and no attendance/video-review/recommendation activity in 15+ days appears as a Tier 2 item.
- Test: a mentor with recent activity (e.g. an attendance mark 3 days ago) does not appear as Tier 2.
- Test: a mentor with zero live classes is never flagged Tier 2, regardless of how old any of their historical (non-live-class) activity is.
- Test: a mentor with `cpdData['total'] === 0` appears as a Tier 3 item; a mentor with CPD > 0 does not.
- Test: a mentor qualifying for both Tier 2 and Tier 3 appears once, at Tier 2.
- Test: empty `$trainings` collection returns an empty array, no error.
- Test: `MentorAnalyticsDashboardService::build()`'s returned array includes an `exceptions` key without altering `kpis`/`matrix`/`chartData`/`insights`.

---

## 8. Acceptance criteria

- [ ] `CoordinatorExceptionResolver` returns correctly tiered, correctly deduplicated items across all three tiers, verified by tests.
- [ ] `MentorAnalyticsDashboardService::build()` exposes the new `exceptions` key without regressing any existing return data.
- [ ] The mentor-mode analytics view renders an exceptions card above the fold; existing KPIs/charts/matrix are preserved but collapsed below it.
- [ ] The zero-CPD-mentor count in `buildInsights()` is now backed by a real, clickable per-mentor list via the exceptions card.
- [ ] No regressions to existing dashboard data or the existing role-based geographic auto-scoping in `AnalyticsDashboardController@index`.

---

## 9. Recommended implementation order

1. `CoordinatorExceptionResolver` service + tests (no integration yet — safe to land first).
2. Wire the resolver into `MentorAnalyticsDashboardService::build()`, add the `exceptions` key.
3. Pass `$mentorExceptions` through `AnalyticsDashboardController@index`; add the exceptions card + collapsed sections to the Blade view.
4. Manual browser verification (coordinator view with a mix of healthy and flagged facilities/mentors, confirming the auto-scoping for `county_mentor_lead`/`subcounty_mentor_lead` still works correctly alongside the new card).
