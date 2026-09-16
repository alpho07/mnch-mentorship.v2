<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlobalTrainingResource\Pages;
use App\Models\Training;
use App\Models\ApprovedTrainingArea;
use App\Models\User;
use App\Models\County;
use App\Models\Partner;
use App\Models\Division;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;

class GlobalTrainingResource extends Resource {

    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'MOH Trainings';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'moh-trainings';
    protected static ?string $recordTitleAttribute = 'title';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_global::training');
    }

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('view_any_global::training');
    }

    public static function canCreate(): bool {
        return static::canAccess();
    }

    public static function canEdit($record): bool {
        return static::canAccess();
    }

    public static function canDelete($record): bool {
        return static::canAccess();
    }

    // Filter to only show global trainings
    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->where('type', 'global_training')
                        ->withoutGlobalScopes([
                            SoftDeletingScope::class,
        ]);
    }

    // Ensure proper route model binding for global trainings only (withTrashed so restore works)
    public static function resolveRecordRouteBinding($value): ?Model {
        return static::getModel()::withTrashed()
            ->where('type', 'global_training')
            ->findOrFail($value);
    }

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            // Hidden field to ensure type is always set
                            Forms\Components\Hidden::make('type')
                            ->default('global_training'),
                            // Hidden field to store mentor_id (logged in user)
                            Forms\Components\Hidden::make('mentor_id')
                            ->default(auth()->id()),
                            Forms\Components\Section::make('Training Information')
                            ->description('Basic details about the training program')
                            ->schema([
                                Forms\Components\Select::make('approved_training_area_id')
                                ->label('Training Area')
                                ->relationship('approvedTrainingArea', 'name', fn(Builder $query) => $query->active()->ordered())
                                ->required()
                                ->searchable()
                                ->preload()
                                ->helperText('Select the approved training area for this program')
                                ->columnSpanFull(),
                                Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                    ->label('Training Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Enter the training title')
                                    ->columnSpanFull(),
                                    TextInput::make('identifier')
                                    ->label('Training ID')
                                    ->required()
                                    ->readOnly(true)
                                    ->unique(Training::class, ignoreRecord: true)
                                    ->maxLength(50)
                                    ->default(fn($record) => $record?->identifier ?? strtoupper('TRN-' . Str::random(8)))
                                    ->disabled(fn($record) => filled($record?->identifier))
                                    ->dehydrated(true)
                                    ->helperText('Auto-generated unique identifier'),
                                ]),
                                Forms\Components\Textarea::make('description')
                                ->label('Training Description')
                                ->rows(3)
                                ->placeholder('Provide a detailed description of the training program')
                                ->columnSpanFull(),
                            ]),
                            Forms\Components\Section::make('Training Leadership')
                            ->description('Select who will lead this training')
                            ->schema([
                                Forms\Components\Select::make('lead_type')
                                ->label('Training Lead Type')
                                ->options([
                                    'national' => 'National',
                                    'county' => 'County',
                                    'partner' => 'Partner Led',
                                ])
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    // Clear dependent fields when lead type changes
                                    $set('lead_division_id', null);
                                    $set('counties', []);
                                    $set('partners', []);
                                }),
                                // National Lead: Division + Multiple Counties
                                Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('lead_division_id')
                                    ->label('Division')
                                    ->relationship('division', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(fn(Get $get): bool => $get('lead_type') === 'national')
                                    ->visible(fn(Get $get): bool => $get('lead_type') === 'national')
                                    ->placeholder('Select the division leading this training'),
                                    Forms\Components\Select::make('counties')
                                    ->label('Counties')
                                    ->relationship('counties', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required(fn(Get $get): bool => $get('lead_type') === 'national')
                                    ->visible(fn(Get $get): bool => $get('lead_type') === 'national')
                                    ->placeholder('Select counties for this training')
                                    ->helperText('Select one or more counties'),
                                ]),
                                // County Lead: Multiple Counties
                                Forms\Components\Select::make('counties')
                                ->label('Counties')
                                ->relationship('counties', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(fn(Get $get): bool => $get('lead_type') === 'county')
                                ->visible(fn(Get $get): bool => $get('lead_type') === 'county')
                                ->placeholder('Select counties leading this training')
                                ->helperText('Select one or more counties')
                                ->columnSpanFull(),
                                // Partner Lead: Multiple Partners with on-the-fly creation
                                Forms\Components\Select::make('partners')
                                ->label('Partner Organizations')
                                ->relationship('partners', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(fn(Get $get): bool => $get('lead_type') === 'partner')
                                ->visible(fn(Get $get): bool => $get('lead_type') === 'partner')
                                ->placeholder('Select partner organizations leading this training')
                                ->helperText('Select one or more partners or create new ones')
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Partner Name'),
                                    Forms\Components\Select::make('type')
                                    ->label('Partner Type')
                                    ->options([
                                        'ngo' => 'NGO',
                                        'private' => 'Private Organization',
                                        'international' => 'International Organization',
                                        'faith_based' => 'Faith-Based Organization',
                                        'academic' => 'Academic Institution',
                                        'development' => 'Development Partner',
                                        'other' => 'Other',
                                    ])
                                    ->default('ngo')
                                    ->required(),
                                    Forms\Components\TextInput::make('contact_person')
                                    ->label('Contact Person')
                                    ->maxLength(255),
                                    Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                    Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(20),
                                    Forms\Components\Textarea::make('description')
                                    ->rows(2),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $partner = Partner::create($data);

                                    Notification::make()
                                            ->title('Partner Created')
                                            ->body("Partner '{$partner->name}' has been created successfully.")
                                            ->success()
                                            ->send();

                                    return $partner->id;
                                })
                                ->columnSpanFull(),
                            ]),
                            Forms\Components\Section::make('Schedule & Logistics')
                            ->description('Training dates and capacity')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M j, Y'),
                                    Forms\Components\DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M j, Y')
                                    ->after('start_date'),
                                    Forms\Components\TextInput::make('max_participants')
                                    ->label('Maximum Participants')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(30)
                                    ->suffix('people'),
                                ]),
                            ]),
                            Forms\Components\Section::make('Training Locations')
                            ->description('Select the type of training location')
                            ->schema([
                                Radio::make('location_type')
                                ->label('Location Type')
                                ->options([
                                    'hospital' => 'Hospital-based',
                                    'hotel' => 'Hotel-based',
                                    'online' => 'Online',
                                ])
                                ->required()
                                ->live()
                                ->descriptions([
                                    'hospital' => 'Training will be conducted at hospital facilities',
                                    'hotel' => 'Training will be conducted at hotels or conference venues',
                                    'online' => 'Training will be conducted online via video conferencing',
                                ])
                                ->columnSpanFull(),
                                // Hospital-based: Multiple hospitals selection
                                Forms\Components\Select::make('hospitals')
                                ->label('Select Hospitals/Facilities')
                                ->relationship('hospitals', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(fn(Get $get): bool => $get('location_type') === 'hospital')
                                ->visible(fn(Get $get): bool => $get('location_type') === 'hospital')
                                ->placeholder('Select one or more hospitals')
                                ->helperText('Hold Ctrl/Cmd to select multiple hospitals')
                                ->columnSpanFull(),
                                // Hotel-based: Multiple hotels input
                                Repeater::make('hotels_data')
                                ->label('Hotels')
                                ->schema([
                                    Grid::make(3)
                                    ->schema([
                                        TextInput::make('hotel_name')
                                        ->label('Hotel Name')
                                        ->required()
                                        ->placeholder('e.g., Sarova Panafric Hotel'),
                                        TextInput::make('hotel_contact')
                                        ->label('Contact Number')
                                        ->tel()
                                        ->placeholder('+254700000000'),
                                        Forms\Components\Textarea::make('hotel_address')
                                        ->label('Address')
                                        ->rows(1)
                                        ->placeholder('Full address of the hotel'),
                                    ]),
                                ])
                                ->required(fn(Get $get): bool => $get('location_type') === 'hotel')
                                ->visible(fn(Get $get): bool => $get('location_type') === 'hotel')
                                ->addActionLabel('Add Another Hotel')
                                ->collapsible()
                                ->itemLabel(fn(array $state): ?string => $state['hotel_name'] ?? 'New Hotel')
                                ->defaultItems(1)
                                ->columnSpanFull(),
                                // Online: Single link input
                                TextInput::make('online_link')
                                ->label('Online Meeting Link')
                                ->url()
                                ->required(fn(Get $get): bool => $get('location_type') === 'online')
                                ->visible(fn(Get $get): bool => $get('location_type') === 'online')
                                ->placeholder('https://zoom.us/j/... or https://meet.google.com/...')
                                ->helperText('Provide the full URL for the online meeting')
                                ->columnSpanFull(),
                            ]),
        ]);
    }

    protected static function mutateFormDataBeforeCreate(array $data): array {
        $data['type'] = 'global_training';
        $data['mentor_id'] = auth()->id();

        // Handle hotels data - save for later processing in afterCreate
        if (isset($data['hotels_data'])) {
            // Store temporarily, will be processed in afterCreate
            $data['_hotels_data'] = $data['hotels_data'];
            unset($data['hotels_data']);
        }

        // Clean up data - remove fields that shouldn't be saved to database main table
        unset($data['counties']);
        unset($data['partners']);
        unset($data['hospitals']);

        return $data;
    }

    protected static function mutateFormDataBeforeSave(array $data): array {
        $data['type'] = 'global_training';

        // Only set mentor_id if it's not already set
        if (empty($data['mentor_id'])) {
            $data['mentor_id'] = auth()->id();
        }

        // Handle hotels data
        if (isset($data['hotels_data'])) {
            $data['_hotels_data'] = $data['hotels_data'];
            unset($data['hotels_data']);
        }

        // Clean up data
        unset($data['counties']);
        unset($data['partners']);
        unset($data['hospitals']);

        return $data;
    }

    public static function table(Table $table): Table {
        return $table
                        ->query(
                                static::getEloquentQuery()->with([
                                    'approvedTrainingArea',
                                    'counties',
                                    'partners',
                                    'division',
                                    'hospitals',
                                    'hotels',
                                    'mentor',
                                    'participants'
                                ])
                        )
                        ->columns([
                            TextColumn::make('identifier')
                            ->label('ID')
                            ->searchable()
                            ->sortable()
                            ->weight('bold'),
                            TextColumn::make('title')
                            ->searchable()
                            ->sortable()
                            ->wrap()
                            ->description(function ($record): string {
                                if (!$record instanceof Training)
                                    return '';
                                return $record->description ? Str::limit($record->description, 60) : '';
                            }),
                            TextColumn::make('approvedTrainingArea.name')
                            ->label('Training Area')
                            ->badge()
                            ->color('primary')
                            ->sortable()
                            ->searchable(),
                            BadgeColumn::make('lead_type')
                            ->label('Lead Type')
                            ->colors([
                                'primary' => 'national',
                                'success' => 'county',
                                'warning' => 'partner',
                            ])
                            ->formatStateUsing(fn(?string $state): string => match ($state) {
                                        'national' => 'National',
                                        'county' => 'County',
                                        'partner' => 'Partner Led',
                                        default => ucfirst($state ?? ''),
                                    }),
                            TextColumn::make('lead_organization')
                            ->label('Lead Organization')
                            ->getStateUsing(function ($record): string {
                                if (!$record instanceof Training)
                                    return 'Not specified';

                                return match ($record->lead_type) {
                                    'national' => $record->division?->name ?? 'Ministry of Health',
                                    'county' => $record->counties->pluck('name')->implode(', ') ?: 'Not specified',
                                    'partner' => $record->partners->pluck('name')->implode(', ') ?: 'Not specified',
                                    default => 'Not specified',
                                };
                            })
                            ->searchable(['division.name', 'counties.name', 'partners.name'])
                            ->limit(50)
                            ->tooltip(function ($record): ?string {
                                if (!$record instanceof Training)
                                    return null;
                                return $record->lead_organization;
                            }),
                            BadgeColumn::make('location_type')
                            ->label('Location Type')
                            ->colors([
                                'info' => 'hospital',
                                'success' => 'hotel',
                                'warning' => 'online',
                            ])
                            ->formatStateUsing(fn(?string $state): string => match ($state) {
                                        'hospital' => 'Hospital',
                                        'hotel' => 'Hotel',
                                        'online' => 'Online',
                                        default => 'Not set',
                                    }),
                            TextColumn::make('location_display')
                            ->label('Locations')
                            ->getStateUsing(function ($record): string {
                                if (!$record instanceof Training)
                                    return 'Not specified';

                                return match ($record->location_type) {
                                    'hospital' => $record->hospitals->count() . ' hospital(s)',
                                    'hotel' => $record->hotels->count() . ' hotel(s)',
                                    'online' => 'Online',
                                    default => 'Not specified',
                                };
                            })
                            ->badge()
                            ->color('info'),
                            TextColumn::make('mentor.full_name')
                            ->label('Created By')
                            ->searchable(['first_name', 'last_name'])
                            ->description(function ($record): string {
                                if (!$record instanceof Training)
                                    return '';
                                return $record->mentor?->facility?->name ?? '';
                            }),
                            TextColumn::make('start_date')
                            ->date('M j, Y')
                            ->sortable()
                            ->description(function ($record): string {
                                if (!$record instanceof Training)
                                    return '';
                                return $record->end_date ? 'to ' . $record->end_date->format('M j, Y') : '';
                            }),
                            TextColumn::make('participants_count')
                            ->label('Participants')
                            ->counts('participants')
                            ->sortable()
                            ->badge()
                            ->color('success'),
                        ])
                        ->filters([
                            SelectFilter::make('approved_training_area_id')
                            ->label('Training Area')
                            ->relationship('approvedTrainingArea', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('lead_type')
                            ->label('Lead Type')
                            ->options([
                                'national' => 'National',
                                'county' => 'County',
                                'partner' => 'Partner Led',
                            ])
                            ->multiple(),
                            SelectFilter::make('location_type')
                            ->label('Location Type')
                            ->options([
                                'hospital' => 'Hospital-based',
                                'hotel' => 'Hotel-based',
                                'online' => 'Online',
                            ])
                            ->multiple(),
                            SelectFilter::make('division')
                            ->relationship('division', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('counties')
                            ->relationship('counties', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('partners')
                            ->relationship('partners', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('mentor')
                            ->relationship('mentor', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn(User $record): string => $record->full_name)
                            ->multiple()
                            ->preload(),
                            Filter::make('date_range')
                            ->form([
                                DatePicker::make('start_date')
                                ->label('From Date'),
                                DatePicker::make('end_date')
                                ->label('To Date'),
                            ])
                            ->query(function (Builder $query, array $data): Builder {
                                return $query
                                                ->when(
                                                        $data['start_date'],
                                                        fn(Builder $query, $date): Builder => $query->whereDate('start_date', '>=', $date),
                                                )
                                                ->when(
                                                        $data['end_date'],
                                                        fn(Builder $query, $date): Builder => $query->whereDate('end_date', '<=', $date),
                                                );
                            })
                            ->indicateUsing(function (array $data): array {
                                $indicators = [];
                                if ($data['start_date'] ?? null) {
                                    $indicators['start_date'] = 'From ' . Carbon::parse($data['start_date'])->toFormattedDateString();
                                }
                                if ($data['end_date'] ?? null) {
                                    $indicators['end_date'] = 'To ' . Carbon::parse($data['end_date'])->toFormattedDateString();
                                }
                                return $indicators;
                            }),
                        ])
                        ->actions([
                            ActionGroup::make([
                                Tables\Actions\ViewAction::make()
                                ->color('info'),
                                Tables\Actions\EditAction::make()
                                ->color('warning'),
                                Action::make('manage_participants')
                                ->label('Participants')
                                ->icon('heroicon-o-users')
                                ->color('success')
                                ->url(function ($record): string {
                                    if (!$record instanceof Training)
                                        return '#';
                                    return static::getUrl('participants', ['record' => $record]);
                                }),
                                Action::make('manage_assessments')
                                ->label('Assessments')
                                ->icon('heroicon-o-clipboard-document-check')
                                ->color('primary')
                                ->url(function ($record): string {
                                    if (!$record instanceof Training)
                                        return '#';
                                    return static::getUrl('assessments', ['record' => $record]);
                                })
                                ->visible(function ($record): bool {
                                    return $record instanceof Training && $record->hasAssessments();
                                }),
                                Action::make('duplicate')
                                ->label('Duplicate')
                                ->icon('heroicon-o-document-duplicate')
                                ->color('gray')
                                ->action(function ($record) {
                                    if (!$record instanceof Training)
                                        return;

                                    $newTraining = $record->replicate();
                                    $newTraining->title = $record->title . ' (Copy)';
                                    $newTraining->identifier = 'TRN-' . strtoupper(Str::random(8));
                                    $newTraining->type = 'global_training';
                                    $newTraining->mentor_id = auth()->id();
                                    $newTraining->save();

                                    // Copy relationships
                                    if ($record->counties && $record->counties->isNotEmpty()) {
                                        $newTraining->counties()->attach($record->counties->pluck('id'));
                                    }
                                    if ($record->partners && $record->partners->isNotEmpty()) {
                                        $newTraining->partners()->attach($record->partners->pluck('id'));
                                    }
                                    if ($record->hospitals && $record->hospitals->isNotEmpty()) {
                                        $newTraining->hospitals()->attach($record->hospitals->pluck('id'));
                                    }
                                    if ($record->hotels && $record->hotels->isNotEmpty()) {
                                        foreach ($record->hotels as $hotel) {
                                            $newTraining->hotels()->create([
                                                'hotel_name' => $hotel->hotel_name,
                                                'hotel_address' => $hotel->hotel_address,
                                                'hotel_contact' => $hotel->hotel_contact,
                                            ]);
                                        }
                                    }

                                    // Copy assessment categories if they exist
                                    if ($record->assessmentCategories && $record->assessmentCategories->isNotEmpty()) {
                                        $categoryData = [];
                                        foreach ($record->assessmentCategories as $category) {
                                            $categoryData[$category->id] = [
                                                'weight_percentage' => $category->pivot->weight_percentage,
                                                'pass_threshold' => $category->pivot->pass_threshold,
                                                'is_required' => $category->pivot->is_required,
                                                'order_sequence' => $category->pivot->order_sequence,
                                                'is_active' => $category->pivot->is_active,
                                            ];
                                        }
                                        $newTraining->assessmentCategories()->sync($categoryData);
                                    }

                                    return redirect()->to(static::getUrl('edit', ['record' => $newTraining]));
                                }),
                                Tables\Actions\DeleteAction::make()
                                ->requiresConfirmation()
                                ->visible(fn($record) => !$record->trashed()),
                                Tables\Actions\RestoreAction::make()
                                ->visible(fn($record) => $record->trashed()),
                                Tables\Actions\ForceDeleteAction::make()
                                ->visible(fn($record) => $record->trashed() && auth()->user()->can('force_delete_any_global::training'))
                                ->requiresConfirmation()
                                ->modalHeading('Permanently Delete Training')
                                ->modalDescription('This will permanently delete the training and ALL associated data (participants, assessments, etc.). This cannot be undone.')
                                ->modalSubmitActionLabel('Yes, permanently delete'),
                            ])
                            ->label('Actions')
                            ->icon('heroicon-m-ellipsis-horizontal')
                            ->size('sm')
                            ->color('gray')
                            ->button()
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make()
                                ->requiresConfirmation(),
                                Tables\Actions\RestoreBulkAction::make(),
                                Tables\Actions\ForceDeleteBulkAction::make()
                                ->visible(fn() => auth()->user()->can('force_delete_any_global::training')),
                            ]),
                        ])
                        ->defaultSort('start_date', 'desc')
                        ->poll('30s')
                        ->striped()
                        ->emptyStateHeading('No MOH Trainings Found')
                        ->emptyStateDescription('Create your first MOH training to get started.')
                        ->emptyStateIcon('heroicon-o-academic-cap')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Create MOH Training')
                            ->icon('heroicon-o-plus'),
        ]);
    }

    public static function getRelations(): array {
        return [];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListGlobalTrainings::route('/'),
            'create' => Pages\CreateGlobalTraining::route('/create'),
            'view' => Pages\ViewGlobalTraining::route('/{record}'),
            'edit' => Pages\EditGlobalTraining::route('/{record}/edit'),
            'participants' => Pages\ManageGlobalTrainingParticipants::route('/{record}/participants'),
            'assessments' => Pages\ManageGlobalTrainingAssessments::route('/{record}/assessments'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        $count = static::getModel()::where('type', 'global_training')
                // ->ongoing()
                ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'success';
    }

    public static function getGloballySearchableAttributes(): array {
        return ['title', 'identifier', 'description'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string {
        return $record->title;
    }

    public static function getGlobalSearchResultDetails(Model $record): array {
        return [
            'ID' => $record->identifier,
            'Area' => $record->approvedTrainingArea?->name,
            'Start Date' => $record->start_date?->format('M j, Y'),
        ];
    }
}
