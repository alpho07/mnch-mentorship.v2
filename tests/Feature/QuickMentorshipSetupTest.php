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

    public function test_modules_continue_action_assigns_modules(): void
    {
        // Isolated unit test for the wrapper method itself — same pattern
        // GuidedMentorshipSetupTest uses for assignModules(): call it
        // directly on the live instance rather than through fillForm()+
        // call(), since a manually-poked property (training/class here)
        // doesn't survive the hydrate cycle a real call() triggers.
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $created = $component->instance()->assignModules([
            'module_ids' => [$programModule->id],
            'auto_create_sessions' => false,
        ]);
        $component->instance()->modulesSaved = true;

        $this->assertSame(1, $created);
        $this->assertTrue($component->instance()->modulesSaved);
        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
    }

    public function test_validate_module_dates_delegates_to_the_shared_service(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->moduleDates = [];

        $error = $component->instance()->validateModuleDates([56]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('Set a start and end date', $error);
    }

    public function test_updated_module_dates_hook_persists_to_the_draft(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->moduleDates = [56 => ['start' => '2027-03-01', 'end' => '2027-03-10']];
        $component->instance()->updatedModuleDates();

        $this->assertSame(
            [56 => ['start' => '2027-03-01', 'end' => '2027-03-10']],
            $training->fresh()->guided_setup_draft['moduleDates']
        );
    }

    public function test_mentees_continue_action_enrolls_selected_users(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $count = $component->instance()->enrollMentees([
            'selected_users' => [$mentee->id],
            'new_mentee' => null,
        ]);
        $component->instance()->menteesSaved = true;

        $this->assertSame(1, $count);
        $this->assertTrue($component->instance()->menteesSaved);
        $this->assertSame(1, $component->instance()->enrolledCount);
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);
    }

    public function test_submit_sends_invitations_and_marks_completed(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create(['email' => 'mentee@example.com']);
        \App\Models\ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $result = $component->instance()->sendInvitations(['recipients' => 'all']);

        $this->assertTrue($component->instance()->completed);
        $this->assertSame(1, $component->instance()->invitedCount);
        $this->assertSame(1, $result['sent']);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MenteeEnrollmentInvitationMail::class, 1);
        $this->assertNotNull($training->fresh()->guided_setup_completed_at);
    }
}
