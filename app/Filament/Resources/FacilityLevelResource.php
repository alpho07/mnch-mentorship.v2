<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityLevelResource\Pages;
use App\Models\FacilityLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FacilityLevelResource extends Resource {

    protected static ?string $model = FacilityLevel::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'System Administration';
    protected static ?string $navigationLabel = 'Facility Levels';
    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_facility::level');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                    ->label('Level Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Level 2 - Dispensaries & Clinics')
                                    ->helperText('Full descriptive name of the facility level'),
                                    Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('L2')
                                    ->helperText('Short code for the level'),
                                    Forms\Components\TextInput::make('level_number')
                                    ->label('Level Number')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(6)
                                    ->placeholder('2')
                                    ->helperText('Numeric value (1-6)'),
                                    Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Only active levels appear in selections'),
                                    Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpan(2)
                                    ->maxLength(65535)
                                    ->helperText('Detailed description of this facility level'),
                                ]),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('level_number')
                            ->label('Level')
                            ->sortable()
                            ->alignCenter()
                            ->size('lg')
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('code')
                            ->label('Code')
                            ->badge()
                            ->color('primary')
                            ->searchable(),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Name')
                            ->searchable()
                            ->sortable()
                            ->weight('medium')
                            ->description(fn(FacilityLevel $record): ?string => $record->description),
                            Tables\Columns\TextColumn::make('facilities_count')
                            ->label('Facilities')
                            ->counts('facilities')
                            ->sortable()
                            ->alignCenter()
                            ->badge()
                            ->color('success'),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\TernaryFilter::make('is_active')
                            ->label('Active Status')
                            ->default(true),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
                        ])
                        ->defaultSort('level_number');
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListFacilityLevels::route('/'),
            'create' => Pages\CreateFacilityLevel::route('/create'),
            'edit' => Pages\EditFacilityLevel::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::active()->count();
    }
}
