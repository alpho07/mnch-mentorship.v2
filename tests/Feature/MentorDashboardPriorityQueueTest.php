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

class MentorDashboardPriorityQueueTest extends TestCase
{
    use RefreshDatabase;

    private function grantDashboardAccess(User $user): void
    {
        Permission::firstOrCreate(['name' => 'page_MentorDashboard', 'guard_name' => 'web']);
        $user->givePermissionTo('page_MentorDashboard');
    }

    public function test_dashboard_exposes_resolver_output_as_priority_queue(): void
    {
        $mentor = User::factory()->create();
        $this->grantDashboardAccess($mentor);

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'status' => 'active',
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
            'status' => 'active',
        ]);
        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);
        // Model::create() overwrites an explicitly-passed updated_at at save time via Eloquent's
        // auto-timestamping — a follow-up query-builder update() (bypassing that) is required to
        // actually backdate it.
        MenteeModuleProgress::where('id', $progress->id)->update(['updated_at' => now()->subDays(20)]);
        $participant->update(['enrolled_at' => now()->subDays(30)]);

        $this->actingAs($mentor);

        Livewire::test(MentorDashboard::class)
            ->assertSet('priorityQueue.0.tier', 3);
    }

    public function test_dashboard_with_no_mentorships_has_empty_priority_queue(): void
    {
        $mentor = User::factory()->create();
        $this->grantDashboardAccess($mentor);
        $this->actingAs($mentor);

        Livewire::test(MentorDashboard::class)
            ->assertSet('priorityQueue', []);
    }
}
