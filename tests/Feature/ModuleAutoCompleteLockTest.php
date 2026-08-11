<?php

namespace Tests\Feature;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleAutoCompleteLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{class: MentorshipClass, classModule: ClassModule, participant: ClassParticipant, progress: MenteeModuleProgress, mentee: User, postQuiz: ProgramModuleQuiz, postOption: QuizOption}
     */
    private function buildScenario(): array
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $module->id,
            'status' => 'in_progress',
        ]);

        $preQuiz = ProgramModuleQuiz::create([
            'program_module_id' => $module->id,
            'type' => 'pre_test',
            'title' => 'Pre-Test',
            'pass_mark_percentage' => 80,
            'order_sequence' => 1,
            'is_active' => true,
        ]);

        $postQuiz = ProgramModuleQuiz::create([
            'program_module_id' => $module->id,
            'type' => 'post_test',
            'title' => 'Post-Test',
            'pass_mark_percentage' => 80,
            'order_sequence' => 2,
            'is_active' => true,
        ]);
        $postQuestion = QuizQuestion::create([
            'program_module_quiz_id' => $postQuiz->id,
            'question_text' => 'Q1',
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        $postOption = QuizOption::create(['quiz_question_id' => $postQuestion->id, 'option_text' => 'Right', 'is_correct' => true, 'order_sequence' => 1]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);

        $preAttempt = QuizAttempt::create([
            'program_module_quiz_id' => $preQuiz->id,
            'user_id' => $mentee->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 0,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(9),
        ]);

        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
            'pre_test_attempt_id' => $preAttempt->id,
            'video_review_status' => 'passed',
            'hands_on_video_url' => 'https://youtu.be/abc12345678',
        ])->fresh();

        return compact('class', 'classModule', 'participant', 'progress', 'mentee', 'postQuiz', 'postOption');
    }

    public function test_submitting_the_post_test_auto_completes_and_locks_the_module(): void
    {
        ['class' => $class, 'classModule' => $classModule, 'progress' => $progress, 'mentee' => $mentee, 'postQuiz' => $postQuiz] = $this->buildScenario();

        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $postQuiz->id,
            'user_id' => $mentee->id,
            'attempt_type' => 'post_test',
            'total_questions' => 1,
            'started_at' => now(),
        ]);
        $question = $postQuiz->questions()->first();
        $option = $question->options()->first();

        $response = $this->actingAs($mentee)->post(
            route('mentee.class.quiz.submit', [$class->id, $classModule->id, $attempt->id]),
            ['responses' => [$question->id => $option->id]]
        );

        $response->assertRedirect();
        $this->assertSame('completed', $progress->fresh()->status);
        $this->assertTrue($progress->fresh()->isLockedForMentee());
    }

    /**
     * `markCompleted()` alone only flips `status` — it doesn't make
     * isLockedForMentee() true (that's deliberately independent, see the
     * model). These tests need the mentee's own steps to genuinely be
     * done, so also record a completed post-test attempt before locking.
     */
    private function lockScenario(MenteeModuleProgress $progress, ProgramModuleQuiz $postQuiz, User $mentee): void
    {
        $postAttempt = QuizAttempt::create([
            'program_module_quiz_id' => $postQuiz->id,
            'user_id' => $mentee->id,
            'attempt_type' => 'post_test',
            'total_questions' => 1,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $progress->update(['post_test_attempt_id' => $postAttempt->id]);
        $progress->markCompleted();
    }

    public function test_starting_a_quiz_on_a_locked_module_is_rejected(): void
    {
        ['class' => $class, 'classModule' => $classModule, 'progress' => $progress, 'mentee' => $mentee, 'postQuiz' => $postQuiz] = $this->buildScenario();
        $this->lockScenario($progress, $postQuiz, $mentee);

        $response = $this->actingAs($mentee)->post(
            route('mentee.class.quiz.start', [$class->id, $classModule->id, $postQuiz->id]),
            ['attempt_type' => 'post_test']
        );

        $response->assertSessionHas('error', 'This module has already been completed and is locked.');
        $this->assertSame(1, QuizAttempt::where('program_module_quiz_id', $postQuiz->id)->where('attempt_type', 'post_test')->count());
    }

    public function test_uploading_a_video_on_a_locked_module_is_rejected(): void
    {
        ['class' => $class, 'classModule' => $classModule, 'progress' => $progress, 'mentee' => $mentee, 'postQuiz' => $postQuiz] = $this->buildScenario();
        $this->lockScenario($progress, $postQuiz, $mentee);

        $response = $this->actingAs($mentee)->post(
            route('mentee.class.video.upload', [$class->id, $classModule->id]),
            ['video_input_type' => 'link', 'hands_on_video_link' => 'https://youtu.be/newvideo123']
        );

        $response->assertSessionHas('error', 'This module has already been completed and is locked.');
        $this->assertNotSame('https://youtu.be/newvideo123', $progress->fresh()->hands_on_video_url);
    }
}
