<?php

namespace Tests\Feature;

use App\Filament\Pages\HeadDrmhReviewMentee;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HeadDrmhReviewMenteeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pretest_shows_score_not_pass_fail_language(): void
    {
        $reviewer = User::factory()->create();
        Permission::firstOrCreate(['name' => 'page_HeadDrmhReviewMentee', 'guard_name' => 'web']);
        $reviewer->givePermissionTo(['page_HeadDrmhReviewMentee']);
        $this->actingAs($reviewer);

        $program = Program::factory()->create(['is_active' => true]);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
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

        $attempt = QuizAttempt::create([
            'program_module_quiz_id' => $quiz->id,
            'user_id' => $mentee->id,
            'attempt_type' => 'pre_test',
            'score' => 33.33,
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

        Livewire::withQueryParams(['participant' => $participant->id])
            ->test(HeadDrmhReviewMentee::class)
            ->assertSuccessful()
            ->assertDontSee('Failed')
            ->assertSee('33');
    }
}
