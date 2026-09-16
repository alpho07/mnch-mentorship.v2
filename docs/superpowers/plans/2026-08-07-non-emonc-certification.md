# Non-EmONC Certificate Issuance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let non-EmONC mentees (Newborn Care, Infant & Child Care) who complete all their modules reach Head DRMH certification and get a downloadable certificate — per `docs/superpowers/specs/2026-08-07-non-emonc-certification-design.md`.

**Architecture:** Fix `ClassParticipant::hasCompletedAllModules()` to only require a passed video review where a module actually has an active rubric (behavior-preserving for EmONC, newly-correct for non-EmONC). Add a shared `ClassParticipant::isReadyForHeadDrmhCertification()` domain method that branches on program type, and wire it into the three places certification readiness is checked. Consolidate the duplicated `isEmonc()` string-match onto `Program::isEmonc()`.

**Tech Stack:** Laravel 12 Eloquent, Filament v3 pages, PHPUnit/Laravel test conventions matching this codebase.

## Global Constraints

- `mentor_approve` (`ManageClassMentees.php:237-275`) is not modified — stays EmONC-only, zero behavior change.
- `markMentorApproved()`, `markHeadDrmhApproved()` are not modified — both already correct; only their callers' guards change.
- All ~19 existing `$this->isEmonc()` call sites in `ManageClassModules.php` and `ManageClassMentees.php` are untouched — only the two private `isEmonc()` method *bodies* change, to delegate to `Program::isEmonc()`.
- Do not introduce a `requires_mentor_approval` column or similar — reuse the existing name-match approach per the spec's explicit scope decision.

---

### Task 1: `Program::isEmonc()` + delegate the two duplicated page methods

