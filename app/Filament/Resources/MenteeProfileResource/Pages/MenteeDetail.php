<?php

namespace App\Filament\Resources\MenteeProfileResource\Pages;

use App\Filament\Resources\MenteeProfileResource;
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

class MenteeDetail extends Page implements HasInfolists, HasTable
{
    use InteractsWithInfolists, InteractsWithTable;

    protected static string $resource = MenteeProfileResource::class;
    protected static string $view = 'filament.pages.mentee-detail';

    public County $county;
    public Facility $facility;
    public Training $mentorship;
    public TrainingParticipant $mentee;

    public function mount( $county,  $facility,  $mentorship,  $mentee): void
    {
        $this->county = County::findOrFail($this->county->id);
        $this->facility = Facility::findOrFail($this->facility->id);
        $this->mentorship = Training::findOrFail($this->mentorship->id);
        $this->mentee = TrainingParticipant::with([
            'user.facility', 'user.department', 'user.cadre',
            'assessmentResults.assessmentCategory',
            'mentorshipStatusLogs'
        ])->findOrFail( $this->mentee->id);
    }

    public function getTitle(): string
    {
        return "Mentee Profile - {$this->mentee->user->full_name}";
    }

    public function getSubheading(): ?string
    {
        return "Mentorship: {$this->mentorship->title} | Facility: {$this->facility->name}";
    }

    protected function getHeaderActions(): array
    {
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
                ->label('Back to Mentees')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MenteeProfileResource::getUrl(
                    'mentorship-mentees',
                    [
                        'county' => $this->county->id,
                        'facility' => $this->facility->id,
                        'mentorship' => $this->mentorship->id
                    ]
                )),
        ];
    }

    public function menteeInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->mentee)
            ->schema([
                Section::make('Mentee Information')
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
                                        $record->mentorshipStatusLogs()->latest()->first()?->new_value ?? 'Active'
                                    )
                                    ->badge()
                                    ->color('success'),
                            ]),
                    ]),

                /*Section::make('Mentorship Information')
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
                                $calculation = $this->mentorship->calculateOverallScore($record);
                                $details = collect();
                                
                                foreach ($record->assessmentResults as $result) {
                                    $details->push("{$result->assessmentCategory->name}: {$result->result}");
                                }
                                
                                return "Overall: {$calculation['status']} ({$calculation['score']}%) | " . $details->join(', ');
                            })
                            ->columnSpanFull(),

                        InfoGrid::make(2)
                            ->schema(
                                $this->mentee->assessmentResults->map(function ($result) {
                                    return TextEntry::make("assessment_{$result->assessment_category_id}")
                                        ->label($result->assessmentCategory->name)
                                        ->getStateUsing(fn() => strtoupper($result->result))
                                        ->badge()
                                        ->color($result->result === 'pass' ? 'success' : 'danger');
                                })->toArray()
                            ),
                    ])
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
          return $table
            ->query(
                TrainingParticipant::query()
                    ->where('training_id', $this->mentorship->id)
                    ->whereHas('user', function ($query) {
                        $query->where('facility_id', $this->facility->id);
                    })
                    ->with(['user.department', 'user.cadre', 'assessmentResults'])
            )
            ->heading('Other Training Programs & Mentorships Attended')
            ->columns([
                TextColumn::make('training.title')
                    ->label('Program Name')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('training.type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn($state) => 
                        $state === 'global_training' ? 'Global Training' : 'Facility Mentorship'
                    )
                    ->colors([
                        'primary' => 'global_training',
                        'success' => 'facility_mentorship'
                    ]),

                TextColumn::make('training.facility.name')
                    ->label('Facility')
                    ->limit(30)
                    ->placeholder('Multiple facilities'),

                TextColumn::make('registration_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('completion_status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'dropped'
                    ]),

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
                        $this->showDetailedAssessmentResults($record);
                    }),
            ])
            ->emptyStateHeading('No Other Programs Attended')
            ->emptyStateDescription('This mentee has not attended any other training programs or mentorships.');
    }

    private function getStatusUpdateForm(): array
    {
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

    private function updateStatus(array $data, int $monthNumber): void
    {
        // Get current value based on status type
        $oldValue = match ($data['status_type']) {
            ParticipantStatusLog::STATUS_TYPE_OVERALL => $this->mentee->mentorshipStatusLogs()->latest()->first()?->new_value ?? 'Active',
            ParticipantStatusLog::STATUS_TYPE_CADRE => $this->mentee->user->cadre?->name,
            ParticipantStatusLog::STATUS_TYPE_DEPARTMENT => $this->mentee->user->department?->name,
            ParticipantStatusLog::STATUS_TYPE_FACILITY => $this->mentee->user->facility?->name,
            default => null
        };

        // Create status log entry for mentorship
        ParticipantStatusLog::create([
            'mentorship_participant_id' => $this->mentee->id,
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
                $this->mentee->user->update(['cadre_id' => $cadre->id]);
            }
        } elseif ($data['status_type'] === ParticipantStatusLog::STATUS_TYPE_DEPARTMENT) {
            $department = Department::where('name', $data['new_value'])->first();
            if ($department) {
                $this->mentee->user->update(['department_id' => $department->id]);
            }
        } elseif ($data['status_type'] === ParticipantStatusLog::STATUS_TYPE_FACILITY) {
            $facility = Facility::where('name', $data['new_value'])->first();
            if ($facility) {
                $this->mentee->user->update(['facility_id' => $facility->id]);
            }
        }

        Notification::make()
            ->title('Status Updated Successfully')
            ->body("Updated {$data['status_type']} for {$monthNumber} months post-mentorship")
            ->success()
            ->send();
    }

    private function showDetailedAssessmentResults($record): void
    {
        $calculation = $record->training->calculateOverallScore($record);
        
        $detailedResults = $record->assessmentResults->map(function ($result) {
            $feedback = $result->feedback ? " | Feedback: {$result->feedback}" : '';
            return "• {$result->assessmentCategory->name}: {$result->result} ({$result->category_weight}%){$feedback}";
        })->join("\n");

        $assessmentDate = $record->assessmentResults->first()?->assessment_date?->format('M j, Y') ?? 'Unknown';

        Notification::make()
            ->title("Detailed Assessment Results - {$record->training->title}")
            ->body("Assessment Date: {$assessmentDate}\nOverall: {$calculation['status']} ({$calculation['score']}%)\n\nDetails:\n{$detailedResults}")
            ->info()
            ->duration(15000)
            ->send();
    }

    public function getStatusLogsProperty()
    {
        return $this->mentee->mentorshipStatusLogs()
            ->with('recorder')
            ->orderBy('month_number')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('month_number');
    }
}