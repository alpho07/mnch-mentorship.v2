# Mentor Journey Phase 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the mentor dashboard around one ranked "who needs you right now" priority queue, replacing two count-only insights with real per-mentee/per-class drill-down items, and adding inactive-mentee detection — a concept that doesn't exist anywhere in the codebase today.

**Architecture:** A new stateless service, `MentorPriorityQueueResolver`, takes a mentor's already-resolved training IDs (reusing `MentorDashboard::getMyTrainingIds()`'s existing scoping — no duplicate role logic) and returns a ranked list across five tiers. `MentorDashboard` calls it once per page load and exposes `$priorityQueue`. The Blade view gets a new queue card near the top; the existing KPI strip, insights row, pending-video-review panel, and mentorship table are consolidated into two collapsed sections below it, mirroring the pattern from Phase 1's mentee dashboard.

**Tech Stack:** Laravel 12, Filament v3 (Livewire pages), Blade, PHPUnit (SQLite in-memory, `RefreshDatabase` per test class).

## Global Constraints

- Reuse the existing EmONC-detection pattern exactly as it appears elsewhere: `str_contains(strtolower($program->name), 'maternal') && str_contains($programName, 'emonc')`.
- Tier 2 (pending mentor approval) is EmONC-only — confirmed non-EmONC programs have no per-mentee finalization step (`mentor_approved_at` is never set for them; completion is class-wide via "End Class"). Do not invent one.
- Tiers 3 (inactive), 4 (struggling), 5 (low-attendance class) must apply across all three programs — do not add an `isEmonc()` gate to these.
- A mentee qualifying for multiple tiers appears only once, under their lowest (most urgent) tier number.
- Every new PHP class needs a test using `Illuminate\Foundation\Testing\RefreshDatabase` on SQLite in-memory, following the pattern in `tests/Unit/Services/MenteeNextActionResolverTest.php`.
- Livewire component tests for Filament Pages must grant the page's permission explicitly (`Permission::firstOrCreate(['name' => 'page_MentorDashboard', 'guard_name' => 'web']); $user->givePermissionTo(...)`) — `Livewire::test()` enforces `canAccess()` and returns a 403 otherwise (learned the hard way in Phase 1).
- Preserve all existing computed dashboard data (`kpis`, `mentorshipItems`, `activityFeed`, `pendingVideoReviews`, `insights`) — this plan relocates/consolidates it, never deletes it.

---

### Task 1: `MentorPriorityQueueResolver` service

**Files:**
- Create: `app/Services/MentorPriorityQueueResolver.php`
- Test: `tests/Unit/Services/MentorPriorityQueueResolverTest.php`

