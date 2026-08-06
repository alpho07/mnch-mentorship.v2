# Mentor Dashboard Exceptions — "See More" Design Spec

**Status:** Approved, ready for implementation plan
**Phase:** Production-Safe System Audit, Phase 4 (Dashboard Design), continuing the Mentorship Dashboard work

## Problem

`resources/views/analytics/dashboard/mentor-mode.blade.php`'s Exceptions section (lines 128-164) renders every item in `$mentorExceptions` unconditionally, with no cap. `CoordinatorExceptionResolver::resolve()` (the source of this array) has no limit either — on a coordinator view with many facilities/mentors, this section could grow very long, and each item's `meta` array (tier-specific numbers: `completion_pct`/`attendance_pct` for facilities, `days_inactive` for inactive mentors, `classes_led` for zero-CPD mentors) is computed but never displayed.

## Design

Purely a template change — `$mentorExceptions` (already fully computed, sorted by tier then urgency) needs no backend/service changes.

1. **Inline list**: show only the first 5 items (`array_slice($mentorExceptions, 0, 5)` or Blade's `->take(5)` if cast to a collection) — same row markup as today, unchanged.
2. **"See more" trigger**: if `count($mentorExceptions) > 5`, a button below the list reads "See all {N} exceptions →" and opens a Bootstrap 5 modal (`data-bs-toggle="modal" data-bs-target="#exceptionsModal"`) — this page already uses Bootstrap's `data-bs-*` attributes elsewhere (the filters collapse), so this matches the existing convention rather than introducing a new JS pattern.
3. **Modal**: a Bootstrap modal (`#exceptionsModal`) containing a table of **all** exceptions with columns:
   - Tier badge (colored per existing tier-color mapping: red/amber/blue)
   - Headline (same text as the inline list)
   - Detail — a new column rendering each tier's `meta` as readable text via a `match($item['tier'])` expression:
     - Tier 1: `"{completion_pct}% completion, {attendance_pct}% attendance"`
     - Tier 2: `"{days_inactive} days inactive"`
     - Tier 3: `"{classes_led} class(es) led, 0 CPD"`
   - Action (the existing button/link, unchanged)

## Testing

A Feature test rendering `analytics.dashboard.index` (mentor mode) directly with a fixture of >5 exceptions (matching the pattern already established in `MentorAnalyticsDashboardRenderSmokeTest`, which bypasses the controller's pre-existing SQLite/`YEAR()` gap) — assert only 5 rows appear in the inline list, the "See all" button appears with the correct count, and the modal's table contains all N rows including the tier-specific Detail text.

## Out of scope

- Any change to `CoordinatorExceptionResolver`'s tier logic, thresholds, or `meta` shape.
- Pagination/search within the modal table (deferred — only relevant if exception counts get very large; not addressed until it's a real problem).
