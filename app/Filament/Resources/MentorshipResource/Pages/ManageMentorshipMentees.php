<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Models\Department;
use App\Models\Cadre;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ManageMentorshipMentees extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-mentees';
    public Training $record;

    public function mount(int|string $record): void {
        $this->record = Training::where('type', 'facility_mentorship')
                ->with(['facility', 'participants.user'])
                ->findOrFail($record);
    }

    public function getTitle(): string {
        return "Manage Mentees - {$this->record->title}";
    }

    public function getSubheading(): ?string {
        return "Facility: {$this->record->facility->name} • {$this->record->participants()->count()} mentees enrolled";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('add_mentees')
                    ->label('Add Mentees')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->form([
                        Section::make('Select Mentees from Facility')
                        ->description("Search and select users to add as mentees")
                        ->schema([
                            TextInput::make('search_users')
                            ->label('Search Users')
                            ->placeholder('Search by name, phone, or email...')
                            ->live(debounce: 300)
                            ->prefixIcon('heroicon-o-magnifying-glass'),
                            CheckboxList::make('selected_users')
                            ->label('Available Users from Facility')
                            ->options(function (Get $get) {
                                $search = $get('search_users');
                                $enrolledUserIds = TrainingParticipant::where('training_id', $this->record->id)
                                                ->pluck('user_id')->toArray();

                                $query = User::query()
                                       // ->where('facility_id', $this->record->facility_id) // Only from this facility
                                        ->where('status', 'active')
                                        ->with(['facility', 'department', 'cadre']);

                                if ($search) {
                                    $query->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                                ->orWhere('first_name', 'like', "%{$search}%")
                                                ->orWhere('last_name', 'like', "%{$search}%")
                                                ->orWhere('phone', 'like', "%{$search}%")
                                                ->orWhere('email', 'like', "%{$search}%");
                                    });
                                }

                                return $query->limit(100)->get()->mapWithKeys(function ($user) use ($enrolledUserIds) {
                                            $name = $user->name ?: trim("{$user->first_name} {$user->last_name}");
                                            $department = $user->department?->name ?: 'No department';
                                            $cadre = $user->cadre?->name ?: 'No cadre';
                                            $phone = $user->phone ?: 'No phone';

                                            // Add enrolled indicator to the label
                                            $label = "{$name} • {$department} • {$cadre} • {$phone}";
                                            if (in_array($user->id, $enrolledUserIds)) {
                                                $label .= " (Already Enrolled)";
                                            }

                                            return [$user->id => $label];
                                        })->toArray();
                            })
                            ->default(function () {
                                // Auto-select already enrolled users
                                return TrainingParticipant::where('training_id', $this->record->id)
                                                ->pluck('user_id')->toArray();
                            })
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(1)
                            ->gridDirection('row'),
                            Placeholder::make('help_text')
                            ->content(new HtmlString('
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-blue-800">Managing Mentees:</h4>
                                    <ul class="mt-1 text-sm text-blue-700 list-disc list-inside space-y-1">
                                        <li>Users from <strong>' . $this->record->facility->name . '</strong></li>
                                        <li>Already enrolled mentees are pre-selected (checked)</li>
                                        <li>Uncheck to remove, check to add new mentees</li>
                                        <li>Search for users using the search box above</li>
                                        <li>Can\'t find someone? Use "Quick Add New User" button</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    ')),
                        ])
                    ])
                    ->action(function (array $data) {
                        $selectedUsers = $data['selected_users'] ?? [];
                        $currentEnrolledIds = TrainingParticipant::where('training_id', $this->record->id)
                                        ->pluck('user_id')->toArray();

                        // Users to add (selected but not currently enrolled)
                        $usersToAdd = array_diff($selectedUsers, $currentEnrolledIds);

                        // Users to remove (currently enrolled but not selected)
                        $usersToRemove = array_diff($currentEnrolledIds, $selectedUsers);

                        $added = 0;
                        $removed = 0;

                        // Add new mentees
                        foreach ($usersToAdd as $userId) {
                            TrainingParticipant::create([
                                'training_id' => $this->record->id,
                                'user_id' => $userId,
                                'registration_date' => now(),
                                'attendance_status' => 'registered',
                            ]);
                            $added++;
                        }

                        // Remove unchecked mentees
                        foreach ($usersToRemove as $userId) {
                            TrainingParticipant::where('training_id', $this->record->id)
                                    ->where('user_id', $userId)
                                    ->delete();
                            $removed++;
                        }

                        // Build notification message
                        $messages = [];
                        if ($added > 0)
                            $messages[] = "{$added} mentee(s) added";
                        if ($removed > 0)
                            $messages[] = "{$removed} mentee(s) removed";

                        if (!empty($messages)) {
                            Notification::make()
                                    ->title('Mentees Updated Successfully')
                                    ->body(implode(', ', $messages) . '.')
                                    ->success()->send();
                        } else {
                            Notification::make()
                                    ->title('No Changes Made')
                                    ->body('Mentee enrollment remains unchanged.')
                                    ->info()->send();
                        }
                    })
                    ->modalWidth('5xl')
                    ->slideOver(),
                            
                    Actions\Action::make('quick_add_user')
                    ->label('Quick Add New User')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Section::make('Create New User at Facility')
                        ->description("Add a new user to {$this->record->facility->name} and enroll them in this mentorship")
                        ->schema([
                            TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            ->placeholder('+254700000000')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $user = User::where('phone', $state)->first();
                                    $set('phone_exists', $user !== null);
                                    if ($user) {
                                        $set('existing_user_name', $user->name ?: "{$user->first_name} {$user->last_name}");
                                    }
                                }
                            }),
                            Placeholder::make('phone_status')
                            ->content(function (Get $get): HtmlString {
                                if ($get('phone_exists')) {
                                    return new HtmlString('
                                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                                <div class="text-red-800 text-sm">
                                                    ❌ Phone number already exists for: <strong>' . $get('existing_user_name') . '</strong>
                                                    <br>Use "Add Mentees" to add existing users.
                                                </div>
                                            </div>
                                        ');
                                } elseif ($get('phone')) {
                                    return new HtmlString('
                                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                                <div class="text-green-800 text-sm">✅ Phone number available - continue filling details</div>
                                            </div>
                                        ');
                                }
                                return new HtmlString('');
                            }),
                            Forms\Components\Hidden::make('phone_exists'),
                            Forms\Components\Hidden::make('existing_user_name'),
                            Grid::make(2)->schema([
                                TextInput::make('first_name')
                                ->label('First Name')
                                ->required()
                                ->disabled(fn(Get $get) => $get('phone_exists')),
                                TextInput::make('last_name')
                                ->label('Last Name')
                                ->required()
                                ->disabled(fn(Get $get) => $get('phone_exists')),
                            ]),
                            TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->disabled(fn(Get $get) => $get('phone_exists')),
                            Grid::make(2)->schema([
                                Select::make('department_id')
                                ->label('Department')
                                ->options(Department::pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn(Get $get) => $get('phone_exists')),
                                Select::make('cadre_id')
                                ->label('Cadre')
                                ->options(Cadre::pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn(Get $get) => $get('phone_exists')),
                            ]),
                        ])
                    ])
                    ->action(function (array $data) {
                        if ($data['phone_exists']) {
                            Notification::make()
                                    ->title('User Already Exists')
                                    ->body('This phone number is already registered.')
                                    ->warning()->send();
                            return;
                        }

                        $user = User::create([
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'email' => $data['email'] ?? null,
                            'phone' => $data['phone'],
                            'facility_id' => $this->record->facility_id, // Auto-assign to training facility
                            'department_id' => $data['department_id'],
                            'cadre_id' => $data['cadre_id'],
                            'password' => bcrypt('password123'),
                            'status' => 'active',
                        ]);

                        TrainingParticipant::create([
                            'training_id' => $this->record->id,
                            'user_id' => $user->id,
                            'registration_date' => now(),
                            'attendance_status' => 'registered',
                        ]);

                        Notification::make()
                                ->title('User Created & Enrolled')
                                ->body("{$user->first_name} {$user->last_name} has been created and enrolled in the mentorship.")
                                ->success()->send();
                    }),
                    Actions\Action::make('import_mentees')
                    ->label('Bulk Import')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('warning')
                    ->form([
                        Section::make('Import Mentees from CSV')
                        ->description("Upload a CSV file with mentee details from {$this->record->facility->name}")
                        ->schema([
                            FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', '.csv'])
                            ->required()
                            ->directory('temp-imports')
                            ->visibility('private')
                            ->helperText('Upload CSV with columns: first_name, last_name, phone, email, department_name, cadre_name'),
                            Placeholder::make('import_help')
                            ->content(new HtmlString('
                                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                        <h4 class="font-medium text-amber-800 mb-2">CSV Format Requirements:</h4>
                                        <ul class="text-sm text-amber-700 space-y-1">
                                            <li>• Required: first_name, last_name, phone</li>
                                            <li>• Optional: email, department_name, cadre_name</li>
                                            <li>• Phone format: +254700000000</li>
                                            <li>• All users will be assigned to <strong>' . $this->record->facility->name . '</strong></li>
                                            <li>• Existing users will be updated</li>
                                            <li>• New users will be created automatically</li>
                                        </ul>
                                    </div>
                                ')),
                        ])
                    ])
                    ->action(function (array $data) {
                        $this->processImport($data['csv_file']);
                    }),
                    Actions\Action::make('back_to_mentorship')
                    ->label('Back to Mentorship')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(TrainingParticipant::query()->where('training_id', $this->record->id))
                        ->columns([
                            Tables\Columns\TextColumn::make('user.full_name')
                            ->label('Mentee Name')
                            ->formatStateUsing(function ($record): string {
                                $user = $record->user;
                                return $user->name ?: trim("{$user->first_name} {$user->last_name}") ?: 'No name';
                            })
                            ->searchable(['name', 'first_name', 'last_name'])
                            ->sortable()
                            ->weight('medium'),
                            Tables\Columns\TextColumn::make('user.phone')
                            ->label('Phone')
                            ->searchable()
                            ->copyable()
                            ->placeholder('No phone'),
                            Tables\Columns\TextColumn::make('user.email')
                            ->label('Email')
                            ->searchable()
                            ->copyable()
                            ->placeholder('No email'),
                            Tables\Columns\TextColumn::make('user.department.name')
                            ->label('Department')
                            ->badge()
                            ->color('info')
                            ->placeholder('No department'),
                            Tables\Columns\TextColumn::make('user.cadre.name')
                            ->label('Cadre')
                            ->badge()
                            ->color('success')
                            ->placeholder('No cadre'),
                            Tables\Columns\TextColumn::make('assessment_progress')
                            ->label('Assessment Progress')
                            ->getStateUsing(fn(TrainingParticipant $record): string =>
                                    $this->getAssessmentProgress($record)
                            )
                            ->badge()
                            ->color(fn(TrainingParticipant $record): string =>
                                    $this->getProgressColor($record)
                            ),
                            Tables\Columns\TextColumn::make('overall_score')
                            ->label('Overall Score')
                            ->getStateUsing(fn(TrainingParticipant $record): string =>
                                    $this->getOverallScore($record)
                            )
                            ->badge()
                            ->color(fn(TrainingParticipant $record): string =>
                                    $this->getScoreColor($record)
                            ),
                            Tables\Columns\BadgeColumn::make('attendance_status')
                            ->label('Status')
                            ->colors([
                                'secondary' => 'registered',
                                'warning' => 'attending',
                                'success' => 'completed',
                                'danger' => 'dropped',
                            ]),
                            Tables\Columns\TextColumn::make('registration_date')
                            ->label('Enrolled')
                            ->date('M j, Y')
                            ->sortable(),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('attendance_status')
                            ->options([
                                'registered' => 'Registered',
                                'attending' => 'Attending',
                                'completed' => 'Completed',
                                'dropped' => 'Dropped',
                            ]),
                            Tables\Filters\SelectFilter::make('department')
                            ->relationship('user.department', 'name')
                            ->multiple()
                            ->preload(),
                            Tables\Filters\SelectFilter::make('cadre')
                            ->relationship('user.cadre', 'name')
                            ->multiple()
                            ->preload(),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('update_status')
                                ->label('Update Status')
                                ->icon('heroicon-o-pencil')
                                ->color('warning')
                                ->form([
                                    Select::make('attendance_status')
                                    ->options([
                                        'registered' => 'Registered',
                                        'attending' => 'Attending',
                                        'completed' => 'Completed',
                                        'dropped' => 'Dropped',
                                    ])
                                    ->required(),
                                    Forms\Components\DatePicker::make('completion_date')
                                    ->label('Completion Date')
                                    ->visible(fn(Get $get) => $get('attendance_status') === 'completed'),
                                ])
                                ->fillForm(fn(TrainingParticipant $record): array => [
                                    'attendance_status' => $record->attendance_status,
                                    'completion_date' => $record->completion_date,
                                        ])
                                ->action(function (TrainingParticipant $record, array $data): void {
                                    $record->update($data);
                                    Notification::make()
                                            ->title('Status Updated')
                                            ->success()->send();
                                }),
                                Tables\Actions\DeleteAction::make()
                                ->label('Remove')
                                ->modalHeading('Remove Mentee')
                                ->modalDescription('Remove this mentee from the mentorship program?'),
                            ])
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\BulkAction::make('mark_attending')
                                ->label('Mark as Attending')
                                ->icon('heroicon-o-check')
                                ->color('success')
                                ->action(function ($records) {
                                    $records->each(fn($record) => $record->update(['attendance_status' => 'attending']));
                                    Notification::make()->title('Status Updated')->success()->send();
                                }),
                                Tables\Actions\DeleteBulkAction::make()
                                ->label('Remove Selected'),
                            ]),
                        ])
                        ->defaultSort('registration_date', 'desc')
                        ->emptyStateHeading('No Mentees Yet')
                        ->emptyStateDescription('Start by adding mentees from your facility to this mentorship program.')
                        ->emptyStateIcon('heroicon-o-users')
                        ->emptyStateActions([
                            Tables\Actions\Action::make('add_first_mentee')
                            ->label('Add First Mentee')
                            ->icon('heroicon-o-plus')
                            ->button()
                            ->action(function () {
                                // Trigger the add mentees action
                                $this->mountAction('add_mentees');
                            }),
        ]);
    }

    private function processImport(string $filePath): void {
        try {
            $fullPath = Storage::disk('local')->path($filePath);
            $csv = Reader::createFromPath($fullPath, 'r');
            $csv->setHeaderOffset(0);

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($csv->getRecords() as $index => $record) {
                $rowNumber = $index + 2;

                try {
                    if (empty($record['first_name']) || empty($record['phone'])) {
                        $errors[] = "Row {$rowNumber}: First name and phone required";
                        $skipped++;
                        continue;
                    }

                    $department = null;
                    if (!empty($record['department_name'])) {
                        $department = Department::where('name', $record['department_name'])->first();
                    }

                    $cadre = null;
                    if (!empty($record['cadre_name'])) {
                        $cadre = Cadre::where('name', $record['cadre_name'])->first();
                    }

                    $user = User::updateOrCreate(
                            ['phone' => $record['phone']],
                            [
                                'first_name' => $record['first_name'],
                                'last_name' => $record['last_name'] ?? '',
                                'email' => $record['email'] ?? null,
                                'facility_id' => $this->record->facility_id, // Always assign to training facility
                                'department_id' => $department?->id,
                                'cadre_id' => $cadre?->id,
                                'password' => bcrypt('password123'),
                                'status' => 'active',
                            ]
                    );

                    if (!TrainingParticipant::where('training_id', $this->record->id)
                                    ->where('user_id', $user->id)->exists()) {

                        TrainingParticipant::create([
                            'training_id' => $this->record->id,
                            'user_id' => $user->id,
                            'registration_date' => now(),
                            'attendance_status' => 'registered',
                        ]);
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: {$e->getMessage()}";
                    $skipped++;
                }
            }

            Storage::disk('local')->delete($filePath);

            $message = "Import completed: {$imported} mentees added";
            if ($skipped > 0)
                $message .= ", {$skipped} skipped";

            Notification::make()
                    ->title('Import Complete')
                    ->body($message)
                    ->success()
                    ->send();
        } catch (\Exception $e) {
            Storage::disk('local')->delete($filePath);
            Notification::make()
                    ->title('Import Failed')
                    ->body("Error: {$e->getMessage()}")
                    ->danger()
                    ->send();
        }
    }

    // Helper Methods
    private function getAssessmentProgress(TrainingParticipant $record): string {
        $totalCategories = $this->record->assessmentCategories()->count();
        $completedCategories = $record->assessmentResults()
                ->whereIn('assessment_category_id', $this->record->assessmentCategories->pluck('id'))
                ->distinct('assessment_category_id')
                ->count();

        $percentage = $totalCategories > 0 ? round(($completedCategories / $totalCategories) * 100) : 0;
        return "{$completedCategories}/{$totalCategories} ({$percentage}%)";
    }

    private function getOverallScore(TrainingParticipant $record): string {
        $calculation = $this->record->calculateOverallScore($record);

        if (!$calculation['all_assessed']) {
            return 'Not assessed';
        }

        return $calculation['score'] . '%';
    }

    private function getProgressColor(TrainingParticipant $record): string {
        $totalCategories = $this->record->assessmentCategories()->count();
        $completedCategories = $record->assessmentResults()
                ->whereIn('assessment_category_id', $this->record->assessmentCategories->pluck('id'))
                ->distinct('assessment_category_id')
                ->count();

        if ($totalCategories == 0)
            return 'gray';
        $percentage = ($completedCategories / $totalCategories) * 100;

        if ($percentage == 100)
            return 'success';
        if ($percentage > 0)
            return 'warning';
        return 'danger';
    }

    private function getScoreColor(TrainingParticipant $record): string {
        $calculation = $this->record->calculateOverallScore($record);

        if (!$calculation['all_assessed'])
            return 'gray';

        $score = $calculation['score'];
        if ($score >= 80)
            return 'success';
        if ($score >= 70)
            return 'warning';
        return 'danger';
    }
}
