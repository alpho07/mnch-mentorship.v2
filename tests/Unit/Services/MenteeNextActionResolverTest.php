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
