# Fix Dead Mentor-Approve/Certify Buttons — Design Spec

**Status:** Approved, ready for implementation plan
**Phase:** Production-Safe System Audit, Phase 5 (Workflow Optimization) — found during the EmONC mentor→mentee workflow audit
**Related:** Workflow audit findings #1 and #2 (dead approve/certify buttons on `ManageClassMentees.php`; `MentorPriorityQueueResolver`'s "Approve Mentee" link routes into that same dead UI)

## Problem

`ClassParticipant::markCompleted()` (`app/Models/ClassParticipant.php:153-158`, sets `status='completed'`) has zero callers anywhere in `app/`. `ManageClassMentees.php`'s `mentor_approve` and `head_drmh_certify` row actions (and their bulk equivalents) are gated on `$record->status === 'completed'` — a condition no live code path ever produces. Mentors following the app's own Priority Queue ("Approve Mentee" → links to `class-mentees`) land on a page where the button they need is invisible, for every mentee, always. Live DB check: 594 `class_participants` rows exist, 297 already show `status='completed'` (legacy/pre-dating current code) and the remaining rows can never transition through the app as currently written.

## Root cause

`hasCompletedAllModules()` (`ClassParticipant.php:191-220`) — the real, correct readiness check, already used by both the approve button's `visible()` gate and its `action()` handler's re-validation, and by `markMentorApproved()`'s own defense-in-depth check — is never used to *set* `status`. Nothing syncs the two.

## Design

### 1. New model method: `ClassParticipant::syncCompletionStatus()`

```php
public function syncCompletionStatus(): bool
{
    if ($this->status === 'completed') {
        return false;
    }

    if (! $this->hasCompletedAllModules()) {
        return false;
    }

    return $this->markCompleted();
}
```

Idempotent (safe to call repeatedly/redundantly), read-then-write, no side effects beyond the existing `markCompleted()` behavior (`status`, `completed_at`).

### 2. Call it at both points where readiness can flip false→true

- `ManageClassModules::saveActivityCompletions()` (`app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php`, inside the `$newlyCompletedParticipantIds` loop) — a mentee's last module just finished via activities.
- `ConductRubricAssessment::submitAssessment()` (`app/Filament/Resources/RubricAssessmentResource/Pages/ConductRubricAssessment.php`, after `syncVideoReviewStatus()`) — a mentee's last pending video-review-via-rubric just passed. **Not touching `syncVideoReviewStatus()`'s own logic** — it's a deliberate, documented decision ("per EmONC meeting item 4") that the rubric score is the single source of truth for hands-on pass/fail; this fix only adds the completion-status sync alongside it.

### 3. No changes needed to `ManageClassMentees.php` or `MentorPriorityQueueResolver.php`

Verified directly: both already reference the correct, real gate (`hasCompletedAllModules()` via `isReadyForMentorApproval()`) and the correct destination page. They were only ever blocked by `status` never being set. Once it is, both start working with zero further changes — confirmed by reading `ManageClassMentees.php:237-275` (mentor_approve) and `MentorPriorityQueueResolver.php:95-125` (tier 2) in full.

### 4. Backfill for already-stuck real records

A new Artisan console command, dry-run by default, reusable and auditable (not an ad-hoc script):

```
php artisan mentorship:sync-completion-status           # dry-run: reports what WOULD change
php artisan mentorship:sync-completion-status --apply    # actually applies
```

Iterates all `ClassParticipant` rows where `status != 'completed'`, checks `hasCompletedAllModules()`, and reports/applies `syncCompletionStatus()` for any that qualify. Run against the real database only after: fresh backup, dry-run report reviewed, then `--apply`, then before/after row-count and spot-check verification — same rigor as every other real-DB change this session.

## Testing

- Unit test on `ClassParticipant::syncCompletionStatus()`: no-op when already completed, no-op when not all modules done, sets status+completed_at when genuinely ready.
- Feature test: submitting the last activity completion for a mentee's last module correctly flips their `ClassParticipant.status` to `completed` (via `ManageClassModules::saveActivityCompletions()`), and the `mentor_approve` action becomes visible on `ManageClassMentees.php` immediately after, without a separate manual step.
- Feature test: submitting a passing rubric assessment as the last remaining readiness condition (activities already done, video review was the only gap) also flips status correctly.
- Feature test: the backfill command in dry-run mode reports but doesn't change data; in `--apply` mode it correctly syncs only genuinely-ready participants and leaves others untouched.

## Out of scope

- The rubric/video-review decoupling (audit finding #5) — confirmed deliberate, documented team decision, not touched.
- The self-enrollment attendance gap (audit finding #3) — explicitly deferred per your scoping choice; a separate design if/when picked up.
- Any change to `markMentorApproved()`/`markHeadDrmhApproved()`'s own domain-invariant checks — already correct, already defense-in-depth, untouched.
- The four-mentorship-creation-flow product-surface finding — a bigger, separate product decision, not a code defect.
