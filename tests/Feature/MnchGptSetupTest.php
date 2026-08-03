<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup;
use App\Models\County;
use App\Models\Facility;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Setting;
use App\Models\Subcounty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MnchGptSetupTest extends TestCase
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

    private function fakeDeepSeekToolCall(string $toolName, array $arguments, string $finalReply): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => $toolName, 'arguments' => json_encode($arguments)],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => $finalReply]]],
                ]),
        ]);
    }

    public function test_page_is_hidden_when_the_setting_is_off(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, false);

        $this->assertFalse(MnchGptSetup::canAccess());
    }

    public function test_valid_extraction_fills_slots_and_advances_the_flow(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);
        $county = County::factory()->create();

        $this->fakeDeepSeekToolCall('fill_mentorship_setup_slots', [
            'is_pilot' => 0,
            'county_id' => (string) $county->id,
        ], 'Got it — live mentorship in that county. What facility?');

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'This is a real mentorship in that county');

        $this->assertSame(0, $component->get('answers')['is_pilot']);
        // Tool-call arguments arrive as JSON strings (the schema
        // deliberately declares them as strings — see
        // MentorshipSetupToolProvider::propertyFor()); Eloquent's where()
        // handles the string-to-int comparison transparently downstream.
        $this->assertEquals($county->id, $component->get('answers')['county_id']);
    }

    public function test_invalid_extraction_is_dropped_and_falls_back_to_the_card_ui(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $this->fakeDeepSeekToolCall('fill_mentorship_setup_slots', [
            'max_participants' => 999,
        ], 'I tried to set that but it needs to be between 2 and 10.');

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'up to 999 mentees please');

        $this->assertArrayNotHasKey('max_participants', $component->get('answers'));
    }

    public function test_a_query_only_message_does_not_touch_answers(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'There are 3 live mentorships.']]],
            ]),
        ]);

        $component = Livewire::test(MnchGptSetup::class);
        $answersBefore = $component->get('answers');

        $component->call('sendMessage', 'how many live mentorships are there?');

        $this->assertSame($answersBefore, $component->get('answers'));
    }

    public function test_bot_reply_is_rendered_as_markdown_and_dispatches_a_reply_event(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => "Here's what's **missing**:\n\n- County\n- Facility"]]],
            ]),
        ]);

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'what is still needed?');

        $component->assertDispatched('mnchgpt-reply');
        $component->assertSeeHtml('<strong>missing</strong>');
        $component->assertSeeHtml('<li>County</li>');
    }

    public function test_naming_county_and_facility_in_one_message_fills_both_across_tool_rounds(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $county = County::factory()->create(['name' => 'Tharaka Nithi']);
        $subcounty = Subcounty::create(['name' => 'Chuka', 'county_id' => $county->id]);
        $facility = Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka County Referral Hospital']);

        // facility_id's options depend on county_id, so on round 1 (before
        // county_id is answered) the model can only offer county_id —
        // facility_id isn't in that round's schema at all (see
        // MentorshipSetupToolProviderTest::test_a_slot_with_an_unmet_dependency_is_excluded_from_the_schema).
        // The registry gets rebuilt for round 2, where it's now available.
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode([
                                    'is_pilot' => 0,
                                    'county_id' => (string) $county->id,
                                ])],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode([
                                    'facility_id' => (string) $facility->id,
                                ])],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'Got it — Chuka County Referral Hospital in Tharaka Nithi. What program is being mentored?']]],
                ]),
        ]);

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'Tharaka Nithi county, Chuka County Referral Hospital');

        $this->assertEquals($county->id, $component->get('answers')['county_id']);
        $this->assertEquals($facility->id, $component->get('answers')['facility_id']);
    }

    public function test_naming_a_module_at_the_modules_stage_assigns_it_via_the_chat(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $program = Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'name' => 'Neonatal Resuscitation']);

        $component = Livewire::test(MnchGptSetup::class);
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

        $this->assertSame('modules', $component->instance()->activeStage());

        $this->fakeDeepSeekToolCall('fill_mentorship_modules', [
            'module_names' => ['Neonatal Resuscitation'],
        ], 'Added Neonatal Resuscitation. Who should be mentored in this class?');

        $component->call('sendMessage', 'Neonatal Resuscitation please');

        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $component->instance()->class->id,
            'program_module_id' => $module->id,
        ]);
    }
}