**Files:**
- Modify: `app/Models/Program.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ManageClassMentees.php:1161-1168`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php:257-264`
- Test: `tests/Unit/Models/ProgramIsEmoncTest.php`

**Interfaces:**
- Produces: `Program::isEmonc(): bool` — consumed by Task 1's own delegating wrappers and by Task 3's `ClassParticipant::isReadyForHeadDrmhCertification()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramIsEmoncTest extends TestCase
{
    use RefreshDatabase;

    public function test_maternal_emonc_program_name_is_emonc(): void
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);

        $this->assertTrue($program->isEmonc());
    }

    public function test_newborn_care_program_name_is_not_emonc(): void
    {
        $program = Program::factory()->create(['name' => 'Newborn Care']);

        $this->assertFalse($program->isEmonc());
    }

    public function test_infant_and_child_care_program_name_is_not_emonc(): void
    {
        $program = Program::factory()->create(['name' => 'Infant and Child Care']);

        $this->assertFalse($program->isEmonc());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Unit/Models/ProgramIsEmoncTest.php`
Expected: FAIL — `isEmonc()` doesn't exist on `Program` yet.

- [ ] **Step 3: Add `Program::isEmonc()`**

In `app/Models/Program.php`, add near the relationships:

```php
    public function isEmonc(): bool
    {
        return str_contains(strtolower($this->name), 'maternal')
            && str_contains(strtolower($this->name), 'emonc');
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Models/ProgramIsEmoncTest.php`
Expected: PASS on all 3.

- [ ] **Step 5: Delegate both pages' private `isEmonc()` to it**

In `app/Filament/Resources/MentorshipResource/Pages/ManageClassMentees.php`, replace:

```php
    private function isEmonc(): bool
    {
        $program = Program::find($this->training->program_id);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }
```

with:

```php
    private function isEmonc(): bool
    {
        $program = Program::find($this->training->program_id);

        return $program?->isEmonc() ?? false;
    }
```

Make the identical replacement in `app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php` (same method body, lines 257-264).

- [ ] **Step 6: Run the full existing test suite for both pages to confirm no regressions**

Run: `php artisan test tests/Feature/ --filter=ManageClass`
Expected: PASS (any existing tests touching these pages' EmONC-conditional UI still pass — the delegation is behavior-identical).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Program.php app/Filament/Resources/MentorshipResource/Pages/ManageClassMentees.php app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php tests/Unit/Models/ProgramIsEmoncTest.php
git commit -m "refactor: consolidate isEmonc() program-name check onto Program::isEmonc()"
```

---

### Task 2: Make `hasCompletedAllModules()` program-agnostic

**Files:**
- Modify: `app/Models/ClassParticipant.php`
- Test: `tests/Unit/Models/ClassParticipantHasCompletedAllModulesTest.php`

**Interfaces:**
- Consumes: `ModuleRubric` model (`app/Models/ModuleRubric.php`, existing, unmodified) — queried by `program_module_id` + `is_active`.
- Produces: `hasCompletedAllModules(): bool` — same signature as before; behavior is now conditional on rubric existence per module. Consumed by `syncCompletionStatus()` (unmodified, already shipped) and by Task 3's `isReadyForHeadDrmhCertification()`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit\Models;

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
use Tests\TestCase;

class ClassParticipantHasCompletedAllModulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{participant: ClassParticipant, classModule: ClassModule, programModule: ProgramModule}
     */
    private function makeParticipantWithOneModule(string $programName): array
    {
        $program = Program::factory()->create(['name' => $programName]);
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

        return compact('participant', 'classModule', 'programModule');
    }

    public function test_emonc_participant_with_pending_video_review_is_not_complete(): void
    {
        ['participant' => $participant, 'classModule' => $classModule, 'programModule' => $programModule]
            = $this->makeParticipantWithOneModule('Maternal Health (EmONC)');

        ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Rubric',
            'total_marks' => 1,
            'pass_marks' => 1,
            'pass_percentage' => 100,
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'pending',
        ]);

        $this->assertFalse($participant->hasCompletedAllModules());
    }

    public function test_emonc_participant_with_passed_video_review_is_complete(): void
    {
        ['participant' => $participant, 'classModule' => $classModule, 'programModule' => $programModule]
            = $this->makeParticipantWithOneModule('Maternal Health (EmONC)');

        ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Rubric',
            'total_marks' => 1,
            'pass_marks' => 1,
            'pass_percentage' => 100,
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'passed',
        ]);

        $this->assertTrue($participant->hasCompletedAllModules());
    }

    public function test_non_emonc_participant_with_completed_progress_and_no_rubric_is_complete(): void
    {
        ['participant' => $participant, 'classModule' => $classModule] = $this->makeParticipantWithOneModule('Newborn Care');

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'pending', // never reviewed — no rubric exists to review against
        ]);

        $this->assertTrue($participant->hasCompletedAllModules());
    }

    public function test_non_emonc_participant_with_incomplete_progress_is_not_complete(): void
    {
        ['participant' => $participant, 'classModule' => $classModule] = $this->makeParticipantWithOneModule('Newborn Care');

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
            'video_review_status' => 'pending',
        ]);

        $this->assertFalse($participant->hasCompletedAllModules());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Unit/Models/ClassParticipantHasCompletedAllModulesTest.php`
Expected: The two EmONC tests PASS already (current behavior). The two non-EmONC tests FAIL — `test_non_emonc_participant_with_completed_progress_and_no_rubric_is_complete` currently returns `false` (blocked by the unconditional video-review check) instead of the expected `true`.

- [ ] **Step 3: Rewrite `hasCompletedAllModules()`**

In `app/Models/ClassParticipant.php`, add the import at the top:

```php
use App\Models\ModuleRubric;
```

(Not needed — `ModuleRubric` is already in the same `App\Models` namespace as `ClassParticipant`, so no `use` import is required. Skip this step.)

Replace the method body:

```php
    public function hasCompletedAllModules(): bool
    {
        $class = $this->relationLoaded('mentorshipClass') ? $this->mentorshipClass : $this->mentorshipClass()->first();

        if (! $class) {
            return false;
        }

        $classModules = $class->classModules()->get();

        if ($classModules->isEmpty()) {
            return false;
        }

        $progressRecords = $this->moduleProgress()
            ->whereIn('class_module_id', $classModules->pluck('id'))
            ->get()
            ->keyBy('class_module_id');

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

Update the docblock immediately above it (currently: "Every module in the class has mentee progress that is completed/exempted, with a passed video review — the gate for mentor approval and, transitively, certification.") to:

```php
    /**
     * Every module in the class has mentee progress that is completed/exempted.
     * Modules with an active rubric additionally require a passed video review;
     * modules with no rubric (non-EmONC programs never have one) are judged on
     * progress status alone. The gate for mentor approval and, transitively,
     * certification.
     */
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Models/ClassParticipantHasCompletedAllModulesTest.php`
Expected: PASS on all 4.

- [ ] **Step 5: Run the completion-status-fix tests from the prior plan to confirm no regression**

Run: `php artisan test tests/Unit/Models/ClassParticipantSyncCompletionStatusTest.php tests/Feature/MentorshipResource/ActivityCompletionSyncsParticipantStatusTest.php tests/Feature/RubricAssessment/PassingRubricSyncsParticipantStatusTest.php tests/Feature/Console/SyncMentorshipCompletionStatusTest.php`
Expected: PASS on all (these all use EmONC scenarios with rubrics present, so behavior is unchanged).

- [ ] **Step 6: Commit**

```bash
git add app/Models/ClassParticipant.php tests/Unit/Models/ClassParticipantHasCompletedAllModulesTest.php
git commit -m "fix: hasCompletedAllModules() only requires a passed video review where a rubric exists"
```

---

### Task 3: `ClassParticipant::isReadyForHeadDrmhCertification()`

**Files:**
- Modify: `app/Models/ClassParticipant.php`
- Test: `tests/Unit/Models/ClassParticipantIsReadyForHeadDrmhCertificationTest.php`

**Interfaces:**
- Consumes: `Program::isEmonc()` (Task 1), `isMentorApproved()` (existing, unmodified), `hasCompletedAllModules()` (Task 2).
- Produces: `isReadyForHeadDrmhCertification(): bool` — consumed by Task 4 (three call sites).

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

class ClassParticipantIsReadyForHeadDrmhCertificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeParticipant(string $programName): ClassParticipant
    {
        $program = Program::factory()->create(['name' => $programName]);
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

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'passed',
        ]);

        return $participant;
    }

    public function test_emonc_participant_not_mentor_approved_is_not_ready(): void
    {
        $participant = $this->makeParticipant('Maternal Health (EmONC)');

        $this->assertFalse($participant->isReadyForHeadDrmhCertification());
    }

    public function test_emonc_participant_mentor_approved_is_ready(): void
    {
        $participant = $this->makeParticipant('Maternal Health (EmONC)');
        $participant->update(['mentor_approved_at' => now(), 'mentor_approved_by' => $participant->user_id]);

        $this->assertTrue($participant->isReadyForHeadDrmhCertification());
    }

    public function test_non_emonc_participant_with_all_modules_complete_is_ready_without_mentor_approval(): void
    {
        $participant = $this->makeParticipant('Newborn Care');

        $this->assertTrue($participant->isReadyForHeadDrmhCertification());
    }

    public function test_non_emonc_participant_with_incomplete_modules_is_not_ready(): void
    {
        $program = Program::factory()->create(['name' => 'Newborn Care']);
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

        $this->assertFalse($participant->isReadyForHeadDrmhCertification());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Unit/Models/ClassParticipantIsReadyForHeadDrmhCertificationTest.php`
Expected: FAIL — `isReadyForHeadDrmhCertification()` doesn't exist yet.

- [ ] **Step 3: Add the method**

In `app/Models/ClassParticipant.php`, add near `isCertified()`:

```php
    /**
     * The shared readiness gate for Head DRMH certification, used by both
     * the class roster page's Certify button and the Head DRMH Dashboard —
     * EmONC mentees still require mentor approval first; non-EmONC mentees
     * (which never go through mentor_approve — see ManageClassMentees.php)
     * are ready the moment they've completed every module.
     */
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

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Models/ClassParticipantIsReadyForHeadDrmhCertificationTest.php`
Expected: PASS on all 4.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ClassParticipant.php tests/Unit/Models/ClassParticipantIsReadyForHeadDrmhCertificationTest.php
git commit -m "feat: add ClassParticipant::isReadyForHeadDrmhCertification()"
```

---

### Task 4: Wire the shared readiness rule into the three certification call sites

**Files:**
- Modify: `app/Filament/Pages/HeadDrmhDashboard.php`
- Modify: `app/Filament/Pages/HeadDrmhReviewMentee.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ManageClassMentees.php`
- Test: `tests/Feature/HeadDrmh/NonEmoncCertificationTest.php`

**Interfaces:**
- Consumes: `ClassParticipant::isReadyForHeadDrmhCertification()` (Task 3).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\HeadDrmh;

use App\Filament\Pages\HeadDrmhDashboard;
use App\Filament\Pages\HeadDrmhReviewMentee;
use App\Filament\Resources\MentorshipResource\Pages\ManageClassMentees;
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
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NonEmoncCertificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{training: Training, class: MentorshipClass, participant: ClassParticipant}
     */
    private function makeReadyNonEmoncParticipant(): array
    {
        $program = Program::factory()->create(['name' => 'Newborn Care']);
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
            'mentorship_class_id' => $class->id, 'user_id' => $mentee->id, 'status' => 'completed',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id, 'class_module_id' => $classModule->id,
            'status' => 'completed', 'video_review_status' => 'pending',
        ]);

        return compact('training', 'class', 'participant');
    }

    private function actingAsHeadDrmh(): User
    {
        $user = User::factory()->create(['name' => 'Head DRMH']);
        Permission::firstOrCreate(['name' => 'page_HeadDrmhDashboard', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'page_HeadDrmhReviewMentee', 'guard_name' => 'web']);
        $user->givePermissionTo(['page_HeadDrmhDashboard', 'page_HeadDrmhReviewMentee']);
        $this->actingAs($user);

        return $user;
    }

    public function test_completed_non_emonc_participant_appears_in_head_drmh_pending_list(): void
    {
        $this->actingAsHeadDrmh();
        ['participant' => $participant] = $this->makeReadyNonEmoncParticipant();

        $component = Livewire::test(HeadDrmhDashboard::class);

        $pendingIds = collect($component->get('pendingList'))->pluck('id');

        $this->assertTrue($pendingIds->contains($participant->id));
    }

    public function test_head_drmh_can_certify_a_ready_non_emonc_participant_with_no_prior_mentor_approval(): void
    {
        $this->actingAsHeadDrmh();
        ['participant' => $participant] = $this->makeReadyNonEmoncParticipant();

        $this->assertNull($participant->mentor_approved_at);

        Livewire::withQueryParams(['participant' => $participant->id])
            ->test(HeadDrmhReviewMentee::class)
            ->call('certify');

        $this->assertNotNull($participant->fresh()->head_drmh_approved_at);
    }

    public function test_roster_page_certify_action_is_visible_and_works_for_a_ready_non_emonc_participant(): void
    {
        $mentor = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'page_HeadDrmhDashboard', 'guard_name' => 'web']);
        $mentor->givePermissionTo(['view_any_mentorship::training', 'page_HeadDrmhDashboard']);
        $this->actingAs($mentor);

        ['training' => $training, 'class' => $class, 'participant' => $participant] = $this->makeReadyNonEmoncParticipant();
        $training->update(['mentor_id' => $mentor->id]);

        Livewire::test(ManageClassMentees::class, [
            'training' => $training,
            'class' => $class,
        ])->callTableAction('head_drmh_certify', $participant);

        $this->assertNotNull($participant->fresh()->head_drmh_approved_at);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/HeadDrmh/NonEmoncCertificationTest.php`
Expected: FAIL on all 3 — the participant never appears in `pendingList` (query still requires `mentor_approved_at`), `certify()` still requires `isMentorApproved()`, and the roster action's `visible()` still requires `mentor_approved_at`.

- [ ] **Step 3: Update `HeadDrmhDashboard::loadData()`**

In `app/Filament/Pages/HeadDrmhDashboard.php`, replace:

```php
        $pending = ClassParticipant::query()
            ->whereNotNull('mentor_approved_at')
            ->whereNull('head_drmh_approved_at')
            ->whereHas('mentorshipClass.training', fn ($q) => $q->where('type', 'facility_mentorship'))
            ->with($with)
            ->orderByDesc('mentor_approved_at')
            ->get();
