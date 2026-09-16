<?php

namespace Tests\Unit\Models;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassParticipantHasCompletedAllModulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{participant: ClassParticipant, classModule: ClassModule, programModule: ProgramModule}
     */
    private function makeParticipantWithOneModule(string $programName): array
    {
        $program = Program::factory()->create(['name' => $programName]);
        $mentor = User::factory()->create();
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);

        return compact('participant', 'classModule', 'programModule');
    }

    public function test_emonc_participant_with_pending_video_review_is_not_complete(): void
    {
        ['participant' => $participant, 'classModule' => $classModule, 'programModule' => $programModule]
            = $this->makeParticipantWithOneModule('Maternal Health (EmONC)');

        ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Rubric',
            'total_marks' => 1,
            'pass_marks' => 1,
            'pass_percentage' => 100,
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'pending',
        ]);

        $this->assertFalse($participant->hasCompletedAllModules());
    }

    public function test_emonc_participant_with_passed_video_review_is_complete(): void
    {
        ['participant' => $participant, 'classModule' => $classModule, 'programModule' => $programModule]
            = $this->makeParticipantWithOneModule('Maternal Health (EmONC)');

        ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Rubric',
            'total_marks' => 1,
            'pass_marks' => 1,
            'pass_percentage' => 100,
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'passed',
        ]);

        $this->assertTrue($participant->hasCompletedAllModules());
    }

    public function test_non_emonc_participant_with_completed_progress_and_no_rubric_is_complete(): void
    {
        ['participant' => $participant, 'classModule' => $classModule] = $this->makeParticipantWithOneModule('Newborn Care');

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'pending', // never reviewed — no rubric exists to review against
        ]);

        $this->assertTrue($participant->hasCompletedAllModules());
    }

    public function test_non_emonc_participant_with_incomplete_progress_is_not_complete(): void
    {
        ['participant' => $participant, 'classModule' => $classModule] = $this->makeParticipantWithOneModule('Newborn Care');

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
            'video_review_status' => 'pending',
        ]);

        $this->assertFalse($participant->hasCompletedAllModules());
    }
}
