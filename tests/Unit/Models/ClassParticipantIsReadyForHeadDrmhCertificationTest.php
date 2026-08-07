<?php

namespace Tests\Unit\Models;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassParticipantIsReadyForHeadDrmhCertificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeParticipant(string $programName): ClassParticipant
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

        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'video_review_status' => 'passed',
        ]);

        return $participant;
    }

    public function test_emonc_participant_not_mentor_approved_is_not_ready(): void
    {
        $participant = $this->makeParticipant('Maternal Health (EmONC)');

        $this->assertFalse($participant->isReadyForHeadDrmhCertification());
    }

    public function test_emonc_participant_mentor_approved_is_ready(): void
    {
        $participant = $this->makeParticipant('Maternal Health (EmONC)');
        $participant->update(['mentor_approved_at' => now(), 'mentor_approved_by' => $participant->user_id]);

        $this->assertTrue($participant->isReadyForHeadDrmhCertification());
    }

    public function test_non_emonc_participant_with_all_modules_complete_is_ready_without_mentor_approval(): void
    {
        $participant = $this->makeParticipant('Newborn Care');

        $this->assertTrue($participant->isReadyForHeadDrmhCertification());
    }

    public function test_non_emonc_participant_with_incomplete_modules_is_not_ready(): void
    {
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $mentor = User::factory()->create();
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id, 'mentor_id' => $mentor->id, 'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $mentee->id, 'status' => 'enrolled',
        ]);

        $this->assertFalse($participant->isReadyForHeadDrmhCertification());
    }
}
