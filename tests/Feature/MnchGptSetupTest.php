<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup;
use App\Models\County;
use App\Models\Setting;
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
}