**Interfaces:**
- Consumes: `App\Services\EmoncReportingService::pendingVideoReviewItemsForUser(int $userId, array $trainingIds): array` (existing, unchanged).
- Produces: `MentorPriorityQueueResolver::resolve(User $mentor, array $trainingIds): array` returning a list of:
  ```php
  [
      'tier' => int,        // 1-5
      'label' => string,
      'headline' => string,
      'subtext' => string,
      'url' => string,
      'meta' => array,      // e.g. ['days_inactive' => 16], ['completion_pct' => 32], ['attendance_rate' => 45]
  ]
  ```
  Consumed by Task 2 (`MentorDashboard::loadDashboard()`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/MentorPriorityQueueResolverTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorPriorityQueueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorPriorityQueueResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeMentorship(string $programName): array
    {
        $mentor = User::factory()->create();
        $program = Program::factory()->create(['name' => $programName]);
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'Test Module']);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        return compact('mentor', 'program', 'training', 'class', 'programModule', 'classModule');
    }

    private function makeMentee(array $env, string $progressStatus = 'in_progress'): array
    {
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $env['class']->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $env['classModule']->id,
            'status' => $progressStatus,
        ]);

        return compact('mentee', 'participant');
    }

    public function test_pending_video_review_is_tier_one(): void
    {
        $env = $this->makeMentorship('Maternal Health (EmONC)');
        $mentee = $this->makeMentee($env, 'in_progress');
        MenteeModuleProgress::where('class_participant_id', $mentee['participant']->id)->update([
            'video_review_status' => 'pending',
            'hands_on_video_url' => 'https://youtube.com/watch?v=abc12345678',
        ]);
        // Confirmed attendance so this class's rate is 100% — keeps this test isolated to Tier 1,
        // rather than also incidentally tripping Tier 5 (the sole enrolled mentee having zero
        // confirmed attendance would otherwise make the class's own rate 0%).
        ClassAttendance::create([
            'class_id' => $env['class']->id,
            'class_module_id' => $env['classModule']->id,
            'user_id' => $mentee['mentee']->id,
            'marked_by' => $env['mentor']->id,
            'marked_at' => now(),
            'source' => 'manual',
        ]);

        $result = (app(MentorPriorityQueueResolver::class))->resolve($env['mentor'], [$env['training']->id]);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['tier']);
    }

    public function test_completed_mentee_pending_approval_is_tier_two_for_emonc(): void
    {
        $env = $this->makeMentorship('Maternal Health (EmONC)');
        $mentee = $this->makeMentee($env, 'completed');
        MenteeModuleProgress::where('class_participant_id', $mentee['participant']->id)->update([
            'video_review_status' => 'passed',
            'hands_on_video_url' => 'https://youtube.com/watch?v=abc12345678',
        ]);
        $mentee['participant']->update(['status' => 'completed', 'completed_at' => now()]);

        $result = (app(MentorPriorityQueueResolver::class))->resolve($env['mentor'], [$env['training']->id]);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['tier']);
    }

    public function test_completed_status_without_finished_modules_is_not_tier_two(): void
    {
        $env = $this->makeMentorship('Maternal Health (EmONC)');
        // Progress left in_progress (video not submitted) even though participant status is completed —
        // hasCompletedAllModules() must be false, so this must NOT surface as a Tier 2 approval item.
        // (It legitimately surfaces as Tier 4 "struggling" instead — 0% complete — which is correct;
        // this test only asserts the Tier 2 absence.)
        $mentee = $this->makeMentee($env, 'in_progress');
        $mentee['participant']->update(['status' => 'completed', 'completed_at' => now()]);

        $result = (app(MentorPriorityQueueResolver::class))->resolve($env['mentor'], [$env['training']->id]);

        $this->assertNull(collect($result)->firstWhere('tier', 2));
    }

    public function test_non_emonc_mentor_never_gets_tier_one_or_two(): void
    {
        $env = $this->makeMentorship('Newborn Care');
        $mentee = $this->makeMentee($env, 'completed');
        $mentee['participant']->update(['status' => 'completed', 'completed_at' => now()]);

        $result = (app(MentorPriorityQueueResolver::class))->resolve($env['mentor'], [$env['training']->id]);

        $this->assertCount(0, $result);
    }

    public function test_inactive_mentee_is_tier_three_across_programs(): void
    {
        $env = $this->makeMentorship('Newborn Care');
        $mentee = $this->makeMentee($env, 'in_progress');
        MenteeModuleProgress::where('class_participant_id', $mentee['participant']->id)
            ->update(['updated_at' => now()->subDays(16)]);
        $mentee['participant']->update(['enrolled_at' => now()->subDays(30)]);
        // Attendance confirmed, but dated BEFORE the 16-day-old progress update, so the most-recent
        // activity signal stays the progress update — keeps them correctly flagged inactive while
        // also keeping this class's attendance rate at 100% (avoiding an incidental Tier 5 item).
        ClassAttendance::create([
            'class_id' => $env['class']->id,
            'class_module_id' => $env['classModule']->id,
            'user_id' => $mentee['mentee']->id,
            'marked_by' => $env['mentor']->id,
            'marked_at' => now()->subDays(25),
            'source' => 'manual',
        ]);

        $result = (app(MentorPriorityQueueResolver::class))->resolve($env['mentor'], [$env['training']->id]);

        $this->assertCount(1, $result);
        $this->assertSame(3, $result[0]['tier']);
    }

    public function test_recently_active_mentee_is_not_flagged_inactive(): void
    {
        $env = $this->makeMentorship('Infant and Child Care');
        $mentee = $this->makeMentee($env, 'in_progress');
        MenteeModuleProgress::where('class_participant_id', $mentee['participant']->id)
            ->update(['updated_at' => now()->subDays(3)]);

        $result = (app(MentorPriorityQueueResolver::class))->resolve($env['mentor'], [$env['training']->id]);

        // 0% complete with only 3 days since activity: must not be flagged inactive (Tier 3),
        // even though it legitimately still qualifies as struggling (Tier 4) — this test only
        // asserts the Tier 3 absence.
        $this->assertNull(collect($result)->firstWhere('tier', 3));
    }

    public function test_struggling_mentee_is_tier_four_across_programs(): void
    {
        $env = $this->makeMentorship('Infant and Child Care');
        $mentee = $this->makeMentee($env, 'in_progress');
        MenteeModuleProgress::where('class_participant_id', $mentee['participant']->id)
            ->update(['updated_at' => now()->subDays(2)]); // recently active, not inactive
        $secondModule = ClassModule::factory()->create([
            'mentorship_class_id' => $env['class']->id,
            'program_module_id' => $env['programModule']->id,
            'status' => 'in_progress',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $mentee['participant']->id,
            'class_module_id' => $secondModule->id,
            'status' => 'not_started',
            'updated_at' => now()->subDays(2),
        ]);
        // 2 modules total, 0 completed => 0% completion, well under 40%
        // Confirmed attendance keeps this class's rate at 100%, isolating this test to Tier 4.
        ClassAttendance::create([
            'class_id' => $env['class']->id,
            'class_module_id' => $env['classModule']->id,
            'user_id' => $mentee['mentee']->id,
            'marked_by' => $env['mentor']->id,
            'marked_at' => now(),
            'source' => 'manual',
        ]);

        $result = (app(MentorPriorityQueueResolver::class))->resolve($env['mentor'], [$env['training']->id]);

        $this->assertCount(1, $result);
        $this->assertSame(4, $result[0]['tier']);
    }

    public function test_low_attendance_class_is_tier_five(): void
    {
        $env = $this->makeMentorship('Newborn Care');
        $mentee1 = $this->makeMentee($env, 'completed');
        $mentee2 = $this->makeMentee($env, 'completed');
        // Only 1 of 2 enrolled mentees has a confirmed attendance record => 50% < 60%
        ClassAttendance::create([
            'class_id' => $env['class']->id,
            'class_module_id' => $env['classModule']->id,
            'user_id' => $mentee1['mentee']->id,
            'marked_by' => $env['mentor']->id,
            'marked_at' => now(),
            'source' => 'manual',
        ]);

        $result = (app(MentorPriorityQueueResolver::class))->resolve($env['mentor'], [$env['training']->id]);

        $tier5 = collect($result)->firstWhere('tier', 5);
        $this->assertNotNull($tier5);
    }

    public function test_mentee_qualifying_for_multiple_tiers_appears_once_at_lowest_tier(): void
    {
        $env = $this->makeMentorship('Maternal Health (EmONC)');
        $mentee = $this->makeMentee($env, 'in_progress');
        // Both inactive (16 days) AND would be "struggling" (0% done) — must appear once, at Tier 3,
        // not twice. Attendance is confirmed so Tier 5 (class-level, independent of mentee dedup)
        // does not also fire and confound the count.
        MenteeModuleProgress::where('class_participant_id', $mentee['participant']->id)
            ->update(['updated_at' => now()->subDays(16)]);
        $mentee['participant']->update(['enrolled_at' => now()->subDays(30)]);
        // Dated before the 16-day-old progress update, so the most-recent activity signal stays
        // the progress update — keeps them correctly flagged inactive (Tier 3), not "recently active."
        ClassAttendance::create([
            'class_id' => $env['class']->id,
            'class_module_id' => $env['classModule']->id,
            'user_id' => $mentee['mentee']->id,
            'marked_by' => $env['mentor']->id,
            'marked_at' => now()->subDays(25),
            'source' => 'manual',
        ]);

        $result = (app(MentorPriorityQueueResolver::class))->resolve($env['mentor'], [$env['training']->id]);

        $this->assertCount(1, $result);
        $this->assertSame(3, $result[0]['tier']);
    }

    public function test_mentor_with_no_training_ids_gets_empty_queue(): void
    {
        $mentor = User::factory()->create();

        $result = (app(MentorPriorityQueueResolver::class))->resolve($mentor, []);

        $this->assertSame([], $result);
    }
}
```

**Note (verified during implementation):** `MentorPriorityQueueResolver` has a constructor dependency on `EmoncReportingService` (see Step 3), so tests must resolve it via `app(MentorPriorityQueueResolver::class)`, not `new MentorPriorityQueueResolver()`. Also, several tests need an explicit confirmed `ClassAttendance` record (with a correctly-chosen `marked_at`, since it feeds Tier 3's "last activity" calculation) purely to keep that class's attendance rate at 100% — otherwise a single-mentee class with zero attendance records incidentally trips Tier 5 too, and the timestamp matters: too recent and it can flip Tier 3's inactivity computation off entirely.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/MentorPriorityQueueResolverTest.php`
Expected: FAIL — `App\Services\MentorPriorityQueueResolver` does not exist.

