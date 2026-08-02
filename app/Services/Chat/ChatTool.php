<?php

namespace App\Services\Chat;

use App\Models\User;

/**
 * One capability the LLM can invoke — either a mentorship-setup slot filler
 * or a read-only analytics query. See
 * docs/superpowers/specs/2026-08-03-mnchgpt-llm-assistant-design.md.
 */
interface ChatTool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON schema for the tool's parameters object (the "parameters" value
     * in an OpenAI-format tool definition).
     */
    public function schema(): array;

    public function authorize(User $user): bool;

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed> data for the model to summarize, or (for
     *                              setup tools) a result the caller inspects for validation outcomes
     */
    public function execute(array $args, User $user): array;
}
