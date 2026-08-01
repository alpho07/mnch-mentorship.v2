<?php

namespace App\Services\Chat;

use Closure;

/**
 * One question in the chat script. Declarative wrapper around a handful of
 * closures — see app/Services/Chat/MentorshipChatScript.php for how these
 * are assembled per stage, and docs/superpowers/specs/2026-08-01-chat-mentorship-setup-design.md
 * for the slot/stage model this implements.
 */
class Slot
{
    public string $id;

    public string $stage;

    protected Closure $questionResolver;

    protected Render $renderKind = Render::FREE_TEXT;

    protected ?Closure $optionsResolver = null;

    protected ?Closure $echoResolver = null;

    protected ?Closure $visibleResolver = null;

    protected bool $requiredFlag = true;

    protected array $dependsOnIds = [];

    protected ?Closure $validator = null;

    protected function __construct(string $id)
    {
        $this->id = $id;
        $this->questionResolver = fn () => $id;
    }

    public static function make(string $id): static
    {
        return new static($id);
    }

    public function stage(string $stage): static
    {
        $this->stage = $stage;

        return $this;
    }

    public function question(Closure $resolver): static
    {
        $this->questionResolver = $resolver;

        return $this;
    }

    public function render(Render $render): static
    {
        $this->renderKind = $render;

        return $this;
    }

    public function optionsFrom(Closure $resolver): static
    {
        $this->optionsResolver = $resolver;

        return $this;
    }

    public function echoUsing(Closure $resolver): static
    {
        $this->echoResolver = $resolver;

        return $this;
    }

    public function visibleWhen(Closure $resolver): static
    {
        $this->visibleResolver = $resolver;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->requiredFlag = $required;

        return $this;
    }

    public function dependsOn(string ...$slotIds): static
    {
        $this->dependsOnIds = $slotIds;

        return $this;
    }

    public function rule(Closure $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function renderKind(): Render
    {
        return $this->renderKind;
    }

    public function dependencies(): array
    {
        return $this->dependsOnIds;
    }

    public function getQuestion(array $answers): string
    {
        return ($this->questionResolver)($answers);
    }

    public function getOptions(array $answers): array
    {
        return $this->optionsResolver ? ($this->optionsResolver)($answers) : [];
    }

    public function getEcho(mixed $value, array $answers): string
    {
        if ($this->echoResolver) {
            return ($this->echoResolver)($value, $answers);
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }

    public function isVisible(array $answers): bool
    {
        return $this->visibleResolver ? (bool) ($this->visibleResolver)($answers) : true;
    }

    public function isRequired(): bool
    {
        return $this->requiredFlag;
    }

    /**
     * Returns an error message, or null if valid.
     */
    public function validate(mixed $value, array $answers): ?string
    {
        if ($this->isRequired() && ($value === null || $value === '' || $value === [])) {
            return 'This is required.';
        }

        return $this->validator ? ($this->validator)($value, $answers) : null;
    }
}
