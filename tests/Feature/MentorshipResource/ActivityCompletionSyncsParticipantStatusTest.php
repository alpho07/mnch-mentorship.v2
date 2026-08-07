<?php

namespace Tests\Feature\MentorshipResource;

use App\Models\Activity;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleActivity;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityCompletionSyncsParticipantStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_the_last_activity_for_a_mentees_last_module_marks_the_participant_completed(): void
    {
        $mentor = User::factory()->create(['name' => 'Mentor']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $mentor->givePermissionTo('view_any_mentorship::training');
        $this->actingAs($mentor);

        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
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
        $activity = Activity::firstOrCreate(['name' => 'CME']);
        ProgramModuleActivity::create(['program_module_id' => $programModule->id, 'activity_id' => $activity->id]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'not_started',
            'video_review_status' => 'passed', // only remaining gap is the activity below
        ]);

        $component = Livewire::test(\App\Filament\Resources\MentorshipResource\Pages\ManageClassModules::class, [
            'training' => $training,
            'class' => $class,
        ]);

        $component->assertOk();

        $component->call('saveActivityCompletions', $classModule->id, [
            ['participantId' => $participant->id, 'activityIds' => [$activity->id]],
        ]);

        $this->assertSame('completed', $participant->fresh()->status);
    }
}
