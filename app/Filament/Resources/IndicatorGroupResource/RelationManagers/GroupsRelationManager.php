<?php

namespace App\Filament\Resources\IndicatorGroupResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class GroupsRelationManager extends RelationManager {

    protected static string $relationship = 'groups';
    protected static ?string $title = 'Module Groups';

    public function form(Form $form): Form {
        return $form->schema([
                    Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('code')
                                ->required()->maxLength(20),
                                Forms\Components\TextInput::make('sort_order')
                                ->numeric()->default(0),
                    ]),
                            Forms\Components\TextInput::make('name')
                            ->required()->maxLength(150)->columnSpanFull(),
                            Forms\Components\Textarea::make('description')
                            ->rows(2)->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('code')->badge()->color('gray'),
                            Tables\Columns\TextColumn::make('name')->searchable(),
                            Tables\Columns\TextColumn::make('indicators_count')
                            ->counts('indicators')->badge()->color('info')->alignCenter(),
                            Tables\Columns\IconColumn::make('is_active')->boolean(),
                            Tables\Columns\TextColumn::make('sort_order')->sortable()->alignCenter(),
                        ])
                        ->headerActions([Tables\Actions\CreateAction::make()])
                        ->actions([
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make(),
                        ])
                        ->defaultSort('sort_order')
                        ->reorderable('sort_order');
    }
}