- [ ] **Step 3: Write the implementation**

Create `app/Services/MentorPriorityQueueResolver.php`:

```php
<?php

namespace App\Services;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassAttendance;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Carbon\Carbon;

class MentorPriorityQueueResolver
{
    private const INACTIVE_DAYS = 14;

    private const STRUGGLING_THRESHOLD_PCT = 40;

    private const LOW_ATTENDANCE_THRESHOLD = 0.6;

    public function __construct(private EmoncReportingService $reportingService)
    {
    }

    public function resolve(User $mentor, array $trainingIds): array
    {
        if (empty($trainingIds)) {
            return [];
        }

        $seenUserIds = [];
        $items = collect();

        $items = $items->merge($this->tier1PendingVideoReviews($mentor, $trainingIds, $seenUserIds));
        $items = $items->merge($this->tier2PendingApprovals($trainingIds, $seenUserIds));

        $classIds = MentorshipClass::whereIn('training_id', $trainingIds)->pluck('id');
        $participants = ClassParticipant::with('user', 'mentorshipClass.training.program')
            ->whereIn('mentorship_class_id', $classIds)
            ->whereIn('status', ['enrolled', 'active'])
            ->get();

        $items = $items->merge($this->tier3InactiveMentees($participants, $seenUserIds));
        $items = $items->merge($this->tier4StrugglingMentees($participants, $seenUserIds));
        $items = $items->merge($this->tier5LowAttendanceClasses($classIds, $participants));

        return $items->sort(function (array $a, array $b) {
            if ($a['tier'] !== $b['tier']) {
                return $a['tier'] <=> $b['tier'];
            }

            return $a['sort_ts'] <=> $b['sort_ts'];
        })->map(function (array $item) {
            unset($item['sort_ts']);

            return $item;
        })->values()->toArray();
    }

    private function tier1PendingVideoReviews(User $mentor, array $trainingIds, array &$seenUserIds): array
    {
        $reviews = $this->reportingService->pendingVideoReviewItemsForUser($mentor->id, $trainingIds);
        $items = [];

        foreach ($reviews as $review) {
            $userId = $review['mentee']?->id;
            if (! $userId || isset($seenUserIds[$userId])) {
                continue;
            }

            $items[] = [
                'tier' => 1,
                'label' => 'Review Video',
                'headline' => ($review['mentee']?->full_name ?? $review['mentee']?->name ?? 'A mentee').' — hands-on video ready for review',
                'subtext' => $review['programModule']?->name ?? 'Module',
                'url' => $review['url'],
                'meta' => [],
                'sort_ts' => optional($review['progress']->updated_at)->timestamp ?? 0,
            ];
            $seenUserIds[$userId] = true;
        }

        return $items;
    }

    private function tier2PendingApprovals(array $trainingIds, array &$seenUserIds): array
    {
        $trainings = Training::whereIn('id', $trainingIds)->with('program')->get();
        $items = [];

        foreach ($trainings as $training) {
            if (! $this->isEmonc($training->program?->name)) {
                continue;
            }

            $classes = MentorshipClass::where('training_id', $training->id)->get();

            foreach ($classes as $class) {
                $participants = ClassParticipant::with('user', 'mentorshipClass')
                    ->where('mentorship_class_id', $class->id)
                    ->whereNull('mentor_approved_at')
                    ->get();

                foreach ($participants as $participant) {
                    $userId = $participant->user_id;
                    if (isset($seenUserIds[$userId]) || ! $participant->hasCompletedAllModules()) {
                        continue;
                    }

                    $items[] = [
                        'tier' => 2,
                        'label' => 'Approve Mentee',
                        'headline' => ($participant->user?->full_name ?? $participant->user?->name ?? 'A mentee').' — ready for your approval',
                        'subtext' => $class->name ?? 'Class',
                        'url' => MentorshipTrainingResource::getUrl('class-mentees', [
                            'training' => $training->id,
                            'class' => $class->id,
                        ]),
                        'meta' => [],
                        'sort_ts' => optional($participant->completed_at)->timestamp ?? 0,
                    ];
                    $seenUserIds[$userId] = true;
                }
            }
        }

        return $items;
    }

    private function tier3InactiveMentees($participants, array &$seenUserIds): array
    {
        $items = [];

        foreach ($participants->groupBy('user_id') as $userId => $userParticipants) {
            if (isset($seenUserIds[$userId])) {
                continue;
            }

            $participantIds = $userParticipants->pluck('id');
            $hasIncomplete = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
                ->whereNotIn('status', ['completed', 'exempted'])
                ->exists();

            if (! $hasIncomplete) {
                continue;
            }

            $lastProgress = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)->max('updated_at');
            $lastAttendance = ClassAttendance::where('user_id', $userId)
                ->whereIn('class_id', $userParticipants->pluck('mentorship_class_id'))
                ->max('marked_at');

            $lastActivity = collect([$lastProgress, $lastAttendance])
                ->filter()
                ->map(fn ($d) => Carbon::parse($d))
                ->sortByDesc(fn ($d) => $d->timestamp)
                ->first();

            if (! $lastActivity) {
                $earliestEnrollment = $userParticipants->min('enrolled_at');
                $lastActivity = $earliestEnrollment ? Carbon::parse($earliestEnrollment) : now();
            }

            $daysInactive = (int) $lastActivity->diffInDays(now());
            if ($daysInactive < self::INACTIVE_DAYS) {
                continue;
            }

            $participant = $userParticipants->first();
            $class = $participant->mentorshipClass;

            $items[] = [
                'tier' => 3,
                'label' => 'Follow Up',
                'headline' => ($participant->user?->full_name ?? $participant->user?->name ?? 'A mentee')." — inactive {$daysInactive} days",
                'subtext' => $class?->name ?? 'Class',
                'url' => $class
                    ? MentorshipTrainingResource::getUrl('class-mentees', [
                        'training' => $class->training_id,
                        'class' => $class->id,
                    ])
                    : '#',
                'meta' => ['days_inactive' => $daysInactive],
                'sort_ts' => -$daysInactive,
            ];
            $seenUserIds[$userId] = true;
        }

        return $items;
    }

    private function tier4StrugglingMentees($participants, array &$seenUserIds): array
    {
        $items = [];

        foreach ($participants->groupBy('user_id') as $userId => $userParticipants) {
            if (isset($seenUserIds[$userId])) {
                continue;
            }

            $participantIds = $userParticipants->pluck('id');
            $progressRows = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)->get();
            $total = $progressRows->count();

            if ($total === 0) {
                continue;
            }

            $done = $progressRows->whereIn('status', ['completed', 'exempted'])->count();
            $pct = (int) round(($done / $total) * 100);

            if ($pct >= self::STRUGGLING_THRESHOLD_PCT) {
                continue;
            }

            $participant = $userParticipants->first();
            $class = $participant->mentorshipClass;

            $items[] = [
                'tier' => 4,
                'label' => 'Support Mentee',
                'headline' => ($participant->user?->full_name ?? $participant->user?->name ?? 'A mentee')." — {$pct}% complete",
                'subtext' => $class?->name ?? 'Class',
                'url' => $class
                    ? MentorshipTrainingResource::getUrl('class-mentees', [
                        'training' => $class->training_id,
                        'class' => $class->id,
                    ])
                    : '#',
                'meta' => ['completion_pct' => $pct],
                'sort_ts' => $pct,
            ];
            $seenUserIds[$userId] = true;
        }

        return $items;
    }

    private function tier5LowAttendanceClasses($classIds, $participants): array
    {
        $classes = MentorshipClass::whereIn('id', $classIds)->get();
        $items = [];

        foreach ($classes as $class) {
            $enrolled = $participants->where('mentorship_class_id', $class->id)->count();
            if ($enrolled === 0) {
                continue;
            }

            $confirmed = ClassAttendance::where('class_id', $class->id)->count();
            $rate = $confirmed / $enrolled;

            if ($rate >= self::LOW_ATTENDANCE_THRESHOLD) {
                continue;
            }

            $pct = (int) round($rate * 100);

            $items[] = [
                'tier' => 5,
                'label' => 'Review Class',
                'headline' => ($class->name ?? 'A class')." — {$pct}% attendance",
                'subtext' => 'Confirmed attendance below 60%',
                'url' => MentorshipTrainingResource::getUrl('classes', ['record' => $class->training_id]),
                'meta' => ['attendance_rate' => $pct],
                'sort_ts' => $pct,
            ];
        }

        return $items;
    }

    private function isEmonc(?string $programName): bool
    {
        $name = strtolower($programName ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Services/MentorPriorityQueueResolverTest.php`
