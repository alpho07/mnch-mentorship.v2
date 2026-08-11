<?php

namespace Tests\Unit;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenteeModuleProgressAutoCompleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{classModule: ClassModule, progress: MenteeModuleProgress, mentee: User, preQuiz: ?ProgramModuleQuiz, postQuiz: ?ProgramModuleQuiz}
     */
    private function buildProgress(string $programName, bool $withPreTest, bool $withPostTest): array
    {
        $program = Program::factory()->create(['name' => $programName]);
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $module->id,
        ]);

        $preQuiz = $withPreTest ? ProgramModuleQuiz::create([
            'program_module_id' => $module->id,
            'type' => 'pre_test',
            'title' => 'Pre-Test',
            'pass_mark_percentage' => 80,
            'order_sequence' => 1,
            'is_active' => true,
        ]) : null;

        $postQuiz = $withPostTest ? ProgramModuleQuiz::create([
            'program_module_id' => $module->id,
            'type' => 'post_test',
            'title' => 'Post-Test',
            'pass_mark_percentage' => 80,
            'order_sequence' => 2,
            'is_active' => true,
        ]) : null;

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);

        // fresh() so the DB-level default for assessment_status ('pending')
        // is actually loaded into the instance, matching how a real request
        // would see this row (never operating on the just-inserted instance
        // in the same breath it was created in).
        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ])->fresh();

        return compact('classModule', 'progress', 'mentee', 'preQuiz', 'postQuiz');
    }

    private function completedAttempt(ProgramModuleQuiz $quiz, User $mentee, string $type): QuizAttempt
    {
        return QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $mentee->id,
            'attempt_type' => $type,
            'total_questions' => 0,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function test_not_ready_when_program_is_not_emonc(): void
    {
        ['progress' => $progress, 'mentee' => $mentee, 'preQuiz' => $preQuiz, 'postQuiz' => $postQuiz] = $this->buildProgress('Newborn Care', true, true);
        $pre = $this->completedAttempt($preQuiz, $mentee, 'pre_test');
        $post = $this->completedAttempt($postQuiz, $mentee, 'post_test');
        $progress->update(['pre_test_attempt_id' => $pre->id, 'post_test_attempt_id' => $post->id, 'video_review_status' => 'passed']);

        $this->assertFalse($progress->readyForMenteeAutoCompletion());
    }

    public function test_not_ready_when_video_not_passed(): void
    {
        ['progress' => $progress, 'mentee' => $mentee, 'preQuiz' => $preQuiz, 'postQuiz' => $postQuiz] = $this->buildProgress('Maternal Health (EmONC)', true, true);
        $pre = $this->completedAttempt($preQuiz, $mentee, 'pre_test');
        $post = $this->completedAttempt($postQuiz, $mentee, 'post_test');
        $progress->update(['pre_test_attempt_id' => $pre->id, 'post_test_attempt_id' => $post->id]);

        $this->assertFalse($progress->readyForMenteeAutoCompletion());
    }

    public function test_not_ready_when_pre_test_exists_but_not_attempted(): void
    {
        ['progress' => $progress, 'mentee' => $mentee, 'postQuiz' => $postQuiz] = $this->buildProgress('Maternal Health (EmONC)', true, true);
        $post = $this->completedAttempt($postQuiz, $mentee, 'post_test');
        $progress->update(['post_test_attempt_id' => $post->id, 'video_review_status' => 'passed']);

        $this->assertFalse($progress->readyForMenteeAutoCompletion());
    }

    public function test_not_ready_when_post_test_exists_but_not_attempted(): void
    {
        ['progress' => $progress, 'mentee' => $mentee, 'preQuiz' => $preQuiz] = $this->buildProgress('Maternal Health (EmONC)', true, true);
        $pre = $this->completedAttempt($preQuiz, $mentee, 'pre_test');
        $progress->update(['pre_test_attempt_id' => $pre->id, 'video_review_status' => 'passed']);

        $this->assertFalse($progress->readyForMenteeAutoCompletion());
    }

    public function test_ready_when_pretest_video_and_posttest_all_done(): void
    {
        ['progress' => $progress, 'mentee' => $mentee, 'preQuiz' => $preQuiz, 'postQuiz' => $postQuiz] = $this->buildProgress('Maternal Health (EmONC)', true, true);
        $pre = $this->completedAttempt($preQuiz, $mentee, 'pre_test');
        $post = $this->completedAttempt($postQuiz, $mentee, 'post_test');
        $progress->update(['pre_test_attempt_id' => $pre->id, 'post_test_attempt_id' => $post->id, 'video_review_status' => 'passed']);

        $this->assertTrue($progress->readyForMenteeAutoCompletion());
    }

    public function test_ready_when_module_has_no_quizzes_and_video_is_passed(): void
    {
        ['progress' => $progress] = $this->buildProgress('Maternal Health (EmONC)', false, false);
        $progress->update(['video_review_status' => 'passed']);

        $this->assertTrue($progress->readyForMenteeAutoCompletion());
    }

    public function test_maybe_auto_complete_marks_completed_and_locks(): void
    {
        ['progress' => $progress, 'mentee' => $mentee, 'preQuiz' => $preQuiz, 'postQuiz' => $postQuiz] = $this->buildProgress('Maternal Health (EmONC)', true, true);
        $pre = $this->completedAttempt($preQuiz, $mentee, 'pre_test');
        $post = $this->completedAttempt($postQuiz, $mentee, 'post_test');
        $progress->update(['pre_test_attempt_id' => $pre->id, 'post_test_attempt_id' => $post->id, 'video_review_status' => 'passed']);

        $result = $progress->maybeAutoComplete();

        $this->assertTrue($result);
        $this->assertSame('completed', $progress->fresh()->status);
        $this->assertNotNull($progress->fresh()->completed_at);
        $this->assertTrue($progress->fresh()->isLockedForMentee());
    }

    public function test_maybe_auto_complete_is_a_noop_when_not_ready(): void
    {
        ['progress' => $progress] = $this->buildProgress('Maternal Health (EmONC)', true, true);

        $this->assertFalse($progress->maybeAutoComplete());
        $this->assertSame('in_progress', $progress->fresh()->status);
    }

    public function test_maybe_auto_complete_does_not_touch_an_exempted_module(): void
    {
        ['progress' => $progress, 'mentee' => $mentee, 'preQuiz' => $preQuiz, 'postQuiz' => $postQuiz] = $this->buildProgress('Maternal Health (EmONC)', true, true);
        $pre = $this->completedAttempt($preQuiz, $mentee, 'pre_test');
        $post = $this->completedAttempt($postQuiz, $mentee, 'post_test');
        $progress->update([
            'status' => 'exempted',
            'pre_test_attempt_id' => $pre->id,
            'post_test_attempt_id' => $post->id,
            'video_review_status' => 'passed',
        ]);

        $this->assertFalse($progress->maybeAutoComplete());
        $this->assertSame('exempted', $progress->fresh()->status);
    }
}
