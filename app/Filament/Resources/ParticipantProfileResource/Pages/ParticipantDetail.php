<?php

namespace App\Filament\Resources\ParticipantProfileResource\Pages;

use App\Filament\Resources\ParticipantProfileResource;
use App\Models\County;
use App\Models\Training;
use App\Models\Facility;
use App\Models\TrainingParticipant;
use App\Models\ParticipantStatusLog;
use App\Models\MenteeStatus;
use App\Models\Department;
use App\Models\Cadre;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Grid;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid as InfoGrid;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Notifications\Notification;

class ParticipantDetail extends Page implements HasInfolists, HasTable {

    use InteractsWithInfolists,
        InteractsWithTable;

    protected static string $resource = ParticipantProfileResource::class;
    protected static string $view = 'filament.pages.participant-detail';
    public County $county;
    public Training $training;
    public Facility $facility;
    public TrainingParticipant $participant;

    public function mount($county, $training, $facility, $participant): void {
        $this->county = County::findOrFail($this->county->id);
        $this->training = Training::findOrFail($this->training->id);
        $this->facility = Facility::findOrFail($this->facility->id);
        $this->participant = TrainingParticipant::with([
                    'user.facility', 'user.department', 'user.cadre',
                    'assessmentResults.assessmentCategory',
                    'statusLogs'
                ])->findOrFail($this->participant->id);
    }

    public function getTitle(): string {
        return "Participant Profile - {$this->participant->user->full_name}";
    }

