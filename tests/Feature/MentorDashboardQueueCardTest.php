<?php

namespace Tests\Feature;

use App\Filament\Pages\MentorDashboard;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorDashboardQueueCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_card_shows_inactive_mentee_item(): void
    {
        $mentor = User::factory()->create();
        Permission::firstOrCreate(['name' => 'page_MentorDashboard', 'guard_name' => 'web']);
        $mentor->givePermissionTo('page_MentorDashboard');

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $mentee = User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Mwende']);
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
            'enrolled_at' => now()->subDays(30),
        ]);
        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);
        MenteeModuleProgress::where('id', $progress->id)->update(['updated_at' => now()->subDays(20)]);

        $this->actingAs($mentor);

        Livewire::test(MentorDashboard::class)
            ->assertSee('Follow Up')
            ->assertSee('inactive 20 days');
    }
}