Expected: PASS — all 10 tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Services/MentorPriorityQueueResolver.php tests/Unit/Services/MentorPriorityQueueResolverTest.php
git commit -m "feat: add MentorPriorityQueueResolver for ranked mentor action queue"
```

---

### Task 2: Wire the resolver into `MentorDashboard`

**Files:**
- Modify: `app/Filament/Pages/MentorDashboard.php`
- Test: `tests/Feature/MentorDashboardPriorityQueueTest.php`

**Interfaces:**
- Consumes: `MentorPriorityQueueResolver::resolve(User $mentor, array $trainingIds): array` (Task 1)
- Produces: `MentorDashboard::$priorityQueue` (public array property), consumed by Task 3's Blade view.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MentorDashboardPriorityQueueTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\MentorDashboard;
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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorDashboardPriorityQueueTest extends TestCase
{
    use RefreshDatabase;

    private function grantDashboardAccess(User $user): void
    {
        Permission::firstOrCreate(['name' => 'page_MentorDashboard', 'guard_name' => 'web']);
        $user->givePermissionTo('page_MentorDashboard');
    }

    public function test_dashboard_exposes_resolver_output_as_priority_queue(): void
    {
        $mentor = User::factory()->create();
        $this->grantDashboardAccess($mentor);

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
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
            'status' => 'active',
        ]);
        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);
        // Model::create() overwrites an explicitly-passed updated_at at save time via Eloquent's
        // auto-timestamping — a follow-up query-builder update() (bypassing that) is required to
        // actually backdate it.
        MenteeModuleProgress::where('id', $progress->id)->update(['updated_at' => now()->subDays(20)]);
        $participant->update(['enrolled_at' => now()->subDays(30)]);

        $this->actingAs($mentor);

        Livewire::test(MentorDashboard::class)
            ->assertSet('priorityQueue.0.tier', 3);
    }

    public function test_dashboard_with_no_mentorships_has_empty_priority_queue(): void
    {
        $mentor = User::factory()->create();
        $this->grantDashboardAccess($mentor);
        $this->actingAs($mentor);

        Livewire::test(MentorDashboard::class)
            ->assertSet('priorityQueue', []);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MentorDashboardPriorityQueueTest.php`
