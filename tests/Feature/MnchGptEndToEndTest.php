<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup;
use App\Models\ClassParticipant;
use App\Models\County;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MnchGptEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_setup_turn_and_a_query_turn_both_work_in_the_same_session(): void
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);
        $county = County::factory()->create();
        $program = Program::factory()->create();
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'is_pilot' => false]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => User::factory()->create()->id]);

        // A single sequence covering both turns: Http::fake() replaces
        // stubbed responses per URL pattern but stacks stub callbacks, so a
        // second fake() call for the same pattern would leave the first
        // (now-exhausted) sequence registered ahead of the new one and it
        // throws before the new sequence is ever reached.
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
                                'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode(['is_pilot' => 0, 'county_id' => (string) $county->id])],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'Got it.']]],
                ])
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => ['name' => 'get_mentorship_counts', 'arguments' => '{}'],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'There is 1 live mentorship with 1 mentee.']]],
                ]),
        ]);

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'live mentorship in that county');

        $this->assertSame(0, $component->get('answers')['is_pilot']);

        // Turn 2: an analytics query — must not disturb the setup answers.
        $answersBefore = $component->get('answers');
        $component->call('sendMessage', 'how many live mentorships are there?');

        $this->assertSame($answersBefore, $component->get('answers'));
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertSame('There is 1 live mentorship with 1 mentee.', $lastMessage['text']);
    }
}
