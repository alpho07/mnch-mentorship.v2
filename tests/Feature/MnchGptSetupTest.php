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

    public function test_a_reply_gets_a_proactively_rendered_option_list_appended(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        // No tool call at all this turn — the model just chats — but
        // is_pilot is still the next slot and has only 2 options, so the
        // backend appends its list regardless of what the model said.
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Sure — is this a real mentorship or a pilot run?']]],
            ]),
        ]);

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'I want to set up a mentorship');

        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('Live Mentorship', $lastMessage['text']);
        $this->assertStringContainsString('Pilot Run', $lastMessage['text']);
        $this->assertSame('is_pilot', $component->get('pendingOptions')['slot']);
    }

    public function test_an_ambiguous_facility_name_appends_a_candidate_shortlist(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $county = County::factory()->create(['name' => 'Tharaka Nithi']);
        $subcounty = Subcounty::create(['name' => 'Chuka', 'county_id' => $county->id]);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka County Referral Hospital']);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka Sub-District Hospital']);

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $county->id);

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
                                'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode(['facility_id' => 'Chuka'])],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'A couple of facilities match "Chuka" — which one did you mean?']]],
                ]),
        ]);

        $component->call('sendMessage', 'Chuka hospital');

        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('Chuka County Referral Hospital', $lastMessage['text']);
        $this->assertStringContainsString('Chuka Sub-District Hospital', $lastMessage['text']);
        $this->assertSame('facility_id', $component->get('pendingOptions')['slot']);
        $this->assertArrayNotHasKey('facility_id', $component->get('answers'));
    }

    public function test_a_bare_number_reply_resolves_instantly_without_calling_the_llm(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $component = Livewire::test(MnchGptSetup::class);
        $messagesBefore = count($component->get('messages'));
        $component->set('pendingOptions', [
            'slot' => 'is_pilot',
            'options' => [
                1 => ['id' => 0, 'label' => 'Live Mentorship'],
                2 => ['id' => 1, 'label' => 'Pilot Run'],
            ],
        ]);

        Http::fake(); // no request should be made at all

        $component->call('sendMessage', '1');

        Http::assertNothingSent();
        $this->assertSame(0, $component->get('answers')['is_pilot']);
        // Exactly one user message (the echoed choice, via answer()'s own
        // getEcho()) and one bot message (the next question) got added —
        // guards against double-posting from both answer()'s own
        // message-appending and this fast path's.
        $this->assertCount($messagesBefore + 2, $component->get('messages'));
    }

    public function test_a_letter_reply_maps_to_the_matching_position(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $component = Livewire::test(MnchGptSetup::class);
        $component->set('pendingOptions', [
            'slot' => 'is_pilot',
            'options' => [
                1 => ['id' => 0, 'label' => 'Live Mentorship'],
                2 => ['id' => 1, 'label' => 'Pilot Run'],
            ],
        ]);

        Http::fake();

        $component->call('sendMessage', 'b');

        Http::assertNothingSent();
        $this->assertSame(1, $component->get('answers')['is_pilot']);
    }

    public function test_an_out_of_range_number_falls_through_to_the_normal_llm_flow(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $component = Livewire::test(MnchGptSetup::class);
        $component->set('pendingOptions', [
            'slot' => 'is_pilot',
            'options' => [
                1 => ['id' => 0, 'label' => 'Live Mentorship'],
                2 => ['id' => 1, 'label' => 'Pilot Run'],
            ],
        ]);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'I only have options 1 and 2 — which did you mean?']]],
            ]),
        ]);

        $component->call('sendMessage', '99');

        Http::assertSentCount(1);
    }

    public function test_pending_options_are_cleared_once_a_different_step_is_computed(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $component = Livewire::test(MnchGptSetup::class);
        $component->set('pendingOptions', [
            'slot' => 'county_id',
            'options' => [1 => ['id' => 999, 'label' => 'Stale County']],
        ]);

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
                                'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode(['is_pilot' => 0])],
                            ]],
                        ],
                    ]],
                ])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Got it.']]]]),
        ]);

        $component->call('sendMessage', 'live mentorship');

        // The stale list is gone — a bare "1" now falls through to the LLM
        // rather than resolving against the old (irrelevant) county list.
        $this->assertNotSame('county_id', $component->get('pendingOptions')['slot'] ?? null);
    }
}