Expected: FAIL — `priorityQueue` property does not exist on `MentorDashboard`.

- [ ] **Step 3: Wire the resolver**

In `app/Filament/Pages/MentorDashboard.php`, add the import alongside the existing ones:

```php
use App\Services\MentorPriorityQueueResolver;
```

Add the new public property next to `public array $insights = [];`:

```php
    public array $insights = [];

    public array $priorityQueue = [];
```

In `loadDashboard()`, update the early-return branch (currently):

```php
        if (empty($trainingIds)) {
            $this->kpis = $this->emptyKpis();
            $this->mentorshipItems = [];
            $this->mentorshipsTotal = 0;
            $this->mentorshipsPage = 1;
            $this->insights = [
                'mentees_needing_attention' => 0,
                'low_attendance_classes'    => 0,
                'stalled_modules'           => 0,
                'recs_coverage'             => 100,
            ];

            return;
        }
```

to:

```php
        if (empty($trainingIds)) {
            $this->kpis = $this->emptyKpis();
            $this->mentorshipItems = [];
            $this->mentorshipsTotal = 0;
            $this->mentorshipsPage = 1;
            $this->insights = [
                'mentees_needing_attention' => 0,
                'low_attendance_classes'    => 0,
                'stalled_modules'           => 0,
                'recs_coverage'             => 100,
            ];
            $this->priorityQueue = [];

            return;
        }
```

