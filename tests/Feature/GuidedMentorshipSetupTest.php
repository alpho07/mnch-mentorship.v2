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

    public function test_validate_module_dates_fails_when_a_selected_module_has_no_dates(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->moduleDates = [];

        $error = $component->instance()->validateModuleDates([56]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('Set a start and end date', $error);
    }

    public function test_validate_module_dates_fails_when_end_is_before_start(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->moduleDates = [
            56 => ['start' => '2027-03-10', 'end' => '2027-03-01'],
        ];

        $error = $component->instance()->validateModuleDates([56]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('on or after', $error);
    }

    public function test_validate_module_dates_passes_when_every_selected_module_has_a_valid_range(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->moduleDates = [
            56 => ['start' => '2027-03-01', 'end' => '2027-03-01'], // same-day allowed
            57 => ['start' => '2027-04-01', 'end' => '2027-04-10'],
        ];

        $error = $component->instance()->validateModuleDates([56, 57]);

        $this->assertNull($error);
    }

    public function test_assign_modules_applies_per_module_dates_to_newly_created_modules_only(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $existingModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        \App\Models\ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $existingModule->id,
            'start_date' => null,
            'end_date' => null,
        ]);
        $newModuleA = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $newModuleB = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $component->instance()->assignModules([
            'module_ids' => [$existingModule->id, $newModuleA->id, $newModuleB->id],
            'auto_create_sessions' => false,
            // Per-row dates, keyed by program_module_id — each newly-picked
            // module/track can carry its own range from the wizard's modal.
            'module_dates' => [
                $newModuleA->id => ['start' => '2027-02-01', 'end' => '2027-02-14'],
                $newModuleB->id => ['start' => '2027-03-01', 'end' => '2027-03-10'],
            ],
        ]);

        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $newModuleA->id,
            'start_date' => '2027-02-01 00:00:00',
            'end_date' => '2027-02-14 00:00:00',
        ]);
        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $newModuleB->id,
            'start_date' => '2027-03-01 00:00:00',
            'end_date' => '2027-03-10 00:00:00',
        ]);
        // Already-assigned module wasn't touched — dates only apply to
        // what's newly added in this pass, not re-stamped on old ones.
        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $existingModule->id,
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    public function test_assign_modules_clears_module_ids_from_the_draft_and_locked_options_reflect_it(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'guided_setup_draft' => ['module_ids' => [], 'selected_users' => [1]],
        ]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;
        $component->instance()->assignModules([
            'module_ids' => [$programModule->id],
            'auto_create_sessions' => false,
        ]);

        // The draft's module_ids key is gone — the pick is now a real
        // ClassModule row, not a pending draft — but the unrelated
        // selected_users key must survive untouched.
        $this->assertSame(
            ['selected_users' => [1]],
            $training->fresh()->guided_setup_draft
        );

        // A fresh "Continue" session must show the module as "Already
        // added" (locked), not have it silently vanish from the picker.
        $available = app(\App\Services\ModuleUsageService::class)->getAvailableModules($training, $class);
        $this->assertFalse($available->pluck('id')->contains($programModule->id));
        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
    }

    public function test_mount_falls_back_to_real_assignments_when_no_draft_exists(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $mentee = User::factory()->create();

        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'guided_setup_draft' => null,
        ]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        \App\Models\ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
        \App\Models\ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->trainingId = $training->id;
        $component->instance()->classId = $class->id;
        $component->instance()->mount();

        // Never visited this step before (no draft key) — defaults to
        // what's really assigned/enrolled, pre-checked.
        $component->assertFormSet([
            'module_ids' => [$programModule->id],
            'selected_users' => [$mentee->id],
        ]);
    }

    public function test_mount_treats_an_explicit_draft_as_authoritative_over_real_assignments(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            // The user already unchecked this module once and it's
            // pending removal on the next Next click — the draft explicitly
            // omitting it must NOT be overridden by what's still (for now)
            // really assigned.
            'guided_setup_draft' => ['module_ids' => []],
        ]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        \App\Models\ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->trainingId = $training->id;
        $component->instance()->classId = $class->id;
        $component->instance()->mount();

        $component->assertFormSet(['module_ids' => []]);
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

    public function test_assign_modules_removes_an_unchecked_module(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        \App\Models\ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'not_started',
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        // Empty desired set = the user unchecked the only assigned module.
        $component->instance()->assignModules(['module_ids' => [], 'auto_create_sessions' => false]);

        $this->assertDatabaseMissing('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
    }

    public function test_assign_modules_refuses_to_remove_a_module_with_mentee_progress(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $classModule = \App\Models\ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'not_started',
        ]);
        $participant = \App\Models\ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => User::factory(),
        ]);
        \App\Models\MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'not_started',
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $component->instance()->assignModules(['module_ids' => [], 'auto_create_sessions' => false]);

        $this->assertDatabaseHas('class_modules', ['id' => $classModule->id]);
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
        $this->assertNotNull($training->fresh()->guided_setup_completed_at);
        // No modules assigned (Modules step was skipped) — canStart() is
        // false, so the class correctly stays in draft rather than being
        // force-started.
        $this->assertFalse($component->instance()->classStarted);
        $this->assertSame('draft', $class->fresh()->status);
    }

    public function test_send_invitations_starts_the_class_when_it_has_modules_and_mentees(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        \App\Models\ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'not_started',
        ]);
        $mentee = User::factory()->create(['email' => 'mentee2@example.com']);
        \App\Models\ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $component->instance()->sendInvitations(['recipients' => 'all']);

        $this->assertTrue($component->instance()->classStarted);
        $this->assertSame('active', $class->fresh()->status);
    }

    public function test_pending_guided_setup_scope_excludes_completed_and_other_users_trainings(): void
    {
        $mentor = $this->actingAsCoordinator();
        $otherMentor = User::factory()->create();

        $pending = \App\Models\Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'guided_setup_completed_at' => null,
        ]);
        \App\Models\Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'guided_setup_completed_at' => now(),
        ]);
        \App\Models\Training::factory()->facilityMentorship()->create([
            'mentor_id' => $otherMentor->id,
            'guided_setup_completed_at' => null,
        ]);

        $result = \App\Models\Training::pendingGuidedSetup()->where('mentor_id', $mentor->id)->get();

        $this->assertCount(1, $result);
        $this->assertSame($pending->id, $result->first()->id);
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

    public function test_mount_resumes_run_type_and_location_from_url_before_training_exists(): void
    {
        $this->actingAsCoordinator();
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->urlIsPilot = 1;
        $component->instance()->urlCountyId = $facility->subcounty->county_id;
        $component->instance()->urlFacilityId = $facility->id;
        $component->instance()->mount();

        $component->assertFormSet([
            'is_pilot' => 1,
            'county_id' => $facility->subcounty->county_id,
            'facility_id' => $facility->id,
        ]);
    }

    public function test_mount_prefers_training_record_over_url_mirrors_once_training_exists(): void
    {
        $this->actingAsCoordinator();
        $otherFacility = \App\Models\Facility::factory()->create();
        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'is_pilot' => 0,
            'facility_id' => $otherFacility->id,
            'county_id' => $otherFacility->subcounty->county_id,
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        // Stale URL mirrors from an earlier, since-abandoned run type/location
        // pick — the persisted Training record must win once it exists.
        $component->instance()->urlIsPilot = 1;
        $component->instance()->urlCountyId = 999999;
        $component->instance()->urlFacilityId = 999999;
        $component->instance()->trainingId = $training->id;
        $component->instance()->mount();

        $component->assertFormSet([
            'is_pilot' => 0,
            'county_id' => $otherFacility->subcounty->county_id,
            'facility_id' => $otherFacility->id,
        ]);
    }

    public function test_mount_restores_module_and_mentee_picks_from_training_draft(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'guided_setup_draft' => [
                'module_ids' => [39, 41],
                'selected_users' => [100],
            ],
        ]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        // Simulates the pending-setup banner's Continue link: a brand new
        // session that only ever knew the training/class IDs, never the
        // original browser tab's URL — the draft on the Training record is
        // the only place these picks could come from.
        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->trainingId = $training->id;
        $component->instance()->classId = $class->id;
        $component->instance()->mount();

        $component->assertFormSet([
            'module_ids' => [39, 41],
            'selected_users' => [100],
        ]);
    }

    public function test_save_wizard_draft_merges_into_existing_training_draft(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'guided_setup_draft' => ['module_ids' => [39]],
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;

        $reflection = new \ReflectionMethod($component->instance(), 'saveWizardDraft');
        $reflection->setAccessible(true);
        $reflection->invoke($component->instance(), 'selected_users', [100, 200]);

        $this->assertSame(
            ['module_ids' => [39], 'selected_users' => [100, 200]],
            $training->fresh()->guided_setup_draft
        );
    }

    public function test_save_wizard_draft_preserves_associative_keys_for_module_dates(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;

        $reflection = new \ReflectionMethod($component->instance(), 'saveWizardDraft');
        $reflection->setAccessible(true);
        // moduleDates is an id => {start,end} map — array_is_list() must
        // route it away from the array_values() re-indexing used for the
        // flat module_ids/selected_users id lists, or the keys (module
        // ids) would be silently discarded.
        $reflection->invoke($component->instance(), 'moduleDates', [
            56 => ['start' => '2027-03-01', 'end' => '2027-03-10'],
        ]);

        $this->assertSame(
            ['moduleDates' => [56 => ['start' => '2027-03-01', 'end' => '2027-03-10']]],
            $training->fresh()->guided_setup_draft
        );
    }

    public function test_updated_module_dates_hook_persists_to_the_draft(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->moduleDates = [56 => ['start' => '2027-03-01', 'end' => '2027-03-10']];
        $component->instance()->updatedModuleDates();

        $this->assertSame(
            [56 => ['start' => '2027-03-01', 'end' => '2027-03-10']],
            $training->fresh()->guided_setup_draft['moduleDates']
        );
    }

    public function test_mount_restores_module_dates_from_the_training_draft(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'guided_setup_draft' => [
                'moduleDates' => [56 => ['start' => '2027-03-01', 'end' => '2027-03-10']],
            ],
        ]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->trainingId = $training->id;
        $component->instance()->classId = $class->id;
        $component->instance()->mount();

        $this->assertSame(
            [56 => ['start' => '2027-03-01', 'end' => '2027-03-10']],
            $component->instance()->moduleDates
        );
    }

    public function test_assign_modules_clears_the_module_dates_draft_after_applying(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'guided_setup_draft' => [
                'moduleDates' => [999 => ['start' => '2027-01-01', 'end' => '2027-01-05']],
            ],
        ]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;
        $component->instance()->moduleDates = [
            $programModule->id => ['start' => '2027-02-01', 'end' => '2027-02-05'],
        ];

        $component->instance()->assignModules([
            'module_ids' => [$programModule->id],
            'auto_create_sessions' => false,
            'module_dates' => $component->instance()->moduleDates,
        ]);

        $this->assertArrayNotHasKey('moduleDates', $training->fresh()->guided_setup_draft ?? []);
        $this->assertSame([], $component->instance()->moduleDates);
    }

    public function test_send_invitations_clears_the_draft(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'guided_setup_draft' => ['module_ids' => [39]],
        ]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $component->instance()->sendInvitations(['recipients' => 'all']);

        $this->assertNull($training->fresh()->guided_setup_draft);
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

    public function test_enroll_mentees_removes_an_unchecked_mentee(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create();
        \App\Models\ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        // Empty desired set = the user unchecked the only enrolled mentee.
        $component->instance()->enrollMentees(['selected_users' => [], 'new_mentee' => null]);

        $this->assertDatabaseMissing('class_participants', [
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);
    }
}
