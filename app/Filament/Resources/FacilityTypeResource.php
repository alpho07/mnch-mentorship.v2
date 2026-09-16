<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityTypeResource\Pages;
use App\Filament\Resources\FacilityTypeResource\RelationManagers\FacilitiesRelationManager;
use App\Models\FacilityType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;

class FacilityTypeResource extends Resource {

    protected static ?string $model = FacilityType::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Geographic Structure';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Facility Types';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_facility::type');}

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Facility Type Details')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->label('Facility Type Name')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->columnSpanFull()
                                ->helperText('e.g., Hospital, Health Centre, Dispensary, Clinic'),
                                Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(4)
                                ->maxLength(1000)
                                ->columnSpanFull()
                                ->helperText('Describe the characteristics and role of this facility type'),
                                Forms\Components\TextInput::make('capacity_range')
                                ->label('Typical Capacity Range')
                                ->maxLength(100)
                                ->placeholder('e.g., 50-200 beds')
                                ->helperText('Optional: typical capacity or size range'),
                                Forms\Components\Select::make('level')
                                ->label('Health System Level')
                                ->options([
                                    'primary' => 'Primary Care',
                                    'secondary' => 'Secondary Care',
                                    'tertiary' => 'Tertiary Care',
                                    'specialized' => 'Specialized Care',
                                ])
                                ->helperText('Health system level this facility type represents'),
                            ])
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('name')
                            ->label('Facility Type')
                            ->sortable()
                            ->searchable()
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('description')
                            ->label('Description')
                            ->limit(60)
                            ->wrap()
                            ->placeholder('No description'),
                            Tables\Columns\TextColumn::make('level')
                            ->label('System Level')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'primary' => 'success',
                                        'secondary' => 'info',
                                        'tertiary' => 'warning',
                                        'specialized' => 'danger',
                                        default => 'gray',
                                    }),
                            Tables\Columns\TextColumn::make('facility_count')
                            ->label('Facilities')
                            ->badge()
                            ->color('primary')
                            ->description('Total count'),
                            Tables\Columns\TextColumn::make('hub_facility_count')
                            ->label('Hub Facilities')
                            ->badge()
                            ->color('warning')
                            ->description('Hub count'),
                            Tables\Columns\TextColumn::make('capacity_range')
                            ->label('Capacity Range')
                            ->badge()
                            ->color('gray')
                            ->placeholder('Not specified'),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->date()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->defaultSort('name')
                        ->filters([
                            Tables\Filters\SelectFilter::make('level')
                            ->label('System Level')
                            ->options([
                                'primary' => 'Primary Care',
                                'secondary' => 'Secondary Care',
                                'tertiary' => 'Tertiary Care',
                                'specialized' => 'Specialized Care',
                            ]),
                            Tables\Filters\Filter::make('has_facilities')
                            ->label('Has Facilities')
                            ->query(fn($query) => $query->withFacilities()),
                            Tables\Filters\Filter::make('has_hub_facilities')
                            ->label('Has Hub Facilities')
                            ->query(fn($query) => $query->whereHas('facilities', function ($q) {
                                        $q->where('is_hub', true);
                                    })),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make()
                            ->requiresConfirmation()
                            ->modalDescription('This will delete the facility type. Facilities will lose their type assignment.'),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make()
                                ->requiresConfirmation(),
                            ]),
                        ])
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Infolists\Components\Section::make('Facility Type Overview')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                ->label('Facility Type')
                                ->size('xl')
                                ->weight('bold')
                                ->color('primary'),
                                Infolists\Components\TextEntry::make('level')
                                ->label('Health System Level')
                                ->badge()
                                ->size('lg')
                                ->color(fn(string $state): string => match ($state) {
                                            'primary' => 'success',
                                            'secondary' => 'info',
                                            'tertiary' => 'warning',
                                            'specialized' => 'danger',
                                            default => 'gray',
                                        }),
                                Infolists\Components\TextEntry::make('capacity_range')
                                ->label('Typical Capacity')
                                ->badge()
                                ->color('gray')
                                ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('description')
                                ->label('Description')
                                ->prose()
                                ->placeholder('No description provided')
                                ->columnSpanFull(),
                            ])
                            ->columns(3),
                            Infolists\Components\Section::make('Statistics')
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                ->schema([
                                    Infolists\Components\TextEntry::make('facility_count')
                                    ->label('Total Facilities')
                                    ->badge()
                                    ->size('lg')
                                    ->color('primary')
                                    ->icon('heroicon-o-building-office-2'),
                                    Infolists\Components\TextEntry::make('hub_facility_count')
                                    ->label('Hub Facilities')
                                    ->badge()
                                    ->size('lg')
                                    ->color('warning')
                                    ->icon('heroicon-o-star'),
                                    Infolists\Components\TextEntry::make('spoke_facilities_count')
                                    ->label('Spoke Facilities')
                                    ->badge()
                                    ->size('lg')
                                    ->color('info')
                                    ->icon('heroicon-o-arrow-path')
                                    ->getStateUsing(function (FacilityType $record): int {
                                        return $record->facilities()
                                                        ->where('is_hub', false)
                                                        ->whereNotNull('hub_id')
                                                        ->count();
                                    }),
                                    Infolists\Components\TextEntry::make('training_count')
                                    ->label('Total Trainings')
                                    ->badge()
                                    ->size('lg')
                                    ->color('success')
                                    ->icon('heroicon-o-academic-cap')
                                    ->getStateUsing(function (FacilityType $record): int {
                                        return \App\Models\Training::whereHas('facility', function ($query) use ($record) {
                                                    $query->where('facility_type_id', $record->id);
                                                })->count();
                                    }),
                                ]),
                            ])
                            ->columns(1),
                            Infolists\Components\Section::make('Geographic Distribution')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('county_distribution')
                                ->label('Distribution by County')
                                ->schema([
                                    Infolists\Components\TextEntry::make('county_name')
                                    ->label('County')
                                    ->badge()
                                    ->color('primary'),
                                    Infolists\Components\TextEntry::make('facility_count')
                                    ->label('Facilities')
                                    ->badge()
                                    ->color('success'),
                                    Infolists\Components\TextEntry::make('hub_count')
                                    ->label('Hubs')
                                    ->badge()
                                    ->color('warning'),
                                ])
                                ->getStateUsing(function (FacilityType $record) {
                                    return $record->facilities()
                                                    ->with('subcounty.county')
                                                    ->get()
                                                    ->groupBy('subcounty.county.name')
                                                    ->map(function ($facilities, $countyName) {
                                                        return [
                                                            'county_name' => $countyName,
                                                            'facility_count' => $facilities->count(),
                                                            'hub_count' => $facilities->where('is_hub', true)->count(),
                                                        ];
                                                    })
                                                    ->values()
                                                    ->toArray();
                                })
                                ->columns(3)
                                ->placeholder('No facilities of this type yet'),
                            ])
                            ->columns(1)
                            ->collapsible(),
                            Infolists\Components\Section::make('System Information')
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime()
                                ->icon('heroicon-o-calendar'),
                                Infolists\Components\TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime()
                                ->icon('heroicon-o-clock'),
                                Infolists\Components\TextEntry::make('id')
                                ->label('System ID')
                                ->badge()
                                ->color('gray'),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->collapsed(),
        ]);
    }

    public static function getRelations(): array {
        return [
                //FacilitiesRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListFacilityTypes::route('/'),
            'create' => Pages\CreateFacilityType::route('/create'),
            'view' => Pages\ViewFacilityType::route('/{record}'),
            'edit' => Pages\EditFacilityType::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array {
        return ['name', 'description'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array {
        return [
            'Level' => $record->level ? ucfirst($record->level) . ' Care' : null,
            'Facilities' => $record->facility_count . ' facilities',
        ];
    }
}
