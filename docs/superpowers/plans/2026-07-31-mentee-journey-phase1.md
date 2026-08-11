# Mentee Journey Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the mentee dashboard around one prioritized "next best action," fix a real feedback-visibility bug (failed video reviews never reach the mentee), and add basic upload resilience for the highest-risk mentee interaction (hands-on video upload on rural connectivity).

**Architecture:** A new stateless service, `MenteeNextActionResolver`, computes a single ranked action across all of a mentee's enrollments/modules (both EmONC and non-EmONC programs). `MenteeDashboard` (a Filament Page / Livewire component) calls it once per page load and exposes the result as `$nextAction`. The Blade view renders it as a hero card above the existing (now collapsed-by-default) stats/donut/feed/class-accordion content. Two small, independent follow-on changes (feedback surfacing, empty-state copy, upload JS) round out Phase 1.

**Tech Stack:** Laravel 12, Filament v3 (Livewire pages), Blade + Alpine.js, PHPUnit (SQLite in-memory, `RefreshDatabase` per test class — this codebase's established pattern, see `tests/Unit/Models/ScopeTest.php`).

## Global Constraints

- Reuse the existing EmONC-detection pattern exactly as it appears elsewhere in the codebase: `str_contains(strtolower($program->name), 'maternal') && str_contains($programName, 'emonc')`. Do not extract it into a shared helper — that's out of scope for this plan.
- Do not modify the `mentee_module_progress`, `class_modules`, `class_participants`, or any other table schema. Every field this plan reads already exists.
- Every new PHP class needs a corresponding test using `Illuminate\Foundation\Testing\RefreshDatabase` on SQLite in-memory (already configured in `phpunit.xml`), following the pattern in `tests/Unit/Models/ScopeTest.php`.
- Preserve all existing computed dashboard data (`globalStats`, `enrollments`, `activityFeed`) — this plan relocates/extends it, never deletes it.

---

### Task 1: `MenteeNextActionResolver` service

**Files:**
- Create: `app/Services/MenteeNextActionResolver.php`
- Test: `tests/Unit/Services/MenteeNextActionResolverTest.php`

**Interfaces:**
- Produces: `MenteeNextActionResolver::resolve(User $mentee): array` returning:
  ```php
  [
      'tier' => int,       // 1-6
      'label' => string,   // button text
      'headline' => string,
      'subtext' => string,
      'url' => string,
      'meta' => array,     // tier-specific extra data, e.g. ['video_review_notes' => '...']
  ]
  ```
  This is consumed by Task 2 (`MenteeDashboard::loadDashboard()`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/MenteeNextActionResolverTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\ClassSession;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Models\Training;
use App\Models\User;
use App\Services\MenteeNextActionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenteeNextActionResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeEnrollment(string $programName, string $moduleStatus = 'in_progress'): array
    {
        $mentee = User::factory()->create();
        $program = Program::factory()->create(['name' => $programName]);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create([
            'program_id' => $program->id,
            'name' => 'Postpartum Haemorrhage Management',
        ]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => $moduleStatus,
        ]);
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);

        return compact('mentee', 'program', 'training', 'class', 'programModule', 'classModule', 'participant');
    }

    private function makeQuizAttempt(ProgramModule $programModule, User $mentee, string $type): QuizAttempt
    {
        $quiz = ProgramModuleQuiz::create([
            'program_module_id' => $programModule->id,
            'type' => $type,
            'title' => ucfirst(str_replace('_', ' ', $type)),
            'order_sequence' => 1,
        ]);

        return QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $mentee->id,
            'attempt_type' => $type,
            'score' => 80,
            'total_questions' => 10,
            'correct_answers' => 8,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function test_failed_video_review_wins_over_everything_else(): void
    {
        $env = $this->makeEnrollment('Maternal Health (EmONC)');
        $preTest = $this->makeQuizAttempt($env['programModule'], $env['mentee'], 'pre_test');

        MenteeModuleProgress::create([
            'class_participant_id' => $env['participant']->id,
            'class_module_id' => $env['classModule']->id,
            'status' => 'in_progress',
            'pre_test_attempt_id' => $preTest->id,
            'video_review_status' => 'failed',
            'video_review_notes' => 'Please redo the bimanual compression steps.',
        ]);

        $result = (new MenteeNextActionResolver())->resolve($env['mentee']);

        $this->assertSame(1, $result['tier']);
        $this->assertSame('Review Mentor Feedback', $result['label']);
        $this->assertSame('Please redo the bimanual compression steps.', $result['meta']['video_review_notes']);
    }

    public function test_open_attendance_link_beats_unconfirmed_module(): void
    {
        $env = $this->makeEnrollment('Maternal Health (EmONC)');
        $env['classModule']->update([
            'attendance_link_active' => true,
            'attendance_token' => 'test-token-123',
        ]);

        MenteeModuleProgress::create([
            'class_participant_id' => $env['participant']->id,
            'class_module_id' => $env['classModule']->id,
            'status' => 'not_started',
        ]);

        $result = (new MenteeNextActionResolver())->resolve($env['mentee']);

        $this->assertSame(2, $result['tier']);
        $this->assertSame('Confirm Attendance', $result['label']);
        $this->assertStringContainsString('test-token-123', $result['url']);
    }

    public function test_emonc_mentee_gets_continue_learning_when_pretest_already_taken(): void
    {
        $env = $this->makeEnrollment('Maternal Health (EmONC)');
        $preTest = $this->makeQuizAttempt($env['programModule'], $env['mentee'], 'pre_test');

        MenteeModuleProgress::create([
            'class_participant_id' => $env['participant']->id,
            'class_module_id' => $env['classModule']->id,
            'status' => 'in_progress',
            'pre_test_attempt_id' => $preTest->id,
        ]);

        $result = (new MenteeNextActionResolver())->resolve($env['mentee']);

        $this->assertSame(3, $result['tier']);
        $this->assertSame('Continue Learning', $result['label']);
    }

    public function test_non_emonc_mentee_gets_continue_learning_when_in_progress(): void
    {
        $env = $this->makeEnrollment('Newborn Care');

        MenteeModuleProgress::create([
            'class_participant_id' => $env['participant']->id,
            'class_module_id' => $env['classModule']->id,
            'status' => 'in_progress',
        ]);

        $result = (new MenteeNextActionResolver())->resolve($env['mentee']);

        $this->assertSame(3, $result['tier']);
        $this->assertSame('Continue Learning', $result['label']);
    }

    public function test_post_test_available_after_video_passed(): void
    {
        $env = $this->makeEnrollment('Maternal Health (EmONC)');
        $preTest = $this->makeQuizAttempt($env['programModule'], $env['mentee'], 'pre_test');
        ProgramModuleQuiz::create([
            'program_module_id' => $env['programModule']->id,
            'type' => 'post_test',
            'title' => 'Post Test',
            'order_sequence' => 2,
        ]);

        MenteeModuleProgress::create([
            'class_participant_id' => $env['participant']->id,
            'class_module_id' => $env['classModule']->id,
            'status' => 'in_progress',
            'pre_test_attempt_id' => $preTest->id,
            'hands_on_video_url' => 'https://youtube.com/watch?v=abc12345678',
            'video_review_status' => 'passed',
            'post_test_attempt_id' => null,
        ]);

        $result = (new MenteeNextActionResolver())->resolve($env['mentee']);

        $this->assertSame(4, $result['tier']);
        $this->assertSame('Take Assessment', $result['label']);
    }

    public function test_pre_test_not_taken_on_emonc_module(): void
    {
        $env = $this->makeEnrollment('Maternal Health (EmONC)');

        MenteeModuleProgress::create([
            'class_participant_id' => $env['participant']->id,
            'class_module_id' => $env['classModule']->id,
            'status' => 'in_progress',
            'pre_test_attempt_id' => null,
        ]);

        $result = (new MenteeNextActionResolver())->resolve($env['mentee']);

        $this->assertSame(5, $result['tier']);
        $this->assertSame('Start Module', $result['label']);
    }

    public function test_falls_back_to_upcoming_session_when_nothing_urgent(): void
    {
        $env = $this->makeEnrollment('Newborn Care', moduleStatus: 'completed');

        MenteeModuleProgress::create([
            'class_participant_id' => $env['participant']->id,
            'class_module_id' => $env['classModule']->id,
            'status' => 'completed',
        ]);

        $futureModule = ClassModule::factory()->create([
            'mentorship_class_id' => $env['class']->id,
            'program_module_id' => $env['programModule']->id,
            'status' => 'not_started',
        ]);
        ClassSession::factory()->create([
            'class_module_id' => $futureModule->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '09:00:00',
            'status' => 'scheduled',
        ]);

        $result = (new MenteeNextActionResolver())->resolve($env['mentee']);

        $this->assertSame(6, $result['tier']);
        $this->assertStringContainsString('on track', strtolower($result['headline']));
    }

    public function test_falls_back_to_certificate_nudge_when_fully_certified(): void
    {
        $env = $this->makeEnrollment('Newborn Care', moduleStatus: 'completed');

        MenteeModuleProgress::create([
            'class_participant_id' => $env['participant']->id,
            'class_module_id' => $env['classModule']->id,
            'status' => 'completed',
        ]);

        $env['participant']->update([
            'mentor_approved_at' => now(),
            'mentor_approved_by' => $env['mentee']->id,
            'head_drmh_approved_at' => now(),
            'head_drmh_approved_by' => $env['mentee']->id,
        ]);

        $result = (new MenteeNextActionResolver())->resolve($env['mentee']);

        $this->assertSame(6, $result['tier']);
        $this->assertSame('Download Certificate', $result['label']);
    }

    public function test_no_data_module_does_not_throw(): void
    {
        $env = $this->makeEnrollment('Newborn Care', moduleStatus: 'in_progress');
        // No MenteeModuleProgress row created at all for this module.

        $result = (new MenteeNextActionResolver())->resolve($env['mentee']);

        $this->assertSame(6, $result['tier']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/MenteeNextActionResolverTest.php`
Expected: FAIL — `App\Services\MenteeNextActionResolver` does not exist.

- [ ] **Step 3: Write the implementation**

Create `app/Services/MenteeNextActionResolver.php`:

```php
<?php

namespace App\Services;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MenteeNextActionResolver
{
    public function resolve(User $mentee): array
    {
        $participants = ClassParticipant::with([
            'mentorshipClass.training.program',
            'mentorshipClass.classModules.programModule.quizzes',
            'mentorshipClass.classModules.sessions',
            'moduleProgress',
        ])
            ->where('user_id', $mentee->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->get();

        $candidates = collect();

        foreach ($participants as $participant) {
            $class = $participant->mentorshipClass;
            if (! $class) {
                continue;
            }

            $isEmonc = $this->isEmonc($class->training?->program?->name);
            $progressByModule = $participant->moduleProgress->keyBy('class_module_id');

            foreach ($class->classModules as $classModule) {
                if ($classModule->status === 'not_started') {
                    continue;
                }

                $progress = $progressByModule->get($classModule->id);
                if (! $progress) {
                    continue;
                }

                $candidate = $this->evaluateModule($participant, $classModule, $progress, $isEmonc);
                if ($candidate) {
                    $candidates->push($candidate);
                }
            }
        }

        if ($candidates->isEmpty()) {
            return $this->onTrackAction($participants);
        }

        return $candidates->sort(function (array $a, array $b) {
            if ($a['tier'] !== $b['tier']) {
                return $a['tier'] <=> $b['tier'];
            }
            if ($a['completion_fraction'] !== $b['completion_fraction']) {
                return $b['completion_fraction'] <=> $a['completion_fraction'];
            }

            return $b['sort_ts'] <=> $a['sort_ts'];
        })->first();
    }

    private function evaluateModule(
        ClassParticipant $participant,
        ClassModule $classModule,
        $progress,
        bool $isEmonc
    ): ?array {
        $moduleName = $classModule->programModule?->name ?? 'this module';
        $moduleUrl = route('mentee.class.module', [
            'class' => $participant->mentorship_class_id,
            'classModule' => $classModule->id,
        ]);

        if ($isEmonc && $progress->video_review_status === 'failed') {
            return [
                'tier' => 1,
                'label' => 'Review Mentor Feedback',
                'headline' => 'Your hands-on video needs changes',
                'subtext' => $moduleName,
                'url' => $moduleUrl,
                'meta' => ['video_review_notes' => $progress->video_review_notes],
                'completion_fraction' => 0.0,
                'sort_ts' => optional($progress->video_reviewed_at)->timestamp ?? 0,
            ];
        }

        $confirmed = ClassAttendance::where('user_id', $participant->user_id)
            ->where('class_module_id', $classModule->id)
            ->exists();

        if ($classModule->attendance_link_active && $classModule->status === 'in_progress' && ! $confirmed) {
            return [
                'tier' => 2,
                'label' => 'Confirm Attendance',
                'headline' => 'Confirm your attendance',
                'subtext' => $moduleName,
                'url' => route('module.attend', ['token' => $classModule->attendance_token]),
                'meta' => [],
                'completion_fraction' => 0.0,
                'sort_ts' => optional($classModule->started_at)->timestamp ?? 0,
            ];
        }

        $hasPostTestQuiz = $isEmonc
            && $classModule->programModule
            && $classModule->programModule->quizzes->contains(fn ($q) => $q->isPostTest());

        if ($isEmonc
            && $hasPostTestQuiz
            && $progress->hasSubmittedVideo()
            && $progress->isVideoPassed()
            && is_null($progress->post_test_attempt_id)
        ) {
            return [
                'tier' => 4,
                'label' => 'Take Assessment',
                'headline' => 'Take your post-test',
                'subtext' => $moduleName,
                'url' => $moduleUrl,
                'meta' => [],
                'completion_fraction' => 0.0,
                'sort_ts' => optional($progress->video_reviewed_at)->timestamp ?? 0,
            ];
        }

        if ($isEmonc && $progress->status === 'in_progress' && is_null($progress->pre_test_attempt_id)) {
            return [
                'tier' => 5,
                'label' => 'Start Module',
                'headline' => 'Start your pre-test',
                'subtext' => $moduleName,
                'url' => $moduleUrl,
                'meta' => [],
                'completion_fraction' => 0.0,
                'sort_ts' => optional($progress->started_at)->timestamp ?? 0,
            ];
        }

        if ($progress->status === 'in_progress') {
            $fraction = $this->completionFraction($participant, $classModule, $isEmonc);

            return [
                'tier' => 3,
                'label' => 'Continue Learning',
                'headline' => 'Continue your current module',
                'subtext' => $moduleName.' — '.((int) round($fraction * 100)).'% completed',
                'url' => $moduleUrl,
                'meta' => ['completion_percent' => (int) round($fraction * 100)],
                'completion_fraction' => $fraction,
                'sort_ts' => optional($progress->updated_at)->timestamp ?? 0,
            ];
        }

        return null;
    }

    private function completionFraction(ClassParticipant $participant, ClassModule $classModule, bool $isEmonc): float
    {
        if ($isEmonc) {
            $total = ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
                ->where('class_participant_id', $participant->id)
                ->count();

            if ($total === 0) {
                return 0.0;
            }

            $done = ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
                ->where('class_participant_id', $participant->id)
                ->where('status', 'completed')
                ->count();

            return $done / $total;
        }

        $totalSessions = $classModule->sessions->count();
        if ($totalSessions === 0) {
            return 0.0;
        }

        $attended = $classModule->sessions->filter(
            fn ($session) => $session->attendanceRecords()
                ->where('class_participant_id', $participant->id)
                ->where('status', 'present')
                ->exists()
        )->count();

        return $attended / $totalSessions;
    }

    private function onTrackAction(Collection $participants): array
    {
        $nextSession = null;

        foreach ($participants as $participant) {
            foreach ($participant->mentorshipClass?->classModules ?? [] as $classModule) {
                foreach ($classModule->sessions as $session) {
                    if ($session->status !== 'scheduled' || ! $session->scheduled_date) {
                        continue;
                    }
                    if (Carbon::parse($session->scheduled_date)->isPast()) {
                        continue;
                    }
                    if (! $nextSession || Carbon::parse($session->scheduled_date)->lt(Carbon::parse($nextSession->scheduled_date))) {
                        $nextSession = $session;
                    }
                }
            }
        }

        if ($nextSession) {
            return [
                'tier' => 6,
                'label' => 'View Class',
                'headline' => "You're on track",
                'subtext' => 'Next session: '.Carbon::parse($nextSession->scheduled_date)->format('D, M j')
                    .($nextSession->scheduled_time ? ' at '.Carbon::parse($nextSession->scheduled_time)->format('H:i') : ''),
                'url' => route('mentee.class.progress', ['class' => $nextSession->classModule->mentorship_class_id]),
                'meta' => [],
            ];
        }

        $certifiedParticipant = $participants->first(fn ($p) => $p->isCertified());
        if ($certifiedParticipant) {
            return [
                'tier' => 6,
                'label' => 'Download Certificate',
                'headline' => "You're certified!",
                'subtext' => $certifiedParticipant->mentorshipClass?->name ?? '',
                'url' => route('reports.class.certificate', [
                    $certifiedParticipant->mentorship_class_id,
                    $certifiedParticipant->id,
                ]),
                'meta' => [],
            ];
        }

        return [
            'tier' => 6,
            'label' => 'View Class',
            'headline' => "You're on track",
            'subtext' => 'Nothing needs your attention right now.',
            'url' => route('mentee.class.progress', ['class' => $participants->first()->mentorship_class_id]),
            'meta' => [],
        ];
    }

    private function isEmonc(?string $programName): bool
    {
        $name = strtolower($programName ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Services/MenteeNextActionResolverTest.php`
Expected: PASS — all 9 tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Services/MenteeNextActionResolver.php tests/Unit/Services/MenteeNextActionResolverTest.php
git commit -m "feat: add MenteeNextActionResolver for cross-program next-best-action"
```

---

### Task 2: Wire the resolver into `MenteeDashboard`

**Files:**
- Modify: `app/Filament/Pages/MenteeDashboard.php`
- Test: `tests/Feature/MenteeDashboardNextActionTest.php`

**Interfaces:**
- Consumes: `MenteeNextActionResolver::resolve(User $mentee): array` (Task 1)
- Produces: `MenteeDashboard::$nextAction` (public array property), consumed by Task 3's Blade view.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MenteeDashboardNextActionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\MenteeDashboard;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenteeDashboardNextActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_resolver_output_as_next_action(): void
    {
        $mentee = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ])->tap(function ($participant) use ($classModule) {
            MenteeModuleProgress::create([
                'class_participant_id' => $participant->id,
                'class_module_id' => $classModule->id,
                'status' => 'in_progress',
            ]);
        });

        $this->actingAs($mentee);

        Livewire::test(MenteeDashboard::class)
            ->assertSet('nextAction.tier', 3)
            ->assertSet('nextAction.label', 'Continue Learning');
    }

    public function test_dashboard_with_no_enrollments_has_empty_next_action(): void
    {
        $mentee = User::factory()->create();
        $this->actingAs($mentee);

        Livewire::test(MenteeDashboard::class)
            ->assertSet('nextAction', []);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MenteeDashboardNextActionTest.php`
Expected: FAIL — `nextAction` property does not exist on `MenteeDashboard`.

- [ ] **Step 3: Wire the resolver**

In `app/Filament/Pages/MenteeDashboard.php`, add the import alongside the existing ones:

```php
use App\Services\MenteeNextActionResolver;
```

Add the new public property next to the existing ones (after `public array $activityFeed = [];`):

```php
    public array $activityFeed = [];

    public array $nextAction = [];
```

In `loadDashboard()`, update the early-return branch (currently):

```php
        if ($participants->isEmpty()) {
            $this->globalStats = $this->emptyGlobalStats();
            $this->enrollments = [];
            $this->activityFeed = [];

            return;
        }
```

to:

```php
        if ($participants->isEmpty()) {
            $this->globalStats = $this->emptyGlobalStats();
            $this->enrollments = [];
            $this->activityFeed = [];
            $this->nextAction = [];

            return;
        }
```

Remove the now-superseded per-enrollment "next module" computation. Delete this block from inside the `$this->enrollments = $participants->map(...)` closure:

```php
            $programName = strtolower($training?->program?->name ?? '');
            $isEmonc = str_contains($programName, 'maternal') && str_contains($programName, 'emonc');

            // Determine the next module a mentee should work on (EmONC only).
            // Only surface modules where the mentee has confirmed attendance (progress = in_progress).
            // Not-started modules are locked until the mentor starts them and attendance is confirmed.
            $nextModule = null;
            if ($isEmonc) {
                $nextModule = $mods->first(function ($m) {
                    return $m['progress_status'] === 'in_progress';
                });
            }
```

and replace with just:

```php
            $programName = strtolower($training?->program?->name ?? '');
            $isEmonc = str_contains($programName, 'maternal') && str_contains($programName, 'emonc');
```

(keeping `$isEmonc` — it's still returned in the per-enrollment array and consumed by `filament.components.mentee-class-card`). In the same closure's `return [...]` array, delete this now-dead key:

```php
                'next_module' => $nextModule ? [
                    'id' => $nextModule['id'],
                    'name' => $nextModule['name'],
                ] : null,
```

Finally, after step 8 (`$this->activityFeed = $this->buildActivityFeed(...)`) at the end of `loadDashboard()`, add:

```php
        // ── 9. Next-best-action ──────────────────────────────────────────────
        $this->nextAction = app(MenteeNextActionResolver::class)->resolve($user);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/MenteeDashboardNextActionTest.php`
Expected: PASS

- [ ] **Step 5: Run the full dashboard-related suite to check for regressions**

Run: `php artisan test tests/Feature/MenteeDashboardNextActionTest.php tests/Unit/Services/MenteeNextActionResolverTest.php`
Expected: PASS, all tests green.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/MenteeDashboard.php tests/Feature/MenteeDashboardNextActionTest.php
git commit -m "feat: wire MenteeNextActionResolver into MenteeDashboard as \$nextAction"
```

---

### Task 3: Hero card + collapse existing sections in the dashboard view

**Files:**
- Modify: `resources/views/filament/pages/mentee-dashboard.blade.php`
- Test: `tests/Feature/MenteeDashboardHeroTest.php`

**Interfaces:**
- Consumes: `$nextAction` (public property from Task 2), `$profile`, `$globalStats`, `$enrollments`, `$recommendations`, `$suggestedModules`, `$activityFeed` (all pre-existing).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MenteeDashboardHeroTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\MenteeDashboard;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenteeDashboardHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_card_shows_continue_learning_action(): void
    {
        $mentee = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
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
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($mentee);

        Livewire::test(MenteeDashboard::class)
            ->assertSee('Continue your current module')
            ->assertSee('Continue Learning')
            ->assertSee('Essential Newborn Care');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MenteeDashboardHeroTest.php`
Expected: FAIL — hero markup does not exist yet.

- [ ] **Step 3: Restructure the Blade view**

In `resources/views/filament/pages/mentee-dashboard.blade.php`, replace the block that runs from the end of the profile card through the start of the enrollments conditional — i.e. replace this entire contiguous region:

```blade
        {{-- ══ GLOBAL STATS ═══════════════════════════════════════════════════════ --}}
@php
$stats = $globalStats;
$cards = [
    ['icon' => '📚', 'value' => $stats['total_classes'],       'label' => 'Classes Enrolled',   'bg' => '#eff6ff', 'ic' => '#dbeafe', 'vc' => '#1d4ed8'],
    ['icon' => '✅', 'value' => $stats['completed_classes'],   'label' => 'Classes Completed',   'bg' => '#f0fdf4', 'ic' => '#dcfce7', 'vc' => '#16a34a'],
    ['icon' => '🔵', 'value' => $stats['active_classes'],      'label' => 'Classes Active',      'bg' => '#f0f9ff', 'ic' => '#e0f2fe', 'vc' => '#0284c7'],
    ['icon' => '🧩', 'value' => $stats['total_modules'],       'label' => 'Total Modules',       'bg' => '#faf5ff', 'ic' => '#ede9fe', 'vc' => '#7c3aed'],
    ['icon' => '🏆', 'value' => $stats['completed_modules'],   'label' => 'Modules Completed',   'bg' => '#f0fdf4', 'ic' => '#dcfce7', 'vc' => '#16a34a'],
    ['icon' => '⏳', 'value' => $stats['in_progress_modules'], 'label' => 'Modules In Progress', 'bg' => '#fffbeb', 'ic' => '#fef9c3', 'vc' => '#d97706'],
    ['icon' => '📋', 'value' => $stats['pending_attendance'],  'label' => 'Pending Attendance',  'bg' => '#fef2f2', 'ic' => '#fee2e2', 'vc' => '#dc2626'],
    ['icon' => '⭐', 'value' => $stats['exempted_modules'],    'label' => 'Modules Exempted',    'bg' => '#f8fafc', 'ic' => '#f1f5f9', 'vc' => '#475569'],
];
        @endphp

        <div class="md-stats-grid">
            @foreach($cards as $c)
                <div class="md-stat-card">
                    <div class="md-stat-icon" style="background:{{ $c['ic'] }}">{{ $c['icon'] }}</div>
                    <div>
                        <div class="md-stat-value" style="color:{{ $c['vc'] }}">{{ $c['value'] }}</div>
                        <div class="md-stat-label">{{ $c['label'] }}</div>
                    </div>
            </div>
        @endforeach
        </div>

{{-- ══ PENDING ENROLLMENT ══════════════════════════════════════════════════ --}}
        @php
            $pendingEnrollment = session('enrollment_intent');
        @endphp
        @if(is_array($pendingEnrollment) && ! empty($pendingEnrollment['class_id']))
            @php
                $pendingClass = \App\Models\MentorshipClass::with('training')->find($pendingEnrollment['class_id']);
            @endphp
            @if($pendingClass)
                <div class="md-card" style="background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 100%);border:1px solid #bbf7d0;margin-bottom:24px;">
                    <div style="display:flex;align-items:flex-start;gap:16px;">
                        <div style="width:44px;height:44px;border-radius:12px;background:#22c55e;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px;">⏳</div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:15px;font-weight:800;color:#14532d;">Pending Enrollment</div>
                            <div style="font-size:13px;color:#166534;margin-top:2px;">
                                You started joining <strong>{{ $pendingClass->name }}</strong> but didn't complete login.
                                @if(! empty($pendingEnrollment['email']))
                                    <span style="color:#15803d;">Email: {{ $pendingEnrollment['email'] }}</span>
                                @endif
                            </div>
                            <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                                <a href="{{ route('filament.admin.auth.login') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;background:#22c55e;color:#fff;font-size:12px;font-weight:700;text-decoration:none;transition:background .15s;">
                                    Complete Enrollment →
                                </a>
                                <button type="button" wire:click="clearPendingEnrollment" style="padding:8px 16px;border-radius:8px;background:#fff;border:1px solid #bbf7d0;color:#166534;font-size:12px;font-weight:600;cursor:pointer;">
                                    Dismiss
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

{{-- ══ MAIN CONTENT + SIDEBAR ══════════════════════════════════════════════ --}}
            @if(empty($enrollments))
        <div class="md-empty">
                <div class="md-empty-icon">🎓</div>
                <div class="md-empty-title">You're not enrolled in any classes yet</div>
            <div class="md-empty-sub">Your mentor will send you an enrollment invitation. Check your email inbox.</div>
        </div>
@else

        <div class="md-two-col">
```

with:

```blade
{{-- ══ PENDING ENROLLMENT ══════════════════════════════════════════════════ --}}
        @php
            $pendingEnrollment = session('enrollment_intent');
        @endphp
        @if(is_array($pendingEnrollment) && ! empty($pendingEnrollment['class_id']))
            @php
                $pendingClass = \App\Models\MentorshipClass::with('training')->find($pendingEnrollment['class_id']);
            @endphp
            @if($pendingClass)
                <div class="md-card" style="background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 100%);border:1px solid #bbf7d0;margin-bottom:24px;">
                    <div style="display:flex;align-items:flex-start;gap:16px;">
                        <div style="width:44px;height:44px;border-radius:12px;background:#22c55e;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px;">⏳</div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:15px;font-weight:800;color:#14532d;">Pending Enrollment</div>
                            <div style="font-size:13px;color:#166534;margin-top:2px;">
                                You started joining <strong>{{ $pendingClass->name }}</strong> but didn't complete login.
                                @if(! empty($pendingEnrollment['email']))
                                    <span style="color:#15803d;">Email: {{ $pendingEnrollment['email'] }}</span>
                                @endif
                            </div>
                            <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                                <a href="{{ route('filament.admin.auth.login') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;background:#22c55e;color:#fff;font-size:12px;font-weight:700;text-decoration:none;transition:background .15s;">
                                    Complete Enrollment →
                                </a>
                                <button type="button" wire:click="clearPendingEnrollment" style="padding:8px 16px;border-radius:8px;background:#fff;border:1px solid #bbf7d0;color:#166534;font-size:12px;font-weight:600;cursor:pointer;">
                                    Dismiss
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if(empty($enrollments))
        <div class="md-empty">
                <div class="md-empty-icon">🎓</div>
                <div class="md-empty-title">You're not enrolled in any classes yet</div>
            <div class="md-empty-sub">Your mentor will send you an enrollment invitation. Check your email inbox.</div>
        </div>
        @else

        {{-- ══ HERO: NEXT BEST ACTION ═══════════════════════════════════════════ --}}
        @php
            $tier = $nextAction['tier'] ?? 6;
            $heroClass = $tier <= 2 ? 'md-hero-urgent' : ($tier <= 5 ? 'md-hero-active' : 'md-hero-clear');
        @endphp
        @if(!empty($nextAction))
            <a href="{{ $nextAction['url'] }}" class="md-hero {{ $heroClass }}">
                <div class="md-hero-text">
                    <div class="md-hero-headline">{{ $nextAction['headline'] }}</div>
                    <div class="md-hero-subtext">{{ $nextAction['subtext'] }}</div>
                </div>
                <div class="md-hero-action">{{ $nextAction['label'] }} →</div>
            </a>
        @endif

        {{-- ── Secondary strip ──────────────────────────────────────────────── --}}
        <div class="md-secondary-strip">
            <div class="md-secondary-item">
                <strong>{{ $stats['pending_attendance'] ?? 0 }}</strong> pending attendance
            </div>
            <div class="md-secondary-item">
                <strong>{{ count($recommendations) }}</strong> mentor feedback item{{ count($recommendations) !== 1 ? 's' : '' }}
            </div>
            <div class="md-secondary-item">
                <strong>{{ $stats['completion_rate'] ?? 0 }}%</strong> overall completion
            </div>
        </div>

        {{-- ══ GLOBAL STATS ═══════════════════════════════════════════════════════ --}}
        <details class="md-collapse-section">
            <summary class="md-collapse-summary">📊 My Progress</summary>
            <div class="md-collapse-body">
@php
$stats = $globalStats;
$cards = [
    ['icon' => '📚', 'value' => $stats['total_classes'],       'label' => 'Classes Enrolled',   'bg' => '#eff6ff', 'ic' => '#dbeafe', 'vc' => '#1d4ed8'],
    ['icon' => '✅', 'value' => $stats['completed_classes'],   'label' => 'Classes Completed',   'bg' => '#f0fdf4', 'ic' => '#dcfce7', 'vc' => '#16a34a'],
    ['icon' => '🔵', 'value' => $stats['active_classes'],      'label' => 'Classes Active',      'bg' => '#f0f9ff', 'ic' => '#e0f2fe', 'vc' => '#0284c7'],
    ['icon' => '🧩', 'value' => $stats['total_modules'],       'label' => 'Total Modules',       'bg' => '#faf5ff', 'ic' => '#ede9fe', 'vc' => '#7c3aed'],
    ['icon' => '🏆', 'value' => $stats['completed_modules'],   'label' => 'Modules Completed',   'bg' => '#f0fdf4', 'ic' => '#dcfce7', 'vc' => '#16a34a'],
    ['icon' => '⏳', 'value' => $stats['in_progress_modules'], 'label' => 'Modules In Progress', 'bg' => '#fffbeb', 'ic' => '#fef9c3', 'vc' => '#d97706'],
    ['icon' => '📋', 'value' => $stats['pending_attendance'],  'label' => 'Pending Attendance',  'bg' => '#fef2f2', 'ic' => '#fee2e2', 'vc' => '#dc2626'],
    ['icon' => '⭐', 'value' => $stats['exempted_modules'],    'label' => 'Modules Exempted',    'bg' => '#f8fafc', 'ic' => '#f1f5f9', 'vc' => '#475569'],
];
        @endphp

        <div class="md-stats-grid">
            @foreach($cards as $c)
                <div class="md-stat-card">
                    <div class="md-stat-icon" style="background:{{ $c['ic'] }}">{{ $c['icon'] }}</div>
                    <div>
                        <div class="md-stat-value" style="color:{{ $c['vc'] }}">{{ $c['value'] }}</div>
                        <div class="md-stat-label">{{ $c['label'] }}</div>
                    </div>
            </div>
        @endforeach
        </div>
            </div>
        </details>

{{-- ══ MAIN CONTENT + SIDEBAR ══════════════════════════════════════════════ --}}
        <details class="md-collapse-section">
            <summary class="md-collapse-summary">📚 My Classes</summary>
            <div class="md-collapse-body">

        <div class="md-two-col">
```

Then, immediately before the closing `@endif` that currently ends the `@if(empty($enrollments))` conditional (originally right after `</div>{{-- /two-col --}}`), close the two new wrapper elements. Replace:

```blade
            </div>{{-- /sidebar --}}
        </div>{{-- /two-col --}}
@endif
```

with:

```blade
            </div>{{-- /sidebar --}}
        </div>{{-- /two-col --}}
            </div>{{-- /md-collapse-body --}}
        </details>
        @endif
```

- [ ] **Step 4: Add hero/collapse CSS**

In the same file's `<style>` block, immediately before the closing `</style>` tag, append:

```css
        /* ── Hero next-action card ────────────────────────────────────────────── */
        .md-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            border-radius: 18px;
            padding: 22px 26px;
            margin-bottom: 14px;
            text-decoration: none;
            transition: transform .15s ease;
        }
        .md-hero:hover {
            transform: translateY(-1px);
        }
        .md-hero-urgent {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 1px solid #fca5a5;
        }
        .md-hero-active {
            background: linear-gradient(135deg, #dbeafe, #e0e7ff);
            border: 1px solid #93c5fd;
        }
        .md-hero-clear {
            background: linear-gradient(135deg, #dcfce7, #d1fae5);
            border: 1px solid #86efac;
        }
        .md-hero-headline {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .md-hero-subtext {
            font-size: 13px;
            color: #475569;
            font-weight: 500;
        }
        .md-hero-action {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            padding: 10px 18px;
            border-radius: 10px;
            background: rgba(255,255,255,0.6);
        }

        /* ── Secondary strip ──────────────────────────────────────────────────── */
        .md-secondary-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 12.5px;
            color: #64748b;
            margin-bottom: 20px;
            padding: 0 4px;
        }
        .dark .md-secondary-strip {
            color: #94a3b8;
        }
        .md-secondary-item strong {
            color: #1e293b;
            font-weight: 800;
        }
        .dark .md-secondary-item strong {
            color: #f1f5f9;
        }

        /* ── Collapsible sections ─────────────────────────────────────────────── */
        .md-collapse-section {
            margin-bottom: 16px;
        }
        .md-collapse-summary {
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            padding: 10px 4px;
            list-style: none;
            user-select: none;
        }
        .dark .md-collapse-summary {
            color: #94a3b8;
        }
        .md-collapse-summary::-webkit-details-marker {
            display: none;
        }
        .md-collapse-summary::before {
            content: '▸ ';
        }
        details[open] > .md-collapse-summary::before {
            content: '▾ ';
        }
        .md-collapse-body {
            padding-top: 6px;
        }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/MenteeDashboardHeroTest.php`
Expected: PASS

- [ ] **Step 6: Manually verify in browser**

Log in as a mentee with an in-progress enrollment and confirm: hero card renders above the fold with the correct action; "My Progress" and "My Classes" sections are collapsed by default and expand on click; no visual regressions to existing card content once expanded.

- [ ] **Step 7: Commit**

```bash
git add resources/views/filament/pages/mentee-dashboard.blade.php tests/Feature/MenteeDashboardHeroTest.php
git commit -m "feat: add next-action hero card to mentee dashboard, collapse secondary sections"
```

---

### Task 4: Surface failed video-review feedback

**Files:**
- Modify: `app/Filament/Pages/MenteeDashboard.php`
- Modify: `resources/views/filament/pages/mentee-dashboard.blade.php`
- Test: `tests/Feature/MenteeDashboardFeedbackTest.php`

**Interfaces:**
- Consumes: `MenteeModuleProgress::video_review_status`, `::video_review_notes`, `::video_reviewed_at` (existing fields).
- Produces: `MenteeDashboard::$recommendations` items change shape from `{name, recommendation, rec_at}` to `{name, text, at, type}` — `type` is `'recommendation'` or `'video_review'`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MenteeDashboardFeedbackTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\MenteeDashboard;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenteeDashboardFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_video_review_notes_appear_in_recommendations(): void
    {
        $mentee = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'PPH Management']);
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
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
            'video_review_status' => 'failed',
            'video_review_notes' => 'Redo the uterine massage technique.',
            'video_reviewed_at' => now(),
        ]);

        $this->actingAs($mentee);

        $component = Livewire::test(MenteeDashboard::class);

        $recommendations = $component->get('recommendations');
        $this->assertCount(1, $recommendations);
        $this->assertSame('video_review', $recommendations[0]['type']);
        $this->assertSame('Redo the uterine massage technique.', $recommendations[0]['text']);

        $component->assertSee('Redo the uterine massage technique.');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MenteeDashboardFeedbackTest.php`
Expected: FAIL — `video_review_status`/`video_review_notes` are not read into `$allModules`, and `$recommendations` items don't have a `type` key.

- [ ] **Step 3: Add the fields to the per-module array**

In `app/Filament/Pages/MenteeDashboard.php`, inside the `$modules = ... ->map(function (ClassModule $m) use (...) { ... return [...]; })` closure, add three new keys to the returned array (alongside the existing `'recommendation' => $prog?->mentor_recommendation,` line):

```php
                    'recommendation' => $prog?->mentor_recommendation,
                    'rec_at' => $prog?->recommendation_written_at ? Carbon::parse($prog->recommendation_written_at)->format('d M Y') : null,
                    'video_review_status' => $prog?->video_review_status,
                    'video_review_notes' => $prog?->video_review_notes,
                    'video_reviewed_at' => $prog?->video_reviewed_at ? Carbon::parse($prog->video_reviewed_at)->format('d M Y') : null,
```

(The first two lines already exist — only the three `video_*` lines are new.)

- [ ] **Step 4: Rebuild the recommendations list**

Replace the existing step 6 block:

```php
        // ── 6. Mentor recommendations ────────────────────────────────────────
        $this->recommendations = $allModules
            ->filter(fn ($m) => ! empty($m['recommendation']))
            ->sortByDesc('rec_at')
            ->take(5)
            ->values()
            ->toArray();
```

with:

```php
        // ── 6. Mentor recommendations + failed video-review feedback ─────────
        $mentorRecommendations = $allModules
            ->filter(fn ($m) => ! empty($m['recommendation']))
            ->map(fn ($m) => [
                'name' => $m['name'],
                'text' => $m['recommendation'],
                'at' => $m['rec_at'],
                'type' => 'recommendation',
            ]);

        $videoFeedback = $allModules
            ->filter(fn ($m) => ($m['video_review_status'] ?? null) === 'failed' && ! empty($m['video_review_notes']))
            ->map(fn ($m) => [
                'name' => $m['name'],
                'text' => $m['video_review_notes'],
                'at' => $m['video_reviewed_at'],
                'type' => 'video_review',
            ]);

        $this->recommendations = $mentorRecommendations
            ->merge($videoFeedback)
            ->sortByDesc('at')
            ->take(5)
            ->values()
            ->toArray();
```

- [ ] **Step 5: Update the Blade sidebar card**

In `resources/views/filament/pages/mentee-dashboard.blade.php`, replace the "Mentor Recommendations" block:

```blade
        {{-- Mentor Recommendations --}}
        @if(!empty($recommendations))
                <div class="md-sidebar-card">
                    <div class="md-sidebar-header">📋 Mentor Feedback</div>
                    <div class="md-sidebar-body" style="padding-bottom:8px;">
                @foreach($recommendations as $rec)
                        <div class="md-rec-card">
                            <div class="md-rec-module">{{ $rec['name'] }}</div>
                            <div class="md-rec-text">{{ $rec['recommendation'] }}</div>
                    @if($rec['rec_at'])
                            <div class="md-rec-date">{{ $rec['rec_at'] }}</div>
                    @endif
                        </div>
                @endforeach
                    </div>
                </div>
        @endif
```

with:

```blade
        {{-- Mentor Recommendations + Video Review Feedback --}}
        @if(!empty($recommendations))
                <div class="md-sidebar-card">
                    <div class="md-sidebar-header">📋 Mentor Feedback</div>
                    <div class="md-sidebar-body" style="padding-bottom:8px;">
                @foreach($recommendations as $rec)
                        <div class="md-rec-card">
                            <div class="md-rec-module">
                                {{ $rec['name'] }}
                                @if($rec['type'] === 'video_review')
                                    <span style="margin-left:6px;font-size:9px;font-weight:700;padding:2px 7px;border-radius:100px;background:#fee2e2;color:#b91c1c;text-transform:uppercase;letter-spacing:.04em;">Needs Revision</span>
                                @endif
                            </div>
                            <div class="md-rec-text">{{ $rec['text'] }}</div>
                    @if($rec['at'])
                            <div class="md-rec-date">{{ $rec['at'] }}</div>
                    @endif
                        </div>
                @endforeach
                    </div>
                </div>
        @endif
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/MenteeDashboardFeedbackTest.php`
Expected: PASS

- [ ] **Step 7: Run the full mentee-dashboard test group to check for regressions**

Run: `php artisan test tests/Feature/MenteeDashboardNextActionTest.php tests/Feature/MenteeDashboardHeroTest.php tests/Feature/MenteeDashboardFeedbackTest.php tests/Unit/Services/MenteeNextActionResolverTest.php`
Expected: PASS, all tests green.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Pages/MenteeDashboard.php resources/views/filament/pages/mentee-dashboard.blade.php tests/Feature/MenteeDashboardFeedbackTest.php
git commit -m "fix: surface failed video-review notes as mentor feedback on mentee dashboard"
```

---

### Task 5: Empty-state fix on module-detail page

**Files:**
- Modify: `resources/views/mentee/module-detail.blade.php`

**Note on scope:** during planning, `resources/views/mentee/class-progress.blade.php`'s "No modules started yet" empty state (around line 533-534) was re-checked against the guide's standard and found already compliant — it has both a heading and an explanation ("Your mentor will start modules and you'll see them here."). No change is needed there; this task only touches `module-detail.blade.php`.

- [ ] **Step 1: Add a "Back to Class" link to the no-content empty state**

In `resources/views/mentee/module-detail.blade.php`, find this block (around line 340-356):

```blade
            {{-- ── NO CONTENT BLOCK ────────────────────────────────────────── --}}
            @if(!$hasAnyContent)
                <div class="fade-slide section-card" style="border:2px dashed #cbd5e1">
                    <div style="padding:40px 32px;text-align:center">
                        <div style="width:56px;height:56px;border-radius:16px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                            <svg style="width:28px;height:28px;color:#94a3b8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <h3 style="font-size:16px;font-weight:800;color:#1e293b;margin-bottom:8px" class="dark:text-white">Module content not yet set up</h3>
                        <p style="font-size:13px;color:#64748b;max-width:380px;margin:0 auto 20px;line-height:1.6">
                            Your mentor hasn't added any content to this module yet — no introduction, pre/post tests, case scenarios, or activities have been configured for you.
                        </p>
                        <div style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;background:linear-gradient(135deg,#eff6ff,#eef2ff);border:1px solid #bfdbfe">
                            <svg style="width:16px;height:16px;color:#1d4ed8;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <span style="font-size:12px;font-weight:600;color:#1d4ed8">Contact your mentor to be enrolled in the module services</span>
                        </div>
                    </div>
                </div>
            @endif
```

Replace it with (adds a concrete navigation action below the existing contact-mentor notice):

```blade
            {{-- ── NO CONTENT BLOCK ────────────────────────────────────────── --}}
            @if(!$hasAnyContent)
                <div class="fade-slide section-card" style="border:2px dashed #cbd5e1">
                    <div style="padding:40px 32px;text-align:center">
                        <div style="width:56px;height:56px;border-radius:16px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                            <svg style="width:28px;height:28px;color:#94a3b8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <h3 style="font-size:16px;font-weight:800;color:#1e293b;margin-bottom:8px" class="dark:text-white">Module content not yet set up</h3>
                        <p style="font-size:13px;color:#64748b;max-width:380px;margin:0 auto 20px;line-height:1.6">
                            Your mentor hasn't added any content to this module yet — no introduction, pre/post tests, case scenarios, or activities have been configured for you.
                        </p>
                        <div style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;background:linear-gradient(135deg,#eff6ff,#eef2ff);border:1px solid #bfdbfe;margin-bottom:14px">
                            <svg style="width:16px;height:16px;color:#1d4ed8;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <span style="font-size:12px;font-weight:600;color:#1d4ed8">Contact your mentor to be enrolled in the module services</span>
                        </div>
                        <div>
                            <a href="{{ route('mentee.class.progress', ['class' => $class->id]) }}" style="font-size:12px;font-weight:700;color:#475569;text-decoration:none">← Back to Class</a>
                        </div>
                    </div>
                </div>
            @endif
```

- [ ] **Step 2: Manually verify in browser**

Navigate to a module-detail page for a module with no configured content (as a mentee) and confirm the "Back to Class" link appears and correctly returns to `mentee.class.progress`.

- [ ] **Step 3: Commit**

```bash
git add resources/views/mentee/module-detail.blade.php
git commit -m "fix: add Back to Class link to module-detail empty state"
```

---

### Task 6: Hands-on video upload — progress indicator and retry-on-failure

**Files:**
- Modify: `resources/views/mentee/module-detail.blade.php`

**Interfaces:**
- Consumes: existing route `mentee.class.video.upload` (POST, `{class}`, `{classModule}`) — no backend changes.

- [ ] **Step 1: Convert the video-upload form to an XHR submission with progress tracking**

In `resources/views/mentee/module-detail.blade.php`, find the video-upload section's opening `x-data` (around line 766):

```blade
                    <div id="submit-video" class="section-card" x-data="{ inputType: 'file' }">
```

Replace with (adds upload-state fields alongside the existing `inputType`):

```blade
                    <div id="submit-video" class="section-card" x-data="{ inputType: 'file', uploading: false, uploadPct: 0, uploadFailed: false }">
```

Find the closing `<form>` tag (around line 824-850):

```blade
                            <form action="{{ route('mentee.class.video.upload', [$class->id, $classModule->id]) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                                @csrf
```

Replace with:

```blade
                            <form
                                action="{{ route('mentee.class.video.upload', [$class->id, $classModule->id]) }}"
                                method="POST"
                                enctype="multipart/form-data"
                                class="space-y-4"
                                x-on:submit.prevent="uploadFailed = false; uploadVideoForm($event.target, () => { uploading = true; uploadPct = 0; }, (pct) => { uploadPct = pct; }, () => { window.location.reload(); }, () => { uploading = false; uploadFailed = true; })"
                            >
                                @csrf
```

Immediately after the closing `</form>` tag for this section (right before the closing `</div></div>` of the `#submit-video` card, after line 850's `</form>`), add the progress/retry UI:

```blade
                            </form>

                            <div x-show="uploading" x-cloak style="margin-top:14px">
                                <div style="display:flex;justify-content:space-between;font-size:11px;color:#92400e;margin-bottom:5px;font-weight:600">
                                    <span>Uploading…</span>
                                    <span x-text="uploadPct + '%'"></span>
                                </div>
                                <div style="height:6px;border-radius:100px;background:#fef3c7;overflow:hidden">
                                    <div style="height:100%;background:#f59e0b;border-radius:100px;transition:width .2s" :style="'width:' + uploadPct + '%'"></div>
                                </div>
                            </div>

                            <div x-show="uploadFailed" x-cloak style="margin-top:14px;padding:12px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;display:flex;align-items:center;justify-content:space-between;gap:12px">
                                <span style="font-size:12px;font-weight:600;color:#b91c1c">Upload didn't finish — your file is still selected. Check your connection and retry.</span>
                                <button type="button" x-on:click="$el.closest('.section-card').querySelector('form').requestSubmit()" style="flex-shrink:0;padding:6px 14px;border-radius:8px;background:#dc2626;color:#fff;font-size:11px;font-weight:700;border:none;cursor:pointer">
                                    Retry Upload
                                </button>
                            </div>
```

- [ ] **Step 2: Add the `uploadVideoForm` JS helper**

Find the module-detail page's existing `@push('scripts')` block (or, if none exists in this file, add one at the end of the file, immediately before the final Blade layout closing tag — check for an existing `@push('scripts')`/`@endpush` first and add to it if present). Add this function:

```html
@push('scripts')
<script>
function uploadVideoForm(formEl, onStart, onProgress, onSuccess, onError) {
    const xhr = new XMLHttpRequest();
    const formData = new FormData(formEl);

    xhr.open('POST', formEl.action, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.upload.addEventListener('progress', function (e) {
        if (e.lengthComputable) {
            onProgress(Math.round((e.loaded / e.total) * 100));
        }
    });

    xhr.addEventListener('load', function () {
        if (xhr.status >= 200 && xhr.status < 400) {
            onSuccess();
        } else {
            onError();
        }
    });

    xhr.addEventListener('error', onError);
    xhr.addEventListener('timeout', onError);

    onStart();
    xhr.send(formData);
}
</script>
@endpush
```

If a `@push('scripts')` block already exists elsewhere in the file, add the `uploadVideoForm` function inside the existing `<script>` tag instead of creating a second push block.

- [ ] **Step 3: Manually verify in browser (no automated test — XHR progress/network-failure behavior is not practically testable in PHPUnit)**

As a mentee on an EmONC module's detail page:
1. Select a video file and submit — confirm the progress bar appears and advances, then the page reloads showing the submitted video.
2. Throttle the network to "Offline" mid-upload (browser dev tools) and submit — confirm the retry banner appears with the file still selected, and clicking "Retry Upload" resubmits without needing to re-pick the file.
3. Confirm the existing "paste a video link" path (`inputType = 'link'`) still submits and saves correctly — it goes through the same XHR path but completes near-instantly since it's not a file upload.

- [ ] **Step 4: Commit**

```bash
git add resources/views/mentee/module-detail.blade.php
git commit -m "feat: add upload progress and retry-on-failure for hands-on video submission"
```

---

## Self-Review Notes

- **Spec coverage:** Section 3 (next-best-action) → Task 1. Section 4 (layout) → Task 3. Section 5 (feedback fix) → Task 4. Section 6 (empty states) → Task 5, with the `class-progress.blade.php` half of that section dropped after re-inspection showed it already compliant (documented in Task 5's note rather than silently skipped). Section 7 (offline resilience) → Task 6. Section 8 (edge cases) → covered by Task 1's tests (`test_no_data_module_does_not_throw`, certified/upcoming-session fallbacks). Section 9's acceptance criteria are each covered by a task's test or manual-verification step.
- **Placeholder scan:** no TBD/TODO markers; every step has real, complete code or a fully-specified manual verification procedure (Tasks 5's manual browser check and Task 6's three-part manual check are procedures, not vague hand-waving).
- **Type consistency:** `$nextAction` array shape (`tier`, `label`, `headline`, `subtext`, `url`, `meta`) is identical across Task 1's resolver, Task 2's wiring, and Task 3's Blade consumption. `$recommendations` item shape (`name`, `text`, `at`, `type`) is identical across Task 4's PHP and Blade changes.
