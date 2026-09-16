<?php

namespace App\Services\Chat;

use App\Models\User;
use Closure;

/**
 * Generic closure-based ChatTool implementation — lets each tool-provider
 * class stay a single file with several small tools defined inline, rather
 * than one class per tool.
 */
class SimpleChatTool implements ChatTool
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly array $schema,
        private readonly Closure $authorize,
        private readonly Closure $execute,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function schema(): array
    {
        return $this->schema;
    }

    public function authorize(User $user): bool
    {
        return (bool) ($this->authorize)($user);
    }

    public function execute(array $args, User $user): array
    {
        return ($this->execute)($args, $user);
    }
}
