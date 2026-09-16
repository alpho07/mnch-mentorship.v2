<?php

namespace App\Filament\Resources\ProgramModuleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QuizzesRelationManager extends RelationManager
{
    protected static string $relationship = 'quizzes';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Quiz Title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\Select::make('type')
                ->label('Quiz Type')
                ->options([
                    'pre_test' => 'Pre-test only',
                    'post_test' => 'Post-test only',
                    'both' => 'Both pre-test and post-test',
                ])
                ->required()
                ->native(false),

            Forms\Components\TextInput::make('pass_mark_percentage')
                ->label('Pass Mark (%)')
                ->numeric()
                ->default(85)
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->required(),

            Forms\Components\TextInput::make('order_sequence')
                ->label('Order Sequence')
                ->numeric()
                ->default(0)
                ->required(),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('order_sequence')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pre_test' => 'Pre-test',
                        'post_test' => 'Post-test',
                        'both' => 'Pre & Post',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pre_test' => 'info',
                        'post_test' => 'warning',
                        'both' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('pass_mark_percentage')
                    ->label('Pass Mark')
                    ->suffix('%')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('questions_count')
                    ->label('Questions')
                    ->counts('questions')
                    ->badge()
                    ->color('info'),

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
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['program_module_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    }),
            ]);
    }
}
