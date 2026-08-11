<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ManageClassModules;
use App\Models\Activity;
use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityCompletionMatrixLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_activities_are_not_toggled_for_a_locked_mentee(): void
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $module->id,
        ]);
        $activity = Activity::create(['name' => 'Drill', 'is_active' => true]);
        $module->activities()->attach($activity->id);

        $lockedMentee = User::factory()->create();
        $lockedParticipant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $lockedMentee->id,
            'status' => 'enrolled',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $lockedParticipant->id,
            'class_module_id' => $classModule->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        // The activity was already completed before the module got locked.
        ClassModuleActivityParticipant::create([
            'class_module_id' => $classModule->id,
            'class_participant_id' => $lockedParticipant->id,
            'activity_id' => $activity->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $openMentee = User::factory()->create();
        $openParticipant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $openMentee->id,
            'status' => 'enrolled',
        ]);

        $mentor = User::factory()->create();
        $this->actingAs($mentor);

        $page = new ManageClassModules();
        $page->mount($training, $class);

        // Attempt to UN-complete the locked mentee's activity and complete
        // the open mentee's — the locked one must stay exactly as it was.
        $page->saveActivityCompletions($classModule->id, [
            ['participantId' => $lockedParticipant->id, 'activityIds' => []],
            ['participantId' => $openParticipant->id, 'activityIds' => [$activity->id]],
        ]);

        $this->assertDatabaseHas('class_module_activity_participants', [
            'class_module_id' => $classModule->id,
            'class_participant_id' => $lockedParticipant->id,
            'activity_id' => $activity->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('class_module_activity_participants', [
            'class_module_id' => $classModule->id,
            'class_participant_id' => $openParticipant->id,
            'activity_id' => $activity->id,
            'status' => 'completed',
        ]);
    }
}
