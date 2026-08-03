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

    /**
     * PHP can't distinguish an empty associative array from an empty
     * indexed one, so json_encode(['properties' => []]) always produces the
     * JSON array `[]` — invalid per JSON Schema, which requires an object.
     * Confirmed live: this made DeepSeek reject the *entire* chat
     * completion request with a 400, since every registered tool's schema
     * ships together in one request — hitting this constantly (it's
     * exactly what schemaFor() returns whenever nothing is left to offer,
     * e.g. throughout the modules/enroll_mentees stages) broke every other
     * tool call alongside it, surfacing as "Sorry, I couldn't process
     * that." json_encode()'ing the real schema output is the only way to
     * actually prove this stays fixed — asserting on the PHP shape alone
     * previously would have passed even with the bug present.
     */
    public function test_schema_serializes_to_valid_json_schema_even_when_theres_nothing_to_offer(): void
    {
        $this->actingAsCoordinator();
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
        $json = json_encode($tool->schema());

        $this->assertStringContainsString('"properties":{}', $json);
        $this->assertStringNotContainsString('"properties":[]', $json);
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

    /**
     * A single free-text message can resolve several slots from the same
     * stage in one execute() call — before this fix, each individually
     * called $page->answer(), and answer()'s own trailing "next question"
     * message reflected whatever nextUnfilledSlot() returned *at that
     * point*, which changes after every slot filled. That produced one
     * stale "next question" bot message per slot resolved (observed live
     * as e.g. "Which facility?" repeated several times, each followed by
     * an unrelated answer to something else entirely). Only the initial
     * mount() greeting should remain — MnchGptSetup's own final reply is
     * the single source of "what's next" for a batched turn.
     */
    public function test_execute_does_not_append_a_stale_next_question_per_slot_in_a_batch(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create();
        $page = new ChatMentorshipSetup;
        $page->mount();
        $botMessagesBefore = collect($page->messages)->where('role', 'bot')->count();

        $tool = MentorshipSetupToolProvider::tool($page);
        // None of these complete the training_details stage (facility_id/
        // program_id are still missing), so the only question mark is
        // whether the per-slot loop leaked its own intermediate messages.
        $tool->execute([
            'is_pilot' => 0,
            'county_id' => (string) $county->id,
            'max_participants' => 8,
        ], $user);

        $botMessagesAfter = collect($page->messages)->where('role', 'bot')->count();

        $this->assertSame($botMessagesBefore, $botMessagesAfter);
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
        $this->assertArrayNotHasKey('county_id', $result['candidates']);
        $this->assertArrayNotHasKey('county_id', $page->answers);
    }

    public function test_execute_returns_a_candidate_shortlist_for_an_ambiguous_partial_match(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create(['name' => 'Tharaka Nithi']);
        $subcounty = Subcounty::create(['name' => 'Chuka', 'county_id' => $county->id]);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka County Referral Hospital']);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka Sub-District Hospital']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        MentorshipSetupToolProvider::tool($page)->execute(['county_id' => (string) $county->id], $user);

        // "Chuka" alone matches both facilities' labels — instead of a dead
        // end, this now surfaces both as a shortlist for the user to pick
        // from (see FuzzyOptionMatcher).
        $result = MentorshipSetupToolProvider::tool($page)->execute(['facility_id' => 'Chuka'], $user);

        $this->assertArrayNotHasKey('facility_id', $page->answers);
        $this->assertArrayHasKey('facility_id', $result['candidates']);
        // Labels carry an MFL-code prefix ("12345 — Name"), so check
        // containment rather than an exact match against the bare name.
        $labelsText = implode(' | ', array_column($result['candidates']['facility_id'], 'label'));
        $this->assertStringContainsString('Chuka County Referral Hospital', $labelsText);
        $this->assertStringContainsString('Chuka Sub-District Hospital', $labelsText);
    }

    public function test_execute_returns_a_shortlist_for_a_near_miss_typo(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create(['id' => 56427, 'name' => 'Tharaka Nithi']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        // "Tharaja Nithi" (one letter swapped, "k" -> "j") isn't a prefix or
        // substring of the real name in either direction, so it can't
        // resolve via the exact/substring tiers — only fuzzy matching gets
        // this, exactly the plausible-typo case those tiers can't cover.
        $result = MentorshipSetupToolProvider::tool($page)->execute(['county_id' => 'Tharaja Nithi'], $user);

        $this->assertArrayNotHasKey('county_id', $page->answers);
        $this->assertArrayHasKey('county_id', $result['candidates']);
        $this->assertSame($county->id, $result['candidates']['county_id'][0]['id']);
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
     * schemaFor() only ever offers slots from the *current* stage (see
     * test_schema_only_lists_currently_eligible_unfilled_slots above), but
     * nothing stops the model from calling the tool with an argument name
     * outside that schema anyway — DeepSeek's tool schema is guidance, not
     * an enforced contract. Before this fix, execute() would happily accept
     * it anyway (it only checked "does this slot exist at all" and "is it
     * already answered"), letting a first_class-stage slot like class_name
     * get filled while facility_id — an earlier, still-required
     * training_details slot — was never resolved. That leaves $this
     * ->training permanently null (training_details never completes) while
     * the checklist shows first_class items as done, and the user gets
     * stuck being re-asked for the facility forever.
     */
    public function test_execute_rejects_a_slot_from_a_later_stage_than_the_current_one(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create();
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $tool->execute(['is_pilot' => 0, 'county_id' => (string) $county->id], $user);

        // facility_id (training_details) is still unresolved — class_name
        // belongs to the later first_class stage and must not be accepted
        // yet, even though the slot itself exists and isn't already
        // answered.
        $tool = MentorshipSetupToolProvider::tool($page);
        $result = $tool->execute(['class_name' => 'Cohort A'], $user);

        $this->assertContains('class_name', $result['rejected']);
        $this->assertArrayNotHasKey('class_name', $page->answers);
    }

    /**
     * A single free-text message naming both the remaining training_details
     * fields AND the first_class fields in one breath is exactly the kind
     * of dense paragraph MNCHGPT is meant to parse in one round — the model
     * puts everything into one fill_mentorship_setup_slots call, and
     * training_details genuinely does complete by the end of processing
     * that call. But json_decode(...)'s key order follows however the
     * model happened to write the JSON, not slot declaration order — if a
     * later-stage key like class_name lands *before* the training_details
     * keys in that same object, the per-iteration stage check (comparing
     * against nextUnfilledSlot() at that exact moment) would wrongly reject
     * it, since training_details hadn't completed yet *when that key was
     * evaluated* — even though it does complete moments later in the same
     * loop. Live symptom: a message giving the class name/dates alongside
     * the rest got them silently dropped, $this->class was never created,
     * and a later "add these modules" request had no legitimate stage to
     * act from at all — yet the model still claimed success on both.
     */
    public function test_execute_resolves_a_later_stage_slot_regardless_of_its_position_in_the_args(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create();
        $subcounty = Subcounty::create(['name' => 'Chuka', 'county_id' => $county->id]);
        $facility = Facility::factory()->create(['subcounty_id' => $subcounty->id]);
        $program = \App\Models\Program::factory()->create(['is_active' => true]);
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        // class_name deliberately listed first — before any of the
        // training_details keys that must resolve first to unlock it.
        $result = $tool->execute([
            'class_name' => 'August Cohort 2026',
            'is_pilot' => 0,
            'county_id' => (string) $county->id,
            'facility_id' => (string) $facility->id,
            'program_id' => (string) $program->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_participants' => 8,
        ], $user);

        $this->assertContains('class_name', $result['filled']);
        $this->assertSame('August Cohort 2026', $page->answers['class_name']);
        $this->assertNotNull($page->training);
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
        // Cast to (object), never a plain empty array — json_encode(['properties'
        // => []]) produces the invalid JSON Schema `"properties": []`
        // instead of `{}`, which was confirmed to make DeepSeek reject the
        // whole request with a 400 (see schemaFor()'s comment on this).
        $this->assertEquals((object) [], $tool->schema()['properties']);

        // And even if the model called it anyway, execute() must not act —
        // sending invitations this early would complete the class with
        // nothing in it.
        $result = $tool->execute(['recipients' => 'all'], $user);
        $this->assertArrayNotHasKey('recipients', $page->answers);
        $this->assertFalse($page->completed);
    }
}
