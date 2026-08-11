<?php

namespace App\Services;

use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\QuizResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuizAttemptService
{
    /**
     * Start a quiz attempt — resumes an existing in-progress (uncompleted)
     * attempt for this quiz/user/type if one exists, rather than creating a
     * new one with a fresh started_at. Without this, re-hitting the "start
     * quiz" route (e.g. because the in-progress attempt didn't survive a
     * page refresh) silently restarted the countdown from full duration
     * every time.
     */
    public function startAttempt(ProgramModuleQuiz $quiz, User $user, string $attemptType): QuizAttempt
    {
        $this->validateAttemptType($quiz, $attemptType);

        return DB::transaction(function () use ($quiz, $user, $attemptType) {
            $existing = QuizAttempt::where('program_module_quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->where('attempt_type', $attemptType)
                ->whereNull('completed_at')
                ->latest('started_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            return QuizAttempt::create([
                'program_module_quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'attempt_type' => $attemptType,
                'total_questions' => $quiz->questions()->where('is_active', true)->count(),
                'started_at' => now(),
            ]);
        });
    }

    /**
     * Persist a single answer as the mentee picks it, so closing the quiz
     * modal (or a crashed tab/lost connection) doesn't lose their progress —
     * the timer keeps running regardless, and reopening the modal restores
     * whatever was already selected.
     */
    public function saveResponse(QuizAttempt $attempt, int $questionId, int $optionId): QuizResponse
    {
        if ($attempt->completed_at !== null) {
            throw new InvalidArgumentException('This attempt has already been submitted.');
        }

        $question = $attempt->quiz->questions()->where('is_active', true)->findOrFail($questionId);
        $option = $question->options()->findOrFail($optionId);

        return QuizResponse::updateOrCreate(
            ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $question->id],
            ['quiz_option_id' => $option->id, 'is_correct' => (bool) $option->is_correct]
        );
    }

    /**
     * Submit responses for an attempt and score it.
     *
     * @param  array<int, int>  $responses  question_id => option_id
     */
    public function submitAttempt(QuizAttempt $attempt, array $responses): QuizAttempt
    {
        if ($attempt->completed_at !== null) {
            throw new InvalidArgumentException('This attempt has already been submitted.');
        }

        $quiz = $attempt->quiz;
        $questions = $quiz->questions()->where('is_active', true)->with('options')->get();

        // A timed-out attempt is allowed to submit whatever was answered so
        // far — unanswered questions just score as incorrect (no response
        // row is written for them; every existing reader already treats a
        // missing response as unanswered via optional chaining). A normal,
        // still-in-time submission keeps the original strict requirement.
        $isExpired = $this->isExpired($attempt);

        if (! $isExpired && count($responses) !== $questions->count()) {
            throw new InvalidArgumentException('All questions must be answered.');
        }

        $correctAnswers = 0;
        $responsesToInsert = [];

        DB::transaction(function () use ($attempt, $questions, $responses, $isExpired, &$correctAnswers, &$responsesToInsert) {
            foreach ($questions as $question) {
                $selectedOptionId = $responses[$question->id] ?? null;

                if ($selectedOptionId === null) {
                    if ($isExpired) {
                        continue;
                    }

                    throw new InvalidArgumentException("Question {$question->id} has no selected answer.");
                }

                $selectedOption = $question->options->firstWhere('id', $selectedOptionId);

                if ($selectedOption === null) {
                    throw new InvalidArgumentException("Invalid option selected for question {$question->id}.");
                }

                $isCorrect = (bool) $selectedOption->is_correct;

                if ($isCorrect) {
                    $correctAnswers++;
                }

                $responsesToInsert[] = [
                    'quiz_attempt_id' => $attempt->id,
                    'quiz_question_id' => $question->id,
                    'quiz_option_id' => $selectedOption->id,
                    'is_correct' => $isCorrect,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Clear any autosaved responses first — the submitted form is the
            // authoritative final state, and a question autosaved earlier via
            // saveResponse() would otherwise collide with this bulk insert on
            // the (quiz_attempt_id, quiz_question_id) unique constraint.
            QuizResponse::where('quiz_attempt_id', $attempt->id)->delete();

            if (! empty($responsesToInsert)) {
                QuizResponse::insert($responsesToInsert);
            }

            $totalQuestions = $questions->count();
            $score = $totalQuestions > 0
                ? round(($correctAnswers / $totalQuestions) * 100, 2)
                : 0.00;

            $attempt->update([
                'score' => $score,
                'correct_answers' => $correctAnswers,
                'total_questions' => $totalQuestions,
                'completed_at' => now(),
            ]);
        });

        return $attempt->fresh(['responses.option', 'responses.question', 'quiz']);
    }

    /**
     * Get detailed results for a completed attempt, including correct answers.
     */
    public function getResults(QuizAttempt $attempt): array
    {
        if ($attempt->completed_at === null) {
            throw new InvalidArgumentException('Attempt has not been completed yet.');
        }

        $quiz = $attempt->quiz;
        $questions = $quiz->questions()->where('is_active', true)->with(['options', 'correctOption'])->get();

        $questionResults = $questions->map(function (QuizQuestion $question) use ($attempt) {
            $response = $attempt->responses->firstWhere('quiz_question_id', $question->id);
            $selectedOption = $response?->option;
            $correctOption = $question->correctOption;

            return [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'explanation' => $question->explanation,
                'selected_option_id' => $selectedOption?->id,
                'selected_option_text' => $selectedOption?->option_text,
                'correct_option_id' => $correctOption?->id,
                'correct_option_text' => $correctOption?->option_text,
                'is_correct' => $response?->is_correct ?? false,
            ];
        });

        return [
            'attempt_id' => $attempt->id,
            'attempt_type' => $attempt->attempt_type,
            'score' => $attempt->score,
            'pass_mark_percentage' => $quiz->pass_mark_percentage,
            'passed' => $attempt->isPassed(),
            'total_questions' => $attempt->total_questions,
            'correct_answers' => $attempt->correct_answers,
            'questions' => $questionResults,
        ];
    }

    /**
     * Get the average score of pre-test and post-test attempts for a user and quiz.
     */
    public function getAverageScore(User $user, ProgramModuleQuiz $quiz): ?float
    {
        $attempts = QuizAttempt::where('user_id', $user->id)
            ->where('program_module_quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->get();

        if ($attempts->isEmpty()) {
            return null;
        }

        return round($attempts->avg('score'), 2);
    }

    /**
     * Get the latest completed pre-test and post-test attempts for a user and quiz.
     *
     * @return array{pre_test: QuizAttempt|null, post_test: QuizAttempt|null}
     */
    public function getLatestAttempts(User $user, ProgramModuleQuiz $quiz): array
    {
        return [
            'pre_test' => QuizAttempt::with(['responses.option'])
                ->where('user_id', $user->id)
                ->where('program_module_quiz_id', $quiz->id)
                ->where('attempt_type', 'pre_test')
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->first(),
            'post_test' => QuizAttempt::with(['responses.option'])
                ->where('user_id', $user->id)
                ->where('program_module_quiz_id', $quiz->id)
                ->where('attempt_type', 'post_test')
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->first(),
        ];
    }

    /**
     * Whether this attempt's quiz has a time limit and it has already
     * passed, based on when the attempt was started.
     */
    private function isExpired(QuizAttempt $attempt): bool
    {
        $quiz = $attempt->quiz;

        if (! $quiz || $quiz->time_limit_minutes === null || $attempt->started_at === null) {
            return false;
        }

        return now()->greaterThan($attempt->started_at->clone()->addMinutes($quiz->time_limit_minutes));
    }

    /**
     * Validate that the quiz allows the requested attempt type.
     */
    private function validateAttemptType(ProgramModuleQuiz $quiz, string $attemptType): void
    {
        if (! in_array($attemptType, ['pre_test', 'post_test'], true)) {
            throw new InvalidArgumentException('Attempt type must be pre_test or post_test.');
        }

        if ($attemptType === 'pre_test' && ! $quiz->isPreTest()) {
            throw new InvalidArgumentException('This quiz does not allow pre-test attempts.');
        }

        if ($attemptType === 'post_test' && ! $quiz->isPostTest()) {
            throw new InvalidArgumentException('This quiz does not allow post-test attempts.');
        }
    }
}
