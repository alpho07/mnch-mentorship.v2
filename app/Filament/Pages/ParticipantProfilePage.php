<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\TrainingParticipant;
use App\Models\ParticipantStatusLog;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Actions as InfolistActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;

class ParticipantProfilePage extends Page implements Tables\Contracts\HasTable, HasActions, HasForms {

    use Tables\Concerns\InteractsWithTable;
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Participant Profiles';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 50;
    protected static string $view = 'filament.pages.participant-profile-page';
    
    public $user = null;
    
      public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Table (Landing State)
    |--------------------------------------------------------------------------
    */
    public function table(Table $table): Table {
        return $table
            ->query(User::query()->with(['cadre', 'department', 'facility.subcounty.county']))
            ->columns([
                Tables\Columns\TextColumn::make('full_name')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->label('Phone')->searchable(),
                Tables\Columns\TextColumn::make('department.name')->label('Department')->sortable(),
                Tables\Columns\TextColumn::make('cadre.name')->label('Cadre')->sortable(),
                Tables\Columns\TextColumn::make('facility.code')->label('MFL Code'),
                Tables\Columns\TextColumn::make('facility.name')->label('Facility')->sortable(),
                Tables\Columns\TextColumn::make('facility.subcounty.name')->label('Subcounty')->sortable(),
                Tables\Columns\TextColumn::make('facility.subcounty.county.name')->label('County')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('county_id')
                    ->label('County')
                    ->options(\App\Models\County::orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(function ($query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('facility.subcounty.county', fn($q) => $q->where('id', $data['value']));
                        }
                    }),
                Tables\Filters\SelectFilter::make('subcounty_id')
                    ->label('Subcounty')
                    ->options(\App\Models\Subcounty::orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(function ($query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('facility.subcounty', fn($q) => $q->where('id', $data['value']));
                        }
                    }),
            ])
            ->actions([
                Action::make('view_profile')
                    ->label('View Profile')
                    ->icon('heroicon-o-eye')
                    ->action(fn(User $record) => $this->loadUser($record->id)),
            ])
            ->paginated(15);
    }

