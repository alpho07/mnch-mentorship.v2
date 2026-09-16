<?php

namespace Tests\Feature\RubricAssessment;

use App\Filament\Resources\RubricAssessmentResource\Pages\ConductRubricAssessment;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\RubricItem;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PassingRubricSyncsParticipantStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_passing_rubric_assessment_that_was_the_last_gap_marks_the_participant_completed(): void
    {
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);

        $mentor = User::factory()->create(['name' => 'Mentor']);
        $mentor->assignRole('facility_mentor');
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_rubric::assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_rubric::assessment', 'guard_name' => 'web']);
        $mentor->givePermissionTo([
            'view_any_mentorship::training',
            'view_any_rubric::assessment',
            'create_rubric::assessment',
        ]);
        $this->actingAs($mentor);

        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $rubric = ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Test Rubric',
            'total_marks' => 1,
            'pass_marks' => 1,
            'pass_percentage' => 100,
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        $item = RubricItem::create(['module_rubric_id' => $rubric->id, 'description' => 'Step 1', 'order_sequence' => 1, 'is_active' => true]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);
        // Activities already fully done (progress status completed) — video
        // review is the only remaining gap before syncCompletionStatus() can fire.
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'pending',
        ]);

        $component = Livewire::withQueryParams([
            'rubric_id' => $rubric->id,
            'mentee_id' => $mentee->id,
            'class_module_id' => $classModule->id,
        ])->test(ConductRubricAssessment::class);

        $component->call('proceedToScoring');
        $component->call('toggleItem', $item->id);
        $component->call('submitAssessment');

        $this->assertSame('completed', $participant->fresh()->status);
    }
}
