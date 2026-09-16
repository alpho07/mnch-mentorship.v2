<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityOwnershipResource\Pages;
use App\Models\FacilityOwnership;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FacilityOwnershipResource extends Resource {

    protected static ?string $model = FacilityOwnership::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'System Administration';
    protected static ?string $navigationLabel = 'Facility Ownership';
    protected static ?int $navigationSort = 11;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_facility::ownership');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                    ->label('Ownership Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Public (Government)')
                                    ->helperText('Full name of the ownership type'),
                                    Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('PUBLIC')
                                    ->helperText('Short code for the ownership type')
                                    ->columnSpan(1),
                                    Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Only active ownership types appear in selections'),
                                    Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpan(2)
                                    ->maxLength(65535)
                                    ->helperText('Detailed description of this ownership type'),
                                ]),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('code')
                            ->label('Code')
                            ->badge()
                            ->color('primary')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Name')
                            ->searchable()
                            ->sortable()
                            ->weight('medium')
                            ->description(fn(FacilityOwnership $record): ?string => $record->description),
                            Tables\Columns\TextColumn::make('facilities_count')
                            ->label('Facilities')
                            ->counts('facilities')
                            ->sortable()
                            ->alignCenter()
                            ->badge()
                            ->color(fn(int $state): string => match (true) {
                                        $state > 100 => 'success',
                                        $state > 50 => 'warning',
                                        $state > 0 => 'info',
                                        default => 'gray',
                                    }),
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
                        ->defaultSort('name');
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListFacilityOwnerships::route('/'),
            'create' => Pages\CreateFacilityOwnership::route('/create'),
            'edit' => Pages\EditFacilityOwnership::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::active()->count();
    }
}
