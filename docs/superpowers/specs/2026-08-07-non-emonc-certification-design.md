# Non-EmONC Certificate Issuance — Design Spec

**Status:** Approved, ready for implementation plan
**Phase:** Production-Safe System Audit, Phase 5 (Workflow Optimization) continuation
**Related:** [[2026-08-07-mentee-completion-status-fix-design]] — this spec builds directly on `ClassParticipant::hasCompletedAllModules()` / `syncCompletionStatus()` shipped by that fix.

## Problem

Mentees in non-EmONC programs (Newborn Care, Infant & Child Care) can complete every module in their class and never receive a certificate. Not because certification itself is EmONC-only — `HeadDrmhDashboard`, `HeadDrmhReviewMentee`, and the `reports.class.certificate` download route are all already program-agnostic, driven purely by `mentor_approved_at`/`head_drmh_approved_at` — but because nothing can ever set `mentor_approved_at` for a non-EmONC mentee:

- `ManageClassMentees.php`'s `mentor_approve` action is explicitly gated `$this->isEmonc()` (line 241).
- Even if that gate were removed, its readiness check — `hasCompletedAllModules()` — requires a passed video review on *every* module. Video review is a rubric-assessment concept; non-EmONC modules never have a `ModuleRubric` row, so `isVideoPassed()` can never be true for them. The check would always fail regardless of the `isEmonc()` gate.

## Design

### 1. Fix `hasCompletedAllModules()` to be program-agnostic

`app/Models/ClassParticipant.php` — the video-review requirement inside the per-module loop becomes conditional on whether that module actually has an active rubric to be reviewed against:

```php
public function hasCompletedAllModules(): bool
{
    $class = $this->relationLoaded('mentorshipClass') ? $this->mentorshipClass : $this->mentorshipClass()->first();

    if (! $class) {
        return false;
    }

    $classModules = $class->classModules()->get(); // was ->pluck('id')

    if ($classModules->isEmpty()) {
        return false;
    }

    $progressRecords = $this->moduleProgress()->whereIn('class_module_id', $classModules->pluck('id'))->get()->keyBy('class_module_id');

    if ($progressRecords->count() !== $classModules->count()) {
        return false;
    }

    foreach ($classModules as $classModule) {
        $progress = $progressRecords->get($classModule->id);

        if (! in_array($progress->status, ['completed', 'exempted'])) {
            return false;
        }

        $hasRubric = ModuleRubric::where('program_module_id', $classModule->program_module_id)
            ->where('is_active', true)
            ->exists();

        if ($hasRubric && ! $progress->isVideoPassed()) {
            return false;
        }
    }

    return true;
}
```

EmONC modules always have an active rubric today (all 24 real `ModuleRubric` rows are EmONC), so this is behavior-preserving for EmONC — the `$hasRubric` branch is always taken and the video-review check still applies exactly as before. Non-EmONC modules have zero rubrics, so the check is skipped and readiness is judged purely on module-progress status.

This also means `ClassParticipant::syncCompletionStatus()` (already shipped) starts correctly flipping `status` to `completed` for non-EmONC mentees the moment their modules are done — no change needed to that method or its two call sites.

### 2. Consolidate `isEmonc()` onto `Program`

The exact same string-match block is duplicated verbatim in `ManageClassMentees.php:1161-1168` and `ManageClassModules.php:257-264`. Add:

```php
// app/Models/Program.php
public function isEmonc(): bool
{
    return str_contains(strtolower($this->name), 'maternal')
        && str_contains(strtolower($this->name), 'emonc');
}
```

Both pages' existing private `isEmonc()` methods become thin delegating wrappers (all ~19 call sites across both files are untouched — only the method bodies change):

```php
private function isEmonc(): bool
{
    $program = Program::find($this->training->program_id);

    return $program?->isEmonc() ?? false;
}
```

This is a pure extraction (identical resulting logic) done because this feature is about to need the same check in a third location (`ClassParticipant`) — a good moment to stop tripling the string-match, not a broad refactor.

### 3. New domain method: `ClassParticipant::isReadyForHeadDrmhCertification()`

```php
public function isReadyForHeadDrmhCertification(): bool
{
    $training = $this->relationLoaded('mentorshipClass')
        ? $this->mentorshipClass?->training
        : $this->mentorshipClass()->first()?->training;

    $program = $training ? Program::find($training->program_id) : null;

    if ($program?->isEmonc()) {
        return $this->isMentorApproved();
    }

    return $this->hasCompletedAllModules();
}
```

