# Mentor Journey Phase 2 — Ranked Priority Queue & Drill-Down Insights

> Date: 2026-07-31
> Project: MNCH Mentorship Platform
> Scope: Phase 2 of the design overhaul described in `mnch_mentorship_agentic_development_guide.txt` — the mentor-facing dashboard only.

---

## 1. Goal

Rebuild the mentor dashboard around the guide's core principle for mentors (§7.2, §9, §10): **"which mentees need support, in what order" — not a wall of aggregate stats.** Today `MentorDashboard` (`app/Filament/Pages/MentorDashboard.php`, 488 lines) computes rich KPIs and two `insights` fields (`mentees_needing_attention`, `low_attendance_classes`) that are **counts with no drill-down** — a mentor sees "3 mentees need attention" with no way to see who they are or act on it. Pending video reviews already have a per-item list (`EmoncReportingService::pendingVideoReviewItemsForUser()`), but it isn't merged or ranked against approvals or struggling/inactive mentees into one queue.

This phase closes three related gaps:
1. No ranked, cross-type priority queue ("do this first, then this").
2. Count-only insights (`mentees_needing_attention`, `low_attendance_classes`) with no underlying list to click into.
3. No "inactive mentee" concept exists anywhere — nothing tracks days-since-last-activity.

---

## 2. Context — what already exists, and what's EmONC-only

