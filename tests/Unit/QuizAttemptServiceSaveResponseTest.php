<?php

namespace Tests\Unit;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\QuizAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class QuizAttemptServiceSaveResponseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{quiz: ProgramModuleQuiz, question: QuizQuestion, correct: QuizOption, wrong: QuizOption}
     */
    private function buildQuizWithQuestion(): array
    {
        $program = Program::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);

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

        return compact('quiz', 'question', 'correct', 'wrong');
    }

    public function test_save_response_persists_the_selected_option(): void
    {
        ['quiz' => $quiz, 'question' => $question, 'wrong' => $wrong] = $this->buildQuizWithQuestion();
        $user = User::factory()->create();
        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 1,
            'started_at' => now(),
        ]);

        $response = app(QuizAttemptService::class)->saveResponse($attempt, $question->id, $wrong->id);

        $this->assertSame($wrong->id, $response->quiz_option_id);
        $this->assertFalse($response->is_correct);
        $this->assertDatabaseHas('quiz_responses', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $question->id,
            'quiz_option_id' => $wrong->id,
        ]);
    }

    public function test_saving_again_updates_the_same_row_instead_of_duplicating(): void
    {
        ['quiz' => $quiz, 'question' => $question, 'correct' => $correct, 'wrong' => $wrong] = $this->buildQuizWithQuestion();
        $user = User::factory()->create();
        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 1,
            'started_at' => now(),
        ]);

        $service = app(QuizAttemptService::class);
        $first = $service->saveResponse($attempt, $question->id, $wrong->id);
        $second = $service->saveResponse($attempt, $question->id, $correct->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($correct->id, $second->fresh()->quiz_option_id);
        $this->assertTrue($second->fresh()->is_correct);
        $this->assertSame(1, QuizResponse::where('quiz_attempt_id', $attempt->id)->count());
    }

    public function test_save_response_rejects_an_already_completed_attempt(): void
    {
        ['quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->buildQuizWithQuestion();
        $user = User::factory()->create();
        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 1,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This attempt has already been submitted.');

        app(QuizAttemptService::class)->saveResponse($attempt, $question->id, $correct->id);
    }

    public function test_final_submit_does_not_collide_with_an_earlier_autosaved_response(): void
    {
        ['quiz' => $quiz, 'question' => $question, 'correct' => $correct, 'wrong' => $wrong] = $this->buildQuizWithQuestion();
        $user = User::factory()->create();
        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 1,
            'started_at' => now(),
        ]);

        $service = app(QuizAttemptService::class);
        // Mentee picks "wrong" first (autosaved), then changes their mind to
        // "correct" before the final submit — the submit's bulk insert must
        // not collide with the row saveResponse() already wrote.
        $service->saveResponse($attempt, $question->id, $wrong->id);

        $result = $service->submitAttempt($attempt, [$question->id => $correct->id]);

        $this->assertSame(1, $result->correct_answers);
        $this->assertSame(1, QuizResponse::where('quiz_attempt_id', $attempt->id)->count());
        $this->assertDatabaseHas('quiz_responses', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $question->id,
            'quiz_option_id' => $correct->id,
        ]);
    }
}
