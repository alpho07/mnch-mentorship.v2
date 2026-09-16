# Fix Dead Mentor-Approve/Certify Buttons Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire `ClassParticipant.status` to actually transition to `'completed'` when a mentee genuinely finishes all their EmONC modules, fixing the dead `mentor_approve`/`head_drmh_certify` buttons on `ManageClassMentees.php` and the Priority Queue link that routes into them — per `docs/superpowers/specs/2026-08-07-mentee-completion-status-fix-design.md`.

**Architecture:** One new idempotent model method (`ClassParticipant::syncCompletionStatus()`) called from the two places a mentee's readiness can flip false→true, plus a dry-run-by-default Artisan command to backfill already-stuck real records. No changes to `ManageClassMentees.php` or `MentorPriorityQueueResolver.php` — both already reference the correct gate and destination, confirmed by reading them in full during design.

**Tech Stack:** Laravel 12 Eloquent, Artisan console command, PHPUnit/Laravel test conventions matching this codebase.

## Global Constraints

- Do not modify `ConductRubricAssessment::syncVideoReviewStatus()`'s existing rubric-is-source-of-truth logic — it's a deliberate, documented decision ("per EmONC meeting item 4"). Only add the completion-status sync alongside it.
- Do not modify `markMentorApproved()`, `markHeadDrmhApproved()`, or `hasCompletedAllModules()` — all three are already correct and are the ground truth this fix syncs `status` against.
- The backfill command must default to dry-run (report only) — applying changes requires an explicit `--apply` flag.
- Do not run the backfill against the real database as part of this plan's execution — that's a separate, explicit step after the code is merged and tested, same as every other real-DB change this session (backup first, dry-run report reviewed, then apply).

---

### Task 1: `ClassParticipant::syncCompletionStatus()`

**Files:**
- Modify: `app/Models/ClassParticipant.php` (add the new method near `markCompleted()`/`hasCompletedAllModules()`)
- Test: `tests/Unit/Models/ClassParticipantSyncCompletionStatusTest.php`

**Interfaces:**
- Consumes: `hasCompletedAllModules()` (`ClassParticipant.php:191-220`, unchanged), `markCompleted()` (`ClassParticipant.php:153-158`, unchanged).
- Produces: `syncCompletionStatus(): bool` — consumed by Tasks 2 and 3.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassParticipantSyncCompletionStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeParticipantWithOneModule(): array
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $mentor = User::factory()->create();
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);

        return compact('participant', 'classModule');
    }

    public function test_it_is_a_no_op_when_not_all_modules_are_complete(): void
    {
        ['participant' => $participant] = $this->makeParticipantWithOneModule();

        $this->assertFalse($participant->syncCompletionStatus());
        $this->assertSame('enrolled', $participant->fresh()->status);
    }

    public function test_it_is_a_no_op_when_status_is_already_completed(): void
    {
        ['participant' => $participant] = $this->makeParticipantWithOneModule();
        $participant->update(['status' => 'completed']);

        $this->assertFalse($participant->syncCompletionStatus());
    }

    public function test_it_marks_completed_when_all_modules_are_done_and_video_passed(): void
    {
        ['participant' => $participant, 'classModule' => $classModule] = $this->makeParticipantWithOneModule();

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'passed',
        ]);

        $this->assertTrue($participant->syncCompletionStatus());
        $fresh = $participant->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_it_stays_false_when_modules_done_but_video_still_pending(): void
    {
        ['participant' => $participant, 'classModule' => $classModule] = $this->makeParticipantWithOneModule();

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'pending',
        ]);

        $this->assertFalse($participant->syncCompletionStatus());
        $this->assertSame('enrolled', $participant->fresh()->status);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Unit/Models/ClassParticipantSyncCompletionStatusTest.php`
Expected: FAIL — `syncCompletionStatus()` doesn't exist yet (method-not-found error on all 4).

- [ ] **Step 3: Add the method**

In `app/Models/ClassParticipant.php`, add near `markCompleted()`:

```php
    /**
     * Sets status to 'completed' the moment a mentee genuinely finishes
     * every module (all progress completed/exempted, every video review
     * passed) — see docs/PHASE1-DISCOVERY-BASELINE.md for why this needed
     * adding: markCompleted() previously had zero callers anywhere, so
     * mentor_approve/head_drmh_certify's status==='completed' visibility
     * gate on ManageClassMentees.php could never become true.
     */
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

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Models/ClassParticipantSyncCompletionStatusTest.php`
Expected: PASS on all 4.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ClassParticipant.php tests/Unit/Models/ClassParticipantSyncCompletionStatusTest.php
git commit -m "feat: add ClassParticipant::syncCompletionStatus()"
```

