<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityResource\Pages;
use App\Filament\Resources\FacilityResource\RelationManagers;
use App\Models\Facility;
use App\Models\FacilityLevel;
use App\Models\FacilityOwnership;
use App\Models\Subcounty;
use App\Models\FacilityType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\Grid as InfoGrid;

class FacilityResource extends Resource {

    protected static ?string $model = Facility::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'System Administration';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_facility');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Section::make('Basic Information')
                            ->description('Facility identification and classification')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                    ->label('Facility Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                    Forms\Components\TextInput::make('mfl_code')
                                    ->label('MFL Code')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->helperText('Master Facility List Code'),
                                    Forms\Components\TextInput::make('uid')
                                    ->label('Unique Identifier')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->helperText('System-generated or custom UID'),
                                    Forms\Components\Select::make('subcounty_id')
                                    ->label('Sub-County')
                                    ->relationship('subcounty', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                        ->required(),
                                        Forms\Components\Select::make('county_id')
                                        ->relationship('county', 'name')
                                        ->required(),
                                    ])
                                    ->helperText('County is automatically determined from Sub-County'),
                                    Forms\Components\TextInput::make('ward')
                                    ->label('Ward')
                                    ->maxLength(255),
                                    Forms\Components\Select::make('facility_level_id')
                                    ->label('Facility Level')
                                    ->relationship('facilityLevel', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Level 2, 3, 4, 5, or 6'),
                                    Forms\Components\Select::make('facility_type_id')
                                    ->label('Facility Type')
                                    ->relationship('facilityType', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                        ->required(),
                                        Forms\Components\Textarea::make('description'),
                                    ])
                                    ->helperText('Hospital, Health Centre, Dispensary, etc.'),
                                    Forms\Components\Select::make('facility_ownership_id')
                                    ->label('Ownership')
                                    ->relationship('facilityOwnership', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Public, Private, FBO, NGO, etc.'),
                                ]),
                            ])
                            ->collapsible(),
                            Section::make('GPS Coordinates & Address')
                            ->description('Location information')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('lat')
                                    ->label('Latitude')
                                    ->numeric()
                                    ->step(0.0000001)
                                    ->minValue(-90)
                                    ->maxValue(90)
                                    ->placeholder('-1.286389')
                                    ->helperText('Decimal degrees format'),
                                    Forms\Components\TextInput::make('long')
                                    ->label('Longitude')
                                    ->numeric()
                                    ->step(0.0000001)
                                    ->minValue(-180)
                                    ->maxValue(180)
                                    ->placeholder('36.817223')
                                    ->helperText('Decimal degrees format'),
                                    Forms\Components\Textarea::make('physical_address')
                                    ->label('Physical Address/Location')
                                    ->rows(2)
                                    ->columnSpan(2)
                                    ->helperText('Detailed location description'),
                                    Forms\Components\TextInput::make('postal_address')
                                    ->label('Postal Address')
                                    ->maxLength(255)
                                    ->placeholder('P.O. Box 1234-00100'),
                                ]),
                            ])
                            ->collapsible(),
                            Section::make('Contact Information')
                            ->description('Facility contact details')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('telephone')
                                    ->label('Telephone Number')
                                    ->tel()
                                    ->maxLength(255)
                                    ->placeholder('+254 712 345 678'),
                                    Forms\Components\TextInput::make('email')
                                    ->label('Email Address')
                                    ->email()
                                    ->maxLength(255)
                                    ->placeholder('facility@example.com'),
                                ]),
                            ])
                            ->collapsible(),
                            Section::make('Facility In-charge')
                            ->description('Details of the person in charge')
                            ->schema([
                                Grid::make(3)
                                ->schema([
                                    Forms\Components\TextInput::make('incharge_name')
                                    ->label('Name of Facility In-charge')
                                    ->maxLength(255)
                                    ->placeholder('Dr. John Doe'),
                                    Forms\Components\TextInput::make('incharge_designation')
                                    ->label('Title/Designation')
                                    ->maxLength(255)
                                    ->placeholder('Medical Superintendent, Nursing Officer In-charge, etc.')
                                    ->helperText('Job title of the in-charge'),
                                    Forms\Components\TextInput::make('incharge_contact')
                                    ->label('Contact of In-charge')
                                    ->tel()
                                    ->maxLength(255)
                                    ->placeholder('+254 712 345 678'),
                                ]),
                            ])
                            ->collapsible(),
                            Section::make('Hub & Spoke Configuration')
                            ->description('For training and mentorship programs')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    Forms\Components\Toggle::make('is_hub')
                                    ->label('Is this facility a Hub?')
                                    ->reactive()
                                    ->helperText('Hub facilities can have spoke facilities linked to them'),
                                    Forms\Components\Select::make('hub_id')
                                    ->label('Parent Hub Facility')
                                    ->relationship('hub', 'name', fn(Builder $query) => $query->where('is_hub', true))
                                    ->searchable()
                                    ->preload()
                                    ->hidden(fn(Forms\Get $get) => $get('is_hub'))
                                    ->helperText('Select the hub this facility reports to'),
                                ]),
                            ])
                            ->collapsible()
                            ->collapsed(),
                            Section::make('Central Store Configuration')
                            ->description('For inventory management')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    Forms\Components\Toggle::make('is_central_store')
                                    ->label('Is this a Central Store?')
                                    ->reactive()
                                    ->helperText('Central stores manage inventory distribution'),
                                    Forms\Components\TextInput::make('storage_capacity')
                                    ->label('Storage Capacity')
                                    ->maxLength(255)
                                    ->visible(fn(Forms\Get $get) => $get('is_central_store'))
                                    ->placeholder('e.g., 1000 cubic meters'),
                                    Forms\Components\KeyValue::make('operating_hours')
                                    ->label('Operating Hours')
                                    ->keyLabel('Day')
                                    ->valueLabel('Hours')
                                    ->visible(fn(Forms\Get $get) => $get('is_central_store'))
                                    ->helperText('Define operating hours for each day'),
                                ]),
                            ])
                            ->collapsible()
                            ->collapsed(),
                            Section::make('Additional Information')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    Forms\Components\Toggle::make('is_active')
                                    ->label('Active Facility')
                                    ->default(true)
                                    ->helperText('Only active facilities appear in selections'),
                                    Forms\Components\Placeholder::make('created_at')
                                    ->label('Created At')
                                    ->content(fn(?Facility $record): string => $record?->created_at?->diffForHumans() ?? '-')
                                    ->hidden(fn(?Facility $record) => $record === null),
                                    Forms\Components\Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(3)
                                    ->columnSpan(2)
                                    ->helperText('Any additional information about the facility'),
                                ]),
                            ])
                            ->collapsible()
                            ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('name')
                            ->label('Facility Name')
                            ->searchable()
                            ->sortable()
                            ->weight('medium')
                            ->description(fn(Facility $record): string => $record->mfl_code ? "MFL: {$record->mfl_code}" : ''),
                            Tables\Columns\TextColumn::make('facilityLevel.name')
                            ->label('Level')
                            ->badge()
                            ->sortable()
                            ->searchable(),
                            Tables\Columns\TextColumn::make('facilityType.name')
                            ->label('Type')
                            ->sortable()
                            ->searchable(),
                            Tables\Columns\TextColumn::make('facilityOwnership.name')
                            ->label('Ownership')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'Public (Government)' => 'success',
                                        'Private' => 'info',
                                        'Faith-Based Organization (FBO)' => 'warning',
                                        'Non-Governmental Organization (NGO)' => 'primary',
                                        default => 'gray',
                                    })
                            ->sortable(),
                            Tables\Columns\TextColumn::make('subcounty.name')
                            ->label('Sub-County')
                            ->sortable()
                            ->searchable()
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('subcounty.county.name')
                            ->label('County')
                            ->sortable()
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\TextColumn::make('ward')
                            ->label('Ward')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\TextColumn::make('incharge_name')
                            ->label('In-charge')
                            ->searchable()
                            ->description(fn(Facility $record): ?string => $record->incharge_designation)
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('telephone')
                            ->label('Contact')
                            ->icon('heroicon-m-phone')
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\IconColumn::make('is_hub')
                            ->label('Hub')
                            ->boolean()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\IconColumn::make('is_central_store')
                            ->label('Central Store')
                            ->boolean()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('assessments_count')
                            ->label('Assessments')
                            ->counts('assessments')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('facility_level_id')
                            ->label('Level')
                            ->relationship('facilityLevel', 'name')
                            ->multiple()
                            ->preload(),
                            Tables\Filters\SelectFilter::make('facility_type_id')
                            ->label('Type')
                            ->relationship('facilityType', 'name')
                            ->multiple()
                            ->preload(),
                            Tables\Filters\SelectFilter::make('facility_ownership_id')
                            ->label('Ownership')
                            ->relationship('facilityOwnership', 'name')
                            ->multiple()
                            ->preload(),
                            Tables\Filters\SelectFilter::make('subcounty_id')
                            ->label('Sub-County')
                            ->relationship('subcounty', 'name')
                            ->searchable()
                            ->preload()
                            ->multiple(),
                            Tables\Filters\Filter::make('has_coordinates')
                            ->label('Has GPS Coordinates')
                            ->query(fn(Builder $query): Builder => $query->whereNotNull('latitude')->whereNotNull('longitude')),
                            Tables\Filters\TernaryFilter::make('is_hub')
                            ->label('Hub Facility'),
                            Tables\Filters\TernaryFilter::make('is_central_store')
                            ->label('Central Store'),
                            Tables\Filters\TernaryFilter::make('is_active')
                            ->label('Active Status')
                            ->default(true),
                            Tables\Filters\TrashedFilter::make(),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make(),
                            Tables\Actions\ForceDeleteAction::make(),
                            Tables\Actions\RestoreAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                                Tables\Actions\ForceDeleteBulkAction::make(),
                                Tables\Actions\RestoreBulkAction::make(),
                            ]),
                        ])
                        ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            InfoSection::make('Basic Information')
                            ->schema([
                                InfoGrid::make(2)
                                ->schema([
                                    TextEntry::make('name')
                                    ->label('Facility Name')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->columnSpan(2),
                                    TextEntry::make('mfl_code')
                                    ->label('MFL Code')
                                    ->badge()
                                    ->color('success'),
                                    TextEntry::make('uid')
                                    ->label('Unique ID')
                                    ->copyable(),
                                    TextEntry::make('facilityLevel.name')
                                    ->label('Facility Level')
                                    ->badge(),
                                    TextEntry::make('facilityType.name')
                                    ->label('Facility Type')
                                    ->badge(),
                                    TextEntry::make('facilityOwnership.name')
                                    ->label('Ownership')
                                    ->badge(),
                                ]),
                            ]),
                            InfoSection::make('Location Information')
                            ->schema([
                                InfoGrid::make(2)
                                ->schema([
                                    TextEntry::make('subcounty.county.name')
                                    ->label('County'),
                                    TextEntry::make('subcounty.name')
                                    ->label('Sub-County'),
                                    TextEntry::make('ward')
                                    ->label('Ward')
                                    ->placeholder('Not specified'),
                                    TextEntry::make('coordinates')
                                    ->label('GPS Coordinates')
                                    ->formatStateUsing(fn(?array $state): string =>
                                            $state ? "{$state['latitude']}, {$state['longitude']}" : 'Not set'
                                    )
                                    ->copyable()
                                    ->placeholder('Not set'),
                                    TextEntry::make('physical_address')
                                    ->label('Physical Address')
                                    ->columnSpan(2)
                                    ->placeholder('Not provided'),
                                    TextEntry::make('postal_address')
                                    ->label('Postal Address')
                                    ->placeholder('Not provided'),
                                ]),
                            ])
                            ->collapsible(),
                            InfoSection::make('Contact Information')
                            ->schema([
                                InfoGrid::make(2)
                                ->schema([
                                    TextEntry::make('telephone')
                                    ->label('Telephone')
                                    ->icon('heroicon-m-phone')
                                    ->placeholder('Not provided'),
                                    TextEntry::make('email')
                                    ->label('Email')
                                    ->icon('heroicon-m-envelope')
                                    ->copyable()
                                    ->placeholder('Not provided'),
                                ]),
                            ])
                            ->collapsible(),
                            InfoSection::make('Facility In-charge')
                            ->schema([
                                InfoGrid::make(3)
                                ->schema([
                                    TextEntry::make('incharge_name')
                                    ->label('Name')
                                    ->placeholder('Not specified'),
                                    TextEntry::make('incharge_designation')
                                    ->label('Designation')
                                    ->placeholder('Not specified'),
                                    TextEntry::make('incharge_contact')
                                    ->label('Contact')
                                    ->icon('heroicon-m-phone')
                                    ->placeholder('Not provided'),
                                ]),
                            ])
                            ->collapsible(),
                            InfoSection::make('System Configuration')
                            ->schema([
                                InfoGrid::make(2)
                                ->schema([
                                    TextEntry::make('is_hub')
                                    ->label('Hub Facility')
                                    ->badge()
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Yes' : 'No')
                                    ->color(fn(bool $state): string => $state ? 'success' : 'gray'),
                                    TextEntry::make('hub.name')
                                    ->label('Parent Hub')
                                    ->placeholder('Not linked to a hub'),
                                    TextEntry::make('is_central_store')
                                    ->label('Central Store')
                                    ->badge()
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Yes' : 'No')
                                    ->color(fn(bool $state): string => $state ? 'success' : 'gray'),
                                    TextEntry::make('storage_capacity')
                                    ->label('Storage Capacity')
                                    ->placeholder('Not specified'),
                                    TextEntry::make('is_active')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Active' : 'Inactive')
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),
                                ]),
                            ])
                            ->collapsible(),
                            InfoSection::make('Additional Information')
                            ->schema([
                                TextEntry::make('notes')
                                ->label('Notes')
                                ->placeholder('No notes')
                                ->columnSpan(2),
                                InfoGrid::make(2)
                                ->schema([
                                    TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                    TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                                ]),
                            ])
                            ->collapsible()
                            ->collapsed(),
        ]);
    }

    public static function getRelations(): array {
        return [
                //RelationManagers\AssessmentsRelationManager::class,
                //RelationManagers\TrainingsRelationManager::class,
                //RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListFacilities::route('/'),
            'create' => Pages\CreateFacility::route('/create'),
            'view' => Pages\ViewFacility::route('/{record}'),
            'edit' => Pages\EditFacility::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->withoutGlobalScopes([
                            SoftDeletingScope::class,
        ]);
    }

    public static function getNavigationBadge(): ?string {
        return number_format(static::getModel()::count(), 0);
    }
}
