<?php

namespace Tests\Unit;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\County;
use App\Models\Facility;
use App\Models\Subcounty;
use App\Models\User;
use App\Services\Chat\Tools\MentorshipSetupToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipSetupToolProviderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_schema_only_lists_currently_eligible_unfilled_slots(): void
    {
        $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $properties = array_keys($tool->schema()['properties']);

        $this->assertContains('is_pilot', $properties);
        $this->assertContains('county_id', $properties);
        // class_name belongs to a later stage, not eligible yet.
        $this->assertNotContains('class_name', $properties);
    }

    public function test_execute_fills_valid_slots_and_reports_rejected_ones(): void
    {
        $user = $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);

        $result = $tool->execute([
            'is_pilot' => 0,
            'max_participants' => 999, // invalid — over the 2-10 cap
        ], $user);

        $this->assertContains('is_pilot', $result['filled']);
        $this->assertContains('max_participants', $result['rejected']);
        $this->assertSame(0, $page->answers['is_pilot']);
        $this->assertArrayNotHasKey('max_participants', $page->answers);
    }

    public function test_a_slot_with_an_unmet_dependency_is_excluded_from_the_schema(): void
    {
        $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $properties = array_keys($tool->schema()['properties']);

        // facility_id depends on county_id, which isn't answered yet — with
        // 10,000+ facilities system-wide, offering it unscoped would either
        // dump an enormous enum into the prompt or (with county_id unmet)
        // resolve to an empty one, which is what caused the model to
        // wrongly tell users a real facility "isn't available".
        $this->assertNotContains('facility_id', $properties);
    }

    public function test_a_slot_is_included_once_its_dependency_is_met(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create();
        $page = new ChatMentorshipSetup;
        $page->mount();

        MentorshipSetupToolProvider::tool($page)->execute([
            'is_pilot' => 0,
            'county_id' => (string) $county->id,
        ], $user);

        $tool = MentorshipSetupToolProvider::tool($page);
        $properties = array_keys($tool->schema()['properties']);

        $this->assertContains('facility_id', $properties);
    }

    public function test_schema_exposes_county_options_as_names_not_raw_database_ids(): void
    {
        $this->actingAsCoordinator();
        // Real county ids in this app are large, non-sequential surrogate
        // keys (e.g. 56427) — nothing a model could ever guess from "the
        // user said Tharaka Nithi".
        $county = County::factory()->create(['id' => 56427, 'name' => 'Tharaka Nithi']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $enum = $tool->schema()['properties']['county_id']['enum'];

        $this->assertContains('Tharaka Nithi', $enum);
        $this->assertNotContains((string) $county->id, $enum);
    }

    public function test_execute_resolves_a_county_name_to_its_id(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create(['id' => 56427, 'name' => 'Tharaka Nithi']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $result = $tool->execute(['county_id' => 'Tharaka Nithi'], $user);

        $this->assertContains('county_id', $result['filled']);
        $this->assertSame($county->id, $page->answers['county_id']);
    }

    public function test_execute_resolves_a_facility_name_to_its_id_once_county_is_set(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create(['name' => 'Tharaka Nithi']);
        $subcounty = Subcounty::create(['name' => 'Chuka', 'county_id' => $county->id]);
        $facility = Facility::factory()->create([
            'subcounty_id' => $subcounty->id,
            'name' => 'Chuka County Referral Hospital',
        ]);
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $tool->execute(['county_id' => (string) $county->id], $user);

        $tool = MentorshipSetupToolProvider::tool($page);
        $result = $tool->execute(['facility_id' => 'Chuka County Referral Hospital'], $user);

        $this->assertContains('facility_id', $result['filled']);
        $this->assertSame($facility->id, $page->answers['facility_id']);
    }

    public function test_execute_rejects_a_value_that_matches_no_option_instead_of_guessing(): void
    {
        $user = $this->actingAsCoordinator();
        County::factory()->create(['name' => 'Tharaka Nithi']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        // A hallucinated county that doesn't exist in the option list —
        // must be rejected outright, never silently mapped to a nearby id.
        $result = $tool->execute(['county_id' => 'Nairobi'], $user);

        $this->assertContains('county_id', $result['rejected']);
        $this->assertArrayNotHasKey('county_id', $page->answers);
    }

    public function test_execute_rejects_an_ambiguous_partial_match_instead_of_guessing(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create(['name' => 'Tharaka Nithi']);
        $subcounty = Subcounty::create(['name' => 'Chuka', 'county_id' => $county->id]);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka County Referral Hospital']);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka Sub-District Hospital']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        MentorshipSetupToolProvider::tool($page)->execute(['county_id' => (string) $county->id], $user);

        // "Chuka" alone matches both facilities' labels — must not guess.
        $result = MentorshipSetupToolProvider::tool($page)->execute(['facility_id' => 'Chuka'], $user);

        $this->assertContains('facility_id', $result['rejected']);
        $this->assertArrayNotHasKey('facility_id', $page->answers);
    }

    public function test_execute_still_accepts_a_raw_id_for_backward_compatibility(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create(['id' => 56427, 'name' => 'Tharaka Nithi']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $result = $tool->execute(['county_id' => (string) $county->id], $user);

        $this->assertContains('county_id', $result['filled']);
        $this->assertSame($county->id, $page->answers['county_id']);
    }

    /**
     * module_ids/selected_users aren't generic Slot objects (see
     * HasMentorshipChatSlots::answer()'s comment on this exact point), so
     * nextUnfilledSlot() skips straight past the modules/enroll_mentees
     * stages to 'recipients' (send_invitations). Without this guard, the
     * schema would offer 'recipients' as fillable the moment first_class
     * completes — and answering it fires sendInvitations() immediately,
     * marking the class complete with no modules and no mentees enrolled.
     */
    public function test_schema_excludes_later_stage_slots_while_in_the_modules_or_mentees_stage(): void
    {
        $user = $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $page = new ChatMentorshipSetup;
        $page->mount();

        $page->answer('is_pilot', 0);
        $page->answer('county_id', $facility->subcounty->county_id);
        $page->answer('facility_id', $facility->id);
        $page->answer('program_id', $program->id);
        $page->answer('start_date', now()->addDay()->toDateString());
        $page->answer('end_date', now()->addMonth()->toDateString());
        $page->answer('max_participants', 8);
        $page->answer('class_name', 'Cohort A');
        $page->answer('class_start_date', now()->addDay()->toDateString());
        $page->answer('class_end_date', now()->addMonth()->toDateString());
        $page->answer('class_description', 'skip');

        $this->assertSame('modules', $page->activeStage());

        $tool = MentorshipSetupToolProvider::tool($page);
        $this->assertSame([], $tool->schema()['properties']);

        // And even if the model called it anyway, execute() must not act —
        // sending invitations this early would complete the class with
        // nothing in it.
        $result = $tool->execute(['recipients' => 'all'], $user);
        $this->assertArrayNotHasKey('recipients', $page->answers);
        $this->assertFalse($page->completed);
    }
}
