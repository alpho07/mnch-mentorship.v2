<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Objective;
use App\Models\ParticipantObjectiveResult;
use App\Models\Grade;
use App\Models\User;
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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ManageTrainingAssessments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = GlobalTrainingResource::class;

    protected static string $view = 'filament.pages.manage-assessments';

    public Training $record;

    public function mount(int|string $record): void
    {
        $this->record = Training::where('type', 'global_training')->findOrFail($this->record->id);
    }

    public function getTitle(): string
    {
        return "Training Assessments - {$this->record->title}";
    }

    public function getViewData(): array
    {
        return [
            'record' => $this->record,
            'objectives' => $this->record->sessions()->with('objectives')->get()->pluck('objectives')->flatten(),
            'participants' => $this->record->participants()->with('user')->get(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_objectives')
                ->label('Add Training Objectives')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    Forms\Components\Section::make('Training Objectives')
                        ->description('Define the learning objectives that participants will be assessed against')
                        ->schema([
                            Repeater::make('objectives')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('objective_text')
                                                ->label('Objective Description')
                                                ->required()
                                                ->placeholder('e.g., Demonstrate proper hand hygiene techniques')
                                                ->columnSpan(2),

                                            Select::make('type')
                                                ->label('Objective Type')
                                                ->options([
                                                    'knowledge' => 'Knowledge',
                                                    'skill' => 'Practical Skill',
                                                    'attitude' => 'Attitude/Behavior',
                                                    'competency' => 'Overall Competency',
                                                ])
                                                ->required(),

                                            TextInput::make('pass_criteria')
                                                ->label('Pass Score (%)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->default(70)
                                                ->suffix('%'),
                                        ]),

                                    TextInput::make('objective_order')
                                        ->label('Order')
                                        ->numeric()
                                        ->default(1),

                                    Textarea::make('assessment_method')
                                        ->label('Assessment Method')
                                        ->placeholder('How will this objective be assessed? (e.g., Written test, Practical demonstration, Observation)')
                                        ->rows(2),
                                ])
                                ->addActionLabel('Add Objective')
                                ->collapsible()
                                ->itemLabel(
                                    fn(array $state): ?string =>
                                    $state['objective_text'] ?? 'New Objective'
                                )
                                ->defaultItems(1)
                                ->columnSpanFull(),
                        ])
                ])
                ->action(function (array $data) {
                    foreach ($data['objectives'] as $objectiveData) {
                        Objective::create([
                            'training_id' => $this->record->id,
                            'objective_text' => $objectiveData['objective_text'],
                            'type' => $objectiveData['type'],
                            'pass_criteria' => $objectiveData['pass_criteria'] ?? 70,
                            'objective_order' => $objectiveData['objective_order'] ?? 1,
                            'assessment_method' => $objectiveData['assessment_method'] ?? null,
                        ]);
                    }

                    Notification::make()
                        ->title('Objectives Added')
                        ->body('Training objectives have been added successfully.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('bulk_assess')
                ->label('Bulk Assessment')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->form([
                    Forms\Components\Section::make('Bulk Assessment')
                        ->schema([
                            Select::make('objective_id')
                                ->label('Select Objective')
                                ->options(function () {
                                    return Objective::where('training_id', $this->record->id)
                                        ->get()
                                        ->pluck('objective_text', 'id');
                                })
                                ->required()
                                ->live(),

                            Select::make('participants')
                                ->label('Select Participants')
                                ->multiple()
                                ->options(function () {
                                    return $this->record->participants()
                                        ->with('user')
                                        ->get()
                                        ->mapWithKeys(fn($participant) => [
                                            $participant->id => $participant->user->full_name
                                        ]);
                                })
                                ->required(),

                            Grid::make(2)
                                ->schema([
                                    TextInput::make('score')
                                        ->label('Score (%)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->required(),

                                    Select::make('grade_id')
                                        ->label('Grade')
                                        ->options(Grade::all()->pluck('name', 'id'))
                                        ->required(),
                                ]),

                            Textarea::make('feedback')
                                ->label('Assessment Feedback')
                                ->rows(3),
                        ])
                ])
                ->action(function (array $data) {
                    foreach ($data['participants'] as $participantId) {
                        ParticipantObjectiveResult::updateOrCreate(
                            [
                                'participant_id' => $participantId,
                                'objective_id' => $data['objective_id'],
                            ],
                            [
                                'score' => $data['score'],
                                'grade_id' => $data['grade_id'],
                                'assessed_by' => auth()->id(),
                                'assessment_date' => now(),
                                'feedback' => $data['feedback'] ?? null,
                            ]
                        );
                    }

                    Notification::make()
                        ->title('Bulk Assessment Complete')
                        ->body('Assessment results have been recorded for selected participants.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('back_to_training')
                ->label('Back to Training')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn() => GlobalTrainingResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TrainingParticipant::query()
                    ->where('training_id', $this->record->id)
                    ->join('users', 'training_participants.user_id', '=', 'users.id')
                    ->select('training_participants.*')
                    ->with(['user.facility', 'user.department', 'user.cadre', 'objectiveResults.objective', 'objectiveResults.grade'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('Participant Name')
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->sortable(['users.first_name', 'users.last_name']),

                Tables\Columns\TextColumn::make('user.facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('objectives_assessed')
                    ->label('Objectives Assessed')
                    ->getStateUsing(function (TrainingParticipant $record): string {
                        $totalObjectives = Objective::where('training_id', $this->record->id)->count();
                        $assessedObjectives = $record->objectiveResults()->count();
                        return "{$assessedObjectives}/{$totalObjectives}";
                    })
                    ->badge()
                    ->color(function (TrainingParticipant $record): string {
                        $totalObjectives = Objective::where('training_id', $this->record->id)->count();
                        $assessedObjectives = $record->objectiveResults()->count();

                        if ($totalObjectives == 0) return 'gray';
                        if ($assessedObjectives == $totalObjectives) return 'success';
                        if ($assessedObjectives > 0) return 'warning';
                        return 'danger';
                    }),

                Tables\Columns\TextColumn::make('overall_score')
                    ->label('Overall Score')
                    ->getStateUsing(function (TrainingParticipant $record): string {
                        $averageScore = $record->objectiveResults()->avg('score');
                        return $averageScore ? number_format($averageScore, 1) . '%' : 'Not assessed';
                    })
                    ->badge()
                    ->color(function (TrainingParticipant $record): string {
                        $averageScore = $record->objectiveResults()->avg('score');
                        if (!$averageScore) return 'gray';
                        if ($averageScore >= 80) return 'success';
                        if ($averageScore >= 70) return 'warning';
                        return 'danger';
                    }),

                Tables\Columns\BadgeColumn::make('overall_result')
                    ->label('Result')
                    ->getStateUsing(function (TrainingParticipant $record): string {
                        $averageScore = $record->objectiveResults()->avg('score');
                        if (!$averageScore) return 'Pending';
                        return $averageScore >= 70 ? 'PASS' : 'FAIL';
                    })
                    ->colors([
                        'success' => 'PASS',
                        'danger' => 'FAIL',
                        'gray' => 'Pending',
                    ]),

                Tables\Columns\TextColumn::make('last_assessed')
                    ->label('Last Assessed')
                    ->getStateUsing(function (TrainingParticipant $record): string {
                        $lastAssessment = $record->objectiveResults()
                            ->latest('assessment_date')
                            ->first();
                        return $lastAssessment?->assessment_date?->format('M j, Y') ?? 'Never';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('assessment_status')
                    ->label('Assessment Status')
                    ->options([
                        'complete' => 'Fully Assessed',
                        'partial' => 'Partially Assessed',
                        'not_assessed' => 'Not Assessed',
                    ])
                    ->query(function ($query, $state) {
                        if (!$state['value']) return $query;

                        $totalObjectives = Objective::where('training_id', $this->record->id)->count();

                        if ($state['value'] === 'complete') {
                            return $query->whereHas('objectiveResults', function ($q) use ($totalObjectives) {
                                $q->select(\DB::raw('COUNT(*)'))
                                    ->havingRaw('COUNT(*) = ?', [$totalObjectives]);
                            });
                        } elseif ($state['value'] === 'partial') {
                            return $query->whereHas('objectiveResults', function ($q) use ($totalObjectives) {
                                $q->select(\DB::raw('COUNT(*)'))
                                    ->havingRaw('COUNT(*) > 0 AND COUNT(*) < ?', [$totalObjectives]);
                            });
                        } else { // not_assessed
                            return $query->whereDoesntHave('objectiveResults');
                        }
                    }),

                Tables\Filters\SelectFilter::make('result')
                    ->options([
                        'pass' => 'PASS',
                        'fail' => 'FAIL',
                    ])
                    ->query(function ($query, $state) {
                        if (!$state['value']) return $query;

                        if ($state['value'] === 'pass') {
                            return $query->whereHas('objectiveResults', function ($q) {
                                $q->select(\DB::raw('AVG(score) as avg_score'))
                                    ->havingRaw('AVG(score) >= 70');
                            });
                        } else {
                            return $query->whereHas('objectiveResults', function ($q) {
                                $q->select(\DB::raw('AVG(score) as avg_score'))
                                    ->havingRaw('AVG(score) < 70');
                            });
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('assess_participant')
                        ->label('Assess')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('primary')
                        ->form(function (TrainingParticipant $record) {
                            $objectives = Objective::where('training_id', $this->record->id)->get();
                            $objectiveCount = $objectives->count();

                            return [
                                Forms\Components\Section::make("Assess {$record->user->full_name}")
                                    ->schema([
                                        Repeater::make('assessments')
                                            ->schema([
                                                Forms\Components\Hidden::make('objective_id'),

                                                Forms\Components\Placeholder::make('objective_text')
                                                    ->content(
                                                        fn(Get $get): string =>
                                                        Objective::find($get('objective_id'))?->objective_text ?? ''
                                                    ),

                                                Grid::make(3)
                                                    ->schema([
                                                        TextInput::make('score')
                                                            ->label('Score (%)')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue(100)
                                                            ->required(),

                                                        Select::make('grade_id')
                                                            ->label('Grade')
                                                            ->options(Grade::all()->pluck('name', 'id'))
                                                            ->required(),

                                                        Textarea::make('feedback')
                                                            ->label('Feedback')
                                                            ->rows(2),
                                                    ]),
                                            ])
                                            ->defaultItems($objectiveCount)
                                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data) use ($objectives, $record): array {
                                                // Pre-populate the repeater with existing assessment data
                                                $assessmentData = [];

                                                foreach ($objectives as $index => $objective) {
                                                    $existingResult = ParticipantObjectiveResult::where([
                                                        'participant_id' => $record->id,
                                                        'objective_id' => $objective->id,
                                                    ])->first();

                                                    $assessmentData[$index] = [
                                                        'objective_id' => $objective->id,
                                                        'score' => $existingResult?->score,
                                                        'grade_id' => $existingResult?->grade_id,
                                                        'feedback' => $existingResult?->feedback,
                                                    ];
                                                }

                                                return $assessmentData;
                                            })
                                            ->afterStateHydrated(function (Repeater $component, ?array $state) use ($objectives, $record) {
                                                // Populate with existing data when form loads
                                                $assessmentData = [];

                                                foreach ($objectives as $objective) {
                                                    $existingResult = ParticipantObjectiveResult::where([
                                                        'participant_id' => $record->id,
                                                        'objective_id' => $objective->id,
                                                    ])->first();

                                                    $assessmentData[] = [
                                                        'objective_id' => $objective->id,
                                                        'score' => $existingResult?->score,
                                                        'grade_id' => $existingResult?->grade_id,
                                                        'feedback' => $existingResult?->feedback,
                                                    ];
                                                }

                                                $component->state($assessmentData);
                                            })
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ])
                            ];
                        })
                        ->action(function (TrainingParticipant $record, array $data) {
                            foreach ($data['assessments'] as $assessment) {
                                ParticipantObjectiveResult::updateOrCreate(
                                    [
                                        'participant_id' => $record->id,
                                        'objective_id' => $assessment['objective_id'],
                                    ],
                                    [
                                        'score' => $assessment['score'],
                                        'grade_id' => $assessment['grade_id'],
                                        'assessed_by' => auth()->id(),
                                        'assessment_date' => now(),
                                        'feedback' => $assessment['feedback'] ?? null,
                                    ]
                                );
                            }

                            // Update participant completion status
                            $averageScore = ParticipantObjectiveResult::where('participant_id', $record->id)
                                ->avg('score');

                            $record->update([
                                'completion_status' => $averageScore >= 70 ? 'completed' : 'in_progress',
                                'completion_date' => $averageScore >= 70 ? now() : null,
                            ]);

                            Notification::make()
                                ->title('Assessment Complete')
                                ->body("Assessment results recorded for {$record->user->full_name}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('view_results')
                        ->label('View Results')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->modalHeading(fn(TrainingParticipant $record): string => "Assessment Results - {$record->user->full_name}")
                        ->modalContent(
                            fn(TrainingParticipant $record): \Illuminate\View\View =>
                            view('filament.components.assessment-results', ['participant' => $record])
                        )
                        ->modalWidth('2xl'),
                ])
            ])
            ->defaultSort('users.first_name', 'asc');
    }
}