EmONC mentees still require the existing mentor-approval step. Non-EmONC mentees are ready the moment `hasCompletedAllModules()` is true — no mentor-approval step, per your decision to skip it for non-EmONC.

### 4. One shared rule, three call sites

- **`HeadDrmhDashboard::loadData()`** (`app/Filament/Pages/HeadDrmhDashboard.php:63-69`) — the `$pending` query currently is `whereNotNull('mentor_approved_at')->whereNull('head_drmh_approved_at')`. Add a second query for non-EmONC participants (`whereNull('mentor_approved_at')`, `whereNull('head_drmh_approved_at')`, `status = 'completed'`, training's program is not EmONC), merge with the existing query's results, sort by a common timestamp (fall back to `updated_at` for the non-EmONC branch, since there's no `mentor_approved_at` to sort by).
- **`HeadDrmhReviewMentee::mount()`** (`app/Filament/Pages/HeadDrmhReviewMentee.php:57`) — `$this->canCertify` becomes `$this->participant->isReadyForHeadDrmhCertification() && ! $this->isCertified` instead of `$this->participant->isMentorApproved() && ! $this->isCertified`.
- **`HeadDrmhReviewMentee::certify()`** (`app/Filament/Pages/HeadDrmhReviewMentee.php:182-189`) — the `! isMentorApproved()` guard (and its "Mentor approval required" message) becomes `! isReadyForHeadDrmhCertification()`, with the error message adjusted to cover both reasons ("This mentee is not yet ready to be certified.").
- **`ManageClassMentees.php`'s `head_drmh_certify` action** (`app/Filament/Resources/MentorshipResource/Pages/ManageClassMentees.php:280-283`) — `visible()`'s `$record->mentor_approved_at &&` clause becomes `$record->isReadyForHeadDrmhCertification() &&`, so the roster page's button lights up for non-EmONC directly, consistent with the dashboard.

`mentor_approve` itself (`ManageClassMentees.php:237-275`) is untouched — still `isEmonc()`-gated, zero behavior change for that action.

### 5. `markHeadDrmhApproved()` unaffected

It already has no readiness check of its own (relies entirely on the calling UI's guard) — both updated call sites (`ManageClassMentees.php`'s action handler, `HeadDrmhReviewMentee::certify()`) still call it exactly as before, just gated by the new shared method instead of the old one.

## Testing

- Unit tests on `Program::isEmonc()` — the extracted method matches the old inline logic (EmONC name variants match, non-EmONC names don't).
- Unit tests on `ClassParticipant::hasCompletedAllModules()`: an EmONC participant with all progress completed but a pending video review still returns false (unchanged behavior); a non-EmONC participant with all progress completed and no rubric on any module returns true; a non-EmONC participant with one module still `in_progress` returns false.
- Unit tests on `ClassParticipant::isReadyForHeadDrmhCertification()`: EmONC + mentor-approved → true; EmONC + not mentor-approved → false regardless of module completion; non-EmONC + all modules complete → true; non-EmONC + modules incomplete → false.
- Feature test: a non-EmONC participant with all modules completed appears in `HeadDrmhDashboard`'s pending list without ever having `mentor_approved_at` set.
- Feature test: `HeadDrmhReviewMentee::certify()` succeeds for a ready non-EmONC participant with no prior mentor approval, and sets `head_drmh_approved_at`.
- Feature test: `ManageClassMentees.php`'s `head_drmh_certify` row action becomes visible and works for a completed non-EmONC participant.
- Regression: existing EmONC-focused tests for `hasCompletedAllModules()`, `markMentorApproved()`, `ManageClassMentees`'s mentor_approve/head_drmh_certify actions, and the completion-status-fix tests from the prior plan all still pass unchanged.

## Out of scope

- `HeadDrmhReviewMentee`'s review page will still render a "Video Review: pending, no video" section for non-EmONC modules (since one never existed) — cosmetically odd but not fixed in this pass unless requested separately.
- Any change to `mentor_approve`'s own gate or behavior — stays EmONC-only.
- The four-mentorship-creation-flow simplification — separate, queued next.
- Program-level configuration (e.g., an explicit `requires_mentor_approval` flag on `Program` instead of name-matching) — the existing string-match approach is reused as-is per the "pure extraction, not a broad refactor" scope decision in §2.