```

with:

```php
        $mentorApprovedPending = ClassParticipant::query()
            ->whereNotNull('mentor_approved_at')
            ->whereNull('head_drmh_approved_at')
            ->whereHas('mentorshipClass.training', fn ($q) => $q->where('type', 'facility_mentorship'))
            ->with($with)
            ->get();

        $nonEmoncPending = ClassParticipant::query()
            ->whereNull('mentor_approved_at')
            ->whereNull('head_drmh_approved_at')
            ->where('status', 'completed')
            ->whereHas('mentorshipClass.training', function ($q) {
                $q->where('type', 'facility_mentorship')
                    ->whereHas('program', fn ($pq) => $pq->whereRaw('LOWER(name) NOT LIKE ?', ['%maternal%'])
                        ->orWhereRaw('LOWER(name) NOT LIKE ?', ['%emonc%']));
            })
            ->with($with)
            ->get();

        $pending = $mentorApprovedPending->concat($nonEmoncPending)
            ->sortByDesc(fn (ClassParticipant $p) => $p->mentor_approved_at ?? $p->updated_at)
            ->values();
```

(`Training::program()` must exist as a `belongsTo(Program::class)` relation for the `whereHas('program', ...)` closure above — verify this during implementation; if the relation is named differently, adjust the closure's relation name accordingly. This is a read verification step, not a code change: `grep -n "function program" app/Models/Training.php`.)

- [ ] **Step 4: Update `HeadDrmhReviewMentee::mount()` and `certify()`**

In `app/Filament/Pages/HeadDrmhReviewMentee.php`, replace:

```php
        $this->canCertify  = $this->participant->isMentorApproved() && ! $this->isCertified;
