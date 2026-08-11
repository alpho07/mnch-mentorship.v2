# Phase 5a Notification Coverage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Newborn Care and Infant/Child Care mentees the same actionable notifications EmONC mentees already get for two events: a mentor writing feedback, and completing a module.

**Architecture:** Two new methods on the existing `EmoncNotificationService` (already program-agnostic internally, just never called from non-EmONC paths), wired into the two exact places those events already happen in code today.

**Tech Stack:** Laravel 12, PHPUnit (SQLite in-memory, `RefreshDatabase`). Filament's `Notification::make()->sendToDatabase($user)` ultimately calls `$user->notify(new \Filament\Notifications\DatabaseNotification($data))` — a real Laravel notification class — so it's testable with Laravel's standard `Illuminate\Support\Facades\Notification::fake()` / `assertSentTo()`, not Filament's own `assertNotified()` helper (which only inspects session-flashed notifications, not database-persisted ones).

## Global Constraints

- Do not rename `EmoncNotificationService` — it's already program-agnostic internally; renaming would touch every existing call site for no functional benefit and is out of scope.
- Do not add a notification for attendance confirmation — too frequent/low-value per the guide's "avoid excessive notifications" guidance.
- Do not add deadlines to notification content in this pass — explicitly deferred, a separate retrofit across all notification types.
- Every new method must follow the exact structure of the existing methods in the class (null-safe user resolution, no-op if absent, reuse the private `notify()` helper).

---

### Task 1: Two new notification methods

**Files:**
- Modify: `app/Services/EmoncNotificationService.php`
- Test: `tests/Unit/Services/EmoncNotificationServiceTest.php`

