<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Flat, bordered-card checkbox list — same card treatment as
 * EmoncModulePicker's module cards (rounded border, header-row style),
 * for any plain id => label option set (program modules, mentees, etc.).
 */
class CardCheckboxList extends Field
{
    protected string $view = 'filament.forms.components.card-checkbox-list';

    protected array|Closure $options = [];

    protected int|Closure|null $maxSelections = null;

    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Caps how many items can be checked. Once reached, unchecked cards
     * become non-interactive client-side (already-checked ones can still
     * be unchecked) and the server rejects an over-the-cap submission.
     */
    public function maxSelections(int|Closure|null $max): static
    {
        $this->maxSelections = $max;

        $this->rule(
            fn () => function (string $attribute, $value, Closure $fail) {
                $max = $this->getMaxSelections();

                if ($max !== null && count($value ?? []) > $max) {
                    $fail("You can select at most {$max}.");
                }
            },
            fn () => $this->getMaxSelections() !== null,
        );

        return $this;
    }

    public function getOptionsList(): array
    {
        return $this->evaluate($this->options) ?? [];
    }

    public function getMaxSelections(): ?int
    {
        return $this->evaluate($this->maxSelections);
    }
}
