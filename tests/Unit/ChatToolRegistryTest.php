<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Chat\ChatToolRegistry;
use App\Services\Chat\SimpleChatTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_schemas_for_only_includes_authorized_tools(): void
    {
        $allowedUser = User::factory()->create();
        $deniedUser = User::factory()->create();

        $registry = new ChatToolRegistry;
        $registry->register(new SimpleChatTool(
            name: 'do_thing',
            description: 'Does a thing.',
            schema: ['type' => 'object', 'properties' => []],
            authorize: fn (User $u) => $u->is($allowedUser),
            execute: fn (array $args, User $u) => ['ok' => true],
        ));

        $allowedSchemas = $registry->schemasFor($allowedUser);
        $deniedSchemas = $registry->schemasFor($deniedUser);

        $this->assertCount(1, $allowedSchemas);
        $this->assertSame('do_thing', $allowedSchemas[0]['function']['name']);
        $this->assertCount(0, $deniedSchemas);
    }

    public function test_execute_runs_the_tools_execute_closure(): void
    {
        $user = User::factory()->create();
        $registry = new ChatToolRegistry;
        $registry->register(new SimpleChatTool(
            name: 'add',
            description: 'Adds two numbers.',
            schema: ['type' => 'object', 'properties' => ['a' => ['type' => 'number'], 'b' => ['type' => 'number']]],
            authorize: fn (User $u) => true,
            execute: fn (array $args, User $u) => ['sum' => $args['a'] + $args['b']],
        ));

        $result = $registry->execute('add', ['a' => 2, 'b' => 3], $user);

        $this->assertSame(['sum' => 5], $result);
    }

    public function test_execute_throws_for_an_unauthorized_tool(): void
    {
        $deniedUser = User::factory()->create();
        $registry = new ChatToolRegistry;
        $registry->register(new SimpleChatTool(
            name: 'secret',
            description: 'Secret tool.',
            schema: ['type' => 'object', 'properties' => []],
            authorize: fn (User $u) => false,
            execute: fn (array $args, User $u) => ['leaked' => true],
        ));

        $this->expectException(\RuntimeException::class);

        $registry->execute('secret', [], $deniedUser);
    }
}
