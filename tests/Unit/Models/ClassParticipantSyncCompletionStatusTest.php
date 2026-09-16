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

class ClassParticipantSyncCompletionStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeParticipantWithOneModule(): array
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
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
        // Every real EmONC module has an active rubric — without one here,
        // hasCompletedAllModules() correctly treats this module as having
        // nothing to video-review, which would make these fixtures
        // unrepresentative of the real EmONC scenario they're testing.
        ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Rubric',
            'total_marks' => 1,
            'pass_marks' => 1,
            'pass_percentage' => 100,
            'order_sequence' => 1,
            'is_active' => true,
        ]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);

        return compact('participant', 'classModule');
    }

    public function test_it_is_a_no_op_when_not_all_modules_are_complete(): void
    {
        ['participant' => $participant] = $this->makeParticipantWithOneModule();

        $this->assertFalse($participant->syncCompletionStatus());
        $this->assertSame('enrolled', $participant->fresh()->status);
    }

    public function test_it_is_a_no_op_when_status_is_already_completed(): void
    {
        ['participant' => $participant] = $this->makeParticipantWithOneModule();
        $participant->update(['status' => 'completed']);

        $this->assertFalse($participant->syncCompletionStatus());
    }

    public function test_it_marks_completed_when_all_modules_are_done_and_video_passed(): void
    {
        ['participant' => $participant, 'classModule' => $classModule] = $this->makeParticipantWithOneModule();

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'passed',
        ]);

        $this->assertTrue($participant->syncCompletionStatus());
        $fresh = $participant->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_it_stays_false_when_modules_done_but_video_still_pending(): void
    {
        ['participant' => $participant, 'classModule' => $classModule] = $this->makeParticipantWithOneModule();

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'pending',
        ]);

        $this->assertFalse($participant->syncCompletionStatus());
        $this->assertSame('enrolled', $participant->fresh()->status);
    }
}