    public function getSubheading(): ?string {
        return "Training: {$this->training->title} | Facility: {$this->facility->name}";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('update_3_months')
                    ->label('3 Months Post-Training')
                    ->icon('heroicon-o-calendar')
                    ->color('warning')
                    ->form($this->getStatusUpdateForm())
                    ->action(fn(array $data) => $this->updateStatus($data, 3)),
                    Actions\Action::make('update_6_months')
                    ->label('6 Months Post-Training')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->form($this->getStatusUpdateForm())
                    ->action(fn(array $data) => $this->updateStatus($data, 6)),
                    Actions\Action::make('update_12_months')
                    ->label('12 Months Post-Training')
                    ->icon('heroicon-o-calendar')
                    ->color('success')
                    ->form($this->getStatusUpdateForm())
                    ->action(fn(array $data) => $this->updateStatus($data, 12)),
                    Actions\Action::make('back')
                    ->label('Back to Participants')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(ParticipantProfileResource::getUrl(
                                    'facility-participants',
                                    [
                                        'county' => $this->county->id,
                                        'training' => $this->training->id,
                                        'facility' => $this->facility->id
                                    ]
                            )),
        ];
    }

    public function participantInfolist(Infolist $infolist): Infolist {
        return $infolist
                        ->record($this->participant)
                        ->schema([
                            Section::make('Participant Information')
                            ->schema([
                                InfoGrid::make(3)
                                ->schema([
                                    ImageEntry::make('user.avatar')
                                    ->label('Avatar')
                                    ->circular()
                                    ->defaultImageUrl(fn($record) =>
                                            'https://ui-avatars.com/api/?name=' .
                                            urlencode($record->user->full_name) .
                                            '&background=0D8ABC&color=fff'
                                    ),
                                    TextEntry::make('user.full_name')
                                    ->label('Full Name')
                                    ->weight('bold')
                                    ->size('lg'),
                                    TextEntry::make('user.phone')
                                    ->label('Phone Number')
                                    ->copyable(),
                                ]),
                                InfoGrid::make(4)
                                ->schema([
                                    TextEntry::make('user.facility.name')
                                    ->label('Current Facility'),
                                    TextEntry::make('user.department.name')
                                    ->label('Current Department'),
                                    TextEntry::make('user.cadre.name')
                                    ->label('Current Cadre'),
                                    TextEntry::make('current_status')
                                    ->label('Overall Status')
                                    ->getStateUsing(fn($record) =>
                                            $record->statusLogs()->latest()->first()?->new_value ?? 'Active'
                                    )
                                    ->badge()
                                    ->color('success'),
                                ]),
                            ]),
                            /*Section::make('Training Information')
                            ->schema([
                                InfoGrid::make(4)
                                ->schema([
                                    TextEntry::make('registration_date')
                                    ->label('Registration Date')
                                    ->date('M j, Y'),
                                    TextEntry::make('attendance_status')
                                    ->label('Attendance Status')
                                    ->badge()
                                    ->colors([
                                        'secondary' => 'registered',
                                        'warning' => 'attending',
                                        'success' => 'completed',
                                        'danger' => 'dropped',
                                    ]),
                                    TextEntry::make('completion_status')
                                    ->label('Completion Status')
                                    ->badge(),
                                    TextEntry::make('completion_date')
                                    ->label('Completion Date')
                                    ->date('M j, Y')
                                    ->placeholder('Not completed'),
                                ]),
                            ]),*/
                            Section::make('Assessment Results')
                            ->schema([
                                TextEntry::make('assessment_summary')
                                ->label('Assessment Summary')
                                ->getStateUsing(function ($record) {
                                    $calculation = $this->training->calculateOverallScore($record);
                                    return "Status: {$calculation['status']} | Score: {$calculation['score']}% | Categories: {$calculation['assessed_categories']}/{$calculation['total_categories']}";
                                })
                                ->badge()
                                ->color(function ($record) {
                                    $status = $this->training->calculateOverallScore($record)['status'];
                                    return match ($status) {
                                        'PASSED' => 'success',
                                        'FAILED' => 'danger',
                                        'INCOMPLETE' => 'warning',
                                        default => 'gray'
                                    };
                                }),
                            ])
                            ->collapsible(),
        ]);
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                TrainingParticipant::query()
                                ->where('user_id', $this->participant->user_id)
                                ->with(['training', 'assessmentResults'])
                                ->latest('registration_date')
                        )
                        ->heading('Other Training Programs Attended')
                        ->columns([
                            TextColumn::make('training.title')
                            ->label('Training Program')
                            ->wrap()
                            ->searchable(),
                            TextColumn::make('training.type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn($state) =>
                                    $state === 'global_training' ? 'MOH Training' : 'Facility Mentorship'
                            )
                            ->colors([
                                'primary' => 'global_training',
                                'success' => 'facility_mentorship'
                            ]),
                            TextColumn::make('registration_date')
                            ->label('Year')
                            ->date('Y')
                            ->sortable(),
                            /*TextColumn::make('completion_status')
                            ->label('Status')
                            ->badge()
                            ->colors([
                                'warning' => 'in_progress',
                                'success' => 'completed',
                                'danger' => 'dropped'
                            ]),*/
                            TextColumn::make('assessment_score')
                            ->label('Score')
                            ->getStateUsing(function ($record) {
                                $calculation = $record->training->calculateOverallScore($record);
                                return $calculation['all_assessed'] ? $calculation['score'] . '%' : 'N/A';
                            })
                            ->badge()
                            ->color('primary'),
                        ])
                        ->actions([
                            Tables\Actions\Action::make('view_assessment')
                            ->label('View Assessment')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->color('primary')
                            ->visible(fn($record) => $record->assessmentResults->isNotEmpty())
                            ->action(function ($record) {
                                $this->showAssessmentDetails($record);
                            }),
                        ])
                        ->emptyStateHeading('No Other Training Programs')
                        ->emptyStateDescription('This participant has not attended any other training programs.');
    }

    private function getStatusUpdateForm(): array {
        return [
                    Grid::make(2)
                    ->schema([
                        Select::make('status_type')
                        ->label('What to Update')
                        ->options([
                            ParticipantStatusLog::STATUS_TYPE_OVERALL => 'Overall Status',
                            ParticipantStatusLog::STATUS_TYPE_CADRE => 'Cadre Change',
                            ParticipantStatusLog::STATUS_TYPE_DEPARTMENT => 'Department Change',
                            ParticipantStatusLog::STATUS_TYPE_FACILITY => 'Facility Change',
                        ])
                        ->required()
                        ->live(),
                        Select::make('new_value')
                        ->label('New Value')
                        ->options(function (Forms\Get $get) {
                            return match ($get('status_type')) {
                                ParticipantStatusLog::STATUS_TYPE_OVERALL => MenteeStatus::where('is_active', true)->pluck('name', 'name'),
                                ParticipantStatusLog::STATUS_TYPE_CADRE => Cadre::pluck('name', 'name'),
                                ParticipantStatusLog::STATUS_TYPE_DEPARTMENT => Department::pluck('name', 'name'),
                                ParticipantStatusLog::STATUS_TYPE_FACILITY => Facility::pluck('name', 'name'),
                                default => []
                            };
                        })
                        ->required()
                        ->searchable(),
                    ]),
                    Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('Add any relevant notes about this status change...')
                    ->rows(3),
        ];
    }

    private function updateStatus(array $data, int $monthNumber): void {
        // Get current value based on status type
        $oldValue = match ($data['status_type']) {
            ParticipantStatusLog::STATUS_TYPE_OVERALL => $this->participant->statusLogs()->latest()->first()?->new_value ?? 'Active',
            ParticipantStatusLog::STATUS_TYPE_CADRE => $this->participant->user->cadre?->name,
            ParticipantStatusLog::STATUS_TYPE_DEPARTMENT => $this->participant->user->department?->name,
            ParticipantStatusLog::STATUS_TYPE_FACILITY => $this->participant->user->facility?->name,
            default => null
        };

        // Create status log entry
        ParticipantStatusLog::create([
            'training_participant_id' => $this->participant->id,
            'month_number' => $monthNumber,
            'status_type' => $data['status_type'],
            'old_value' => $oldValue,
            'new_value' => $data['new_value'],
            'notes' => $data['notes'] ?? null,
            'recorded_by' => auth()->id(),
            'recorded_at' => now(),
        ]);

        // Update user record if applicable
        if ($data['status_type'] === ParticipantStatusLog::STATUS_TYPE_CADRE) {
            $cadre = Cadre::where('name', $data['new_value'])->first();
            if ($cadre) {
                $this->participant->user->update(['cadre_id' => $cadre->id]);
            }
        } elseif ($data['status_type'] === ParticipantStatusLog::STATUS_TYPE_DEPARTMENT) {
            $department = Department::where('name', $data['new_value'])->first();
            if ($department) {
                $this->participant->user->update(['department_id' => $department->id]);
            }
        } elseif ($data['status_type'] === ParticipantStatusLog::STATUS_TYPE_FACILITY) {
            $facility = Facility::where('name', $data['new_value'])->first();
            if ($facility) {
                $this->participant->user->update(['facility_id' => $facility->id]);
            }
        }

        Notification::make()
                ->title('Status Updated Successfully')
                ->body("Updated {$data['status_type']} for {$monthNumber} months post-training")
                ->success()
                ->send();
    }

    private function showAssessmentDetails($record): void {
        $calculation = $record->training->calculateOverallScore($record);
        $assessmentDetails = $record->assessmentResults->map(function ($result) {
                    return "{$result->assessmentCategory->name}: {$result->result} ({$result->category_weight}%)";
                })->join(', ');

        Notification::make()
                ->title("Assessment Results - {$record->training->title}")
                ->body("Overall: {$calculation['status']} ({$calculation['score']}%) | Details: {$assessmentDetails}")
                ->info()
                ->duration(10000)
                ->send();
    }

    public function getStatusLogsProperty() {
        return $this->participant->statusLogs()
                        ->with('recorder')
                        ->orderBy('month_number')
                        ->orderBy('created_at', 'desc')
                        ->get()
                        ->groupBy('month_number');
    }
}