At the end of `loadDashboard()`, after the `$this->insights = [...]` assignment (the final statement in the method), add:

```php
        // ── Priority queue ───────────────────────────────────────────────────
        $this->priorityQueue = app(MentorPriorityQueueResolver::class)->resolve(auth()->user(), $trainingIds);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/MentorDashboardPriorityQueueTest.php`
Expected: PASS

- [ ] **Step 5: Run the full resolver + dashboard test group to check for regressions**

Run: `php artisan test tests/Feature/MentorDashboardPriorityQueueTest.php tests/Unit/Services/MentorPriorityQueueResolverTest.php`
Expected: PASS, all tests green.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/MentorDashboard.php tests/Feature/MentorDashboardPriorityQueueTest.php
git commit -m "feat: wire MentorPriorityQueueResolver into MentorDashboard as \$priorityQueue"
```

---

### Task 3: Priority-queue card + collapse existing sections in the dashboard view

**Files:**
- Modify: `resources/views/filament/pages/mentor-dashboard.blade.php`
- Test: `tests/Feature/MentorDashboardQueueCardTest.php`

**Interfaces:**
- Consumes: `$priorityQueue` (public property from Task 2), plus all pre-existing view data (`$kpis`, `$insights`, `$mentorships`, `$pendingVideoReviews`, `$activityFeed`, `$programOptions`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MentorDashboardQueueCardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\MentorDashboard;
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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorDashboardQueueCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_card_shows_inactive_mentee_item(): void
    {
        $mentor = User::factory()->create();
        Permission::firstOrCreate(['name' => 'page_MentorDashboard', 'guard_name' => 'web']);
        $mentor->givePermissionTo('page_MentorDashboard');

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $mentee = User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Mwende']);
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
            'enrolled_at' => now()->subDays(30),
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
            'updated_at' => now()->subDays(20),
        ]);

        $this->actingAs($mentor);

        Livewire::test(MentorDashboard::class)
            ->assertSee('Follow Up')
            ->assertSee('inactive 20 days');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MentorDashboardQueueCardTest.php`