---

### Task 2: Hook the sync into the activity-completion cascade

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php` (`saveActivityCompletions()`)
- Test: `tests/Feature/MentorshipResource/ActivityCompletionSyncsParticipantStatusTest.php`

**Interfaces:**
- Consumes: `ClassParticipant::syncCompletionStatus()` (Task 1).
- Produces: nothing consumed elsewhere in this plan — but this is the fix for audit findings #1/#2 taking effect for the "last module finishes via activities" path.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\MentorshipResource;

use App\Models\Activity;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleActivity;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityCompletionSyncsParticipantStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_the_last_activity_for_a_mentees_last_module_marks_the_participant_completed(): void
    {
        $mentor = User::factory()->create(['name' => 'Mentor']);
        Permission::firstOrCreate(['name' => 'update_mentorship::training', 'guard_name' => 'web']);
        $mentor->givePermissionTo('update_mentorship::training');
        $this->actingAs($mentor);

        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $activity = Activity::firstOrCreate(['name' => 'CME']);
        ProgramModuleActivity::create(['program_module_id' => $programModule->id, 'activity_id' => $activity->id]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'not_started',
            'video_review_status' => 'passed', // only remaining gap is the activity below
        ]);

        Livewire::test(\App\Filament\Resources\MentorshipResource\Pages\ManageClassModules::class, [
            'training' => $training->id,
            'class' => $class->id,
        ])
            ->call('saveActivityCompletions', $classModule->id, [$participant->id => [$activity->id => true]]);

        $this->assertSame('completed', $participant->fresh()->status);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/MentorshipResource/ActivityCompletionSyncsParticipantStatusTest.php`
Expected: FAIL — `syncCompletionStatus()` isn't called anywhere in `saveActivityCompletions()` yet, `$participant->status` stays `'enrolled'`.

