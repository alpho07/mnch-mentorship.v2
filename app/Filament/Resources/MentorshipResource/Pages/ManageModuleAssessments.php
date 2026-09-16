<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassModule;
use App\Models\ModuleAssessment;
use App\Models\ModuleAssessmentResult;
use App\Models\ClassParticipant;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;

class ManageModuleAssessments extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-module-assessments';
    public Training $training;
    public MentorshipClass $class;
    public ClassModule $module;
    public string $activeTab = 'assessments'; // 'assessments' or 'results'

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load(['programModule', 'mentorshipClass']);
    }

    public function getTitle(): string {
        return "Assessments - {$this->module->programModule->name}";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('create_assessment')
                    ->label('Create Assessment')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('title')
                        ->label('Assessment Title')
                        ->required()
                        ->placeholder('e.g., Module Competency Assessment')
                        ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->placeholder('What does this assessment evaluate?'),
                        Forms\Components\Select::make('assessment_type')
                        ->label('Assessment Type')
                        ->options(ModuleAssessment::getTypeOptions())
                        ->required()
                        ->default(ModuleAssessment::TYPE_SCORE)
                        ->live(),
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('pass_threshold')
                            ->label('Pass Threshold')
                            ->numeric()
                            ->required()
                            ->default(70)
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100),
                            Forms\Components\TextInput::make('max_score')
                            ->label('Maximum Score')
                            ->numeric()
                            ->required()
                            ->default(100)
                            ->minValue(1),
                            Forms\Components\TextInput::make('weight_percentage')
                            ->label('Weight')
                            ->numeric()
                            ->required()
                            ->default(100)
                            ->suffix('%')
                            ->helperText('If multiple assessments, should total 100%'),
                        ]),
                        // Checklist items (if type is checklist)
                        Forms\Components\Repeater::make('checklist_items')
                        ->label('Checklist Items')
                        ->schema([
                            Forms\Components\TextInput::make('item')
                            ->label('Checklist Item')
                            ->required()
                            ->placeholder('e.g., Demonstrates proper hand washing technique'),
                        ])
                        ->visible(fn(Forms\Get $get) =>
                                $get('assessment_type') === ModuleAssessment::TYPE_CHECKLIST
                        )
                        ->defaultItems(3)
                        ->addActionLabel('Add Item')
                        ->columnSpanFull(),
                    ])
                    ->action(fn(array $data) => $this->createAssessment($data)),
                    Actions\Action::make('toggle_tab')
                    ->label($this->activeTab === 'assessments' ? 'View Results' : 'View Assessments')
                    ->icon($this->activeTab === 'assessments' ? 'heroicon-o-clipboard-document-check' : 'heroicon-o-cog')
                    ->color('info')
                    ->action(function () {
                        $this->activeTab = $this->activeTab === 'assessments' ? 'results' : 'assessments';
                    }),
                    Actions\Action::make('back')
                    ->label('Back to Module')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('module-sessions', [
                                'training' => $this->training,
                                'class' => $this->class,
                                'module' => $this->module->id,
                            ])),
        ];
    }

    public function table(Table $table): Table {
        if ($this->activeTab === 'results') {
            return $this->getResultsTable($table);
        }

        return $this->getAssessmentsTable($table);
    }

    private function getAssessmentsTable(Table $table): Table {
        return $table
                        ->query(
                                ModuleAssessment::query()
                                ->where('class_module_id', $this->module->id)
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('order_sequence')
                            ->label('#')
                            ->badge()
                            ->color('gray')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('title')
                            ->searchable()
                            ->weight('bold')
                            ->description(fn($record) => $record->description),
                            Tables\Columns\BadgeColumn::make('assessment_type')
                            ->label('Type')
                            ->formatStateUsing(fn(string $state): string =>
                                    ModuleAssessment::getTypeOptions()[$state] ?? $state
                            )
                            ->colors([
                                'primary' => ModuleAssessment::TYPE_SCORE,
                                'warning' => ModuleAssessment::TYPE_MANUAL,
                                'info' => ModuleAssessment::TYPE_CHECKLIST,
                                'secondary' => ModuleAssessment::TYPE_MCQ,
                            ]),
                            Tables\Columns\TextColumn::make('pass_threshold')
                            ->label('Pass Score')
                            ->suffix('%')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('weight_percentage')
                            ->label('Weight')
                            ->suffix('%')
                            ->badge()
                            ->color('warning'),
                            Tables\Columns\TextColumn::make('completed_count')
                            ->label('Completed')
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('passed_count')
                            ->label('Passed')
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\TextColumn::make('average_score')
                            ->label('Avg Score')
                            ->formatStateUsing(fn($state) => $state ? number_format($state, 1) . '%' : 'N/A')
                            ->badge(),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('grade_mentees')
                                ->label('Grade Mentees')
                                ->icon('heroicon-o-pencil-square')
                                ->color('primary')
                                ->url(fn(ModuleAssessment $record): string =>
                                        route('filament.admin.resources.mentorships.grade-assessment', [
                                            'training' => $this->training,
                                            'class' => $this->class,
                                            'module' => $this->module->id,
                                            'assessment' => $record->id,
                                        ])
                                ),
                                Tables\Actions\EditAction::make()
                                ->form([
                                    Forms\Components\TextInput::make('title')->required(),
                                    Forms\Components\Textarea::make('description')->rows(3),
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\TextInput::make('pass_threshold')
                                        ->numeric()->required()->suffix('%'),
                                        Forms\Components\TextInput::make('max_score')
                                        ->numeric()->required(),
                                        Forms\Components\TextInput::make('weight_percentage')
                                        ->numeric()->required()->suffix('%'),
                                    ]),
                                    Forms\Components\Toggle::make('is_active')
                                    ->label('Active'),
                                ]),
                                Tables\Actions\DeleteAction::make(),
                            ]),
                        ])
                        ->reorderable('order_sequence')
                        ->emptyStateHeading('No Assessments Created')
                        ->emptyStateDescription('Create assessments to evaluate mentee performance.');
    }

    private function getResultsTable(Table $table): Table {
        return $table
                        ->query(
                                ClassParticipant::query()
                                ->where('mentorship_class_id', $this->module->mentorship_class_id)
                                ->with([
                                    'user',
                                    'moduleProgress' => fn($q) => $q->where('class_module_id', $this->module->id),
                                ])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('user.full_name')
                            ->label('Mentee')
                            ->searchable(['first_name', 'last_name'])
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('moduleProgress.assessment_score')
                            ->label('Overall Score')
                            ->getStateUsing(function ($record) {
                                $progress = $record->moduleProgress->first();
                                return $progress?->assessment_score ?
                                        number_format($progress->assessment_score, 1) . '%' : 'Not assessed';
                            })
                            ->badge()
                            ->color(fn($state) => $state === 'Not assessed' ? 'gray' :
                                    (floatval($state) >= 70 ? 'success' : 'danger')),
                            Tables\Columns\BadgeColumn::make('moduleProgress.assessment_status')
                            ->label('Status')
                            ->getStateUsing(function ($record) {
                                $progress = $record->moduleProgress->first();
                                return $progress?->assessment_status ?? 'pending';
                            })
                            ->colors([
                                'success' => 'passed',
                                'danger' => 'failed',
                                'warning' => 'pending',
                            ]),
                            Tables\Columns\TextColumn::make('assessments_completed')
                            ->label('Assessments Done')
                            ->getStateUsing(function ($record) {
                                $total = $this->module->assessments()->count();
                                $completed = ModuleAssessmentResult::where('class_participant_id', $record->id)
                                        ->whereHas('moduleAssessment', fn($q) =>
                                                $q->where('class_module_id', $this->module->id)
                                        )
                                        ->count();
                                return "{$completed}/{$total}";
                            })
                            ->badge(),
        ]);
    }

    private function createAssessment(array $data): void {
        $lastOrder = ModuleAssessment::where('class_module_id', $this->module->id)
                ->max('order_sequence');

        $assessment = ModuleAssessment::create([
            'class_module_id' => $this->module->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assessment_type' => $data['assessment_type'],
            'pass_threshold' => $data['pass_threshold'],
            'max_score' => $data['max_score'],
            'weight_percentage' => $data['weight_percentage'],
            'is_active' => true,
            'questions_data' => $data['checklist_items'] ?? null,
            'order_sequence' => ($lastOrder ?? 0) + 1,
        ]);

        Notification::make()
                ->success()
                ->title('Assessment Created')
                ->body("Assessment '{$data['title']}' has been created")
                ->send();
    }
}
