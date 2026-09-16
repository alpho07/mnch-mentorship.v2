# Mentorship Sections Redesign

**Date:** 2026-04-27
**Status:** Approved

## Problem

The homepage shows a single "Upcoming Mentorships" section that only displays mentorships with `start_date >= today`. This hides the 30+ currently active mentorships (started but not yet ended) and doesn't distinguish between programs that are running now, coming soon, or already finished.

## Goals

1. Split the single section into three — **Ongoing**, **Upcoming**, **Closed**
2. Auto-close mentorships in the database once their `end_date` passes
3. Show a human-readable duration on every card ("6 weeks", "2 months", etc.)

---

## Layout Decision

**Option A — Three stacked sections** (selected over tabbed and mixed-grid alternatives).

Sections render in order: Ongoing → Upcoming → Closed, each with a distinct background colour and header strip. Every section is conditional — it only renders when it has records. No interaction required; everything is visible at once.

---

## Data Queries

Homepage controller (`ResourceController@home`) passes three separate collections. All exclude `status = 'cancelled'`. Bucketing is **date-based** (not relying on the stored `status` column) so it works correctly on day 1 without a backfill and is resilient if the scheduler misses a night.

| Variable | Condition | Limit |
|---|---|---|
| `$ongoingMentorships` | `start_date ≤ now` AND `end_date ≥ now` | 6 |
| `$upcomingMentorships` | `start_date > now` | 4 |
| `$closedMentorships` | `end_date < now` AND `end_date ≥ now - 30 days` | 4 |

All three eager-load `county` and `facility`. The old `$upcomingMentorships` variable is replaced by the three above.

### Cache

Homepage data is cached for 5 minutes (`Cache::remember`). The cache key is already per-user/guest. No changes needed — new variables slot in alongside existing ones.

---

## Duration Helper

A `getDurationLabelAttribute` accessor on the `Training` model. Computes from `start_date` → `end_date` (returns `null` if either is missing).

| Days | Output |
|---|---|
| < 7 | "X days" |
| 7–13 | "1 week" |
| 14–27 | "X weeks" |
| 28–59 | "1 month" |
| 60–364 | "X months" |
| ≥ 365 | "X years" |

Used in Blade as `$mentorship->duration_label`.

---

## Auto-Close Command

**Class:** `app/Console/Commands/AutoCloseMentorships.php`
**Signature:** `mentorships:auto-close`

Logic:
1. Find all `Training` records where `type = 'facility_mentorship'`, `end_date < today`, and `status NOT IN ('completed', 'cancelled')`.
2. Bulk-update `status = 'completed'`.
3. Log the count: `"Auto-closed {$count} mentorships."`.

**Schedule:** registered in `routes/console.php` as `->daily()` (runs at midnight Africa/Nairobi).

Scoped to `facility_mentorship` only.

---

## Homepage Blade Changes

File: `resources/views/frontend/home.blade.php`

Replace the single `@if(isset($upcomingMentorships) ...)` block with three consecutive sections:

### Ongoing section
- Background: teal gradient (`#E0F7FA → #F0FDFF`)
- Header badge: pulsing green dot + "Live Now"
- Card accent: teal border-top bar
- Card shows: title, facility/county, `● ONGOING` badge, duration chip, "ends [date]"

### Upcoming section
- Background: light green (`#F0FDF4 → #DCFCE7`)
- Header badge: calendar icon
- Card accent: green border-top bar
- Card shows: title, facility/county, `UPCOMING` badge, duration chip, "starts [date]"

### Closed section
- Background: grey (`#F9FAFB → #F3F4F6`)
- Header: muted, "Recently Closed"
- Cards: reduced opacity (0.75), greyscale badge
- Card shows: title, facility/county, `CLOSED` badge, duration chip, "ended [date]"

Each section has a count in the header strip (e.g. "Ongoing · 24").

---

## Files Changed

| File | Change |
|---|---|
| `app/Http/Controllers/Frontend/ResourceController.php` | Replace single mentorship query with three date-bucketed queries |
| `app/Models/Training.php` | Add `getDurationLabelAttribute` accessor |
| `app/Console/Commands/AutoCloseMentorships.php` | New command |
| `routes/console.php` | Register command on daily schedule |
| `resources/views/frontend/home.blade.php` | Replace single section with three sections |

---

## Out of Scope

- Pagination or "View all" links for any section (not requested)
- Changes to the admin Filament resources
- Changes to global training display (training section is separate)
