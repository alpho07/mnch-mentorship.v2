<?php

namespace App\Filament\Resources\SurveyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Events';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->helperText('Unique within this survey'),
                Forms\Components\TextInput::make('order')->numeric()->default(0)->required(),
                Forms\Components\Toggle::make('repeatable')
                    ->default(false)
                    ->helperText('On: this event can occur any number of times per subject (e.g. "Follow-up Visit"). Off: happens once per subject (e.g. "Baseline").'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('order')->sortable()->alignCenter(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->badge()->color('gray'),
                Tables\Columns\IconColumn::make('repeatable')->boolean(),
                Tables\Columns\TextColumn::make('responses_count')->label('Responses')->counts('responses')->badge()->color('info'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! isset($data['order']) || $data['order'] === 0) {
                            $data['order'] = ($this->getOwnerRecord()->events()->max('order') ?? 0) + 10;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->responses()->count() > 0) {
                            Notification::make()->title('Cannot delete — has responses')->danger()->send();

                            return false;
                        }
                    }),
            ])
            ->defaultSort('order')
            ->reorderable('order');
    }
}
