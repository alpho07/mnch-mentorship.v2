<?php

namespace App\Filament\Resources\ProgramResource\RelationManagers;

use App\Models\Activity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProgramModulesRelationManager extends RelationManager
{
    protected static string $relationship = 'programModules';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('parent_id')
                ->label('Parent Module')
                ->relationship(
                    'parent',
                    'name',
                    fn ($query) => $query
                        ->where('program_id', $this->getOwnerRecord()->id)
                        ->whereNull('parent_id')
                        ->orderBy('name')
                )
                ->placeholder('Top-level module')
                ->searchable()
                ->preload()
                ->helperText('Leave empty for a module. Select a module to make this a track.'),

            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\Textarea::make('description')
                ->rows(4)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('order_sequence')
                ->label('Order Sequence')
                ->numeric()
                ->default(0)
                ->required(),

            Forms\Components\DatePicker::make('start_date')
                ->label('Start Date')
                ->native(false),

            Forms\Components\DatePicker::make('end_date')
                ->label('End Date')
                ->native(false)
                ->afterOrEqual('start_date'),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->required(),

            Forms\Components\CheckboxList::make('activities')
                ->label('Activities')
                ->relationship('activities', 'name')
                ->options(Activity::where('is_active', true)->pluck('name', 'id'))
                ->columns(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Module')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_track')
                    ->label('Track')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->isTrack()),

                Tables\Columns\TextColumn::make('order_sequence')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('activities.name')
                    ->label('Activities')
                    ->badge()
                    ->color('info')
                    ->separator(',')
                    ->wrap(),
            ])
            ->defaultSort('order_sequence', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('parent_id')
                    ->label('Type')
                    ->placeholder('All')
                    ->trueLabel('Tracks only')
                    ->falseLabel('Modules only')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('parent_id'),
                        false: fn ($query) => $query->whereNull('parent_id'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['program_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
