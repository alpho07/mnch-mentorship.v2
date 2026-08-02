<?php

namespace App\Services\Chat;

use App\Models\User;

class ChatToolRegistry
{
    /** @var array<string, ChatTool> */
    private array $tools = [];

    public function register(ChatTool $tool): static
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    /**
     * OpenAI-format tool schema list, filtered to what this user is
     * actually authorized to use — an unauthorized tool isn't just hidden
     * from execution, it's never even offered to the model as a capability.
     *
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array}}>
     */
    public function schemasFor(User $user): array
    {
        return collect($this->tools)
            ->filter(fn (ChatTool $tool) => $tool->authorize($user))
            ->map(fn (ChatTool $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->schema(),
                ],
            ])
            ->values()
            ->all();
    }

    public function execute(string $name, array $args, User $user): array
    {
        $tool = $this->tools[$name] ?? null;

        if (! $tool || ! $tool->authorize($user)) {
            throw new \RuntimeException("Tool [{$name}] is not available to this user.");
        }

        return $tool->execute($args, $user);
    }
}
