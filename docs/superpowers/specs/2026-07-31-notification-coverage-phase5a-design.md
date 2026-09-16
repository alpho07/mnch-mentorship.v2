# Phase 5a — Notification Coverage for Non-EmONC Programs

> Date: 2026-07-31
> Project: MNCH Mentorship Platform
> Scope: Phase 5 (cross-cutting standards) of the design overhaul, first sub-piece — notification coverage only.

---

## 1. Goal

Close a real gap confirmed by direct code investigation: Newborn Care and Infant/Child Care mentees receive **zero notifications** today — no in-app, no email — for anything that happens in their mentorship. `EmoncNotificationService` (7 event types: activity completed, quiz submitted, video submitted, video reviewed, mentor approved, Head DRMH certified) is already program-agnostic internally (no `isEmonc()` checks inside it), but every one of its call sites lives behind EmONC-gated UI code. This phase adds the two highest-value, lowest-noise notification gaps for the other two programs, per the guide's §16 priority list (returned feedback, task completion — not routine/frequent events like attendance).

---

## 2. Context

- Confirmed via direct investigation: `AttendanceService`, `ModuleAttendanceController`, `ClassModule`/`MentorshipClass` lifecycle methods, `EnrollmentService`, and `MenteeEnrollmentController` contain **no** notification or mail code at all. The only mentorship-adjacent notification mechanism in the codebase is `EmoncNotificationService`, called exclusively from EmONC-gated paths.
- `ManageModuleMentees.php` (~line 472-493) already saves a mentor's written recommendation to `MenteeModuleProgress.mentor_recommendation` for **all three programs** (no `isEmonc` gate on this action) — it just never tells the mentee it happened. It fires a Filament flash toast confirming the save to the mentor, not a notification to the mentee.
- `ClassModule::complete()` (`app/Models/ClassModule.php:162-236`) is the mentor-triggered "Complete Module" action. Per the existing module-table action set, this action is only exposed for non-EmONC modules (EmONC module completion instead runs through the per-mentee activity-completion matrix) — so hooking a notification into this method naturally stays scoped to Newborn/Infant-Child without an explicit program check.
- Not in scope: adding deadlines to notification content (a separate retrofit across all notification types, explicitly deferred per the brainstorming discussion), attendance-confirmation notifications (too frequent/low-value per the guide's own "avoid excessive notifications" guidance), and renaming `EmoncNotificationService` (a larger, riskier refactor touching every existing call site for no functional benefit).

---

## 3. Two new methods on `EmoncNotificationService`

```php
public function mentorRecommendationWritten(MenteeModuleProgress $progress): void;
public function moduleCompleted(MenteeModuleProgress $progress): void;
```

Both follow the exact structure of every existing method in the class: resolve the mentee via `$progress->classParticipant?->user`, no-op if absent, build a module name via `$progress->classModule?->programModule?->name ?? 'a module'`, and call the existing private `notify()` helper (in-app database notification + best-effort email, matching every other event in this service).

- `mentorRecommendationWritten`: title "Mentor Feedback Received", body "Your mentor has written feedback on your progress in {module}. Review it on your dashboard.", links to `mentee.class.progress` for the progress's class, action label "View Feedback".
- `moduleCompleted`: title "Module Completed", body "You've completed {module}. Great work!", same link, action label "View My Progress".

---

## 4. Two call sites

1. **`ManageModuleMentees.php`** — the recommendation-saving action currently discards `MenteeModuleProgress::updateOrCreate(...)`'s return value. Capture it and call `app(EmoncNotificationService::class)->mentorRecommendationWritten($progress)` immediately after, before the existing mentor-facing success toast.
2. **`ClassModule::complete()`** — in the `if ($attended)` branch, after the progress row is finalized (both the `firstOrCreate` and the `wasRecentlyCreated`-guarded `update()` paths), call `app(EmoncNotificationService::class)->moduleCompleted($progress)`.

---

## 5. Business rules / edge cases

- No notification fires if the mentee's `user` relation is unexpectedly null (matches every existing method's guard clause).
- `ClassModule::complete()`'s "absent" branch (mentee not attended) never triggers `moduleCompleted` — only mentees actually marked `completed` do.
- A mentee whose progress row was already `completed` before this module-completion pass (e.g. re-running an idempotent operation) still receives the notification on each call, matching the existing codebase's lack of "already notified" deduplication elsewhere in `EmoncNotificationService` (out of scope to add here — would be a broader notification-deduplication feature).
- Email sending failures must not block the underlying business operation — reuses the existing `notify()` helper's try/catch-and-report behavior; no new code needed here.

---

## 6. Testing

- Test: writing a mentor recommendation for a Newborn Care mentee sends a database notification containing the module name to that mentee.
- Test: writing a mentor recommendation for an Infant/Child Care mentee also sends the notification (confirms no accidental EmONC-only gating was introduced).
- Test: completing a module via `ClassModule::complete()` sends a `moduleCompleted` notification to every mentee whose progress ends up `completed`, and not to mentees who were absent (progress stays `not_started`).
- Test: a `ClassParticipant` with no linked `user` does not throw when either method is called.

---

## 7. Acceptance criteria

- [ ] `EmoncNotificationService::mentorRecommendationWritten()` and `::moduleCompleted()` exist, follow the class's established pattern, and are covered by tests.
- [ ] Writing a mentor recommendation notifies the mentee for all three programs.
- [ ] Completing a (non-EmONC) module notifies every mentee marked completed in that pass.
- [ ] No regressions to the existing EmONC notification methods or the mentor-facing success toast in `ManageModuleMentees.php`.

---

## 8. Recommended implementation order

1. Add the two new methods to `EmoncNotificationService` + unit tests (no call-site changes yet — safe to land first).
2. Wire `mentorRecommendationWritten()` into `ManageModuleMentees.php`.
3. Wire `moduleCompleted()` into `ClassModule::complete()`.
4. Manual browser verification: write a recommendation for a Newborn Care mentee and confirm the in-app notification appears on their account; complete a non-EmONC module and confirm attended mentees receive theirs.
