<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyQuestionResource\Pages;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SurveyQuestionResource extends Resource
{
    protected static ?string $model = SurveyQuestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Survey Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'All Questions';

    protected static ?string $recordTitleAttribute = 'question_text';

    private const TYPE_OPTIONS = [
        'yes_no' => 'Yes / No',
        'yes_no_partial' => 'Yes / No / Partially',
        'number' => 'Number',
        'text' => 'Free Text (multi-line)',
        'short_text' => 'Short Text (single-line)',
        'proportion' => 'Proportion',
        'select' => 'Dropdown Select',
        'radio' => 'Radio Buttons',
        'checkbox' => 'Checkboxes (multi-select)',
        'rating' => 'Rating Scale',
        'date' => 'Date',
        'datetime' => 'Date & Time',
        'email' => 'Email',
        'phone' => 'Phone',
        'file_upload' => 'File Upload',
        'signature' => 'Signature',
        'matrix' => 'Matrix / Likert Grid',
        'repeater' => 'Repeating Rows',
        'cadre_select' => 'Cadre Dropdown',
        'group_completeness' => 'Group Completeness (derived)',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey::question');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey::question');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Question Identity')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('survey_section_id')
                            ->label('Section')
                            ->options(fn () => SurveySection::where('is_active', true)->whereHas('survey')->with('survey')->orderBy('order')->get()
                                ->mapWithKeys(fn ($s) => [$s->id => "{$s->survey->name} — {$s->name}"]))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('question_code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Globally unique — used in conditional logic'),
                        Forms\Components\TextInput::make('order')->numeric()->default(0)->required(),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('group')
                            ->label('Group / Sub-section')
                            ->maxLength(150),
                        Forms\Components\Select::make('question_type')
                            ->options(self::TYPE_OPTIONS)
                            ->required()
                            ->live()
                            ->default('yes_no'),
                    ]),
                    Forms\Components\Textarea::make('question_text')->required()->rows(2)->columnSpanFull(),
                    Forms\Components\Textarea::make('help_text')->rows(2)->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Options & Flags')
                ->schema([
                    Forms\Components\TagsInput::make('options')
                        ->label('Answer Options')
                        ->visible(fn (Get $get) => in_array($get('question_type'), ['select', 'radio', 'checkbox']))
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('options_json')
                        ->label('Options (JSON)')
                        ->visible(fn (Get $get) => in_array($get('question_type'), ['repeater', 'matrix']))
                        ->rows(6)
                        ->dehydrated(false)
                        ->helperText('repeater: [{"key":"...","label":"...","type":"text|select|date|number","options":[...]}] · matrix: {"columns":[...],"rows":[{"key":"...","label":"..."}]}')
                        ->afterStateHydrated(function (Forms\Components\Textarea $component, $record) {
                            if ($record && $record->options) {
                                $component->state(json_encode($record->options, JSON_PRETTY_PRINT));
                            }
                        })
                        ->columnSpanFull(),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('is_required')->default(false),
                        Forms\Components\Toggle::make('is_scored')->default(true)->live(),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ]),
                ]),
            Forms\Components\Section::make('Scoring Configuration')
                ->collapsible()
                ->collapsed()
                ->visible(fn (Get $get) => (bool) $get('is_scored'))
                ->schema([
                    Forms\Components\KeyValue::make('scoring_map')
                        ->label('Scoring Map (Response → Score)')
                        ->keyLabel('Response Value')
                        ->valueLabel('Score')
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Conditional Logic')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('conditional_logic_parent')
                            ->label('Parent Question Code')
                            ->options(fn () => SurveyQuestion::where('is_active', true)->orderBy('question_code')->get()
                                ->mapWithKeys(fn ($q) => [$q->question_code => "[{$q->question_code}] {$q->question_text}"]))
                            ->searchable()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $set('display_conditions', ['question_code' => $state, 'operator' => 'equals', 'value' => '']);
                                }
                            })
                            ->afterStateHydrated(function (Forms\Components\Select $component, $record) {
                                if ($record && ! empty($record->display_conditions['question_code'])) {
                                    $component->state($record->display_conditions['question_code']);
                                }
                            }),
                        Forms\Components\Select::make('display_conditions.operator')
                            ->options(['equals' => 'Equals', 'not_equals' => 'Not Equals', 'in' => 'Is One Of', 'not_in' => 'Is Not One Of', 'greater_than' => 'Greater Than', 'less_than' => 'Less Than'])
                            ->default('equals'),
                        Forms\Components\TextInput::make('display_conditions.value')->label('Trigger Value'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('section.survey.name')->label('Survey')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('section.name')->label('Section')->badge()->color('primary')->searchable(),
                Tables\Columns\TextColumn::make('order')->sortable()->alignCenter()->width(70),
                Tables\Columns\TextColumn::make('question_code')->badge()->color('gray')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('question_text')->searchable()->limit(70)->wrap(),
                Tables\Columns\TextColumn::make('question_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::TYPE_OPTIONS[$state] ?? $state),
                Tables\Columns\IconColumn::make('is_scored')->boolean()->alignCenter(),
                Tables\Columns\IconColumn::make('is_required')->boolean()->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->alignCenter(),
            ])
            ->defaultSort('survey_section_id')
            ->filters([
                Tables\Filters\SelectFilter::make('survey_section_id')->label('Section')->relationship('section', 'name')->preload()->searchable(),
                Tables\Filters\SelectFilter::make('question_type')->options(self::TYPE_OPTIONS),
                Tables\Filters\TernaryFilter::make('is_active')->default(true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (SurveyQuestion $record) {
                        if ($record->responses()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot delete — has responses')
                                ->body('Deactivate this question instead.')
                                ->danger()
                                ->send();

                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveyQuestions::route('/'),
            'create' => Pages\CreateSurveyQuestion::route('/create'),
            'edit' => Pages\EditSurveyQuestion::route('/{record}/edit'),
        ];
    }
}