**Interfaces:**
- Produces: `EmoncNotificationService::mentorRecommendationWritten(MenteeModuleProgress $progress): void` and `::moduleCompleted(MenteeModuleProgress $progress): void`. Consumed by Task 2 and Task 3.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/EmoncNotificationServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\EmoncNotificationService;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class EmoncNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProgress(string $programName): array
    {
        $mentee = User::factory()->create();
        $program = Program::factory()->create(['name' => $programName]);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'Essential Newborn Care']);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);

        return compact('mentee', 'progress');
    }

    public function test_mentor_recommendation_written_notifies_newborn_care_mentee(): void
    {
        NotificationFacade::fake();
        ['mentee' => $mentee, 'progress' => $progress] = $this->makeProgress('Newborn Care');

        app(EmoncNotificationService::class)->mentorRecommendationWritten($progress);

        NotificationFacade::assertSentTo($mentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Mentor Feedback Received';
        });
    }

    public function test_mentor_recommendation_written_notifies_infant_child_care_mentee(): void
    {
        NotificationFacade::fake();
        ['mentee' => $mentee, 'progress' => $progress] = $this->makeProgress('Infant and Child Care');

        app(EmoncNotificationService::class)->mentorRecommendationWritten($progress);

        NotificationFacade::assertSentTo($mentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Mentor Feedback Received';
        });
    }

    public function test_module_completed_notifies_mentee(): void
    {
        NotificationFacade::fake();
        ['mentee' => $mentee, 'progress' => $progress] = $this->makeProgress('Newborn Care');

        app(EmoncNotificationService::class)->moduleCompleted($progress);

        NotificationFacade::assertSentTo($mentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Module Completed'
                && str_contains($notification->data['body'], 'Essential Newborn Care');
        });
    }

    public function test_no_notification_sent_when_participant_has_no_user(): void
    {
        NotificationFacade::fake();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        // Soft-delete the user rather than pointing at a non-existent id — class_participants.user_id
        // has an enforced foreign key, so an id with no matching row would fail at insert time.
        // A soft-deleted user still satisfies the FK but is excluded by User's default scope, so
        // $participant->user resolves to null exactly like the "no user" case this test targets.
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        $mentee->delete();
        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);

        app(EmoncNotificationService::class)->mentorRecommendationWritten($progress);
        app(EmoncNotificationService::class)->moduleCompleted($progress);

        NotificationFacade::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/EmoncNotificationServiceTest.php`
Expected: FAIL — `mentorRecommendationWritten()` and `moduleCompleted()` do not exist on `EmoncNotificationService`.

- [ ] **Step 3: Write the implementation**

In `app/Services/EmoncNotificationService.php`, add these two methods immediately after `headDrmhCertified()` (before the private `notify()` helper):

```php
    public function mentorRecommendationWritten(MenteeModuleProgress $progress): void
    {
        $user = $progress->classParticipant?->user;
        if (! $user) {
            return;
        }

        $classModule = $progress->classModule;
        $moduleName = $classModule?->programModule?->name ?? 'a module';
        $classId = $classModule?->mentorship_class_id;

        $this->notify(
            $user,
            'Mentor Feedback Received',
            "New Feedback — {$moduleName}",
            "Your mentor has written feedback on your progress in {$moduleName}. Review it on your dashboard.",
            $classId ? route('mentee.class.progress', $classId) : null,
            'View Feedback'
        );
    }

    public function moduleCompleted(MenteeModuleProgress $progress): void
    {
        $user = $progress->classParticipant?->user;
        if (! $user) {
            return;
        }

        $classModule = $progress->classModule;
        $moduleName = $classModule?->programModule?->name ?? 'a module';
        $classId = $classModule?->mentorship_class_id;

        $this->notify(
            $user,
            'Module Completed',
            "Module Completed — {$moduleName}",
            "You've completed {$moduleName}. Great work!",
            $classId ? route('mentee.class.progress', $classId) : null,
            'View My Progress'
        );
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Services/EmoncNotificationServiceTest.php`
Expected: PASS — all 4 tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Services/EmoncNotificationService.php tests/Unit/Services/EmoncNotificationServiceTest.php
git commit -m "feat: add mentorRecommendationWritten and moduleCompleted notification methods"
```

---

### Task 2: Wire mentor-feedback notification into `ManageModuleMentees`

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ManageModuleMentees.php`
- Test: `tests/Feature/ManageModuleMenteesNotificationTest.php`

**Interfaces:**
- Consumes: `EmoncNotificationService::mentorRecommendationWritten(MenteeModuleProgress $progress): void` (Task 1). `EmoncNotificationService` is already imported in this file (line 13).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ManageModuleMenteesNotificationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ManageModuleMentees;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManageModuleMenteesNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_writing_a_recommendation_notifies_the_mentee(): void
    {
        NotificationFacade::fake();

        $mentor = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $mentor->givePermissionTo('view_any_mentorship::training');

        $mentee = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'Essential Newborn Care']);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        // The write_recommendation action is gated by ManageModuleMentees::isPresent(), which
        // requires either an in_progress/completed progress row or a ClassAttendance record —
        // without one, the action is invisible and callTableAction() would fail.
        ClassAttendance::create([
            'class_id' => $class->id,
            'class_module_id' => $classModule->id,
            'user_id' => $mentee->id,
            'marked_by' => $mentor->id,
            'marked_at' => now(),
            'source' => 'manual',
        ]);

        $this->actingAs($mentor);

        Livewire::test(ManageModuleMentees::class, [
            'training' => $training,
            'class' => $class,
            'module' => $classModule,
        ])
            ->callTableAction('write_recommendation', $participant, data: [
                'mentor_recommendation' => 'Great progress on newborn resuscitation technique.',
            ]);

        NotificationFacade::assertSentTo($mentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Mentor Feedback Received';
        });
    }
}
```

The action name (`write_recommendation`) has been verified directly against `ManageModuleMentees.php:434` — confirmed correct, no placeholder. Note also that the page's mount parameters (`training`/`class`/`module`) must be passed as model instances, not raw IDs — `Livewire::test()` bypasses Filament's route-model-binding, so a raw int fails to coerce into the page's typed `Training`/`MentorshipClass`/`ClassModule` properties.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ManageModuleMenteesNotificationTest.php`
Expected: FAIL — no notification sent yet (the call site hasn't been wired).

- [ ] **Step 3: Wire the notification**

In `app/Filament/Resources/MentorshipResource/Pages/ManageModuleMentees.php`, find the recommendation action's `->action(...)` closure:

```php
                        ->action(function (ClassParticipant $record, array $data) {
                            // Always query fresh from DB — never use eager-loaded moduleProgress
                            MenteeModuleProgress::updateOrCreate(
                                [
                                    'class_participant_id' => $record->id,
                                    'class_module_id' => $this->module->id,
                                ],
                                [
                                    'status' => 'in_progress',
                                    'started_at' => now(),
                                    'mentor_recommendation' => $data['mentor_recommendation'],
                                    'recommendation_by' => auth()->id(),
                                    'recommendation_written_at' => now(),
                                ]
                            );

                            Notification::make()
                                ->success()
                                ->title('Recommendation Saved')
                                ->body("Recommendation written for {$record->user?->full_name}.")
                                ->send();
                        }),
```

Replace with:

```php
                        ->action(function (ClassParticipant $record, array $data) {
                            // Always query fresh from DB — never use eager-loaded moduleProgress
                            $progress = MenteeModuleProgress::updateOrCreate(
                                [
                                    'class_participant_id' => $record->id,
                                    'class_module_id' => $this->module->id,
                                ],
                                [
                                    'status' => 'in_progress',
                                    'started_at' => now(),
                                    'mentor_recommendation' => $data['mentor_recommendation'],
                                    'recommendation_by' => auth()->id(),
                                    'recommendation_written_at' => now(),
                                ]
                            );

                            app(EmoncNotificationService::class)->mentorRecommendationWritten($progress);

                            Notification::make()
                                ->success()
                                ->title('Recommendation Saved')
                                ->body("Recommendation written for {$record->user?->full_name}.")
                                ->send();
                        }),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/ManageModuleMenteesNotificationTest.php`
Expected: PASS. If the action name or route parameters don't match this file's actual declarations, fix the test to match reality (read the actual action `->name()`/key and the page's `getPages()` route parameter names) rather than changing the source to match a guessed test.

- [ ] **Step 5: Run the full notification test group to check for regressions**

Run: `php artisan test tests/Feature/ManageModuleMenteesNotificationTest.php tests/Unit/Services/EmoncNotificationServiceTest.php`
Expected: PASS, all tests green.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/ManageModuleMentees.php tests/Feature/ManageModuleMenteesNotificationTest.php
git commit -m "feat: notify mentee when mentor writes a recommendation"
```

---

### Task 3: Wire module-completed notification into `ClassModule::complete()`

**Files:**
- Modify: `app/Models/ClassModule.php`
- Test: `tests/Unit/Models/ClassModuleCompleteNotificationTest.php`

**Interfaces:**
- Consumes: `EmoncNotificationService::moduleCompleted(MenteeModuleProgress $progress): void` (Task 1).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/ClassModuleCompleteNotificationTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class ClassModuleCompleteNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_notifies_attended_mentees_but_not_absent_ones(): void
    {
        NotificationFacade::fake();

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'Essential Newborn Care']);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        $attendedMentee = User::factory()->create();
        $attendedParticipant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $attendedMentee->id,
            'status' => 'active',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $attendedParticipant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);

        $absentMentee = User::factory()->create();
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $absentMentee->id,
            'status' => 'active',
        ]);
        // No MenteeModuleProgress row at all for the absent mentee — never confirmed attendance.

        $classModule->complete();

        NotificationFacade::assertSentTo($attendedMentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Module Completed';
        });
        NotificationFacade::assertNotSentTo($absentMentee, DatabaseNotification::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/ClassModuleCompleteNotificationTest.php`
Expected: FAIL — no notification sent yet.

- [ ] **Step 3: Wire the notification**

In `app/Models/ClassModule.php`, add the import alongside the existing ones:

```php
use App\Services\EmoncNotificationService;
```

Find the `if ($attended)` branch inside `complete()`:

```php
                if ($attended) {
                    // Attended → mark completed.
                    // Two-step firstOrCreate then update avoids passing DB::raw()
                    // in the updateOrCreate values array, which triggers a TypeError
                    // when Eloquent's casting layer calls preg_match on the Expression object.
                    $progress = MenteeModuleProgress::firstOrCreate(
                        [
                            'class_participant_id' => $participant->id,
                            'class_module_id' => $this->id,
                        ],
                        [
                            'status' => 'completed',
                            'started_at' => now(),
                            'completed_at' => now(),
                            'attendance_percentage' => 100.0,
                        ]
                    );

                    if (! $progress->wasRecentlyCreated) {
                        $progress->update([
                            'status' => 'completed',
                            'started_at' => $progress->started_at ?? now(),
                            'completed_at' => now(),
                            'attendance_percentage' => 100.0,
                        ]);
                    }
                } else {
```

Replace with:

```php
                if ($attended) {
                    // Attended → mark completed.
                    // Two-step firstOrCreate then update avoids passing DB::raw()
                    // in the updateOrCreate values array, which triggers a TypeError
                    // when Eloquent's casting layer calls preg_match on the Expression object.
                    $progress = MenteeModuleProgress::firstOrCreate(
                        [
                            'class_participant_id' => $participant->id,
                            'class_module_id' => $this->id,
                        ],
                        [
                            'status' => 'completed',
                            'started_at' => now(),
                            'completed_at' => now(),
                            'attendance_percentage' => 100.0,
                        ]
                    );

                    if (! $progress->wasRecentlyCreated) {
                        $progress->update([
                            'status' => 'completed',
                            'started_at' => $progress->started_at ?? now(),
                            'completed_at' => now(),
                            'attendance_percentage' => 100.0,
                        ]);
                    }

                    app(EmoncNotificationService::class)->moduleCompleted($progress);
                } else {
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/ClassModuleCompleteNotificationTest.php`
Expected: PASS

- [ ] **Step 5: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: PASS, with only the two known pre-existing, unrelated failures (`LookupApiTest::user_search_filters_by_facility`, `Feature\ExampleTest`).

- [ ] **Step 6: Manually verify in browser**

Log in as a mentor with a Newborn Care or Infant/Child Care class, write a mentor recommendation for a mentee, and confirm the mentee's account shows the new notification (check the Filament notification bell icon while impersonating/logged in as that mentee, or query `$mentee->notifications` directly via tinker). Separately, use "Complete Module" on a non-EmONC module with at least one attended mentee and confirm they receive a "Module Completed" notification.

- [ ] **Step 7: Commit**

```bash
git add app/Models/ClassModule.php tests/Unit/Models/ClassModuleCompleteNotificationTest.php
git commit -m "feat: notify mentee when their module is marked completed"
```

---

## Self-Review Notes

- **Spec coverage:** Section 3 (two new methods) → Task 1. Section 4 (two call sites) → Tasks 2 and 3. Section 5 (edge cases: null user, absent mentees, email-failure resilience via the existing `notify()` helper) → covered by Task 1's null-user test and Task 3's attended-vs-absent test. Section 6 (testing) → each listed scenario has a corresponding test.
- **Placeholder scan:** no TBD/TODO markers. Task 2's test includes an explicit caveat that the action name/route parameters must be verified against the real file rather than assumed — this is disclosed uncertainty about a UI action's exact identifier, not a placeholder for logic; the plan still specifies exactly what to do if the guess is wrong (fix the test, not the source).
- **Type consistency:** `mentorRecommendationWritten(MenteeModuleProgress $progress)` and `moduleCompleted(MenteeModuleProgress $progress)` signatures are identical between Task 1's definition and Tasks 2/3's call sites.