- `getMyTrainingIds()` already scopes to a mentor's own (or co-mentored) `facility_mentorship` trainings **across all three programs** — no program filter. This phase's resolver reuses that scoping so the queue naturally covers Newborn Care, Infant and Child Care, and EmONC alike.
- **Video review and the two-step Mentor Approve → Head DRMH Approve certification flow are EmONC-only in practice**, confirmed by inspecting the actual UI gating (`ManageClassMentees.php`, `ReviewModuleMentee.php` — both wrap mentor-approval actions in `isEmonc()`/`detectEmonc()` checks). For non-EmONC programs, completion is class-wide only (the "End Class" action in `ManageClassMentees.php` bulk-sets every participant's `status` to `completed`), and `mentor_approved_at` is never set for them. There is **no per-mentee "ready to finalize" concept for non-EmONC today**, and this phase does not invent one — that would be a certification-flow redesign, out of scope here. Confirmed with the project owner: Tiers 3-5 below already give non-EmONC mentors a real, useful queue without it.
- Aside, not in scope for this phase: `ClassReportController::verifyCertificate()` and `::badge()` require `isCertified()` (both approvals) even for non-EmONC participants, while the main certificate route only requires `status=completed` for them — an inconsistency that would 403 a non-EmONC mentee's QR/badge links. Noted for a future fix, untouched here.
- `ClassParticipant::hasCompletedAllModules()` already exists and is the authoritative check `markMentorApproved()` itself relies on (throws `DomainException` if not satisfied) — Tier 2 below reuses this exact method rather than re-deriving the same logic, so the queue never shows an item that would fail if acted on.
- `MenteeModuleProgress` has `updated_at` (touched by quiz attempts, video submission, status changes); `ClassAttendance` has `marked_at`. Between them there's enough signal to derive "last activity" without any new columns.

---

## 3. `MentorPriorityQueueResolver`

New service, `app/Services/MentorPriorityQueueResolver.php`. Unlike Phase 1's `MenteeNextActionResolver` (one winning action), this returns a **ranked list** — mentors juggle several mentees at once, matching the guide's own example ("4 mentees require your attention... in this order").

### Signature
`MentorPriorityQueueResolver::resolve(User $mentor): array` — returns a list of items, each shaped:
```php
[
  'tier' => int,        // 1-5, lower = more urgent
  'label' => string,    // action verb, e.g. "Review Video"
  'headline' => string, // e.g. "Grace Mwende — hands-on video ready for review"
  'subtext' => string,  // e.g. "Postpartum Haemorrhage Management"
  'url' => string,
  'meta' => array,      // tier-specific: days_inactive, completion_pct, attendance_rate, etc.
]
```
Sorted by `tier` ascending, then within each tier by a tier-specific severity metric, oldest/worst first:
- Tier 1: earliest video-submission timestamp first (longest-waiting review first)
- Tier 2: earliest module-completion timestamp first (longest-waiting approval first)
- Tier 3: oldest `last_activity` first (longest inactive first)
- Tier 4: lowest completion percentage first
- Tier 5: lowest attendance rate first

### Tiers

1. **Pending video review** — reuses `EmoncReportingService::pendingVideoReviewItemsForUser()` data, reshaped into the queue format. One item per mentee with a submitted, unreviewed video. (EmONC-only, naturally — non-EmONC progress rows never populate `hands_on_video_url`.)
2. **Pending mentor approval** — one item per `ClassParticipant` where `isEmonc($training->program->name)` is true, `hasCompletedAllModules()` returns true, and `mentor_approved_at` is null. Explicitly EmONC-gated per §2 above.
3. **Inactive mentee** — for each distinct mentee (by `user_id`) with at least one `MenteeModuleProgress` row not in `['completed', 'exempted']`: compute `last_activity` = the later of (max `MenteeModuleProgress.updated_at` across their rows) and (max `ClassAttendance.marked_at` for their confirmed attendances). If `last_activity` is null, fall back to their `ClassParticipant.enrolled_at`. Flag if that date is 14+ days in the past. Applies to all three programs.
4. **Struggling mentee** — existing 40%-completion threshold (`insights.mentees_needing_attention` today), expanded from a count into one item per qualifying mentee: total progress rows > 0 and completed-or-exempted fraction < 40%. Applies to all three programs.
5. **Low-attendance class** — existing 60%-confirmed threshold (`insights.low_attendance_classes` today), expanded into one item per qualifying `MentorshipClass`: enrolled > 0 and confirmed-attendance ratio < 60%. Applies to all three programs.

A mentee could theoretically qualify for both Tier 3 and Tier 4 (inactive **and** struggling) — only their single highest-priority (lowest-tier) item is kept; duplicates for the same mentee are not shown twice.

---

## 4. Dashboard layout

`MentorDashboard.php` calls the resolver once in `loadDashboard()` and exposes `$this->priorityQueue`. The Blade view (`resources/views/filament/pages/mentor-dashboard.blade.php`) is restructured to match the pattern established in Phase 1:

1. **New card, near the top**: "N item(s) need your attention" — lists the queue (mentee name, class/module context, one-line reason, direct action link). Capped at a reasonable on-page count (e.g. 10) with the total shown even if more exist.
2. **Existing KPI stat grid + per-mentorship breakdown table**: demoted below the fold, collapsed by default via `<details>`/`<summary>` (same markup pattern as the mentee dashboard's "My Progress"/"My Classes").
3. **Empty state**: when the queue is empty — "You're all caught up. Nothing needs your attention right now." plus a link to the full mentorship list.
4. Head-DRMH-pending count stays a plain KPI number (not part of the queue) since the mentor can't act on it directly.

No existing computed data is discarded — `kpis`, `mentorshipItems`, `activityFeed` all stay, just relocated under the collapsed sections, matching Phase 1's precedent.

---

## 5. Business rules / edge cases

- A mentor with zero mentorships sees the existing empty state (unchanged) — no queue card renders.
- A mentee flagged in multiple tiers (e.g. inactive AND struggling) appears once, under their most urgent (lowest-numbered) tier only.
- The inactive-mentee check must not flag a mentee whose only progress rows are `completed`/`exempted` (nothing left for them to be inactive on) — only mentees with genuinely outstanding work are eligible.
- The resolver must not throw for a mentor with mentorships that have zero enrolled participants, zero classes, or zero modules — all three tiers must handle empty collections gracefully (falls through to "no items" for that tier, not an error).
- Tier 2 must never surface a mentee for whom `hasCompletedAllModules()` is false, even if their `ClassParticipant.status` is `'completed'` (a class can be ended via "End Class" before every module is truly finished) — this avoids ever presenting an action that would throw `DomainException` if clicked.

---

## 6. Testing

- Feature/unit test: mentor with a pending video review sees it as a Tier 1 item.
- Feature/unit test: EmONC mentor with a mentee who has completed all modules (video passed) and has `mentor_approved_at = null` sees a Tier 2 item; a mentee with incomplete modules but `status = completed` does **not** appear in Tier 2.
- Feature/unit test: non-EmONC mentor (Newborn Care or Infant/Child) never receives a Tier 1 or Tier 2 item, even with completed classes — confirms the EmONC-only gating holds.
- Feature/unit test: mentee inactive 15+ days (no progress update, no attendance record) appears as a Tier 3 item; a mentee inactive only 10 days does not.
- Feature/unit test: mentee below 40% completion appears as a Tier 4 item; a mentee at or above 40% does not.
- Feature/unit test: class below 60% confirmed attendance appears as a Tier 5 item; a class at or above 60% does not.
- Feature/unit test: a mentee qualifying for both Tier 3 and Tier 4 appears exactly once, at Tier 3.
- Feature/unit test: mentor with no mentorships gets an empty queue, no error.

---

## 7. Acceptance criteria

- [ ] `MentorPriorityQueueResolver` returns correctly tiered, correctly deduplicated items covering all five tiers, verified by tests including the EmONC-only gating on Tiers 1-2 and cross-program coverage on Tiers 3-5.
- [ ] Mentor dashboard renders a "needs your attention" card driven by the resolver; existing KPIs/mentorship table/activity feed are preserved but collapsed by default below it.
- [ ] Both former count-only insights (`mentees_needing_attention`, `low_attendance_classes`) are now backed by real, clickable per-mentee/per-class items in the queue.
- [ ] No regressions to existing dashboard data (`kpis`, `mentorshipItems`, `activityFeed` all still computed and displayed, just relocated).

---

## 8. Recommended implementation order

1. `MentorPriorityQueueResolver` service + tests (no UI change yet — safe to land first).
2. Wire the resolver into `MentorDashboard.php`, add `$priorityQueue` property.
3. Priority-queue card in the Blade view; wrap existing KPI/table sections in collapsed panels, matching Phase 1's pattern.
4. Manual browser verification (mentor with a mix of EmONC and non-EmONC mentorships, to confirm the EmONC-only gating and cross-program tiers both render correctly).
