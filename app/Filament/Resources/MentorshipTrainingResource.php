<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\ProgramPicker;
use App\Filament\Resources\MentorshipResource\Pages;
use App\Models\ClassParticipant;
use App\Models\County;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class MentorshipTrainingResource extends Resource
{
    protected static ?string $model = Training::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Mentorships';

    protected static ?string $navigationGroup = 'Training Management';

    protected static ?string $slug = 'mentorship';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && static::userCanAccess(auth()->user());
    }

    public static function canAccess(): bool
    {
        return auth()->check() && static::userCanAccess(auth()->user());
    }

    private static function userCanAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Mentees excluded unless explicitly granted mentorship creation rights
        if ($user->hasRole('mentee')) {
            return $user->canCreateMentorships();
        }

        // Permission covers all authorised mentor/admin roles; flag covers edge cases
        return $user->can('view_any_mentorship::training') || $user->canCreateMentorships();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query scoping
    // ─────────────────────────────────────────────────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ])
            ->where('type', 'facility_mentorship')
            ->with(['facility', 'program', 'county', 'mentor']);

        $user = auth()->user();

        static::applyRoleScope($query, $user);

        // Non-super-admins always see only non-deleted records.
        // Super-admins rely on tab filters (Active / Trash) to control visibility.
        if (! $user->hasRole('super_admin')) {
            $query->whereNull('trainings.deleted_at');
        }

        return $query;
    }

    private static function applyRoleScope(Builder $query, User $user): void
    {
        if ($user->hasRole(['super_admin', 'admin', 'division', 'national_mentor', 'national_mentor_lead'])) {
            return;
        }

        if ($user->hasRole('county_mentor_lead')) {
            $countyIds = $user->counties()->pluck('counties.id')->toArray();
            if ($user->county_id) {
                $countyIds[] = $user->county_id;
            }
            $facilityIds = Facility::whereHas('subcounty',
                fn ($q) => $q->whereIn('county_id', array_unique($countyIds))
            )->pluck('id');
            $query->whereIn('facility_id', $facilityIds);

            return;
        }

        if ($user->hasRole('subcounty_mentor_lead')) {
            $subcountyIds = $user->subcounties()->pluck('subcounties.id');
            $facilityIds = Facility::whereIn('subcounty_id', $subcountyIds)->pluck('id');
            $query->whereIn('facility_id', $facilityIds);

            return;
        }

        if ($user->hasRole('facility_mentor_lead')) {
            $facilityIds = $user->facilities()->pluck('facilities.id')->toArray();
            if ($user->facility_id) {
                $facilityIds[] = $user->facility_id;
            }
            $query->whereIn('facility_id', array_unique($facilityIds));

            return;
        }

        $query->forMentorOrCoMentor($user->id);
    }

    public static function getNavigationLabel(): string
    {
        return 'Mentorship';
    }

    public static function getBreadcrumb(): string
    {
        return 'Mentorship';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Auto-set hidden fields — mentor is always the logged-in user
            Forms\Components\Hidden::make('type')->default('facility_mentorship'),
            Forms\Components\Hidden::make('mentor_id')->default(fn () => auth()->id()),

            // ── Run Type ──────────────────────────────────────────────
            Section::make('Run Type')
                ->description('Is this a real live mentorship or a pilot/test run?')
                ->icon('heroicon-o-beaker')
                ->schema([
                    Forms\Components\Radio::make('is_pilot')
                        ->label('')
                        ->options([
                            0 => 'Live Mentorship',
                            1 => 'Pilot Run',
                        ])
                        ->descriptions([
                            0 => 'Counts in dashboards, KPI badges, and analytics reports.',
                            1 => 'Excluded from all counts, badges, and analytics. Use for testing.',
                        ])
                        ->default(0)
                        ->required()
                        ->inline(false),
                ]),

            Section::make('Mentorship Preparation')
                ->description('Use these steps before starting classes or sending attendance links.')
                ->icon('heroicon-o-light-bulb')
                ->schema([
                    Forms\Components\Placeholder::make('preparation_note')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString('
                                    <div class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                                        <ol class="list-decimal space-y-1 pl-5">
                                            <li>Identify facility gaps first and decide what this mentorship should address.</li>
                                            <li>Review the mentorship manuals before choosing modules and sessions.</li>
                                            <li>Confirm every mentee has a name, email, phone, cadre, and department.</li>
                                        </ol>
                                        <div class="flex flex-wrap gap-2 pt-1">
                                            <a class="text-primary-600 hover:underline" href="'.url('/resources/infant-child-mentorship-manual').'" target="_blank" rel="noopener noreferrer">Infant and Child Mentorship Manual</a>
                                            <span class="text-gray-400">|</span>
                                            <a class="text-primary-600 hover:underline" href="https://mnchkenyamentorship.org/resources/newborn-mentorship-mentors-manual" target="_blank" rel="noopener noreferrer">Newborn Mentorship Mentor\'s Manual</a>
                                            <span class="text-gray-400">|</span>
                                            <a class="text-primary-600 hover:underline" href="'.url('/resources/emonc-mentorship-manual').'" target="_blank" rel="noopener noreferrer">EmONC Mentorship Manual</a>
                                            <span class="text-gray-400">|</span>
                                            <a class="text-primary-600 hover:underline" href="'.route('resources.search', ['q' => 'mentorship manual']).'" target="_blank" rel="noopener noreferrer">Search mentorship manuals</a>
                                            <span class="text-gray-400">|</span>
                                            <a class="text-primary-600 hover:underline" href="'.route('resources.search', ['q' => 'MNCH guidelines']).'" target="_blank" rel="noopener noreferrer">MNCH guidelines</a>
                                        </div>
                                    </div>
                                ')),
                ]),
            // ── Section 1: Location ───────────────────────────────────────
            Section::make('Location')
                ->description('Where is this mentorship being conducted?')
                ->icon('heroicon-o-map-pin')
                ->columns(2)
                ->schema([
                    Select::make('county_id')
                        ->label('County')
                        ->relationship('county', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('facility_id', null))
                        ->prefixIcon('heroicon-o-map')
                        ->helperText('Select the county first'),
                    Select::make('facility_id')
                        ->label('Facility')
                        ->options(function (Get $get) {
                            $countyId = $get('county_id');
                            if (! $countyId) {
                                return [];
                            }

                            return Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $countyId)
                            )->get()->mapWithKeys(fn ($f) => [
                                $f->id => "{$f->mfl_code} — {$f->name}",
                            ]);
                        })
                        ->searchable()
                        ->required()
                        ->disabled(fn (Get $get) => ! $get('county_id'))
                        ->prefixIcon('heroicon-o-building-office-2')
                        ->helperText('Facilities load after selecting a county'),
                ]),
            // ── Section 2: Program & Schedule ─────────────────────────────
            Section::make('Program & Schedule')
                ->description('What program is being mentored and when?')
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    ProgramPicker::make('program_id')
                        ->label('Mentorship Program')
                        ->helperText('Tap a programme card to select it.')
                        ->required()
                        ->validationMessages([
                            'required' => 'Please pick either the Infant and Child or Newborn programme card.',
                        ])
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            $program = $value ? Program::find($value) : null;

                            if ($program && ! $program->isSelectableBy(auth()->user())) {
                                $fail('That program is not active — pick a different one.');
                            }
                        })
                        ->columnSpanFull(),
                    Grid::make(3)->schema([
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required(fn (Get $get) => ! static::isEmonc($get('program_id')))
                            ->visible(fn (Get $get) => ! static::isEmonc($get('program_id')))
                            ->native(false)
                            ->minDate(today())
                            ->displayFormat('M j, Y')
                            ->prefixIcon('heroicon-o-play'),
                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->required(fn (Get $get) => ! static::isEmonc($get('program_id')))
                            ->visible(fn (Get $get) => ! static::isEmonc($get('program_id')))
                            ->native(false)
                            ->minDate(fn (Get $get) => $get('start_date') ?? now())
                            ->afterOrEqual('start_date')
                            ->displayFormat('M j, Y')
                            ->prefixIcon('heroicon-o-stop'),
                        TextInput::make('max_participants')
                            ->label('Number of Mentees')
                            ->numeric()
                            ->default(10)
                            ->minValue(2)
                            ->maxValue(10)
                            ->suffix('mentees')
                            ->prefixIcon('heroicon-o-users')
                            ->helperText('Must be between 2 and 10 mentees.'),
                    ]),
                ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Mentorship Code
                Tables\Columns\IconColumn::make('is_pilot')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->falseIcon(null)
                    ->trueColor('warning')
                    ->tooltip(fn ($record) => $record->is_pilot ? 'Pilot Run — excluded from dashboards' : null)
                    ->width('32px'),
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('Code copied')
                    ->default('—')
                    ->color('primary'),
                // Program (badge)
                Tables\Columns\TextColumn::make('program.name')
                    ->label('Program')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('success'),
                // Facility with MFL code + county as sub-description
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state, Training $record): string => $record->facility?->mfl_code ? "{$record->facility->mfl_code} — {$state}" : ($state ?? '—')
                    )
                    ->description(fn (Training $record): string => $record->county?->name ?? ''
                    ),
                // Mentor
                Tables\Columns\TextColumn::make('mentor.name')
                    ->label('Lead Mentor')
                    ->searchable()
                    ->description(fn (Training $record): string => $record->mentor?->cadre?->name ?? ''
                    )
                    ->toggleable(),
                // Dates — start with "to {end}" description
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Period')
                    ->date('M j, Y')
                    ->sortable()
                    ->description(fn (Training $record): string => $record->end_date ? 'to '.\Carbon\Carbon::parse($record->end_date)->format('M j, Y') : ''
                    ),
                // Active classes count (badge)
                Tables\Columns\TextColumn::make('active_classes')
                    ->label('Active Classes')
                    ->getStateUsing(fn (Training $r) => $r->mentorshipClasses()->count()
                    )
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->alignCenter(),
                // Mentees (distinct users across all classes)
                Tables\Columns\TextColumn::make('mentees_count')
                    ->label('Mentees')
                    ->getStateUsing(fn (Training $r) => ClassParticipant::whereHas('mentorshipClass',
                        fn ($q) => $q->where('training_id', $r->id)
                    )->distinct('user_id')->count('user_id')
                    )
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),
                // Status
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'gray',
                        'completed' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('county_id')
                    ->label('County')
                    ->searchable()
                    ->options(fn () => County::orderBy('name')->pluck('name', 'id')),
                Tables\Filters\Filter::make('facility_id')
                    ->label('Facility')
                    ->form([
                        Select::make('facility_id')
                            ->label('Facility')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Facility::where('name', 'like', "%{$search}%")
                                ->orWhere('mfl_code', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Facility $f) => [$f->id => "{$f->mfl_code} — {$f->name}"]))
                            ->getOptionLabelUsing(fn ($value): ?string => Facility::find($value)?->name),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['facility_id'] ?? null,
                        fn (Builder $q, $facilityId) => $q->where('facility_id', $facilityId)
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['facility_id'] ?? null)
                        ? 'Facility: '.Facility::find($data['facility_id'])?->name
                        : null),
                Tables\Filters\SelectFilter::make('program_id')
                    ->label('Program Area')
                    ->options(fn () => Program::withCount([
                        'trainings' => fn (Builder $q) => $q->where('type', 'facility_mentorship')
                            ->where('is_pilot', false),
                    ])
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Program $program) => [
                            $program->id => "{$program->name} ({$program->trainings_count})",
                        ])),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('manage_classes')
                        ->label('Manage Classes')
                        ->icon('heroicon-o-rectangle-stack')
                        ->color('primary')
                        ->url(fn (Training $r) => static::getUrl('classes', ['record' => $r->id]))
                        ->visible(fn (Training $r) => $r->deleted_at === null),
                    Tables\Actions\Action::make('co_mentors')
                        ->label('Co-Mentors')
                        ->icon('heroicon-o-user-group')
                        ->color('info')
                        ->url(fn (Training $r) => static::getUrl('co-mentors', ['record' => $r->id]))
                        ->visible(fn (Training $r) => $r->deleted_at === null),
                    Tables\Actions\Action::make('mentees')
                        ->label('Mentees')
                        ->icon('heroicon-o-users')
                        ->color('success')
                        ->url(fn (Training $r) => static::getUrl('mentees', ['record' => $r->id]))
                        ->visible(fn (Training $r) => $r->deleted_at === null),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Training $r) => $r->deleted_at === null),
                    Tables\Actions\ViewAction::make()
                        ->visible(fn (Training $r) => $r->deleted_at === null),

                    // ── Delete (all authorized users) ──────────────────────
                    Tables\Actions\Action::make('delete')
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->model(Training::class)
                        ->visible(fn (Training $r) => $r->deleted_at === null)
                        ->disabled(fn (Training $r) => ! $r->canBeDeleted())
                        ->tooltip(fn (Training $r) => ! $r->canBeDeleted()
                    ? (auth()->user()->hasRole('super_admin')
                        ? 'Has enrolled mentees — use Force Delete to override'
                        : 'Cannot delete — has enrolled mentees or active/completed classes')
                    : null
                        )
                        ->requiresConfirmation()
                        ->modalHeading(fn (Training $r) => "Delete {$r->identifier}?")
                        ->modalDescription(fn (Training $r) => new HtmlString(
                            '<p class="text-sm">This will move <strong>'.e($r->identifier).'</strong> to the trash. '.
                            (auth()->user()->hasRole('super_admin')
                                ? 'It can be restored from the <strong>Trash</strong> tab.'
                                : 'A Super Admin can restore it if needed.')
                            .'</p>'
                        ))
                        ->modalSubmitActionLabel('Yes, Delete')
                        ->action(function (Training $record) {
                            $record->deleted_by = auth()->id();
                            $record->save();
                            $record->delete();

                            Notification::make()
                                ->success()
                                ->title('Mentorship moved to trash')
                                ->body("{$record->identifier} has been deleted and can be restored by a Super Admin.")
                                ->send();
                        }),

                    // ── Force Delete (super_admin only, when blocked) ───────
                    Tables\Actions\Action::make('forceDelete')
                        ->label('Force Delete')
                        ->icon('heroicon-o-shield-exclamation')
                        ->color('danger')
                        ->model(Training::class)
                        ->visible(fn (Training $r) => $r->deleted_at === null &&
                            auth()->user()->can('force_delete_any_mentorship::training') &&
                            ! $r->canBeDeleted()
                        )
                        ->modalHeading('⚠️ Super Admin Override Delete')
                        ->modalDescription(fn (Training $r) => new HtmlString(
                            '<div class="space-y-2 text-sm">'.
                            '<p class="text-amber-600 font-semibold">This mentorship has enrolled mentees or active/completed classes.</p>'.
                            '<p>Deleting it will move it to trash. All associated records (classes, participants, progress) remain in the database. This action will be <strong>logged</strong>.</p>'.
                            '</div>'
                        ))
                        ->form([
                            Forms\Components\Textarea::make('deletion_reason')
                                ->label('Reason for override')
                                ->required()
                                ->rows(3)
                                ->placeholder('Explain why this mentorship must be deleted despite having active data...'),
                        ])
                        ->requiresConfirmation()
                        ->modalSubmitActionLabel('Force Delete')
                        ->action(function (Training $record, array $data) {
                            $menteesCount = ClassParticipant::whereHas(
                                'mentorshipClass', fn ($q) => $q->where('training_id', $record->id)
                            )->count();

                            $record->deleted_by = auth()->id();
                            $record->deletion_reason = $data['deletion_reason'];
                            $record->save();
                            $record->delete();

                            \Log::channel('daily')->warning('Super admin force-deleted mentorship', [
                                'training_id' => $record->id,
                                'identifier' => $record->identifier,
                                'deleted_by_id' => auth()->id(),
                                'deleted_by_name' => auth()->user()->name,
                                'reason' => $data['deletion_reason'],
                                'mentees_affected' => $menteesCount,
                                'timestamp' => now()->toIso8601String(),
                            ]);

                            Notification::make()
                                ->warning()
                                ->title('Mentorship force-deleted')
                                ->body("{$record->identifier} has been deleted. Action has been logged.")
                                ->send();
                        }),

                    // ── Restore (super_admin only, trash tab) ───────────────
                    Tables\Actions\Action::make('restore')
                        ->label('Restore')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->model(Training::class)
                        ->visible(fn (Training $r) => $r->deleted_at !== null &&
                            auth()->user()->can('restore_any_mentorship::training')
                        )
                        ->requiresConfirmation()
                        ->modalHeading(fn (Training $r) => "Restore {$r->identifier}?")
                        ->modalDescription('This will restore the mentorship and make it active again.')
                        ->modalSubmitActionLabel('Yes, Restore')
                        ->action(function (Training $record) {
                            $record->restore();

                            Notification::make()
                                ->success()
                                ->title('Mentorship restored')
                                ->body("{$record->identifier} is now active again.")
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Mentorships Yet')
            ->emptyStateDescription('Create your first mentorship program to get started.')
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->label('Create Mentorship'),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pages
    // ─────────────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMentorshipTrainings::route('/'),
            'create' => Pages\CreateMentorshipTraining::route('/create'),
            'guided-setup' => Pages\GuidedMentorshipSetup::route('/guided-setup'),
            'chat-setup' => Pages\ChatMentorshipSetup::route('/chat-setup'),
            'view' => Pages\ViewMentorshipTraining::route('/{record}'),
            'edit' => Pages\EditMentorshipTraining::route('/{record}/edit'),
            'classes' => Pages\ManageMentorshipClasses::route('/{record}/classes'),
            'mentees' => Pages\ManageMentorshipMentees::route('/{record}/mentees'),
            'co-mentors' => Pages\ManageMentorshipCoMentors::route('/{record}/co-mentors'),
            'class-modules' => Pages\ManageClassModules::route('/{training}/classes/{class}/modules'),
            'class-mentees' => Pages\ManageClassMentees::route('/{training}/classes/{class}/mentees'),
            'module-sessions' => Pages\ManageModuleSessions::route('/{training}/classes/{class}/modules/{module}/sessions'),
            'module-mentees' => Pages\ManageModuleMentees::route('/{training}/classes/{class}/modules/{module}/mentees'),
            'module-mentee-review' => Pages\ReviewModuleMentee::route('/{training}/classes/{class}/modules/{module}/mentees/{participant}/review'),
            'module-summary' => Pages\ModuleSummary::route('/{training}/classes/{class}/modules/{module}/summary'),
            'module-resources' => Pages\ManageModuleResources::route('/{training}/classes/{class}/modules/{module}/resources'),
            // 'mentee-dashboard' => Pages\MenteeDashboard::route('/mentee-dashboard'),
            'mentee-progress' => Pages\MenteeProgress::route('/{record}/participants/{participant}/progress'),
            'attendance-report' => Pages\AttendanceReportPage::route('/attendance-report'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Navigation badge
    // ─────────────────────────────────────────────────────────────────────────

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $query = static::getModel()::where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->whereNull('deleted_at');

        static::applyRoleScope($query, $user);

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private static function mentorOptionLabel(User $u): string
    {
        return implode(' · ', array_filter([
            $u->name ?? trim("{$u->first_name} {$u->last_name}"),
            $u->cadre?->name,
            $u->facility?->name,
        ]));
    }

    private static function buildDisplayName(Get $get): string
    {
        return trim(implode(' ', array_filter([
            $get('first_name'),
            $get('middle_name'),
            $get('last_name'),
        ])));
    }

    /**
     * Determine whether the selected program is the EmONC (Maternal Health) package.
     * Used to hide schedule fields that do not apply to EmONC mentorships.
     */
    private static function isEmonc(?int $programId): bool
    {
        if (! $programId) {
            return false;
        }

        $program = Program::find($programId);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }
}
