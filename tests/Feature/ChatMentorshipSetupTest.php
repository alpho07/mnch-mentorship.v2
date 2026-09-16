<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChatMentorshipSetupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Ada Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_page_loads_with_a_greeting_and_the_first_question(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(ChatMentorshipSetup::class)
            ->assertSuccessful()
            ->assertSee('Welcome, Ada!')
            ->assertSee('Is this a real live mentorship or a pilot/test run?');
    }

    public function test_answering_is_pilot_appends_an_echo_and_asks_for_county(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);

        $messages = $component->instance()->messages;

        $this->assertSame('bot', $messages[0]['role']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertSame('Live Mentorship', $messages[1]['text']);
        $this->assertSame('Which county?', $messages[2]['text']);
    }

    public function test_answering_county_asks_for_facility_scoped_to_that_county(): void
    {
        $this->actingAsCoordinator();
        $facility = \App\Models\Facility::factory()->create(['name' => 'Kiambu Level 4']);
        $countyId = $facility->subcounty->county_id;

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $countyId);

        $slot = collect(\App\Services\Chat\MentorshipChatScript::build($component->instance()))
            ->first(fn ($s) => $s->id === 'facility_id');

        $this->assertArrayHasKey($facility->id, $slot->getOptions($component->instance()->answers));
    }

    public function test_completing_the_training_details_stage_creates_the_training(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $facility->subcounty->county_id);
        $component->call('answer', 'facility_id', $facility->id);
        $component->call('answer', 'program_id', $program->id);
        $component->call('answer', 'start_date', now()->addDay()->toDateString());
        $component->call('answer', 'end_date', now()->addMonth()->toDateString());
        $component->call('answer', 'max_participants', 8);

        $this->assertDatabaseHas('trainings', [
            'program_id' => $program->id,
            'facility_id' => $facility->id,
            'max_participants' => 8,
            'guided_setup_method' => 'chat',
        ]);
        $this->assertNotNull($component->instance()->training);
    }

    public function test_emonc_program_skips_the_date_slots(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Maternal Health (EmONC)', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $facility->subcounty->county_id);
        $component->call('answer', 'facility_id', $facility->id);
        $component->call('answer', 'program_id', $program->id);
        $component->call('answer', 'max_participants', 8);

        $this->assertNotNull($component->instance()->training);
        $this->assertNull($component->instance()->training->start_date);
    }

    public function test_completing_the_first_class_stage_creates_the_class(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $facility->subcounty->county_id);
        $component->call('answer', 'facility_id', $facility->id);
        $component->call('answer', 'program_id', $program->id);
        $component->call('answer', 'start_date', now()->addDay()->toDateString());
        $component->call('answer', 'end_date', now()->addMonth()->toDateString());
        $component->call('answer', 'max_participants', 8);
        $component->call('answer', 'class_name', 'January 2027 Cohort');
        $component->call('answer', 'class_start_date', now()->addDay()->toDateString());
        $component->call('answer', 'class_end_date', now()->addMonth()->toDateString());
        $component->call('answer', 'class_description', 'Gap identified: newborn resuscitation.');

        $this->assertDatabaseHas('mentorship_classes', [
            'name' => 'January 2027 Cohort',
            'training_id' => $component->instance()->training->id,
        ]);
        $this->assertNotNull($component->instance()->class);
    }

    private function advanceThroughFirstClass(\Livewire\Features\SupportTesting\Testable $component, \App\Models\Program $program, \App\Models\Facility $facility): void
    {
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $facility->subcounty->county_id);
        $component->call('answer', 'facility_id', $facility->id);
        $component->call('answer', 'program_id', $program->id);
        $component->call('answer', 'start_date', now()->addDay()->toDateString());
        $component->call('answer', 'end_date', now()->addMonth()->toDateString());
        $component->call('answer', 'max_participants', 8);
        $component->call('answer', 'class_name', 'Cohort A');
        $component->call('answer', 'class_start_date', now()->addDay()->toDateString());
        $component->call('answer', 'class_end_date', now()->addMonth()->toDateString());
        $component->call('answer', 'class_description', 'skip');
    }

    public function test_modules_stage_assigns_modules_for_a_standard_program(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();
        $module = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $component = Livewire::test(ChatMentorshipSetup::class);
        $this->advanceThroughFirstClass($component, $program, $facility);

        $component->call('submitModules', [$module->id]);

        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $component->instance()->class->id,
            'program_module_id' => $module->id,
        ]);
    }

    public function test_modules_stage_is_skippable(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $this->advanceThroughFirstClass($component, $program, $facility);

        $component->call('submitModules', []);

        $this->assertSame(0, $component->instance()->class->classModules()->count());
        $this->assertArrayHasKey('module_ids', $component->instance()->answers);
    }

    public function test_enroll_mentees_stage_enrolls_selected_users(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();
        $mentee = User::factory()->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $this->advanceThroughFirstClass($component, $program, $facility);
        $component->call('submitModules', []);

        $component->call('submitMentees', [$mentee->id], null);

        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $component->instance()->class->id,
            'user_id' => $mentee->id,
        ]);
    }

    public function test_send_invitations_stage_completes_the_flow(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();
        $mentee = User::factory()->create(['email' => 'mentee@example.com']);

        $component = Livewire::test(ChatMentorshipSetup::class);
        $this->advanceThroughFirstClass($component, $program, $facility);
        $component->call('submitModules', []);
        $component->call('submitMentees', [$mentee->id], null);
        $component->call('answer', 'recipients', 'all');

        $this->assertTrue($component->instance()->completed);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MenteeEnrollmentInvitationMail::class, 1);
        $this->assertNotNull($component->instance()->training->fresh()->guided_setup_completed_at);
    }

    public function test_resuming_replays_the_full_transcript_and_lands_on_the_next_question(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();

        $first = Livewire::test(ChatMentorshipSetup::class);
        $first->call('answer', 'is_pilot', 0);
        $first->call('answer', 'county_id', $facility->subcounty->county_id);
        $first->call('answer', 'facility_id', $facility->id);
        $first->call('answer', 'program_id', $program->id);
        $first->call('answer', 'start_date', now()->addDay()->toDateString());
        $first->call('answer', 'end_date', now()->addMonth()->toDateString());
        $first->call('answer', 'max_participants', 8);

        $trainingId = $first->instance()->training->id;

        $resumed = Livewire::withQueryParams(['training' => $trainingId])->test(ChatMentorshipSetup::class);

        $this->assertGreaterThanOrEqual(count($first->instance()->messages), count($resumed->instance()->messages));
        $this->assertSame('Live Mentorship', collect($resumed->instance()->messages)->firstWhere('slot', 'is_pilot')['text']);
        $this->assertSame($program->id, $resumed->instance()->answers['program_id']);
    }

    public function test_editing_a_past_answer_reopens_it_without_discarding_later_answers(): void
    {
        $this->actingAsCoordinator();
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $facility->subcounty->county_id);
        $component->call('answer', 'facility_id', $facility->id);

        $component->call('editSlot', 'facility_id');

        $this->assertArrayNotHasKey('facility_id', $component->instance()->answers);
        $this->assertSame(0, $component->instance()->answers['is_pilot']);
        $this->assertSame($facility->subcounty->county_id, $component->instance()->answers['county_id']);
    }

    public function test_list_page_shows_chat_setup_button(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(\App\Filament\Resources\MentorshipResource\Pages\ListMentorshipTrainings::class)
            ->assertSeeHtml('Chat Setup');
    }

    public function test_chat_setup_button_disabled_when_setting_off(): void
    {
        $this->actingAsCoordinator();
        \App\Models\Setting::setBool(\App\Models\Setting::CHAT_SETUP_BUTTON_ENABLED, false);

        $response = $this->get(\App\Filament\Resources\MentorshipTrainingResource::getUrl('chat-setup'));

        $response->assertForbidden();
    }

    public function test_pending_setup_banner_routes_chat_drafts_to_chat_setup(): void
    {
        $mentor = $this->actingAsCoordinator();
        \App\Models\Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'guided_setup_completed_at' => null,
            'guided_setup_method' => 'chat',
        ]);

        $widget = new \App\Filament\Widgets\PendingGuidedSetupNotice;
        $reflection = new \ReflectionMethod($widget, 'getViewData');
        $reflection->setAccessible(true);
        $viewData = $reflection->invoke($widget);

        $this->assertStringContainsString('chat-setup', $viewData['continueUrl']);
    }

    public function test_completing_first_class_does_not_jump_ahead_to_send_invitations(): void
    {
        // Regression: module_ids/selected_users aren't generic Slot objects
        // (Modules/Enroll Mentees are bespoke turns), so the moment
        // createFirstClass() fires, a naive "what's the next generic slot"
        // lookup skips straight past both bespoke stages to `recipients` —
        // announcing "Who should receive the email?" before the mentorship
        // has any modules or mentees, while the actually-rendered turn
        // underneath is still the modules picker. Caught live in a browser,
        // not by earlier tests, because they only asserted DB side effects,
        // never the transcript's ordering at this exact boundary.
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $this->advanceThroughFirstClass($component, $program, $facility);

        $texts = collect($component->instance()->messages)->pluck('text');

        $this->assertFalse(
            $texts->contains('Who should receive the email?'),
            'send_invitations was announced before Modules/Enroll Mentees ran.'
        );
        $this->assertSame('modules', $component->instance()->activeStage());
    }

    public function test_the_full_conversation_reaches_send_invitations_in_the_right_order(): void
    {
        $this->actingAsCoordinator();
        \Illuminate\Support\Facades\Mail::fake();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();
        $mentee = User::factory()->create(['email' => 'mentee@example.com']);

        $component = Livewire::test(ChatMentorshipSetup::class);
        $this->advanceThroughFirstClass($component, $program, $facility);
        $component->call('submitModules', []);
        $component->call('submitMentees', [$mentee->id], null);

        $texts = collect($component->instance()->messages)->pluck('text');
        $this->assertTrue($texts->contains(fn ($t) => str_contains($t, 'Who should receive the email')));

        $component->call('answer', 'recipients', 'all');

        $this->assertTrue($component->instance()->completed);
    }

    public function test_emonc_modules_stage_shows_tracks_and_requires_dates(): void
    {
        // Regression: getModuleFieldOptions() used to flatten
        // EmoncModulePicker::getModules() via pluck('name', 'id'), which
        // collapses a parent-with-tracks module down to just the parent —
        // every track (e.g. PPH's 11) silently disappeared from the chat
        // picker. The chat modules turn must show the same parent/track
        // tree the wizard does, via getEmoncModuleTree(), and still enforce
        // a start/end date per selected module/track before continuing.
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Maternal Health (EmONC)', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();
        $parentModule = \App\Models\ProgramModule::factory()->create([
            'program_id' => $program->id,
            'is_active' => true,
            'name' => 'Postpartum Haemorrhage (PPH)',
        ]);
        $track1 = \App\Models\ProgramModule::factory()->create([
            'program_id' => $program->id,
            'is_active' => true,
            'parent_id' => $parentModule->id,
            'name' => 'Track 1: Uterotonics',
        ]);
        $track2 = \App\Models\ProgramModule::factory()->create([
            'program_id' => $program->id,
            'is_active' => true,
            'parent_id' => $parentModule->id,
            'name' => 'Track 2: Bimanual Compression',
        ]);

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $facility->subcounty->county_id);
        $component->call('answer', 'facility_id', $facility->id);
        $component->call('answer', 'program_id', $program->id);
        $component->call('answer', 'max_participants', 8);
        $component->call('answer', 'class_name', 'Cohort A');
        $component->call('answer', 'class_description', 'skip');

        $this->assertTrue($component->instance()->isModulesStageEmonc());

        $tree = $component->instance()->getEmoncModuleTree();
        $this->assertCount(1, $tree, 'Parent+tracks should collapse to one tree entry, not disappear.');
        $trackIds = $tree->first()->availableChildren->pluck('id');
        $this->assertTrue($trackIds->contains($track1->id));
        $this->assertTrue($trackIds->contains($track2->id));

        // Selecting a track without dates is blocked.
        $component->call('submitModules', [$track1->id]);
        $component->assertHasErrors('value');
        $this->assertDatabaseMissing('class_modules', [
            'mentorship_class_id' => $component->instance()->class->id,
            'program_module_id' => $track1->id,
        ]);

        // With dates set, only the selected track is assigned — not its
        // untouched sibling.
        $component->set('moduleDates', [
            $track1->id => ['start' => now()->addDay()->toDateString(), 'end' => now()->addMonth()->toDateString()],
        ]);
        $component->call('submitModules', [$track1->id]);

        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $component->instance()->class->id,
            'program_module_id' => $track1->id,
        ]);
        $this->assertDatabaseMissing('class_modules', [
            'mentorship_class_id' => $component->instance()->class->id,
            'program_module_id' => $track2->id,
        ]);
    }

    public function test_check_and_submit_mentees_blocks_more_than_max_participants(): void
    {
        // advanceThroughFirstClass() sets max_participants to 8.
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();
        $mentees = User::factory()->count(9)->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $this->advanceThroughFirstClass($component, $program, $facility);
        $component->call('submitModules', []);

        $component->call('checkAndSubmitMentees', $mentees->pluck('id')->all(), null);

        $component->assertHasErrors('value');
        $this->assertSame(0, $component->instance()->class->participants()->count());
    }

    public function test_check_and_submit_mentees_pauses_for_a_mentee_without_an_email(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();
        $withEmail = User::factory()->create(['email' => 'has-email@example.com']);
        $withoutEmail = User::factory()->create(['email' => null]);

        $component = Livewire::test(ChatMentorshipSetup::class);
        $this->advanceThroughFirstClass($component, $program, $facility);
        $component->call('submitModules', []);

        $component->call('checkAndSubmitMentees', [$withEmail->id, $withoutEmail->id], null);

        // Paused — nobody enrolled yet, and the modal's data is populated
        // with exactly the mentee missing an email.
        $this->assertSame(0, $component->instance()->class->participants()->count());
        $this->assertCount(1, $component->instance()->menteesNeedingEmail);
        $this->assertSame($withoutEmail->id, $component->instance()->menteesNeedingEmail[0]['id']);

        $component->set("pendingEmails.{$withoutEmail->id}", 'now-has-email@example.com');
        $component->call('saveMenteeEmailsAndContinue');

        $this->assertSame('now-has-email@example.com', $withoutEmail->fresh()->email);
        $this->assertEmpty($component->instance()->menteesNeedingEmail);
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $component->instance()->class->id,
            'user_id' => $withEmail->id,
        ]);
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $component->instance()->class->id,
            'user_id' => $withoutEmail->id,
        ]);
    }

    public function test_cancel_mentee_email_prompt_discards_the_pending_selection(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();
        $withoutEmail = User::factory()->create(['email' => null]);

        $component = Livewire::test(ChatMentorshipSetup::class);
        $this->advanceThroughFirstClass($component, $program, $facility);
        $component->call('submitModules', []);
        $component->call('checkAndSubmitMentees', [$withoutEmail->id], null);

        $this->assertNotEmpty($component->instance()->menteesNeedingEmail);

        $component->call('cancelMenteeEmailPrompt');

        $this->assertEmpty($component->instance()->menteesNeedingEmail);
        $this->assertSame(0, $component->instance()->class->participants()->count());
        // The modules/mentees turn is still active — nothing was submitted.
        $this->assertArrayNotHasKey('selected_users', $component->instance()->answers);
    }
}
