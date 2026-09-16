<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubcountyResource\Pages;
use App\Filament\Resources\SubcountyResource\RelationManagers\FacilitiesRelationManager;
use App\Models\Subcounty;
use App\Models\County;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;

class SubcountyResource extends Resource {

    protected static ?string $model = Subcounty::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Geographic Structure';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_subcounty');}

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Subcounty Details')
                            ->schema([
                                Forms\Components\Select::make('county_id')
                                ->label('County')
                                ->relationship('county', 'name')
                                ->required()
                                ->searchable()
                                ->preload(),
                                Forms\Components\TextInput::make('name')
                                ->label('Subcounty Name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                                Forms\Components\TextInput::make('uid')
                                ->label('Unique Identifier')
                                ->maxLength(50)
                                ->unique(ignoreRecord: true),
                            ])
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('county.division.name')
                            ->label('Division')
                            ->sortable()
                            ->searchable()
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\TextColumn::make('county.name')
                            ->label('County')
                            ->sortable()
                            ->searchable()
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Subcounty Name')
                            ->sortable()
                            ->searchable()
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('uid')
                            ->label('UID')
                            ->searchable()
                            ->badge()
                            ->color('gray'),
                            Tables\Columns\TextColumn::make('facility_count')
                            ->label('Facilities')
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('hub_facilities_count')
                            ->label('Hub Facilities')
                            ->badge()
                            ->color('warning'),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->date()
                            ->sortable(),
                        ])
                        ->defaultSort('name')
                        ->filters([
                            Tables\Filters\SelectFilter::make('county_id')
                            ->label('County')
                            ->relationship('county', 'name')
                            ->searchable()
                            ->preload(),
                            Tables\Filters\Filter::make('has_facilities')
                            ->label('Has Facilities')
                            ->query(fn($query) => $query->withFacilities()),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make()
                            ->requiresConfirmation(),
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
                            Infolists\Components\Section::make('Subcounty Overview')
                            ->schema([
                                Infolists\Components\TextEntry::make('county.division.name')
                                ->label('Division')
                                ->badge()
                                ->color('primary'),
                                Infolists\Components\TextEntry::make('county.name')
                                ->label('County')
                                ->badge()
                                ->color('info'),
                                Infolists\Components\TextEntry::make('name')
                                ->label('Subcounty Name')
                                ->size('lg')
                                ->weight('bold'),
                                Infolists\Components\TextEntry::make('uid')
                                ->label('Unique Identifier')
                                ->badge()
                                ->color('gray'),
                                Infolists\Components\TextEntry::make('full_location')
                                ->label('Full Location')
                                ->prose(),
                            ])
                            ->columns(2),
                            Infolists\Components\Section::make('Statistics')
                            ->schema([
                                Infolists\Components\TextEntry::make('facility_count')
                                ->label('Total Facilities')
                                ->badge()
                                ->color('success'),
                                Infolists\Components\TextEntry::make('hub_facilities_count')
                                ->label('Hub Facilities')
                                ->badge()
                                ->color('warning'),
                            ])
                            ->columns(2),
        ]);
    }

    public static function getRelations(): array {
        return [
                // FacilitiesRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListSubcounties::route('/'),
            'create' => Pages\CreateSubcounty::route('/create'),
            'view' => Pages\ViewSubcounty::route('/{record}'),
            'edit' => Pages\EditSubcounty::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array {
        return ['name', 'uid', 'county.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array {
        return [
            'County' => $record->county?->name,
            'Division' => $record->county?->division?->name,
        ];
    }
}
