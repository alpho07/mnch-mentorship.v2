<?php

namespace App\Filament\Resources\AssessmentSectionResource\RelationManagers;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Questions';

    // ─────────────────────────────────────────────────────────────────────────
    // FORM — shared by Create and Edit modals
    // ─────────────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Question Configuration')
                ->tabs([
                    // ── TAB 1: Core Question ──────────────────────────────
                    Forms\Components\Tabs\Tab::make('Question')
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('question_code')
                                    ->label('Question Code')
                                    ->required()
                                    ->unique(table: 'assessment_questions', column: 'question_code', ignoreRecord: true)
                                    ->maxLength(100)
                                    ->placeholder('e.g., INFRA_001')
                                    ->helperText('Unique identifier — used for conditional logic references'),
                                Forms\Components\TextInput::make('order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(fn () => (AssessmentQuestion::where(
                                        'assessment_section_id',
                                        $this->getOwnerRecord()->id
                                    )->max('order') ?? 0) + 10)
                                    ->required()
                                    ->helperText('Lower = shown first'),
                                Forms\Components\Select::make('group')
                                    ->label('Group / Sub-section')
                                    ->options(fn () => $this->getGroupOptions())
                                    ->searchable()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                    ->label('New Group Name')
                                    ->required()
                                    ->maxLength(150),
                                    ])
                                    ->createOptionUsing(fn (array $data) => $data['name'])
                                    ->placeholder('No group / Add new…')
                                    ->helperText('Groups questions into sub-sections visually'),
                            ]),
                            Forms\Components\Textarea::make('question_text')
                                ->label('Question Text')
                                ->required()
                                ->rows(2)
                                ->columnSpanFull()
                                ->placeholder('Enter the full question text here'),
                            Forms\Components\Textarea::make('help_text')
                                ->label('Help / Guidance Text')
                                ->rows(2)
                                ->placeholder('Optional guidance for the assessor')
                                ->columnSpanFull(),
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('question_type')
                                    ->label('Question Type')
                                    ->options([
                                        'yes_no' => 'Yes / No',
                                        'yes_no_partial' => 'Yes / No / Partially',
                                        'number' => 'Number',
                                        'text' => 'Free Text',
                                        'proportion' => 'Proportion (x/n)',
                                        'select' => 'Dropdown Select',
                                        'radio' => 'Radio Buttons',
                                        'mortality_three_month' => 'Mortality (3-Month: Aug/Sep/Oct)',
                                    ])
                                    ->required()
                                    ->live()
                                    ->default('yes_no')
                                    ->helperText('Determines the input field rendered'),
                                Forms\Components\Toggle::make('is_required')
                                    ->label('Required')
                                    ->default(false)
                                    ->helperText('Must be answered before saving'),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),
                            // Options — only for select/radio
                            Forms\Components\TagsInput::make('options')
                                ->label('Options')
                                ->placeholder('Add option and press Enter')
                                ->helperText('Options for select/radio question types')
                                ->visible(fn (Get $get) => in_array($get('question_type'), ['select', 'radio']))
                                ->columnSpanFull(),
                        ]),
                    // ── TAB 2: Scoring ────────────────────────────────────
                    Forms\Components\Tabs\Tab::make('Scoring')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Toggle::make('is_scored')
                                    ->label('Include in Scoring')
                                    ->default(true)
                                    ->live()
                                    ->helperText('Toggle off for informational questions'),
                                Forms\Components\TextInput::make('explanation_label')
                                    ->label('Explanation Field Label')
                                    ->default('Comments/Recommendations')
                                    ->maxLength(255)
                                    ->helperText('Label shown above the explanation textarea'),
                            ]),
                            // Scoring map — key/value pairs
                            Forms\Components\Repeater::make('scoring_map')
                                ->label('Scoring Map')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('response')
                                    ->label('Response Value')
                                    ->required()
                                    ->placeholder('Yes'),
                                        Forms\Components\TextInput::make('score')
                                    ->label('Score')
                                    ->numeric()
                                    ->required()
                                    ->placeholder('1'),
                                    ]),
                                ])
                                ->addActionLabel('Add Response → Score')
                                ->collapsible()
                                ->visible(fn (Get $get) => $get('is_scored'))
                                ->helperText('Map each possible response to a numeric score')
                                ->columnSpanFull()
                                ->dehydrateStateUsing(function ($state) {
                                    // Convert repeater format [{response:x,score:y}] → {x:y}
                                    if (! is_array($state)) {
                                        return null;
                                    }
                                    $map = [];
                                    foreach ($state as $item) {
                                        if (isset($item['response']) && isset($item['score'])) {
                                            $map[$item['response']] = (float) $item['score'];
                                        }
                                    }

                                    return empty($map) ? null : $map;
                                })
                                ->afterStateHydrated(function (Forms\Components\Repeater $component, $state) {
                                    // Convert {x:y} → [{response:x,score:y}]
                                    if (is_array($state) && ! array_is_list($state)) {
                                        $items = [];
                                        foreach ($state as $response => $score) {
                                            $items[] = ['response' => $response, 'score' => $score];
                                        }
                                        $component->state($items);
                                    }
                                }),
                            // requires_explanation_on
                            Forms\Components\TagsInput::make('requires_explanation_on')
                                ->label('Require Explanation When Answer Is')
                                ->placeholder('e.g. No, Partially')
                                ->helperText('Explanation textarea appears when response matches any of these values')
                                ->columnSpanFull(),
                            // Validation rules for number/proportion
                            Forms\Components\Section::make('Validation Rules')
                                ->description('For number/proportion fields')
                                ->collapsible()
                                ->collapsed()
                                ->visible(fn (Get $get) => in_array($get('question_type'), ['number', 'proportion']))
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\TextInput::make('validation_rules.min')
                                            ->label('Min Value')
                                            ->numeric(),
                                        Forms\Components\TextInput::make('validation_rules.max')
                                            ->label('Max Value')
                                            ->numeric(),
                                        Forms\Components\TextInput::make('validation_rules.sample_size')
                                            ->label('Sample Size')
                                            ->numeric()
                                            ->default(10)
                                            ->visible(fn (Get $get) => $get('question_type') === 'proportion')
                                            ->helperText('Fixed denominator for proportion questions'),
                                    ]),
                                ]),
                        ]),
                    // ── TAB 3: Conditional Logic (Follower Questions) ─────
                    Forms\Components\Tabs\Tab::make('Conditional Logic')
                        ->icon('heroicon-o-arrow-path')
                        ->badge(fn (Get $get) => $get('display_conditions') ? '✓' : null)
                        ->badgeColor('success')
                        ->schema([
                            Forms\Components\Placeholder::make('conditional_info')
                                ->label('')
                                ->content('Make this question appear only when a parent question has a specific answer. This creates "follower" questions triggered by Yes/No responses.')
                                ->columnSpanFull(),
                            Forms\Components\Select::make('conditional_logic_type')
                                ->label('Logic Type')
                                ->options([
                                    '' => 'Always Visible (no condition)',
                                    'single' => 'Single Condition',
                                    'and' => 'All Must Match (AND)',
                                    'or' => 'Any Must Match (OR)',
                                ])
                                ->default('')
                                ->live()
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Forms\Components\Select $component, $state, $record) {
                                    if (! $record || ! $record->display_conditions) {
                                        $component->state('');

                                        return;
                                    }
                                    $logic = $record->display_conditions;
                                    if (isset($logic['operator']) && $logic['operator'] === 'and') {
                                        $component->state('and');
                                    } elseif (isset($logic['operator']) && $logic['operator'] === 'or') {
                                        $component->state('or');
                                    } elseif (isset($logic['question_code']) || isset($logic['show_if'])) {
                                        $component->state('single');
                                    }
                                }),
                            // ── Single condition ──────────────────────────
                            Forms\Components\Section::make('Single Condition')
                                ->visible(fn (Get $get) => $get('conditional_logic_type') === 'single')
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\Select::make('display_conditions.question_code')
                                            ->label('Parent Question Code')
                                            ->options(fn () => $this->getAllQuestionCodeOptions())
                                            ->searchable()
                                            ->required()
                                            ->helperText('Question whose answer triggers this question'),
                                        Forms\Components\Select::make('display_conditions.operator')
                                            ->label('Operator')
                                            ->options([
                                                'equals' => 'Equals',
                                                'not_equals' => 'Not Equals',
                                                'in' => 'Is One Of',
                                                'not_in' => 'Is Not One Of',
                                                'greater_than' => 'Greater Than',
                                                'less_than' => 'Less Than',
                                            ])
                                            ->default('equals'),
                                        Forms\Components\TextInput::make('display_conditions.value')
                                            ->label('Trigger Value')
                                            ->placeholder('e.g. Yes')
                                            ->helperText('Show this question when parent = this value'),
                                    ]),
                                ]),
                            // ── AND / OR multi-condition ──────────────────
                            Forms\Components\Repeater::make('conditional_logic_conditions')
                                ->label('Conditions')
                                ->visible(fn (Get $get) => in_array($get('conditional_logic_type'), ['and', 'or']))
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\Select::make('question_code')
                                            ->label('Parent Question Code')
                                            ->options(fn () => $this->getAllQuestionCodeOptions())
                                            ->searchable()
                                            ->required(),
                                        Forms\Components\Select::make('operator')
                                            ->label('Operator')
                                            ->options([
                                                'equals' => 'Equals',
                                                'not_equals' => 'Not Equals',
                                                'in' => 'Is One Of',
                                                'not_in' => 'Is Not One Of',
                                                'greater_than' => 'Greater Than',
                                                'less_than' => 'Less Than',
                                            ])
                                            ->default('equals'),
                                        Forms\Components\TextInput::make('value')
                                            ->label('Trigger Value')
                                            ->placeholder('e.g. Yes'),
                                    ]),
                                ])
                                ->addActionLabel('Add Condition')
                                ->collapsible()
                                ->columnSpanFull()
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Forms\Components\Repeater $component, $record) {
                                    if (! $record || ! $record->display_conditions) {
                                        return;
                                    }
                                    $logic = $record->display_conditions;
                                    if (isset($logic['conditions']) && is_array($logic['conditions'])) {
                                        $component->state($logic['conditions']);
                                    }
                                }),
                        ]),
                    // ── TAB 4: Skip Logic ─────────────────────────────────
                    Forms\Components\Tabs\Tab::make('Skip Logic')
                        ->icon('heroicon-o-forward')
                        ->schema([
                            Forms\Components\Placeholder::make('skip_info')
                                ->label('')
                                ->content('Skip logic allows this question to jump to or hide other questions based on the response given here.')
                                ->columnSpanFull(),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('skip_logic.if_response')
                                    ->label('If Response Equals')
                                    ->placeholder('e.g. No'),
                                Forms\Components\Select::make('skip_logic.skip_to')
                                    ->label('Then Skip To Question Code')
                                    ->options(fn () => $this->getAllQuestionCodeOptions())
                                    ->searchable()
                                    ->placeholder('Select target question'),
                            ]),
                            Forms\Components\TagsInput::make('skip_logic.hide_questions')
                                ->label('Also Hide These Question Codes')
                                ->placeholder('Add question code and press Enter')
                                ->helperText('These questions will be hidden when skip is triggered')
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter()
                    ->width(60),
                Tables\Columns\TextColumn::make('question_code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy')
                    ->width(130),
                Tables\Columns\TextColumn::make('group')
                    ->label('Group')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('question_text')
                    ->label('Question')
                    ->searchable()
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn ($state) => strlen($state) > 80 ? $state : null),
                Tables\Columns\BadgeColumn::make('question_type')
                    ->label('Type')
                    ->colors([
                        'success' => 'yes_no',
                        'info' => 'yes_no_partial',
                        'warning' => 'number',
                        'primary' => 'proportion',
                        'danger' => 'mortality_three_month',
                        'gray' => fn ($state) => in_array($state, ['text', 'select', 'radio']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'yes_no' => 'Yes/No',
                        'yes_no_partial' => 'Yes/No/Partly',
                        'number' => 'Number',
                        'text' => 'Text',
                        'proportion' => 'Proportion',
                        'select' => 'Select',
                        'radio' => 'Radio',
                        'mortality_three_month' => 'Mortality 3M',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('has_conditional')
                    ->label('Conditional')
                    ->state(fn (AssessmentQuestion $record): bool => ! empty($record->display_conditions))
                    ->boolean()
                    ->trueIcon('heroicon-o-link')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (AssessmentQuestion $record): ?string => ! empty($record->display_conditions) ? 'This question depends on another answer' : null
                    ),
                Tables\Columns\IconColumn::make('is_scored')
                    ->label('Scored')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('question_type')
                    ->label('Type')
                    ->options([
                        'yes_no' => 'Yes / No',
                        'yes_no_partial' => 'Yes / No / Partially',
                        'number' => 'Number',
                        'text' => 'Text',
                        'proportion' => 'Proportion',
                        'select' => 'Select',
                        'radio' => 'Radio',
                        'mortality_three_month' => 'Mortality (3-Month)',
                    ]),
                Tables\Filters\SelectFilter::make('group')
                    ->label('Group')
                    ->options(fn () => $this->getGroupOptions()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->default(true),
                Tables\Filters\TernaryFilter::make('is_scored')
                    ->label('Scored'),
                Tables\Filters\Filter::make('has_conditional')
                    ->label('Has Conditional Logic')
                    ->query(fn ($query) => $query->whereNotNull('display_conditions')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Question')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->resolveConditionalLogicBeforeSave($data);
                    }),
                // Quick add follower question
                Tables\Actions\Action::make('add_follower')
                    ->label('Add Follower Question')
                    ->icon('heroicon-o-arrow-turn-down-right')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('parent_question_id')
                            ->label('Parent Question (Yes/No)')
                            ->options(fn () => AssessmentQuestion::where('assessment_section_id', $this->getOwnerRecord()->id)
                                ->whereIn('question_type', ['yes_no', 'yes_no_partial', 'select', 'radio'])
                                ->orderBy('order')
                                ->get()
                                ->mapWithKeys(fn ($q) => [$q->id => "[{$q->question_code}] {$q->question_text}"])
                            )
                            ->required()
                            ->searchable()
                            ->live()
                            ->helperText('The question whose answer will trigger this follower'),
                        Forms\Components\Select::make('trigger_value')
                            ->label('Show When Parent Answer Is')
                            ->options(fn (Get $get) => $this->getTriggerValueOptions($get('parent_question_id')))
                            ->required()
                            ->helperText('This follower appears when the parent matches this value'),
                        Forms\Components\TextInput::make('question_code')
                            ->label('Question Code')
                            ->required()
                            ->unique(table: 'assessment_questions', column: 'question_code')
                            ->placeholder('e.g., INFRA_001a'),
                        Forms\Components\Textarea::make('question_text')
                            ->label('Question Text')
                            ->required()
                            ->rows(2),
                        Forms\Components\Select::make('question_type')
                            ->label('Type')
                            ->options([
                                'yes_no' => 'Yes / No',
                                'yes_no_partial' => 'Yes / No / Partially',
                                'number' => 'Number',
                                'text' => 'Free Text',
                                'select' => 'Dropdown',
                                'radio' => 'Radio',
                                'mortality_three_month' => 'Mortality (3-Month: Aug/Sep/Oct)',
                            ])
                            ->default('yes_no')
                            ->required(),
                        Forms\Components\Toggle::make('is_required')->default(false),
                    ])
                    ->action(function (array $data) {
                        $parent = AssessmentQuestion::find($data['parent_question_id']);
                        if (! $parent) {
                            return;
                        }

                        AssessmentQuestion::create([
                            'assessment_section_id' => $this->getOwnerRecord()->id,
                            'question_code' => $data['question_code'],
                            'question_text' => $data['question_text'],
                            'question_type' => $data['question_type'],
                            'is_required' => $data['is_required'] ?? false,
                            'is_active' => true,
                            'is_scored' => true,
                            'order' => $parent->order + 1,
                            'display_conditions' => [
                                'question_code' => $parent->question_code,
                                'value' => $data['trigger_value'],
                                'operator' => 'equals',
                            ],
                        ]);

                        Notification::make()
                            ->title('Follower question created')
                            ->body("'{$data['question_code']}' will appear when [{$parent->question_code}] = '{$data['trigger_value']}'")
                            ->success()
                            ->send();
                    }),
                // Reorder by group action
                Tables\Actions\Action::make('sort_by_group')
                    ->label('Sort by Group')
                    ->icon('heroicon-o-bars-arrow-up')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('This will re-number the order field to group questions together by their group name.')
                    ->action(function () {
                        $questions = $this->getOwnerRecord()
                            ->questions()
                            ->orderBy('group')
                            ->orderBy('order')
                            ->get();

                        $order = 10;
                        foreach ($questions as $q) {
                            $q->update(['order' => $order]);
                            $order += 10;
                        }

                        Notification::make()
                            ->title('Questions re-sorted by group')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => $this->hydrateConditionalLogicForEdit($data))
                    ->mutateRecordDataUsing(fn (array $data) => $this->hydrateConditionalLogicForEdit($data))
                    ->using(function (AssessmentQuestion $record, array $data) {
                        $data = $this->resolveConditionalLogicBeforeSave($data);
                        $record->update($data);

                        return $record;
                    }),
                // Move to another section
                Tables\Actions\Action::make('move_to_section')
                    ->label('Move to Section')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('target_section_id')
                            ->label('Target Section')
                            ->options(
                                AssessmentSection::where('id', '!=', $this->getOwnerRecord()->id)
                                    ->where('is_active', true)
                                    ->orderBy('order')
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('new_order')
                            ->label('New Order (optional)')
                            ->numeric()
                            ->placeholder('Leave blank to append at end'),
                    ])
                    ->action(function (AssessmentQuestion $record, array $data) {
                        $targetSectionId = $data['target_section_id'];
                        $newOrder = $data['new_order'] ?? null;

                        if (! $newOrder) {
                            $newOrder = (AssessmentQuestion::where('assessment_section_id', $targetSectionId)->max('order') ?? 0) + 10;
                        }

                        $record->update([
                            'assessment_section_id' => $targetSectionId,
                            'order' => $newOrder,
                        ]);

                        $targetSection = AssessmentSection::find($targetSectionId);

                        Notification::make()
                            ->title('Question moved')
                            ->body("'{$record->question_code}' moved to '{$targetSection->name}'")
                            ->success()
                            ->send();
                    }),
                // Duplicate question within same section
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (AssessmentQuestion $record) {
                        $newQuestion = $record->replicate();
                        $newQuestion->question_code = $record->question_code.'_copy_'.time();
                        $newQuestion->order = $record->order + 5;
                        $newQuestion->save();

                        Notification::make()
                            ->title('Question duplicated')
                            ->body("Created '{$newQuestion->question_code}'")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (AssessmentQuestion $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (AssessmentQuestion $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (AssessmentQuestion $record) => $record->is_active ? 'warning' : 'success')
                    ->action(fn (AssessmentQuestion $record) => $record->update(['is_active' => ! $record->is_active])),
                Tables\Actions\DeleteAction::make()
                    ->before(function (AssessmentQuestion $record) {
                        if ($record->responses()->count() > 0) {
                            Notification::make()
                                ->title('Cannot delete')
                                ->body('This question has existing responses. Deactivate it instead.')
                                ->danger()
                                ->send();

                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_move_to_section')
                        ->label('Move to Section')
                        ->icon('heroicon-o-arrows-right-left')
                        ->form([
                            Forms\Components\Select::make('target_section_id')
                                ->label('Target Section')
                                ->options(
                                    AssessmentSection::where('id', '!=', $this->getOwnerRecord()->id)
                                        ->where('is_active', true)
                                        ->orderBy('order')
                                        ->pluck('name', 'id')
                                )
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $targetSectionId = $data['target_section_id'];
                            $baseOrder = (AssessmentQuestion::where('assessment_section_id', $targetSectionId)->max('order') ?? 0) + 10;
                            $i = 0;

                            foreach ($records as $record) {
                                $record->update([
                                    'assessment_section_id' => $targetSectionId,
                                    'order' => $baseOrder + ($i * 10),
                                ]);
                                $i++;
                            }

                            $targetSection = AssessmentSection::find($targetSectionId);

                            Notification::make()
                                ->title('Questions moved')
                                ->body("{$records->count()} questions moved to '{$targetSection->name}'")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('bulk_set_group')
                        ->label('Assign Group')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Forms\Components\TextInput::make('group')
                                ->label('Group Name')
                                ->required()
                                ->placeholder('e.g., Maternity Ward'),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['group' => $data['group']]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('bulk_deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records) {
                            $withResponses = $records->filter(fn ($r) => $r->responses()->count() > 0);
                            if ($withResponses->isNotEmpty()) {
                                Notification::make()
                                    ->title('Some questions have responses')
                                    ->body($withResponses->count().' questions could not be deleted (have existing responses). Deactivate them instead.')
                                    ->danger()
                                    ->send();
                                // Only delete the ones without responses
                                $records->filter(fn ($r) => $r->responses()->count() === 0)->each->delete();

                                return false;
                            }
                        }),
                ]),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get distinct group names for this section, for filter/select dropdowns
     */
    private function getGroupOptions(): array
    {
        return AssessmentQuestion::where('assessment_section_id', $this->getOwnerRecord()->id)
            ->whereNotNull('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group', 'group')
            ->toArray();
    }

    /**
     * Get all question codes across ALL sections for conditional logic parent picker
     */
    private function getAllQuestionCodeOptions(): array
    {
        return AssessmentQuestion::where('is_active', true)
            ->orderBy('question_code')
            ->get()
            ->mapWithKeys(fn ($q) => [
                $q->question_code => "[{$q->question_code}] {$q->question_text}",
            ])
            ->toArray();
    }

    /**
     * Get possible answer options for a given question (for trigger_value select)
     */
    private function getTriggerValueOptions(?int $questionId): array
    {
        if (! $questionId) {
            return [];
        }

        $question = AssessmentQuestion::find($questionId);
        if (! $question) {
            return [];
        }

        return match ($question->question_type) {
            'yes_no' => ['Yes' => 'Yes', 'No' => 'No'],
            'yes_no_partial' => ['Yes' => 'Yes', 'No' => 'No', 'Partially' => 'Partially'],
            'select', 'radio' => collect($question->options ?? [])
                ->mapWithKeys(fn ($o) => [$o => $o])
                ->toArray(),
            default => [],
        };
    }

    /**
     * Resolve the display_conditions field (the real, live DB column — not
     * conditional_logic) from the multi-tab form before saving to DB.
     */
    private function resolveConditionalLogicBeforeSave(array $data): array
    {
        $logicType = $data['conditional_logic_type'] ?? '';
        $conditions = $data['conditional_logic_conditions'] ?? [];

        if ($logicType === '' || $logicType === null) {
            $data['display_conditions'] = null;
        } elseif ($logicType === 'single') {
            // Already set via display_conditions.question_code etc — keep as is
            // Just ensure it's an array, not still nested
        } elseif (in_array($logicType, ['and', 'or'])) {
            // Build structured format from repeater
            $data['display_conditions'] = [
                'operator' => $logicType,
                'conditions' => is_array($conditions) ? array_values($conditions) : [],
            ];
        }

        // Clean up form-only fields
        unset($data['conditional_logic_type'], $data['conditional_logic_conditions']);

        return $data;
    }

    /**
     * Hydrate form fields when editing an existing question with conditional logic
     */
    private function hydrateConditionalLogicForEdit(array $data): array
    {
        // Determine type string for the Select field
        if (empty($data['display_conditions'])) {
            $data['conditional_logic_type'] = '';
        } else {
            $logic = $data['display_conditions'];
            if (isset($logic['operator']) && in_array($logic['operator'], ['and', 'or'])) {
                $data['conditional_logic_type'] = $logic['operator'];
                $data['conditional_logic_conditions'] = $logic['conditions'] ?? [];
            } else {
                $data['conditional_logic_type'] = 'single';
            }
        }

        return $data;
    }
}
