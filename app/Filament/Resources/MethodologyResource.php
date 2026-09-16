<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MethodologyResource\Pages;
use App\Models\Methodology;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MethodologyResource extends Resource {

    protected static ?string $model = Methodology::class;
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';
    protected static ?string $navigationGroup = 'Curriculum';
    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_methodology');}

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\TextInput::make('name')
                            ->label('Methodology Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                    Forms\Components\Textarea::make('description')->label('Description'),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                            Tables\Columns\TextColumn::make('description')->limit(40),
                            Tables\Columns\TextColumn::make('created_at')->date(),
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

    public static function getRelations(): array {
        return [];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListMethodologies::route('/'),
            'create' => Pages\CreateMethodology::route('/create'),
            'edit' => Pages\EditMethodology::route('/{record}/edit'),
        ];
    }
}
