<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\GuidedMentorshipSetup;
use App\Filament\Resources\MentorshipResource\Pages\ListMentorshipTrainings;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedMentorshipSetupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_list_page_shows_guided_setup_button(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(ListMentorshipTrainings::class)
            ->assertSeeHtml('Guided Setup');
    }

    public function test_guided_setup_page_loads(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(GuidedMentorshipSetup::class)
            ->assertSuccessful();
    }

    public function test_create_training_persists_correct_attributes(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $facility = \App\Models\Facility::factory()->create(['name' => 'Test Facility']);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $training = $component->instance()->createTraining([
            'is_pilot' => 0,
            'county_id' => $facility->subcounty->county_id,
            'facility_id' => $facility->id,
            'program_id' => $program->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_participants' => 15,
        ]);

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'type' => 'facility_mentorship',
            'program_id' => $program->id,
            'facility_id' => $facility->id,
            'is_pilot' => 0,
            'max_participants' => 15,
        ]);
        $this->assertStringStartsWith('MT-', $training->identifier);
        $this->assertStringContainsString('Newborn Care', $training->title);
        $this->assertStringContainsString('Test Facility', $training->title);
    }

    public function test_create_first_class_persists_and_links_to_training(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;

        $class = $component->instance()->createFirstClass([
            'name' => 'January 2027 Cohort',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'description' => 'Gap identified: newborn resuscitation.',
        ]);

        $this->assertDatabaseHas('mentorship_classes', [
            'id' => $class->id,
            'training_id' => $training->id,
            'name' => 'January 2027 Cohort',
            'status' => 'draft',
        ]);
    }
}
