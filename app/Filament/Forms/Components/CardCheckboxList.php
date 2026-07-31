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

    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getOptionsList(): array
    {
        return $this->evaluate($this->options) ?? [];
    }
}
