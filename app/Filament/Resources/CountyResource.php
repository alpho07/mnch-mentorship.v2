<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CountyResource\Pages;
use App\Filament\Resources\CountyResource\RelationManagers\SubcountiesRelationManager;
use App\Models\County;
use App\Models\Division;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;

class CountyResource extends Resource {

    protected static ?string $model = County::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Geographic Structure';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_county');}

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('County Details')
                            ->schema([
                                Forms\Components\Select::make('division_id')
                                ->label('Division')
                                ->options(Division::pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->preload(),
                                Forms\Components\TextInput::make('name')
                                ->label('County Name')
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
                            Tables\Columns\TextColumn::make('division.name')
                            ->label('Division')
                            ->sortable()
                            ->searchable()
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\TextColumn::make('name')
                            ->label('County Name')
                            ->sortable()
                            ->searchable()
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('uid')
                            ->label('UID')
                            ->searchable()
                            ->badge()
                            ->color('gray'),
                            Tables\Columns\TextColumn::make('subcounty_count')
                            ->label('Subcounties')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('facility_count')
                            ->label('Facilities')
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->date()
                            ->sortable(),
                        ])
                        ->defaultSort('name')
                        ->filters([
                            Tables\Filters\SelectFilter::make('division_id')
                            ->label('Division')
                            ->options(Division::pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                            Tables\Filters\Filter::make('has_subcounties')
                            ->label('Has Subcounties')
                            ->query(fn($query) => $query->has('subcounties')),
                            Tables\Filters\Filter::make('has_facilities')
                            ->label('Has Facilities')
                            ->query(fn($query) => $query->has('facilities')),
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
                            Infolists\Components\Section::make('County Overview')
                            ->schema([
                                Infolists\Components\TextEntry::make('division.name')
                                ->label('Division')
                                ->badge()
                                ->color('primary'),
                                Infolists\Components\TextEntry::make('name')
                                ->label('County Name')
                                ->size('lg')
                                ->weight('bold'),
                                Infolists\Components\TextEntry::make('uid')
                                ->label('Unique Identifier')
                                ->badge()
                                ->color('gray'),
                            ])
                            ->columns(2),
                            Infolists\Components\Section::make('Geographic Statistics')
                            ->schema([
                                Infolists\Components\TextEntry::make('subcounty_count')
                                ->label('Subcounties')
                                ->badge()
                                ->color('info'),
                                Infolists\Components\TextEntry::make('facility_count')
                                ->label('Total Facilities')
                                ->badge()
                                ->color('success'),
                            ])
                            ->columns(2),
                            Infolists\Components\Section::make('Metadata')
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(),
                            ])
                            ->columns(2)
                            ->collapsible(),
        ]);
    }

    public static function getRelations(): array {
        return [
                // SubcountiesRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListCounties::route('/'),
            'create' => Pages\CreateCounty::route('/create'),
            'view' => Pages\ViewCounty::route('/{record}'),
            'edit' => Pages\EditCounty::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array {
        return ['name', 'uid', 'division.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array {
        return [
            'Division' => $record->division?->name,
            'UID' => $record->uid,
        ];
    }
}
