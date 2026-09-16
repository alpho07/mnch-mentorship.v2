<?php

namespace Tests\Feature\Console;

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

class SyncMentorshipCompletionStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeReadyButStuckParticipant(): ClassParticipant
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $mentor = User::factory()->create();
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id, 'mentor_id' => $mentor->id, 'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id, 'program_module_id' => $programModule->id, 'status' => 'in_progress',
        ]);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $mentee->id, 'status' => 'enrolled',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id, 'class_module_id' => $classModule->id,
            'status' => 'completed', 'video_review_status' => 'passed',
        ]);

        return $participant;
    }

    public function test_dry_run_reports_but_does_not_change_data(): void
    {
        $participant = $this->makeReadyButStuckParticipant();

        $this->artisan('mentorship:sync-completion-status')
            ->expectsOutputToContain((string) $participant->id)
            ->assertExitCode(0);

        $this->assertSame('enrolled', $participant->fresh()->status);
    }

    public function test_apply_flag_actually_syncs_ready_participants(): void
    {
        $participant = $this->makeReadyButStuckParticipant();

        $this->artisan('mentorship:sync-completion-status --apply')
            ->assertExitCode(0);

        $this->assertSame('completed', $participant->fresh()->status);
    }

    public function test_participants_not_yet_ready_are_left_untouched(): void
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
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

        $this->artisan('mentorship:sync-completion-status --apply')->assertExitCode(0);

        $this->assertSame('enrolled', $participant->fresh()->status);
    }
}
