<?php

namespace App\Filament\Resources\ProgramModuleQuizResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('order_sequence')
                ->label('Order')
                ->numeric()
                ->default(0)
                ->required(),

            Forms\Components\RichEditor::make('question_text')
                ->label('Question')
                ->required()
                ->columnSpanFull(),

            Forms\Components\RichEditor::make('explanation')
                ->label('Correct Answer Explanation')
                ->helperText('Shown to the mentee after they submit their answer.')
                ->columnSpanFull(),

            Forms\Components\Repeater::make('options')
                ->label('Answer Options')
                ->relationship('options')
                ->schema([
                    Forms\Components\TextInput::make('option_text')
                        ->label('Option Text')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_correct')
                        ->label('This is the correct answer')
                        ->default(false)
                        ->live()
                        ->required(),

                    Forms\Components\TextInput::make('order_sequence')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->required(),
                ])
                ->minItems(2)
                ->reorderableWithButtons()
                ->collapsible()
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                Tables\Columns\TextColumn::make('order_sequence')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('question_text')
                    ->label('Question')
                    ->html()
                    ->limit(80)
                    ->wrap(),

                Tables\Columns\TextColumn::make('options_count')
                    ->label('Options')
                    ->counts('options')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('has_correct_option')
                    ->label('Has Correct Answer')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->options()->where('is_correct', true)->exists()),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('order_sequence', 'asc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }
}
