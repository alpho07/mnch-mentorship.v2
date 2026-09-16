<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Chat\ChatToolRegistry;
use App\Services\Chat\LlmMentorshipAssistantService;
use App\Services\Chat\SimpleChatTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmMentorshipAssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_message_with_no_tool_call_returns_the_models_reply_directly(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Sure, how can I help?']],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $service = new LlmMentorshipAssistantService;

        $result = $service->respond('hello', [], fn () => new ChatToolRegistry, $user);

        $this->assertSame('Sure, how can I help?', $result['reply']);
        $this->assertSame([], $result['tool_calls']);
    }

    public function test_a_tool_call_is_executed_and_results_are_sent_back_for_a_final_reply(): void
    {
        $registry = new ChatToolRegistry;
        $registry->register(new SimpleChatTool(
            name: 'get_number',
            description: 'Returns a number.',
            schema: ['type' => 'object', 'properties' => []],
            authorize: fn (User $u) => true,
            execute: fn (array $args, User $u) => ['value' => 42],
        ));

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
                                'function' => ['name' => 'get_number', 'arguments' => '{}'],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['role' => 'assistant', 'content' => 'The number is 42.']],
                    ],
                ]),
        ]);

        $user = User::factory()->create();
        $service = new LlmMentorshipAssistantService;

        $result = $service->respond('what is the number?', [], fn () => $registry, $user);

        $this->assertSame('The number is 42.', $result['reply']);
        $this->assertCount(1, $result['tool_calls']);
        $this->assertSame('get_number', $result['tool_calls'][0]['name']);
        $this->assertSame(['value' => 42], $result['tool_calls'][0]['result']);
    }

    public function test_a_network_failure_degrades_gracefully(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'server error'], 500),
        ]);

        $user = User::factory()->create();
        $service = new LlmMentorshipAssistantService;

        $result = $service->respond('hello', [], fn () => new ChatToolRegistry, $user);

        $this->assertSame(
            "Sorry, I couldn't process that — try again or use the buttons below.",
            $result['reply']
        );
        $this->assertSame([], $result['tool_calls']);
    }

    public function test_a_tool_call_that_unlocks_a_new_tool_gets_a_second_round_in_the_same_message(): void
    {
        // Mirrors the real facility_id-depends-on-county_id shape: the
        // second tool only becomes available once the registry is rebuilt
        // after the first tool has actually run.
        $state = ['county_set' => false];

        $registryFactory = function () use (&$state) {
            $registry = new ChatToolRegistry;
            $registry->register(new SimpleChatTool(
                name: 'set_county',
                description: 'Sets the county.',
                schema: ['type' => 'object', 'properties' => []],
                authorize: fn (User $u) => true,
                execute: function () use (&$state) {
                    $state['county_set'] = true;

                    return ['ok' => true];
                },
            ));

            if ($state['county_set']) {
                $registry->register(new SimpleChatTool(
                    name: 'set_facility',
                    description: 'Sets the facility.',
                    schema: ['type' => 'object', 'properties' => []],
                    authorize: fn (User $u) => true,
                    execute: fn (array $args, User $u) => ['ok' => true],
                ));
            }

            return $registry;
        };

        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [
                    ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'set_county', 'arguments' => '{}']],
                ]]]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [
                    ['id' => 'call_2', 'type' => 'function', 'function' => ['name' => 'set_facility', 'arguments' => '{}']],
                ]]]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Both set.']]]]),
        ]);

        $user = User::factory()->create();
        $service = new LlmMentorshipAssistantService;

        $result = $service->respond('county and facility please', [], $registryFactory, $user);

        $this->assertSame('Both set.', $result['reply']);
        $this->assertCount(2, $result['tool_calls']);
        $this->assertSame('set_county', $result['tool_calls'][0]['name']);
        $this->assertSame('set_facility', $result['tool_calls'][1]['name']);
    }
}
