<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Component;

class ConditionalExplanation
{
    public static function make(int $questionId, string $label = 'Please explain'): Textarea
    {
        return Textarea::make("explanations.{$questionId}")
            ->label($label)
            ->rows(3)
            ->placeholder('Provide additional details or explanation here')
            ->helperText('Required when response needs clarification')
            ->visible(fn (callable $get) => $get("explanations.{$questionId}_visible") === true)
            ->required(fn (callable $get) => $get("explanations.{$questionId}_visible") === true)
            ->extraAttributes(['class' => 'explanation-field'])
            ->dehydrated(false);
    }
}