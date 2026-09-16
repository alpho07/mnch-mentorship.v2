<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Models\Facility;
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
use League\Csv\Writer;

class ManageGlobalTrainingParticipants extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = GlobalTrainingResource::class;
    protected static string $view = 'filament.pages.participants-global';

    public Training $record;

    public function mount(int|string $record): void
    {
        $this->record = Training::where('type', 'global_training')->findOrFail($this->record->id);
    }

    public function getTitle(): string
    {
        return "Manage Participants - {$this->record->title}";
    }

    public function getViewData(): array
    {
        return ['record' => $this->record];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_participants')
                ->label('Add Participants')
                ->icon('heroicon-o-users')
                ->color('primary')
                ->form([
                    Section::make('Select Participants')
                        ->description('Search and select users to add as participants')
                        ->schema([
                            TextInput::make('search_users')
                                ->label('Search Users')
                                ->placeholder('Search by name, phone, or email...')
                                ->live(debounce: 300)
                                ->prefixIcon('heroicon-o-magnifying-glass'),

                            CheckboxList::make('selected_users')
                                ->label('Available Users')
                                ->options(function (Get $get) {
                                    $search = $get('search_users');
                                    $enrolledUserIds = TrainingParticipant::where('training_id', $this->record->id)
                                        ->pluck('user_id')->toArray();

                                    $query = User::query()
                                        ->whereNotIn('id', $enrolledUserIds)
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

                                    return $query->limit(100)->get()->mapWithKeys(function ($user) {
                                        $name = $user->name ?: trim("{$user->first_name} {$user->last_name}");
                                        $facility = $user->facility?->name ?: 'No facility';
                                        $phone = $user->phone ?: 'No phone';
                                        return [$user->id => "{$name} • {$facility} • {$phone}"];
                                    })->toArray();
                                })->default(function () {
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
                                                <h4 class="text-sm font-medium text-blue-800">How to add participants:</h4>
                                                <ul class="mt-1 text-sm text-blue-700 list-disc list-inside space-y-1">
                                                    <li>Search for users using the search box above</li>
                                                    <li>Select multiple users using checkboxes</li>
                                                    <li>Users already enrolled in this training are excluded</li>
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
                    $added = 0;

                    foreach ($selectedUsers as $userId) {
                        $exists = TrainingParticipant::where('training_id', $this->record->id)
                            ->where('user_id', $userId)->exists();

                        if (!$exists) {
                            TrainingParticipant::create([
                                'training_id' => $this->record->id,
                                'user_id' => $userId,
                                'registration_date' => now(),
                                'attendance_status' => 'registered',
                            ]);
                            $added++;
                        }
                    }

                    if ($added > 0) {
                        Notification::make()
                            ->title('Participants Added Successfully')
                            ->body("{$added} participant(s) enrolled in the training.")
                            ->success()->send();
                    } else {
                        Notification::make()
                            ->title('No New Participants Added')
                            ->body('Selected users may already be enrolled.')
                            ->warning()->send();
                    }
                })
                ->modalWidth('5xl')
                ->slideOver(),

            Actions\Action::make('quick_add_user')
                ->label('Quick Add New User')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->form([
                    Section::make('Create New User')
                        ->description('Add a completely new user and enroll them in this training')
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
                                                    <br>Use "Add Participants" to add existing users.
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
                                    ->disabled(fn (Get $get) => $get('phone_exists')),
                                TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->disabled(fn (Get $get) => $get('phone_exists')),
                            ]),

                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->disabled(fn (Get $get) => $get('phone_exists')),

                            Grid::make(3)->schema([
                                Select::make('facility_id')
                                    ->label('Facility')
                                    ->options(Facility::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled(fn (Get $get) => $get('phone_exists')),

                                Select::make('department_id')
                                    ->label('Department')
                                    ->options(Department::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (Get $get) => $get('phone_exists')),

                                Select::make('cadre_id')
                                    ->label('Cadre')
                                    ->options(Cadre::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (Get $get) => $get('phone_exists')),
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
                        'facility_id' => $data['facility_id'],
                        'department_id' => $data['department_id'] ?? null,
                        'cadre_id' => $data['cadre_id'] ?? null,
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
                        ->body("{$user->first_name} {$user->last_name} has been created and enrolled in the training.")
                        ->success()->send();
                }),

            Actions\Action::make('import_participants')
                ->label('Bulk Import')
                ->icon('heroicon-o-document-arrow-up')
                ->color('warning')
                ->form([
                    Section::make('Import Participants from CSV')
                        ->description('Upload a CSV file with participant details')
                        ->schema([
                            FileUpload::make('csv_file')
                                ->label('CSV File')
                                ->acceptedFileTypes(['text/csv', '.csv'])
                                ->required()
                                ->directory('temp-imports')
                                ->visibility('private')
                                ->helperText('Upload CSV with columns: first_name, last_name, phone, email, facility_name, department_name, cadre_name'),

                            Placeholder::make('import_help')
                                ->content(new HtmlString('
                                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                        <h4 class="font-medium text-amber-800 mb-2">CSV Format Requirements:</h4>
                                        <ul class="text-sm text-amber-700 space-y-1">
                                            <li>• Required: first_name, last_name, phone</li>
                                            <li>• Optional: email, facility_name, department_name, cadre_name</li>
                                            <li>• Phone format: +254700000000</li>
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

            Actions\Action::make('back_to_training')
                ->label('Back to Training')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => GlobalTrainingResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TrainingParticipant::query()->where('training_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('Participant Name')
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

                Tables\Columns\TextColumn::make('user.facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        return $column->getState();
                    }),

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

                Tables\Filters\SelectFilter::make('facility')
                    ->relationship('user.facility', 'name')
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
                                ->visible(fn (Get $get) => $get('attendance_status') === 'completed'),
                        ])
                        ->fillForm(fn (TrainingParticipant $record): array => [
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
                        ->modalHeading('Remove Participant')
                        ->modalDescription('Remove this participant from the training?'),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_attending')
                        ->label('Mark as Attending')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['attendance_status' => 'attending']));
                            Notification::make()->title('Status Updated')->success()->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Remove Selected'),
                ]),
            ])
            ->defaultSort('registration_date', 'desc')
            ->emptyStateHeading('No Participants Yet')
            ->emptyStateDescription('Start by adding participants to this training.')
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateActions([
                Tables\Actions\Action::make('add_first_participant')
                    ->label('Add First Participant')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->action(function () {
                        // Trigger the add participants action
                        $this->mountAction('add_participants');
                    }),
            ]);
    }

    private function processImport(string $filePath): void
    {
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

                    $facility = null;
                    if (!empty($record['facility_name'])) {
                        $facility = Facility::where('name', $record['facility_name'])->first();
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
                            'facility_id' => $facility?->id,
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

            $message = "Import completed: {$imported} participants added";
            if ($skipped > 0) $message .= ", {$skipped} skipped";

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
}