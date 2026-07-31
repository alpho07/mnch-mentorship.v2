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