```

with:

```php
        $this->canCertify  = $this->participant->isReadyForHeadDrmhCertification() && ! $this->isCertified;
```

And replace the guard in `certify()`:

```php
        if (! $this->participant->isMentorApproved()) {
            Notification::make()->danger()
                ->title('Mentor approval required')
                ->body('The mentor must approve this mentee before Head DRMH can certify.')
                ->send();

            return;
        }
```

with:

```php
        if (! $this->participant->isReadyForHeadDrmhCertification()) {
            Notification::make()->danger()
                ->title('Not Ready for Certification')
                ->body('This mentee has not yet met the requirements for certification.')
                ->send();

            return;
        }
```

- [ ] **Step 5: Update `ManageClassMentees.php`'s `head_drmh_certify` action**

Replace:

```php
                Tables\Actions\Action::make('head_drmh_certify')
                    ->label('Head DRMH Certify')
                    ->icon('heroicon-o-shield-check')
                    ->color('primary')
                    ->visible(fn (ClassParticipant $record) => $this->canHeadDrmhCertify() &&
                        $record->mentor_approved_at &&
                        ! $record->head_drmh_approved_at &&
                        $record->status === 'completed'
                    )
```

with:

```php
                Tables\Actions\Action::make('head_drmh_certify')
                    ->label('Head DRMH Certify')
                    ->icon('heroicon-o-shield-check')
                    ->color('primary')
                    ->visible(fn (ClassParticipant $record) => $this->canHeadDrmhCertify() &&
                        $record->isReadyForHeadDrmhCertification() &&
                        ! $record->head_drmh_approved_at &&
                        $record->status === 'completed'
                    )
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/HeadDrmh/NonEmoncCertificationTest.php`
Expected: PASS on all 3.

- [ ] **Step 7: Run the full regression set — every EmONC-focused test touching these three files, plus Tasks 1-3's tests**

Run: `php artisan test tests/Unit/Models/ProgramIsEmoncTest.php tests/Unit/Models/ClassParticipantHasCompletedAllModulesTest.php tests/Unit/Models/ClassParticipantIsReadyForHeadDrmhCertificationTest.php tests/Feature/HeadDrmh/NonEmoncCertificationTest.php tests/Unit/Models/ClassParticipantSyncCompletionStatusTest.php tests/Feature/MentorshipResource/ActivityCompletionSyncsParticipantStatusTest.php tests/Feature/RubricAssessment/PassingRubricSyncsParticipantStatusTest.php tests/Feature/Console/SyncMentorshipCompletionStatusTest.php tests/Feature/ConductRubricAssessmentClassModuleTest.php tests/Feature/ManageModuleMenteesNotificationTest.php`
Expected: PASS on everything.

- [ ] **Step 8: Run the full project test suite**

Run: `php artisan test`
Expected: PASS (0 failures; the pre-existing "risky" warnings are a documented cosmetic artifact, not real failures).

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Pages/HeadDrmhDashboard.php app/Filament/Pages/HeadDrmhReviewMentee.php app/Filament/Resources/MentorshipResource/Pages/ManageClassMentees.php tests/Feature/HeadDrmh/NonEmoncCertificationTest.php
git commit -m "feat: extend Head DRMH certification to completed non-EmONC mentees"
```

