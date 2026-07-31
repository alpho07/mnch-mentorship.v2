<?php

namespace Tests\Feature;

use App\Filament\Pages\MenteeDashboard;
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

class MenteeDashboardNextActionTest extends TestCase
{
    use RefreshDatabase;

    private function grantDashboardAccess(User $user): void
    {
        Permission::firstOrCreate(['name' => 'page_MenteeDashboard', 'guard_name' => 'web']);
        $user->givePermissionTo('page_MenteeDashboard');
    }

    public function test_dashboard_exposes_resolver_output_as_next_action(): void
    {
        $mentee = User::factory()->create();
        $this->grantDashboardAccess($mentee);
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($mentee);

        Livewire::test(MenteeDashboard::class)
            ->assertSet('nextAction.tier', 3)
            ->assertSet('nextAction.label', 'Continue Learning');
    }

    public function test_dashboard_with_no_enrollments_has_empty_next_action(): void
    {
        $mentee = User::factory()->create();
        $this->grantDashboardAccess($mentee);
        $this->actingAs($mentee);

        Livewire::test(MenteeDashboard::class)
            ->assertSet('nextAction', []);
    }
}