    /*
    |--------------------------------------------------------------------------
    | Load Profile
    |--------------------------------------------------------------------------
    */
    public function loadUser(int $id): void {
        $this->user = User::with([
            'cadre', 'department', 'facility.subcounty.county',
            'trainingParticipants.training', 'trainingParticipants.assessmentResults.assessmentCategory'
        ])->findOrFail($id);
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Card with Action Buttons
    |--------------------------------------------------------------------------
    */
    public function infolist(Infolist $infolist): Infolist {
        if (!$this->user) {
            return $infolist;
        }

        return $infolist
            ->record($this->user)
            ->schema([
                Section::make('Participant Profile')
                    ->schema([
                        // Profile Header with Avatar
                        Section::make()
                            ->schema([
                                \Filament\Infolists\Components\Split::make([
                                    \Filament\Infolists\Components\Grid::make(1)
                                        ->schema([
                                            \Filament\Infolists\Components\ImageEntry::make('avatar')
                                                ->label('')
                                                ->circular()
                                                ->size(120)
                                                ->defaultImageUrl(function ($record) {
                                                    return 'https://ui-avatars.com/api/?name=' . urlencode($record->full_name) . 
                                                           '&color=7F9CF5&background=EBF4FF&size=120';
                                                }),
                                            TextEntry::make('full_name')
                                                ->label('')
                                                ->size(TextEntry\TextEntrySize::Large)
                                                ->weight('bold')
                                                ->alignCenter(),
                                            TextEntry::make('cadre.name')
                                                ->label('')
                                                ->badge()
                                                ->color('primary')
                                                ->alignCenter(),
                                        ]),
                                    \Filament\Infolists\Components\Grid::make(2)
                                        ->schema([
                                            TextEntry::make('phone')->label('Phone')->icon('heroicon-m-phone'),
                                            TextEntry::make('email')->label('Email')->icon('heroicon-m-envelope'),
                                            TextEntry::make('department.name')->label('Department')->icon('heroicon-m-building-office'),
                                            TextEntry::make('facility.code')->label('MFL Code')->icon('heroicon-m-identification'),
                                            TextEntry::make('facility.name')->label('Facility')->icon('heroicon-m-building-office-2'),
                                            TextEntry::make('facility.subcounty.name')->label('Subcounty')->icon('heroicon-m-map-pin'),
                                            TextEntry::make('facility.subcounty.county.name')->label('County')->icon('heroicon-m-map'),
                                        ]),
                                ])
                                ->from('md'),
                            ]),

                        // Status Summary Sidebar
                        Section::make('Current Status Overview')
                            ->schema([
                                TextEntry::make('latest_training_status')
                                    ->label('Latest Training Status')
                                    ->formatStateUsing(fn() => $this->getLatestStatus('training')),
                                TextEntry::make('latest_mentorship_status')
                                    ->label('Latest Mentorship Status')
                                    ->formatStateUsing(fn() => $this->getLatestStatus('mentorship')),
                                TextEntry::make('total_trainings')
                                    ->label('Total Trainings')
                                    ->formatStateUsing(fn() => $this->user->trainingParticipants->where('training.type', 'global_training')->count()),
                                TextEntry::make('total_mentorships')
                                    ->label('Total Mentorships')
                                    ->formatStateUsing(fn() => $this->user->trainingParticipants->where('training.type', 'facility_mentorship')->count()),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */
    public function viewAssessments(int $participantId): void {
        $participant = TrainingParticipant::with(['assessmentResults.assessmentCategory', 'training'])->find($participantId);
        
        if (!$participant) {
            Notification::make()
                ->title('Participant not found')
                ->danger()
                ->send();
            return;
        }

        $this->mountAction('viewAssessmentsAction', ['participant' => $participant]);
    }

    public function updateStatus(int $participantId, string $type): void {
        $participant = TrainingParticipant::with('training')->find($participantId);
        
        if (!$participant) {
            Notification::make()
                ->title('Participant not found')
                ->danger()
                ->send();
            return;
        }

        $this->mountAction('updateStatusAction', ['participant' => $participant, 'type' => $type]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */
    private function getLatestStatus(string $type): string {
        if (!$this->user) {
            return 'No data available';
        }

        $participantIds = $this->user->trainingParticipants
            ->where('training.type', $type === 'training' ? 'global_training' : 'facility_mentorship')
            ->pluck('id');

        if ($participantIds->isEmpty()) {
            return 'No participations found';
        }

        $field = $type === 'training' ? 'training_participant_id' : 'mentorship_participant_id';
        
        $latest = ParticipantStatusLog::whereIn($field, $participantIds)
            ->where('status_type', 'overall_status')
            ->latest('recorded_at')
            ->first();

        return $latest?->new_value ?? 'No status recorded';
    }

    /*
    |--------------------------------------------------------------------------
    | Filament Actions
    |--------------------------------------------------------------------------
    */
    public function viewAssessmentsAction(): \Filament\Actions\Action {
        return \Filament\Actions\Action::make('viewAssessments')
            ->modalHeading(function (array $arguments = []) {
                $participantId = $arguments['participantId'] ?? null;
                if (!$participantId) return 'Assessment Results';
                
                $participant = TrainingParticipant::with('training')->find($participantId);
                return 'Assessment Results - ' . ($participant?->training?->title ?? 'Training');
            })
            ->modalContent(function (array $arguments = []) {
                $participantId = $arguments['participantId'] ?? null;
                if (!$participantId) {
                    return view('filament.modals.no-data', ['message' => 'No participant selected']);
                }
                
                $participant = TrainingParticipant::with(['assessmentResults.assessmentCategory', 'training'])
                    ->find($participantId);
                    
                if (!$participant) {
                    return view('filament.modals.no-data', ['message' => 'Participant not found']);
                }
                
                return view('filament.modals.assessments', ['participant' => $participant]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('5xl');
    }

    public function updateStatusAction(): \Filament\Actions\Action {
        return \Filament\Actions\Action::make('updateStatus')
            ->modalHeading(function (array $arguments = []) {
                $participantId = $arguments['participantId'] ?? null;
                if (!$participantId) return 'Update Status';
                
                $participant = TrainingParticipant::with('training')->find($participantId);
                return 'Update Status - ' . ($participant?->training?->title ?? 'Training');
            })
            ->form([
                \Filament\Forms\Components\Grid::make(2)
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('participant_name')
                            ->label('Participant')
                            ->default(fn() => $this->user?->full_name)
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('training_title')
                            ->label('Training/Mentorship')
                            ->default(function (\Filament\Forms\Get $get, array $arguments = []) {
                                $participantId = $arguments['participantId'] ?? null;
                                if (!$participantId) return 'N/A';
                                
                                $participant = TrainingParticipant::with('training')->find($participantId);
                                return $participant?->training?->title ?? 'N/A';
                            })
                            ->disabled(),
                    ]),
                \Filament\Forms\Components\Select::make('month_number')
                    ->label('Post-Training Period')
                    ->options([
                        3 => '3 Months Post-Training',
                        6 => '6 Months Post-Training', 
                        12 => '12 Months Post-Training',
                    ])
                    ->required(),
                \Filament\Forms\Components\Select::make('status_type')
                    ->label('Status Type')
                    ->options(ParticipantStatusLog::getStatusTypes())
                    ->required()
                    ->reactive(),
                \Filament\Forms\Components\TextInput::make('old_value')
                    ->label('Previous Value'),
                \Filament\Forms\Components\Select::make('new_value')
                    ->label('New Value')
                    ->options(function (callable $get) {
                        $statusType = $get('status_type');
                        return match($statusType) {
                            'overall_status' => ParticipantStatusLog::getOverallStatuses(),
                            'cadre_change' => \App\Models\Cadre::pluck('name', 'name')->toArray(),
                            'department_change' => \App\Models\Department::pluck('name', 'name')->toArray(),
                            'facility_change' => \App\Models\Facility::pluck('name', 'name')->toArray(),
                            default => []
                        };
                    })
                    ->searchable()
                    ->visible(fn(callable $get) => in_array($get('status_type'), ['overall_status', 'cadre_change', 'department_change', 'facility_change'])),
                \Filament\Forms\Components\TextInput::make('new_value_text')
                    ->label('New Value')
                    ->hidden(fn(callable $get) => in_array($get('status_type'), ['overall_status', 'cadre_change', 'department_change', 'facility_change'])),
                \Filament\Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ])
            ->action(function (array $data, array $arguments = []) {
                $participantId = $arguments['participantId'] ?? null;
                $type = $arguments['type'] ?? 'training';
                
                if (!$participantId) {
                    Notification::make()
                        ->title('Error: No participant selected')
                        ->danger()
                        ->send();
                    return;
                }
                
                $newValue = $data['new_value'] ?? $data['new_value_text'] ?? null;
                $field = $type === 'training' ? 'training_participant_id' : 'mentorship_participant_id';
                
                ParticipantStatusLog::create([
                    $field => $participantId,
                    'month_number' => $data['month_number'],
                    'status_type' => $data['status_type'],
                    'old_value' => $data['old_value'],
                    'new_value' => $newValue,
                    'notes' => $data['notes'],
                ]);

                $this->loadUser($this->user->id);
                
                Notification::make()
                    ->title('Status updated successfully')
                    ->success()
                    ->send();
            })
            ->modalWidth('3xl');
    }

    protected function getActions(): array
    {
        return [
            $this->viewAssessmentsAction(),
            $this->updateStatusAction(),
        ];
    }
}