---

## Deferred / not in this plan

- Cosmetic cleanup of `HeadDrmhReviewMentee`'s review page video-review section for non-EmONC modules (spec's "Out of scope" §1).
- The four-mentorship-creation-flow simplification — separate, queued next.
- Running any real-database backfill — this plan only changes code/tests; whether any already-completed non-EmONC participants should be surfaced immediately is a follow-up decision after this merges (the existing `mentorship:sync-completion-status` command's dry-run, re-run after this change, will show whether any exist).

## Self-Review

**Spec coverage:** §1 (Program::isEmonc() + delegation) → Task 1. §2 (hasCompletedAllModules() fix — note the spec numbered this section 2 under "Fix hasCompletedAllModules()" and the consolidation as §1; the plan's Task 1/Task 2 ordering swaps them versus the spec's read order for dependency reasons — Program::isEmonc() must exist before isReadyForHeadDrmhCertification() needs it, but hasCompletedAllModules()'s fix has no such dependency, so either order works; kept Program::isEmonc() first since Task 3 depends on it and it's the smallest, lowest-risk change) → Task 2. §3 (isReadyForHeadDrmhCertification()) → Task 3. §4 (three call sites) → Task 4. §5 (markHeadDrmhApproved() unaffected) → verified by Task 4's tests calling it indirectly with no changes to the method itself.

**Placeholder scan:** No TBD/TODO. Task 4 Step 3 includes one explicit "verify during implementation" note for `Training::program()`'s relation name — this is a genuine unknown flagged for the implementer to resolve by reading the model (one grep), not a placeholder for unwritten logic; the surrounding code is complete and concrete either way.

**Type consistency:** `isReadyForHeadDrmhCertification(): bool` used identically across Tasks 3 and 4. `Program::isEmonc(): bool` used identically across Tasks 1 and 3.
