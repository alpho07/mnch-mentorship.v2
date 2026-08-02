<?php

namespace Tests\Unit;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\QuizAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class QuizAttemptServiceTimeoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{quiz: ProgramModuleQuiz, questions: array<int, array{question: QuizQuestion, correct: QuizOption, wrong: QuizOption}>}
     */
    private function buildQuiz(?int $timeLimitMinutes): array
    {
        $program = Program::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);

        $quiz = ProgramModuleQuiz::create([
            'program_module_id' => $module->id,
            'type' => 'pre_test',
            'title' => 'Pre-Test',
            'pass_mark_percentage' => 80,
            'time_limit_minutes' => $timeLimitMinutes,
            'order_sequence' => 1,
            'is_active' => true,
        ]);

        $questions = [];
        foreach (range(1, 3) as $i) {
            $question = QuizQuestion::create([
                'program_module_quiz_id' => $quiz->id,
                'question_text' => "Question {$i}",
                'order_sequence' => $i,
                'is_active' => true,
            ]);
            $correct = QuizOption::create(['quiz_question_id' => $question->id, 'option_text' => 'Right', 'is_correct' => true, 'order_sequence' => 1]);
            $wrong = QuizOption::create(['quiz_question_id' => $question->id, 'option_text' => 'Wrong', 'is_correct' => false, 'order_sequence' => 2]);
            $questions[] = compact('question', 'correct', 'wrong');
        }

        return compact('quiz', 'questions');
    }

    public function test_submit_still_requires_all_questions_when_there_is_no_time_limit(): void
    {
        ['quiz' => $quiz, 'questions' => $questions] = $this->buildQuiz(null);
        $user = User::factory()->create();
        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 3,
            'started_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All questions must be answered.');

        app(QuizAttemptService::class)->submitAttempt($attempt, [
            $questions[0]['question']->id => $questions[0]['correct']->id,
        ]);
    }

    public function test_submit_still_requires_all_questions_when_the_time_limit_has_not_expired(): void
    {
        ['quiz' => $quiz, 'questions' => $questions] = $this->buildQuiz(15);
        $user = User::factory()->create();
        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 3,
            'started_at' => now()->subMinutes(5), // 10 minutes still remaining
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(QuizAttemptService::class)->submitAttempt($attempt, [
            $questions[0]['question']->id => $questions[0]['correct']->id,
        ]);
    }

    public function test_submit_allows_a_partial_answer_set_once_the_time_limit_has_expired(): void
    {
        ['quiz' => $quiz, 'questions' => $questions] = $this->buildQuiz(15);
        $user = User::factory()->create();
        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 3,
            'started_at' => now()->subMinutes(20), // 5 minutes past the 15-minute limit
        ]);

        // Answers question 1 correctly, question 2 wrong, leaves question 3 unanswered.
        $result = app(QuizAttemptService::class)->submitAttempt($attempt, [
            $questions[0]['question']->id => $questions[0]['correct']->id,
            $questions[1]['question']->id => $questions[1]['wrong']->id,
        ]);

        $this->assertNotNull($result->completed_at);
        $this->assertSame(1, $result->correct_answers);
        $this->assertSame(3, $result->total_questions);
        $this->assertEqualsWithDelta(33.33, (float) $result->score, 0.01);

        // No response row at all for the unanswered question — the existing
        // read paths (e.g. QuizAttemptService::getResults()) already treat
        // a missing response as unanswered/incorrect via optional chaining.
        $this->assertDatabaseMissing('quiz_responses', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $questions[2]['question']->id,
        ]);
        $this->assertDatabaseHas('quiz_responses', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $questions[0]['question']->id,
            'quiz_option_id' => $questions[0]['correct']->id,
        ]);
    }
}
