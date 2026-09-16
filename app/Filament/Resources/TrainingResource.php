<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingResource\Pages;
use App\Filament\Resources\TrainingResource\RelationManagers;
use App\Models\Training;
use App\Models\Program;
use App\Models\User;
use App\Models\Module;
use App\Models\Methodology;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Cadre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TrainingResource extends Resource
{
    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?string $navigationLabel = 'Trainings';
    protected static ?string $modelLabel = 'Training';
    protected static ?string $pluralModelLabel = 'Trainings';
    protected static ?int $navigationSort = 1;

       public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'global_training');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Wizard::make([
                // Wizard Step 1: Basic Training Setup
                Forms\Components\Wizard\Step::make('Training Setup')
                    ->description('Basic training information and scheduling')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Section::make('Training Information')
                            ->description('Enter the basic details for your global training program')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Training Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Advanced Clinical Skills Training Program')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                        if (!empty($state)) {
                                            $identifier = 'GT-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $state), 0, 6)) .
                                                         '-' . date('y') . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
                                            $set('identifier', $identifier);
                                        }
                                    })
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('programs')
                                    ->label('Training Programs')
                                    ->multiple()
                                    ->relationship('programs', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->helperText('Select one or more programs for this training')
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('description'),
                                    ])
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('organizer_id')
                                    ->label('Training Organizer')
                                    ->relationship('organizer', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->full_name . ' (' . $record->facility?->name . ')')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(auth()->id())
                                    ->helperText('Person responsible for organizing this training'),

                                Forms\Components\TextInput::make('location')
                                    ->label('Training Location')
                                    ->required()
                                    ->placeholder('e.g., National Training Center, Nairobi')
                                    ->helperText('Where the training will be conducted'),
                            ]),

                        Forms\Components\Section::make('Schedule & Approach')
                            ->description('Set the training dates and teaching approaches')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\DatePicker::make('start_date')
                                        ->label('Start Date')
                                        ->required()
                                        ->minDate(now())
                                        ->live(),

                                    Forms\Components\DatePicker::make('end_date')
                                        ->label('End Date')
                                        ->required()
                                        ->after('start_date'),
                                ]),

                                Forms\Components\Select::make('training_approaches')
                                    ->label('Training Approaches')
                                    ->multiple()
                                    ->options([
                                        'classroom' => 'Classroom Learning',
                                        'practical' => 'Practical Sessions',
                                        'simulation' => 'Simulation-based',
                                        'mentorship' => 'Mentorship',
                                        'peer_learning' => 'Peer Learning',
                                        'case_studies' => 'Case Studies',
                                        'workshops' => 'Workshops',
                                        'field_work' => 'Field Work',
                                    ])
                                    ->required()
                                    ->helperText('Select the teaching approaches for this training'),

                                Forms\Components\Hidden::make('identifier'),
                                Forms\Components\Hidden::make('type')->default('global_training'),
                                Forms\Components\Hidden::make('status')->default('draft'),
                            ]),
                    ]),

                // Wizard Step 2: Content & Methods
                Forms\Components\Wizard\Step::make('Content & Methods')
                    ->description('Select training modules and methodologies')
                    ->icon('heroicon-o-book-open')
                    ->schema([
                        Forms\Components\Section::make('Training Content')
                            ->description('Choose the specific modules and teaching methods')
                            ->schema([
                                Forms\Components\Select::make('modules')
                                    ->label('Training Modules')
                                    ->multiple()
                                    ->relationship('modules', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->options(function (Forms\Get $get) {
                                        $programIds = $get('programs');
                                        if (!$programIds) {
                                            return Module::pluck('name', 'id');
                                        }

                                        // Get unique modules from selected programs
                                        return Module::whereIn('program_id', $programIds)
                                            ->distinct()
                                            ->pluck('name', 'id');
                                    })
                                    ->helperText('Modules will be filtered based on selected programs')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('methodologies')
                                    ->label('Teaching Methodologies')
                                    ->multiple()
                                    ->relationship('methodologies', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Select the teaching and learning methods')
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('description'),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                // Wizard Step 3: Participants
                Forms\Components\Wizard\Step::make('Participants')
                    ->description('Add training participants from different facilities')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Forms\Components\Section::make('Training Participants')
                            ->description('Add participants who will attend this global training')
                            ->schema([
                                Forms\Components\Repeater::make('participants')
                                    ->label('Training Participants')
                                    ->schema([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('name')
                                                ->label('Full Name')
                                                ->required()
                                                ->placeholder('John Doe'),

                                            Forms\Components\TextInput::make('phone')
                                                ->label('Phone Number')
                                                ->required()
                                                ->tel()
                                                ->placeholder('+254712345678')
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get) {
                                                    if (!empty($state)) {
                                                        // Search for existing user by phone
                                                        $user = User::where('phone', $state)->first();
                                                        if ($user) {
                                                            $set('name', $user->full_name);
                                                            $set('email', $user->email);
                                                            $set('department', $user->department?->name);
                                                            $set('cadre', $user->cadre?->name);
                                                            $set('facility_name', $user->facility?->name);
                                                            $set('mfl_code', $user->facility?->mfl_code);

                                                            Notification::make()
                                                                ->title('User Found')
                                                                ->body("Found existing user: {$user->full_name}")
                                                                ->success()
                                                                ->send();
                                                        }
                                                    }
                                                }),
                                        ]),

                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('email')
                                                ->label('Email Address')
                                                ->email()
                                                ->placeholder('john@example.com'),

                                            Forms\Components\Grid::make(2)->schema([
                                                Forms\Components\TextInput::make('facility_name')
                                                    ->label('Facility Name')
                                                    ->required()
                                                    ->placeholder('District Hospital')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                        if (!empty($state)) {
                                                            $facility = Facility::where('name', 'like', "%{$state}%")->first();
                                                            if ($facility) {
                                                                $set('mfl_code', $facility->mfl_code);
                                                            }
                                                        }
                                                    }),

                                                Forms\Components\TextInput::make('mfl_code')
                                                    ->label('MFL Code')
                                                    ->placeholder('12345'),
                                            ]),
                                        ]),

                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('department')
                                                ->label('Department')
                                                ->required()
                                                ->placeholder('Clinical Services')
                                                ->datalist(Department::pluck('name')->toArray()),

                                            Forms\Components\TextInput::make('cadre')
                                                ->label('Cadre')
                                                ->required()
                                                ->placeholder('Nurse')
                                                ->datalist(Cadre::pluck('name')->toArray()),
                                        ]),
                                    ])
                                    ->addActionLabel('Add Participant')
                                    ->reorderableWithButtons()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                    ->columnSpanFull()
                                    ->minItems(1),
                            ]),
                    ]),

                // Wizard Step 4: Preview & Save
                Forms\Components\Wizard\Step::make('Preview & Save')
                    ->description('Review training details before saving')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        Forms\Components\Section::make('Training Summary')
                            ->description('Review all training details before saving')
                            ->schema([
                                Forms\Components\Placeholder::make('review_title')
                                    ->label('Training Title')
                                    ->content(fn (Forms\Get $get): string => $get('title') ?? 'Not set'),

                                Forms\Components\Placeholder::make('review_programs')
                                    ->label('Programs')
                                    ->content(function (Forms\Get $get): string {
                                        $programIds = $get('programs') ?? [];
                                        if (empty($programIds)) return 'None selected';

                                        $programs = Program::whereIn('id', $programIds)->pluck('name');
                                        return $programs->join(', ');
                                    }),

                                Forms\Components\Placeholder::make('review_schedule')
                                    ->label('Schedule')
                                    ->content(function (Forms\Get $get): string {
                                        $start = $get('start_date');
                                        $end = $get('end_date');
                                        if (!$start || !$end) return 'Not set';

                                        return "From {$start} to {$end}";
                                    }),

                                Forms\Components\Placeholder::make('review_location')
                                    ->label('Location')
                                    ->content(fn (Forms\Get $get): string => $get('location') ?? 'Not set'),

                                Forms\Components\Placeholder::make('review_participants')
                                    ->label('Participants')
                                    ->content(function (Forms\Get $get): string {
                                        $participants = $get('participants') ?? [];
                                        $count = count($participants);

                                        if ($count === 0) return 'No participants added';

                                        return "{$count} participant(s) registered";
                                    }),

                                Forms\Components\Placeholder::make('review_organizer')
                                    ->label('Organizer')
                                    ->content(function (Forms\Get $get): string {
                                        $organizerId = $get('organizer_id');
                                        if (!$organizerId) return 'Not set';

                                        $organizer = User::find($organizerId);
                                        return $organizer?->full_name ?? 'Unknown';
                                    }),
                            ])
                            ->columns(2),
                    ]),
            ])
            ->columnSpanFull()
            ->persistStepInQueryString(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Training Code')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->copyable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Training Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('programs_count')
                    ->label('Programs')
                    ->counts('programs')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('organizer.full_name')
                    ->label('Organizer')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable()
                    ->badge()
                    ->color(fn (Training $record): string => match(true) {
                        $record->start_date->isFuture() => 'warning',
                        $record->start_date->isToday() => 'success',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Participants')
                    ->counts('participants')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'info',
                        'ongoing' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'ongoing' => 'Ongoing',
                        'completed' => 'Completed',
                    ]),

                Tables\Filters\Filter::make('upcoming')
                    ->query(fn (Builder $query): Builder => $query->where('start_date', '>', now()))
                    ->label('Upcoming Trainings'),
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
            ->emptyStateHeading('No global trainings found')
            ->emptyStateDescription('Create your first multi-facility training program.')
            ->emptyStateIcon('heroicon-o-academic-cap');
    }

    public static function getRelations(): array
    {
        return [
            //RelationManagers\ParticipantsRelationManager::class,
            //RelationManagers\ObjectivesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainings::route('/'),
            'create' => Pages\CreateTraining::route('/create'),
            'view' => Pages\ViewTraining::route('/{record}'),
            'edit' => Pages\EditTraining::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('type', 'global_training')
            ->where('status', 'ongoing')
            ->count() ?: null;
    }
}
