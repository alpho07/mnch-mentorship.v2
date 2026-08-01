<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentQuestionResource\Pages;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * AssessmentQuestionResource
 *
 * A dedicated Filament resource for managing ALL assessment questions across
 * all sections. Complements the QuestionsRelationManager (which is per-section)
 * by providing a global view with advanced sorting, filtering, and bulk ops.
 */
class AssessmentQuestionResource extends Resource
{
    protected static ?string $model = AssessmentQuestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Assessment Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'All Questions';

    protected static ?string $recordTitleAttribute = 'question_text';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_assessment::question');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_assessment::question');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Question Identity')
                ->icon('heroicon-o-identification')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('assessment_section_id')
                            ->label('Section')
                            ->options(
                                AssessmentSection::where('is_active', true)
                                    ->orderBy('order')
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Which section this question belongs to'),
                        Forms\Components\TextInput::make('question_code')
                            ->label('Question Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->placeholder('e.g., INFRA_001')
                            ->helperText('Globally unique — used in conditional logic'),
                        Forms\Components\TextInput::make('order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('group')
                            ->label('Group / Sub-section')
                            ->maxLength(150)
                            ->placeholder('e.g., Maternity Ward')
                            ->helperText('Visual grouping within the section'),
                        Forms\Components\Select::make('question_type')
                            ->label('Question Type')
                            ->options([
                                'yes_no' => 'Yes / No',
                                'yes_no_partial' => 'Yes / No / Partially',
                                'number' => 'Number',
                                'text' => 'Free Text',
                                'proportion' => 'Proportion',
                                'select' => 'Dropdown Select',
                                'radio' => 'Radio Buttons',
                            ])
                            ->required()
                            ->live()
                            ->default('yes_no'),
                    ]),
                    Forms\Components\Textarea::make('question_text')
                        ->label('Question Text')
                        ->required()
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('help_text')
                        ->label('Help Text')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Options & Flags')
                ->schema([
                    Forms\Components\TagsInput::make('options')
                        ->label('Answer Options')
                        ->visible(fn (Get $get) => in_array($get('question_type'), ['select', 'radio']))
                        ->helperText('Options for select/radio types')
                        ->columnSpanFull(),
                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\Toggle::make('is_required')->label('Required')->default(false),
                        Forms\Components\Toggle::make('is_scored')->label('Scored')->default(true)->live(),
                        Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                    ]),
                ]),
            Forms\Components\Section::make('Scoring Configuration')
                ->collapsible()
                ->collapsed()
                ->visible(fn (Get $get) => (bool) $get('is_scored'))
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('explanation_label')
                            ->label('Explanation Label')
                            ->default('Comments/Recommendations')
                            ->maxLength(255),
                        Forms\Components\TagsInput::make('requires_explanation_on')
                            ->label('Require Explanation When Answer Is')
                            ->placeholder('e.g. No, Partially'),
                    ]),
                    Forms\Components\KeyValue::make('scoring_map')
                        ->label('Scoring Map (Response → Score)')
                        ->keyLabel('Response Value')
                        ->valueLabel('Score')
                        ->addActionLabel('Add Response')
                        ->helperText('Map each response value to a numeric score')
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Conditional Logic')
                ->description('Make this question dependent on another question\'s answer')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('conditional_hint')
                        ->label('')
                        ->content('Use the question code of the parent question. The operator and value determine when THIS question appears.')
                        ->columnSpanFull(),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('conditional_logic_parent')
                            ->label('Parent Question Code')
                            ->options(
                                AssessmentQuestion::where('is_active', true)
                                    ->orderBy('question_code')
                                    ->get()
                                    ->mapWithKeys(fn ($q) => [
                                        $q->question_code => "[{$q->question_code}] {$q->question_text}",
                                    ])
                            )
                            ->searchable()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $set('display_conditions', [
                                        'question_code' => $state,
                                        'operator' => 'equals',
                                        'value' => '',
                                    ]);
                                }
                            })
                            ->afterStateHydrated(function (Forms\Components\Select $component, $record) {
                                if ($record && ! empty($record->display_conditions['question_code'])) {
                                    $component->state($record->display_conditions['question_code']);
                                }
                            }),
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
                            ->placeholder('e.g. Yes'),
                    ]),
                    Forms\Components\Textarea::make('conditional_logic_raw')
                        ->label('Raw JSON (Advanced)')
                        ->rows(4)
                        ->dehydrated(false)
                        ->helperText('For complex AND/OR logic — paste valid JSON here')
                        ->afterStateHydrated(function (Forms\Components\Textarea $component, $record) {
                            if ($record && $record->display_conditions) {
                                $component->state(json_encode($record->display_conditions, JSON_PRETTY_PRINT));
                            }
                        })
                        ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('section.name')
                    ->label('Section')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter()
                    ->width(70),
                Tables\Columns\TextColumn::make('question_code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('group')
                    ->label('Group')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('question_text')
                    ->label('Question')
                    ->searchable()
                    ->limit(70)
                    ->wrap()
                    ->tooltip(fn ($state) => strlen($state) > 70 ? $state : null),
                Tables\Columns\BadgeColumn::make('question_type')
                    ->label('Type')
                    ->colors([
                        'success' => 'yes_no',
                        'info' => 'yes_no_partial',
                        'warning' => 'number',
                        'primary' => 'proportion',
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
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('has_condition')
                    ->label('Cond.')
                    ->state(fn (AssessmentQuestion $r): bool => ! empty($r->display_conditions))
                    ->boolean()
                    ->trueIcon('heroicon-o-link')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (AssessmentQuestion $r): ?string => ! empty($r->display_conditions) ? 'Parent: '.($r->display_conditions['question_code'] ?? 'multi-condition') : null
                    ),
                Tables\Columns\IconColumn::make('is_scored')->label('Scored')->boolean()->alignCenter(),
                Tables\Columns\IconColumn::make('is_required')->label('Req.')->boolean()->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean()->alignCenter(),
                Tables\Columns\TextColumn::make('responses_count')
                    ->label('Responses')
                    ->counts('responses')
                    ->badge()
                    ->color('success')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->defaultSort('assessment_section_id')
            ->filters([
                Tables\Filters\SelectFilter::make('assessment_section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->preload()
                    ->searchable(),
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
                    ]),
                Tables\Filters\Filter::make('has_conditional')
                    ->label('Has Conditional Logic')
                    ->query(fn ($query) => $query->whereNotNull('display_conditions')),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active')->default(true),
                Tables\Filters\TernaryFilter::make('is_scored')->label('Scored'),
                Tables\Filters\TernaryFilter::make('is_required')->label('Required'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_conditional')
                    ->label('Conditions')
                    ->icon('heroicon-o-link')
                    ->color('warning')
                    ->visible(fn (AssessmentQuestion $r) => ! empty($r->display_conditions))
                    ->modalHeading(fn (AssessmentQuestion $r) => "Conditional Logic: {$r->question_code}")
                    ->modalContent(fn (AssessmentQuestion $r) => view('filament.modals.question-conditional-detail', [
                        'question' => $r,
                        'logic' => $r->display_conditions,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('move_to_section')
                    ->label('Move')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('target_section_id')
                            ->label('Target Section')
                            ->options(
                                AssessmentSection::where('is_active', true)
                                    ->orderBy('order')
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (AssessmentQuestion $record, array $data) {
                        $newOrder = (AssessmentQuestion::where('assessment_section_id', $data['target_section_id'])->max('order') ?? 0) + 10;
                        $record->update([
                            'assessment_section_id' => $data['target_section_id'],
                            'order' => $newOrder,
                        ]);
                        $section = AssessmentSection::find($data['target_section_id']);
                        Notification::make()
                            ->title("Moved to '{$section->name}'")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (AssessmentQuestion $record) {
                        if ($record->responses()->count() > 0) {
                            Notification::make()
                                ->title('Cannot delete — has responses')
                                ->body('Deactivate this question instead of deleting it.')
                                ->danger()
                                ->send();

                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_move')
                        ->label('Move to Section')
                        ->icon('heroicon-o-arrows-right-left')
                        ->form([
                            Forms\Components\Select::make('target_section_id')
                                ->label('Target Section')
                                ->options(AssessmentSection::where('is_active', true)->orderBy('order')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $baseOrder = (AssessmentQuestion::where('assessment_section_id', $data['target_section_id'])->max('order') ?? 0) + 10;
                            $i = 0;
                            foreach ($records as $r) {
                                $r->update([
                                    'assessment_section_id' => $data['target_section_id'],
                                    'order' => $baseOrder + ($i++ * 10),
                                ]);
                            }
                            $section = AssessmentSection::find($data['target_section_id']);
                            Notification::make()
                                ->title("{$records->count()} questions moved to '{$section->name}'")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('bulk_group')
                        ->label('Set Group')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Forms\Components\TextInput::make('group')->label('Group Name')->required(),
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
                    Tables\Actions\BulkAction::make('bulk_reorder')
                        ->label('Renumber Order (Selected)')
                        ->icon('heroicon-o-bars-arrow-up')
                        ->requiresConfirmation()
                        ->modalDescription('This will renumber the selected questions in increments of 10, sorted by their current order within each section.')
                        ->action(function (Collection $records) {
                            $bySectionId = $records->groupBy('assessment_section_id');
                            foreach ($bySectionId as $sectionId => $sectionQuestions) {
                                $ordered = $sectionQuestions->sortBy('order')->values();
                                $start = ($ordered->first()->order ?? 0);
                                // Find the lowest existing order in the selection and start from there
                                $i = $start;
                                foreach ($ordered as $q) {
                                    $q->update(['order' => $i]);
                                    $i += 10;
                                }
                            }
                            Notification::make()->title('Questions renumbered')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Questions Found')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PAGES
    // ─────────────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentQuestions::route('/'),
            'create' => Pages\CreateAssessmentQuestion::route('/create'),
            'edit' => Pages\EditAssessmentQuestion::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $inactive = AssessmentQuestion::where('is_active', false)->count();

        return $inactive > 0 ? (string) $inactive.' inactive' : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
}
