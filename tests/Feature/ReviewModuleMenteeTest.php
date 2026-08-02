<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ReviewModuleMentee;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
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
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReviewModuleMenteeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMentor(): User
    {
        $user = User::factory()->create(['name' => 'Mentor User']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Builds a Training/Class/ClassModule/ClassParticipant chain with a
     * completed pre-test QuizAttempt scored 5/15 (below the 80% pass
     * mark), and returns the mentor + route params needed to reach
     * ReviewModuleMentee.
     */
    private function buildScenario(User $mentor): array
    {
        $program = Program::factory()->create(['is_active' => true]);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $training = Training::factory()->facilityMentorship()->create(['mentor_id' => $mentor->id, 'program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);

        $quiz = ProgramModuleQuiz::create([
            'program_module_id' => $programModule->id,
            'type' => 'pre_test',
            'title' => 'Pre-Test',
            'pass_mark_percentage' => 80,
            'order_sequence' => 1,
            'is_active' => true,
        ]);

        // 15 questions, mentee answers 5 correctly.
        $questionIds = [];
        foreach (range(1, 15) as $i) {
            $question = QuizQuestion::create([
                'program_module_quiz_id' => $quiz->id,
                'question_text' => "Question {$i}",
                'order_sequence' => $i,
                'is_active' => true,
            ]);
            QuizOption::create(['quiz_question_id' => $question->id, 'option_text' => 'Right', 'is_correct' => true, 'order_sequence' => 1]);
            QuizOption::create(['quiz_question_id' => $question->id, 'option_text' => 'Wrong', 'is_correct' => false, 'order_sequence' => 2]);
            $questionIds[] = $question->id;
        }

        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $mentee->id,
            'attempt_type' => 'pre_test',
            'score' => round((5 / 15) * 100, 2),
            'total_questions' => 15,
            'correct_answers' => 5,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);

        \App\Models\MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
            'pre_test_attempt_id' => $attempt->id,
        ]);

        return compact('training', 'class', 'classModule', 'participant');
    }

    public function test_pretest_shows_score_not_pass_fail_language(): void
    {
        $mentor = $this->actingAsMentor();
        $scenario = $this->buildScenario($mentor);

        Livewire::test(ReviewModuleMentee::class, [
            'training' => $scenario['training'],
            'class' => $scenario['class'],
            'module' => $scenario['classModule'],
            'participant' => $scenario['participant'],
        ])
            ->assertSuccessful()
            ->assertDontSee('Did Not Pass')
            ->assertSee('5/15');
    }
}