(If the exact `saveActivityCompletions()` call signature above doesn't match the real method — verify its actual parameters by reading `ManageClassModules.php` before writing this test for real; the plan's example assumes the activity-checkbox-array shape implied by the audit's read of lines 960-1015, confirm against the live method signature during implementation.)

- [ ] **Step 3: Add the hook**

In `app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php`, inside `saveActivityCompletions()`, right after the existing `$newlyCompletedParticipantIds[] = $participantId;` line (in the "Auto-complete mentee progress when all activities done" block), add a call that syncs the participant's overall completion status:

```php
                    if (! in_array($progress->status, ['completed', 'exempted'])) {
                        $progress->markCompleted();
                        $newlyCompletedParticipantIds[] = $participantId;
                    }
                }
            }
```

becomes:

```php
                    if (! in_array($progress->status, ['completed', 'exempted'])) {
                        $progress->markCompleted();
                        $newlyCompletedParticipantIds[] = $participantId;
                    }
                }
            }

            // A mentee's overall readiness for mentor approval can flip to
            // true here if this was their last remaining module.
            foreach ($participantIds as $participantId) {
                ClassParticipant::find($participantId)?->syncCompletionStatus();
            }
```

(Placed after the existing per-module completion loop, using `$participantIds` — the same variable already in scope from earlier in the method — so every participant touched by this save gets checked, not just the ones newly completed in this specific call.)

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/MentorshipResource/ActivityCompletionSyncsParticipantStatusTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php tests/Feature/MentorshipResource/ActivityCompletionSyncsParticipantStatusTest.php
git commit -m "fix: sync ClassParticipant completion status after activity completions"
```

---

### Task 3: Hook the sync into the rubric-assessment submission

**Files:**
- Modify: `app/Filament/Resources/RubricAssessmentResource/Pages/ConductRubricAssessment.php` (`syncVideoReviewStatus()`)
- Test: `tests/Feature/RubricAssessment/PassingRubricSyncsParticipantStatusTest.php`

**Interfaces:**
- Consumes: `ClassParticipant::syncCompletionStatus()` (Task 1).
- Produces: nothing consumed elsewhere — this is the fix for the "video review was the last remaining gap" path.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\RubricAssessment;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PassingRubricSyncsParticipantStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_passing_rubric_assessment_that_was_the_last_gap_marks_the_participant_completed(): void
    {
        $mentor = User::factory()->create(['name' => 'Mentor']);
        Permission::firstOrCreate(['name' => 'create_rubric::assessment', 'guard_name' => 'web']);
        $mentor->givePermissionTo('create_rubric::assessment');
        $this->actingAs($mentor);

        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $rubric = ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Test Rubric',
            'total_marks' => 2,
            'pass_marks' => 1,
        ]);
        $item = \App\Models\RubricItem::create(['module_rubric_id' => $rubric->id, 'description' => 'Step 1', 'order_sequence' => 1]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);
        // Activities already fully done — video review is the only remaining gap.
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'pending',
        ]);

        Livewire::test(\App\Filament\Resources\RubricAssessmentResource\Pages\ConductRubricAssessment::class, [
            'module_rubric_id' => $rubric->id,
            'mentee_id' => $mentee->id,
            'class_module_id' => $classModule->id,
        ])
            ->set('mentor_id', $mentor->id)
            ->set('assessed_at', now()->format('Y-m-d\TH:i'))
            ->set('responses', [$item->id => true])
            ->call('submitAssessment');

        $this->assertSame('completed', $participant->fresh()->status);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/RubricAssessment/PassingRubricSyncsParticipantStatusTest.php`
Expected: FAIL — `$participant->status` stays `'enrolled'`.

(As with Task 2, verify `ConductRubricAssessment`'s exact public property names/mount signature against the live file before finalizing this test — the plan's version is built from the class properties already read during design, but confirm nothing else is required to mount cleanly.)

- [ ] **Step 3: Add the hook**

In `app/Filament/Resources/RubricAssessmentResource/Pages/ConductRubricAssessment.php`, inside `syncVideoReviewStatus()`, right after the existing `$progress->recordVideoReview(...)` call, add:

```php
        $progress->recordVideoReview(
            $passed ? 'passed' : 'failed',
            'Derived from practical (rubric) assessment.',
            $this->mentor_id
        );

        $participant->syncCompletionStatus();

        app(EmoncNotificationService::class)->videoReviewed($progress->fresh());
```

(`$participant` is already in scope from earlier in the same method — no new query needed.)

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/RubricAssessment/PassingRubricSyncsParticipantStatusTest.php`
Expected: PASS.

- [ ] **Step 5: Run every test from Tasks 1-3 together, plus the pre-existing EmONC/mentorship test files, to confirm no regressions**

Run: `php artisan test tests/Unit/Models/ClassParticipantSyncCompletionStatusTest.php tests/Feature/MentorshipResource/ActivityCompletionSyncsParticipantStatusTest.php tests/Feature/RubricAssessment/PassingRubricSyncsParticipantStatusTest.php tests/Feature/GuidedMentorshipSetupTest.php tests/Feature/ChatMentorshipSetupTest.php tests/Feature/HeadDrmhReviewMenteeTest.php tests/Feature/ReviewModuleMenteeTest.php`
Expected: PASS on everything.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/RubricAssessmentResource/Pages/ConductRubricAssessment.php tests/Feature/RubricAssessment/PassingRubricSyncsParticipantStatusTest.php
git commit -m "fix: sync ClassParticipant completion status after a passing rubric assessment"
```

---

### Task 4: Backfill command for already-stuck real records

**Files:**
- Create: `app/Console/Commands/SyncMentorshipCompletionStatus.php`
- Test: `tests/Feature/Console/SyncMentorshipCompletionStatusTest.php`

**Interfaces:**
- Consumes: `ClassParticipant::syncCompletionStatus()` (Task 1), iterates all `ClassParticipant::where('status', '!=', 'completed')`.
- Produces: nothing consumed elsewhere — this is the terminal, operator-run tool for the real-database backfill (running it against the real DB is a separate step after this plan merges, not part of plan execution).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Console;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncMentorshipCompletionStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeReadyButStuckParticipant(): ClassParticipant
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $mentor = User::factory()->create();
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id, 'mentor_id' => $mentor->id, 'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id, 'program_module_id' => $programModule->id, 'status' => 'in_progress',
        ]);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $mentee->id, 'status' => 'enrolled',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id, 'class_module_id' => $classModule->id,
            'status' => 'completed', 'video_review_status' => 'passed',
        ]);

        return $participant;
    }

    public function test_dry_run_reports_but_does_not_change_data(): void
    {
        $participant = $this->makeReadyButStuckParticipant();

        $this->artisan('mentorship:sync-completion-status')
            ->expectsOutputToContain((string) $participant->id)
            ->assertExitCode(0);

        $this->assertSame('enrolled', $participant->fresh()->status);
    }

    public function test_apply_flag_actually_syncs_ready_participants(): void
    {
        $participant = $this->makeReadyButStuckParticipant();

        $this->artisan('mentorship:sync-completion-status --apply')
            ->assertExitCode(0);

        $this->assertSame('completed', $participant->fresh()->status);
    }

    public function test_participants_not_yet_ready_are_left_untouched(): void
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $mentor = User::factory()->create();
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id, 'mentor_id' => $mentor->id, 'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $mentee->id, 'status' => 'enrolled',
        ]);

        $this->artisan('mentorship:sync-completion-status --apply')->assertExitCode(0);

        $this->assertSame('enrolled', $participant->fresh()->status);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/Console/SyncMentorshipCompletionStatusTest.php`
Expected: FAIL — the command doesn't exist yet.

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\ClassParticipant;
use Illuminate\Console\Command;

class SyncMentorshipCompletionStatus extends Command
{
    protected $signature = 'mentorship:sync-completion-status {--apply : Actually apply the changes instead of only reporting them}';

    protected $description = 'Backfill ClassParticipant.status to completed for participants who already satisfy hasCompletedAllModules() but are stuck at an earlier status.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $candidates = ClassParticipant::where('status', '!=', 'completed')->get();

        $ready = $candidates->filter(fn (ClassParticipant $p) => $p->hasCompletedAllModules());

        if ($ready->isEmpty()) {
            $this->info('No stuck participants found — nothing to do.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'Applying' : 'Would apply (dry run — pass --apply to actually change data)') . " completion status for {$ready->count()} participant(s):");

        foreach ($ready as $participant) {
            $this->line("  - ClassParticipant #{$participant->id} (user_id={$participant->user_id}, mentorship_class_id={$participant->mentorship_class_id})");

            if ($apply) {
                $participant->syncCompletionStatus();
            }
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Console/SyncMentorshipCompletionStatusTest.php`
Expected: PASS on all 3.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SyncMentorshipCompletionStatus.php tests/Feature/Console/SyncMentorshipCompletionStatusTest.php
git commit -m "feat: add mentorship:sync-completion-status backfill command"
```

---

## Deferred / not in this plan

- Actually running `mentorship:sync-completion-status --apply` against the real `mnch-feb` database — a separate, explicit step after this plan is merged, with a fresh backup and dry-run review first (same rigor as every other real-DB change this session).
- Audit findings #3 (self-enrollment attendance bypass), #5 (video/rubric decoupling — confirmed deliberate, not a defect), #6 (stale video-review status on re-upload), #9 (legacy attendance record complexity), #10 (triplicated new-mentee form), #11 (four creation-flow surfaces — being handled separately as its own UI/UX design).
- Non-EmONC (Newborn Care, Infant/Child Care) completion workflow — confirmed structurally separate from what this plan touches (`mentor_approve`'s own `isEmonc()` gate), not audited this round.

## Self-Review

**Spec coverage:** Task 1 → spec's "New model method" section verbatim. Tasks 2-3 → spec's "two hook points," each independently testable and matching the exact code locations confirmed during design (`ManageClassModules.php`'s `$newlyCompletedParticipantIds` loop, `ConductRubricAssessment.php`'s `syncVideoReviewStatus()`). Task 4 → spec's backfill command section, dry-run-by-default as specified.

**Placeholder scan:** No TBD/TODO. Two steps (Task 2 Step 1, Task 3 Step 1) explicitly flag "verify the exact method signature against the live file before finalizing" rather than guessing blind — this is a deliberate acknowledgment that `saveActivityCompletions()`'s and `ConductRubricAssessment`'s exact call/mount signatures weren't independently re-verified line-by-line beyond what the design phase already read, not a placeholder for unwritten logic. The actual code changes (Step 3 in each task) are complete and specific, quoting real surrounding code as anchors.

**Type consistency:** `syncCompletionStatus(): bool` return type used consistently everywhere it's called (Tasks 2-4). `ClassParticipant::find($participantId)?->syncCompletionStatus()` in Task 2 correctly null-safes in case a participant was removed mid-request (defensive, matches this codebase's existing style elsewhere).