Expected: FAIL — queue card markup does not exist yet.

- [ ] **Step 3: Insert the priority-queue card**

In `resources/views/filament/pages/mentor-dashboard.blade.php`, insert a new section immediately after the hero banner's closing `</div>` (the one that ends the `{{-- ═══ HERO BANNER ═══ --}}` block, right before the `{{-- ═══ KPI STRIP ... --}}` comment on the line starting `<div class="md-kpi-strip rv-animate"`):

```blade
{{-- ═══ PRIORITY QUEUE ═════════════════════════════════════════════════════ --}}
@if(!empty($priorityQueue))
<div class="rv-animate md-card" style="animation-delay:0.05s;margin-bottom:24px;background:#fff;border:1.5px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
    <div style="padding:16px 22px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">{{ count($priorityQueue) }} item{{ count($priorityQueue) !== 1 ? 's' : '' }} need your attention</h3>
    </div>
    <div>
        @foreach(array_slice($priorityQueue, 0, 10) as $item)
            @php
                $tierColor = match(true) {
                    $item['tier'] <= 2 => '#f59e0b',
                    $item['tier'] === 3 => '#ef4444',
                    default => '#3b82f6',
                };
            @endphp
            <div class="md-row" style="padding:13px 22px;{{ !$loop->last ? 'border-bottom:1px solid #f9fafb;' : '' }}display:flex;align-items:center;justify-content:space-between;gap:16px;">
                <div style="display:flex;align-items:center;gap:12px;min-width:0;">
                    <span style="width:8px;height:8px;border-radius:50%;background:{{ $tierColor }};flex-shrink:0;"></span>
                    <div style="min-width:0;">
                        <p style="font-size:13px;font-weight:700;color:#111827;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item['headline'] }}</p>
                        <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item['subtext'] }}</p>
                    </div>
                </div>
                <a href="{{ $item['url'] }}"
                   style="display:inline-flex;align-items:center;gap:6px;background:{{ $tierColor }};color:#fff;border:none;border-radius:9px;padding:8px 16px;font-size:12px;font-weight:700;text-decoration:none;flex-shrink:0;">
                    {{ $item['label'] }}
                </a>
            </div>
        @endforeach
    </div>
</div>
@else
<div class="rv-animate" style="animation-delay:0.05s;margin-bottom:24px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:18px;padding:18px 22px;">
    <p style="font-size:13px;font-weight:700;color:#166534;margin:0;">You're all caught up</p>
    <p style="font-size:12px;color:#15803d;margin:3px 0 0;">Nothing needs your attention right now.</p>
</div>
@endif

```

(Place this block directly before the existing `{{-- ═══ KPI STRIP — all stats in one horizontal row ════════════════════════ --}}` line.)

- [ ] **Step 4: Consolidate existing sections into two collapsed panels**

Remove the two now-redundant conditions from the local `$insightCards` computation near the top of the file (Tiers 3 and 4 of the priority queue now cover these with real per-mentee drill-down). Replace:

```blade
$insightCards = [];
if ($insights['mentees_needing_attention'] > 0)
    $insightCards[] = ['color'=>'#ef4444','bg'=>'#fef2f2','text'=>"<strong>{$insights['mentees_needing_attention']}</strong> mentee(s) have low module completion — consider scheduling a check-in.",'icon'=>'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z','ic'=>'#ef4444'];
if ($insights['low_attendance_classes'] > 0)
    $insightCards[] = ['color'=>'#f59e0b','bg'=>'#fffbeb','text'=>"<strong>{$insights['low_attendance_classes']}</strong> class(es) have attendance below 60% — review and follow up.",'icon'=>'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941','ic'=>'#f59e0b'];
if ($insights['stalled_modules'] > 0)
```

with:

```blade
$insightCards = [];
if ($insights['stalled_modules'] > 0)
```

