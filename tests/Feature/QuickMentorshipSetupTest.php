<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ListMentorshipTrainings;
use App\Filament\Resources\MentorshipResource\Pages\QuickMentorshipSetup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuickMentorshipSetupTest extends TestCase
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

    public function test_list_page_shows_quick_setup_button(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(ListMentorshipTrainings::class)
            ->assertSeeHtml('Quick Setup');
    }

    public function test_quick_setup_page_loads(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(QuickMentorshipSetup::class)
            ->assertSuccessful();
    }

    public function test_page_is_blocked_when_the_setting_is_off_for_a_fresh_visit(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::QUICK_SETUP_BUTTON_ENABLED, false);

        $this->assertFalse(QuickMentorshipSetup::canAccess());
    }

    public function test_page_stays_accessible_with_a_training_query_param_even_when_off(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::QUICK_SETUP_BUTTON_ENABLED, false);
        request()->merge(['training' => 1]);

        $this->assertTrue(QuickMentorshipSetup::canAccess());
    }

    public function test_basics_continue_action_creates_training_and_reveals_first_class_section(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care']);
        $facility = \App\Models\Facility::factory()->create(['name' => 'Test Facility']);

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->fillForm([
            'is_pilot' => 0,
            'county_id' => $facility->subcounty->county_id,
            'facility_id' => $facility->id,
            'program_id' => $program->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_participants' => 10,
        ]);
        $component->call('saveBasics');

        $this->assertTrue($component->instance()->basicsSaved);
        $this->assertDatabaseHas('trainings', [
            'program_id' => $program->id,
            'facility_id' => $facility->id,
        ]);
        $this->assertSame('quick', \App\Models\Training::where('program_id', $program->id)->first()->guided_setup_method);
    }

    public function test_basics_continue_action_fails_validation_without_required_fields(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->call('saveBasics');

        $component->assertHasFormErrors(['program_id']);
        $this->assertFalse($component->instance()->basicsSaved);
    }

    public function test_first_class_continue_action_creates_class_and_reveals_modules_section(): void
    {
        // Chained through the real flow (fillForm/call, not direct ->instance()
        // property pokes) — a manually-set property doesn't survive a
        // subsequent fillForm()/call() cycle, since Livewire's testing harness
        // rehydrates from a real snapshot between requests, same caveat
        // GuidedMentorshipSetupTest documents for its own moduleDates tests.
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care']);
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->fillForm([
            'is_pilot' => 0,
            'county_id' => $facility->subcounty->county_id,
            'facility_id' => $facility->id,
            'program_id' => $program->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_participants' => 10,
        ]);
        $component->call('saveBasics');
        $this->assertTrue($component->instance()->basicsSaved);

        $component->fillForm([
            'class_name' => 'January 2027 Cohort',
            'class_start_date' => now()->addDay()->toDateString(),
            'class_end_date' => now()->addMonth()->toDateString(),
        ]);
        $component->call('saveFirstClass');

        $this->assertTrue($component->instance()->firstClassSaved);
        $training = \App\Models\Training::where('program_id', $program->id)->first();
        $this->assertDatabaseHas('mentorship_classes', [
            'training_id' => $training->id,
            'name' => 'January 2027 Cohort',
        ]);
    }
}
