<?php

namespace Tests\Feature;

use App\Models\ClassModule;
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
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class QuizAnswerAutosaveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{class: MentorshipClass, classModule: ClassModule, attempt: QuizAttempt, question: QuizQuestion, correct: QuizOption, wrong: QuizOption, mentee: User}
     */
    private function buildAttempt(): array
    {
        $program = Program::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $module->id,
        ]);

        $quiz = ProgramModuleQuiz::create([
            'program_module_id' => $module->id,
            'type' => 'pre_test',
            'title' => 'Pre-Test',
            'pass_mark_percentage' => 80,
            'time_limit_minutes' => 15,
            'order_sequence' => 1,
            'is_active' => true,
        ]);

        $question = QuizQuestion::create([
            'program_module_quiz_id' => $quiz->id,
            'question_text' => 'Question 1',
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        $correct = QuizOption::create(['quiz_question_id' => $question->id, 'option_text' => 'Right', 'is_correct' => true, 'order_sequence' => 1]);
        $wrong = QuizOption::create(['quiz_question_id' => $question->id, 'option_text' => 'Wrong', 'is_correct' => false, 'order_sequence' => 2]);

        $mentee = User::factory()->create();
        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $mentee->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 1,
            'started_at' => now(),
        ]);

        return compact('class', 'classModule', 'attempt', 'question', 'correct', 'wrong', 'mentee');
    }

    public function test_mentee_can_autosave_an_answer_for_their_own_in_progress_attempt(): void
    {
        ['class' => $class, 'classModule' => $classModule, 'attempt' => $attempt, 'question' => $question, 'correct' => $correct, 'mentee' => $mentee] = $this->buildAttempt();

        $response = $this->actingAs($mentee)->postJson(
            route('mentee.class.quiz.save', [$class->id, $classModule->id, $attempt->id]),
            ['question_id' => $question->id, 'option_id' => $correct->id]
        );

        $response->assertOk()->assertJson(['saved' => true]);
        $this->assertDatabaseHas('quiz_responses', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $question->id,
            'quiz_option_id' => $correct->id,
        ]);
    }

    public function test_another_users_attempt_cannot_be_autosaved_to(): void
    {
        ['class' => $class, 'classModule' => $classModule, 'attempt' => $attempt, 'question' => $question, 'correct' => $correct] = $this->buildAttempt();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)->postJson(
            route('mentee.class.quiz.save', [$class->id, $classModule->id, $attempt->id]),
            ['question_id' => $question->id, 'option_id' => $correct->id]
        );

        $response->assertForbidden();
    }

    public function test_a_completed_attempt_cannot_be_autosaved_to(): void
    {
        ['class' => $class, 'classModule' => $classModule, 'attempt' => $attempt, 'question' => $question, 'correct' => $correct, 'mentee' => $mentee] = $this->buildAttempt();
        $attempt->update(['completed_at' => now()]);

        $response = $this->actingAs($mentee)->postJson(
            route('mentee.class.quiz.save', [$class->id, $classModule->id, $attempt->id]),
            ['question_id' => $question->id, 'option_id' => $correct->id]
        );

        $response->assertStatus(422);
    }

    public function test_reopening_the_quiz_modal_shows_the_previously_saved_answer_as_checked(): void
    {
        ['class' => $class, 'classModule' => $classModule, 'attempt' => $attempt, 'question' => $question, 'wrong' => $wrong, 'mentee' => $mentee] = $this->buildAttempt();

        app(\App\Services\QuizAttemptService::class)->saveResponse($attempt, $question->id, $wrong->id);

        $this->actingAs($mentee);
        Session::put('quiz_attempt_id', $attempt->id);

        $html = view('mentee.partials.quiz-modal', ['class' => $class, 'classModule' => $classModule])->render();

        $this->assertMatchesRegularExpression(
            '/<input type="radio" name="responses\['.$question->id.'\]" value="'.$wrong->id.'" required\s+checked/',
            $html
        );
    }
}