Then, wrap the region from the `{{-- ═══ KPI STRIP ... --}}` comment through the end of the `{{-- ═══ MENTORSHIP PROGRAMS ═══ --}}` block's closing `</div></div>` (i.e., everything up to but not including `{{-- ═══ RECENT RECOMMENDATIONS ═══ --}}`) in a `<details>` panel. Locate this exact line (the KPI strip's opening tag):

```blade
<div class="md-kpi-strip rv-animate" style="animation-delay:0.08s;margin-bottom:24px;">
```

and prepend, immediately before it:

```blade
<details class="md-collapse-section" style="margin-bottom:16px;">
    <summary class="md-collapse-summary" style="cursor:pointer;font-size:13px;font-weight:700;color:#475569;padding:10px 4px;list-style:none;user-select:none;">📊 Mentorship Overview</summary>
    <div class="md-collapse-body" style="padding-top:6px;">
<div class="md-kpi-strip rv-animate" style="animation-delay:0.08s;margin-bottom:24px;">
```

(the last line here is the original opening tag, kept as-is — only the `<details>`/`<summary>`/wrapper `<div>` are new, inserted just before it).

Locate the end of the "MENTORSHIP PROGRAMS" block — the closing tags right before `{{-- ═══ RECENT RECOMMENDATIONS ═══ --}}`:

```blade
    </div>
</div>

{{-- ═══ RECENT RECOMMENDATIONS ════════════════════════════════════════════ --}}
```

and replace with:

```blade
    </div>
</div>
    </div>
</details>

{{-- ═══ RECENT RECOMMENDATIONS ════════════════════════════════════════════ --}}
```

Then wrap the "RECENT RECOMMENDATIONS" block the same way. Replace:

```blade
{{-- ═══ RECENT RECOMMENDATIONS ════════════════════════════════════════════ --}}
@if(count($activityFeed) > 0)
<div class="rv-animate" style="animation-delay:0.28s;">
```

with:

```blade
{{-- ═══ RECENT RECOMMENDATIONS ════════════════════════════════════════════ --}}
@if(count($activityFeed) > 0)
<details class="md-collapse-section" style="margin-bottom:16px;">
    <summary class="md-collapse-summary" style="cursor:pointer;font-size:13px;font-weight:700;color:#475569;padding:10px 4px;list-style:none;user-select:none;">🕐 Recent Recommendations</summary>
    <div class="md-collapse-body" style="padding-top:6px;">
<div class="rv-animate" style="animation-delay:0.28s;">
```

and replace its closing (currently):

```blade
    </div>
</div>
@endif

@endif {{-- end isEmpty check --}}
```

with:

```blade
    </div>
</div>
    </div>
</details>
@endif

@endif {{-- end isEmpty check --}}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/MentorDashboardQueueCardTest.php`
Expected: PASS

- [ ] **Step 6: Run full regression check**

Run: `php artisan test tests/Feature/MentorDashboardQueueCardTest.php tests/Feature/MentorDashboardPriorityQueueTest.php tests/Unit/Services/MentorPriorityQueueResolverTest.php`
Expected: PASS, all tests green.

- [ ] **Step 7: Manually verify in browser**

Log in as a mentor with a mix of EmONC and non-EmONC mentorships and confirm: the priority queue card renders above the fold with correctly ranked items; "Mentorship Overview" and "Recent Recommendations" are collapsed by default and expand on click; the non-EmONC mentorships never produce Tier 1/2 items; the empty state ("You're all caught up") renders correctly for a mentor with no outstanding items.

- [ ] **Step 8: Commit**

```bash
git add resources/views/filament/pages/mentor-dashboard.blade.php tests/Feature/MentorDashboardQueueCardTest.php
git commit -m "feat: add ranked priority-queue card to mentor dashboard, collapse secondary sections"
```

---

## Self-Review Notes

- **Spec coverage:** Section 3 (resolver + all 5 tiers) → Task 1. Section 4 (layout) → Task 3. Section 5 (edge cases: multi-tier dedup, empty collections, Tier 2's `hasCompletedAllModules()` guard) → covered by Task 1's tests. Section 6 (testing) → each listed scenario has a corresponding test in Task 1 or Task 3. Section 7's acceptance criteria are each covered by a task's test or manual-verification step.
- **Placeholder scan:** no TBD/TODO markers; every step has real, complete code.
- **Type consistency:** the queue item shape (`tier`, `label`, `headline`, `subtext`, `url`, `meta`) is identical across Task 1's resolver, Task 2's wiring, and Task 3's Blade consumption. `MentorPriorityQueueResolver::resolve(User $mentor, array $trainingIds)` signature matches between its definition (Task 1) and its call site (Task 2).
