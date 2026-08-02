<?php

namespace Tests\Feature;

use App\Models\ClassModule;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class QuizModalTimerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{class: MentorshipClass, classModule: ClassModule, attempt: QuizAttempt, startedAt: \Illuminate\Support\Carbon}
     */
    private function buildAttempt(?int $timeLimitMinutes): array
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
            'time_limit_minutes' => $timeLimitMinutes,
            'order_sequence' => 1,
            'is_active' => true,
        ]);

        $mentee = User::factory()->create();
        // startOfSecond() — DB datetime columns don't retain sub-second
        // precision, so a round-tripped started_at would otherwise differ
        // from this in-memory value by a few hundred milliseconds.
        $startedAt = now()->subMinutes(3)->startOfSecond();
        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $mentee->id,
            'attempt_type' => 'pre_test',
            'total_questions' => 0,
            'started_at' => $startedAt,
        ]);

        return compact('class', 'classModule', 'attempt', 'startedAt');
    }

    public function test_timer_markup_renders_with_the_correct_deadline_when_the_quiz_has_a_time_limit(): void
    {
        ['class' => $class, 'classModule' => $classModule, 'attempt' => $attempt, 'startedAt' => $startedAt] = $this->buildAttempt(15);
        Session::put('quiz_attempt_id', $attempt->id);

        $html = view('mentee.partials.quiz-modal', ['class' => $class, 'classModule' => $classModule])->render();

        $expectedDeadlineMs = $startedAt->clone()->addMinutes(15)->valueOf();

        $this->assertStringContainsString('hasTimer: true', $html);
        $this->assertStringContainsString("deadline: {$expectedDeadlineMs}", $html);
        $this->assertStringContainsString('quiz-timer', $html);
        $this->assertStringContainsString('quiz-offline-banner', $html);
        $this->assertStringContainsString("window.addEventListener('offline'", $html);
    }

    public function test_no_timer_markup_data_when_the_quiz_has_no_time_limit(): void
    {
        ['class' => $class, 'classModule' => $classModule, 'attempt' => $attempt] = $this->buildAttempt(null);
        Session::put('quiz_attempt_id', $attempt->id);

        $html = view('mentee.partials.quiz-modal', ['class' => $class, 'classModule' => $classModule])->render();

        $this->assertStringContainsString('hasTimer: false', $html);
        $this->assertStringContainsString('deadline: null', $html);
    }
}
