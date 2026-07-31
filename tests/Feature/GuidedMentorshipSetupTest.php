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

    public function test_assign_modules_creates_class_modules_for_standard_program(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $created = $component->instance()->assignModules([
            'module_ids' => [$programModule->id],
            'auto_create_sessions' => false,
        ]);

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'not_started',
        ]);
    }

    public function test_assign_modules_is_skippable(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $created = $component->instance()->assignModules(['module_ids' => [], 'auto_create_sessions' => false]);

        $this->assertSame(0, $created);
    }

    public function test_enroll_mentees_enrolls_existing_selected_users(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $count = $component->instance()->enrollMentees([
            'selected_users' => [$mentee->id],
            'new_mentee' => null,
        ]);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_enroll_mentees_creates_and_enrolls_new_mentee(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $count = $component->instance()->enrollMentees([
            'selected_users' => [],
            'new_mentee' => [
                'email' => 'jane.wanjiku@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Wanjiku',
            ],
        ]);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('users', ['email' => 'jane.wanjiku@example.com', 'role' => 'mentee']);
        $newUser = User::where('email', 'jane.wanjiku@example.com')->first();
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $class->id,
            'user_id' => $newUser->id,
        ]);
    }

    public function test_enroll_mentees_is_skippable(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $count = $component->instance()->enrollMentees(['selected_users' => [], 'new_mentee' => null]);

        $this->assertSame(0, $count);
    }

    public function test_send_invitations_emails_all_enrolled_mentees_with_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create(['email' => 'mentee@example.com']);
        $participant = \App\Models\ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $result = $component->instance()->sendInvitations(['recipients' => 'all']);

        $this->assertSame(1, $result['sent']);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MenteeEnrollmentInvitationMail::class, 1);
        $this->assertTrue($component->instance()->completed);
        $participant->refresh();
        $this->assertNotNull($participant->invitation_sent_at);
    }

    public function test_send_invitations_completes_with_zero_mentees(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $result = $component->instance()->sendInvitations(['recipients' => 'all']);

        $this->assertSame(0, $result['sent']);
        $this->assertTrue($component->instance()->completed);
    }

    public function test_create_training_updates_existing_training_instead_of_duplicating(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $first = $component->instance()->createTraining([
            'is_pilot' => 0,
            'program_id' => $program->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_participants' => 10,
        ]);

        $second = $component->instance()->createTraining([
            'is_pilot' => 0,
            'program_id' => $program->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_participants' => 25,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\Training::where('program_id', $program->id)->count());
        $this->assertSame(25, $second->fresh()->max_participants);
    }

    public function test_create_first_class_updates_existing_class_instead_of_duplicating(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;

        $first = $component->instance()->createFirstClass(['name' => 'Original Name']);
        $second = $component->instance()->createFirstClass(['name' => 'Renamed Cohort']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\MentorshipClass::where('training_id', $training->id)->count());
        $this->assertSame('Renamed Cohort', $second->fresh()->name);
    }

    public function test_enroll_mentees_does_not_duplicate_already_enrolled_user(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $firstCount = $component->instance()->enrollMentees(['selected_users' => [$mentee->id], 'new_mentee' => null]);
        $secondCount = $component->instance()->enrollMentees(['selected_users' => [$mentee->id], 'new_mentee' => null]);

        $this->assertSame(1, $firstCount);
        $this->assertSame(0, $secondCount);
        $this->assertSame(1, \App\Models\ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $mentee->id)
            ->count());
    }
}
