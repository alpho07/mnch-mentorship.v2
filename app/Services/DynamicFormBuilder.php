<?php

namespace App\Services;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use Filament\Forms;

class DynamicFormBuilder
{
    /**
     * Build form fields for a specific section
     */
    public static function buildForSection(int $sectionId, ?int $assessmentId = null): array
    {
        $questions = AssessmentQuestion::where('assessment_section_id', $sectionId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        if ($questions->isEmpty()) {
            return [
                Forms\Components\Placeholder::make('no_questions')
                    ->label('')
                    ->content('No questions configured for this section yet.')
                    ->columnSpanFull(),
            ];
        }

        $fields = [];

        foreach ($questions as $question) {
            $existingResponse = null;

            if ($assessmentId) {
                $existingResponse = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                    ->where('assessment_question_id', $question->id)
                    ->first();
            }

            $field = static::buildFieldForQuestion($question, $existingResponse);

            if ($field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Build a single field for a question
     */
    protected static function buildFieldForQuestion(AssessmentQuestion $question, ?AssessmentQuestionResponse $existingResponse): mixed
    {
        $fieldName = "question_response_{$question->id}";

        // NBU & Paediatric questions
        if (in_array($question->question_code, ['INFRA_NBU', 'INFRA_PAED'])) {
            return static::buildUnitCapacityField($question, $fieldName, $existingResponse);
        }

        // Build the appropriate field based on question type
        $field = match ($question->question_type) {
            'yes_no' => static::buildYesNoField($question, $fieldName, $existingResponse),
            'yes_no_partial' => static::buildYesNoPartialField($question, $fieldName, $existingResponse),
            'proportion' => static::buildProportionField($question, $fieldName, $existingResponse),
            'number' => static::buildNumberField($question, $fieldName, $existingResponse),
            'text' => static::buildTextField($question, $fieldName, $existingResponse),
            'select' => static::buildSelectField($question, $fieldName, $existingResponse),
            'radio' => static::buildRadioField($question, $fieldName, $existingResponse),
            'mortality_three_month' => static::buildMortalityThreeMonthField($question, $fieldName, $existingResponse),
            default => null,
        };

        // Apply conditional logic if exists
        $conditions = $question->display_conditions;

        if ($field && $conditions) {
            // Decode if it's a JSON string
            if (is_string($conditions)) {
                $conditions = json_decode($conditions, true);
            }

            if (is_array($conditions)) {
                $field = static::applyConditionalLogic($field, $conditions);
            }
        }

        return $field;
    }

    /**
     * Build Yes/No field
     */
    protected static function buildYesNoField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        return static::buildYesNoPartialField($question, $fieldName, $response, ['Yes', 'No']);
    }

    /**
     * Build Yes/No/Partial field
     */
    protected static function buildYesNoPartialField(
        AssessmentQuestion $question,
        string $fieldName,
        ?AssessmentQuestionResponse $response,
        array $options = ['Yes', 'No', 'Partially']
    ) {
        $field = Forms\Components\Radio::make($fieldName)
            ->label($question->question_text)
            ->options(array_combine($options, $options))
            ->required($question->is_required)
            ->inline()
            ->live()
            ->default($response?->response_value);

        if ($question->help_text) {
            $field->helperText($question->help_text);
        }

        $fields = [$field];

        // Explanation field
        $requiresExplanationOn = $question->requires_explanation_on ?? ['No', 'Partially'];
        $requiresExplanationOn = static::normalizeExplanationArray($requiresExplanationOn);

        $explanationField = Forms\Components\Textarea::make("{$fieldName}_explanation")
            ->label($question->explanation_label ?? 'Comments/Recommendations/Remarks')
            ->rows(3)
            ->placeholder('Please provide details, recommendations, or action plans...')
            ->visible(function (Forms\Get $get) use ($fieldName, $requiresExplanationOn) {
                $value = $get($fieldName);

                return in_array($value, $requiresExplanationOn, true);
            })
            ->default($response?->explanation);

        $fields[] = $explanationField;

        return Forms\Components\Group::make($fields)->columnSpanFull();
    }

    /**
     * Normalizes requires_explanation_on so it's ALWAYS an array
     */
    protected static function normalizeExplanationArray($value): array
    {
        if (! $value) {
            return ['No', 'Partially'];
        }

        // JSON?
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // CSV
        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }

        // Single value
        if (! is_array($value)) {
            return [$value];
        }

        return $value;
    }

    /**
     * Build Text field
     */
    protected static function buildTextField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        return Forms\Components\Textarea::make($fieldName)
            ->label($question->question_text)
            ->rows(3)
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text)
            ->columnSpanFull();
    }

    /**
     * Build Number field
     */
    protected static function buildNumberField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        $field = Forms\Components\TextInput::make($fieldName)
            ->label($question->question_text)
            ->numeric()
            ->integer()
            ->required($question->is_required)
            ->default($response?->response_value)
            ->minValue(0);

        if ($question->help_text) {
            $field->helperText($question->help_text);
        }

        if ($question->validation_rules) {
            $rules = is_string($question->validation_rules)
                ? json_decode($question->validation_rules, true)
                : $question->validation_rules;

            if (isset($rules['min'])) {
                $field->minValue($rules['min']);
            }
            if (isset($rules['max'])) {
                $field->maxValue($rules['max'])
                    ->helperText("Maximum value: {$rules['max']}");
            }
        }

        return $field;
    }

    /**
     * Build select field
     */
    protected static function buildSelectField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        $options = $question->options;
        if (is_string($options)) {
            $options = json_decode($options, true) ?? [];
        }

        $optionsArray = is_array($options) ? array_combine($options, $options) : [];

        return Forms\Components\Select::make($fieldName)
            ->label($question->question_text)
            ->options($optionsArray)
            ->required($question->is_required)
            ->searchable()
            ->default($response?->response_value)
            ->helperText($question->help_text)
            ->live();
    }

    /**
     * Build radio field
     */
    protected static function buildRadioField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        return Forms\Components\Radio::make($fieldName)
            ->label($question->question_text)
            ->options(array_combine($question->options ?? [], $question->options ?? []))
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    /**
     * Build the 3-month mortality register field — a trailing 3-month
     * window ending at the current month, each a count. Stored as a JSON
     * object in response_value (e.g. {"aug_2025":2,"sep_2025":2,"oct_2025":2})
     * — the same shape AssessmentPdfReportService already parses for the
     * report. Not scored (data-only) — see DynamicScoringService and
     * saveResponses(), both of which explicitly skip this question type.
     * Legacy plain Yes/No responses (pre-dating this field existing) are
     * tolerated as "not yet answered in the new format" rather than erroring.
     */
    protected static function buildMortalityThreeMonthField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        $existingCounts = [];
        if ($response?->response_value) {
            $decoded = json_decode($response->response_value, true);
            if (is_array($decoded)) {
                $existingCounts = $decoded;
            }
        }

        $fields = [
            Forms\Components\Placeholder::make("{$fieldName}_label")
                ->label('')
                ->content($question->question_text)
                ->columnSpanFull(),
        ];

        $fields[] = Forms\Components\Grid::make(3)->schema(
            collect(static::mortalityMonthKeys())->map(fn (string $key, string $label) => Forms\Components\TextInput::make("{$fieldName}_{$key}")
                ->label($label)
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default($existingCounts[$key] ?? null))->values()->all()
        );

        if ($question->help_text) {
            $fields[0]->content($question->question_text."\n".$question->help_text);
        }

        return Forms\Components\Group::make($fields)->columnSpanFull();
    }

    /**
     * The trailing 3-month window (ending at the current month) used by the
     * mortality register field — shared between field-building and saving
     * so both sides always agree on which sub-field keys exist. Returns
     * label => key, e.g. "Aug 2026" => "aug_2026".
     *
     * @return array<string, string>
     */
    protected static function mortalityMonthKeys(): array
    {
        return collect([2, 1, 0])
            ->map(fn ($monthsAgo) => now()->subMonthsNoOverflow($monthsAgo))
            ->mapWithKeys(fn ($month) => [
                $month->format('M Y') => strtolower($month->format('M')).'_'.$month->format('Y'),
            ])
            ->all();
    }

    /**
     * Build Proportion field
     */
    protected static function buildProportionField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        $metadata = $response?->metadata ?? [];
        $sampleSize = $question->validation_rules['sample_size'] ?? 10;

        return Forms\Components\Group::make([
            Forms\Components\Placeholder::make("{$fieldName}_label")
                ->label('')
                ->content($question->question_text)
                ->columnSpanFull(),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make("{$fieldName}_sample_size")
                    ->label('Sample Size')
                    ->numeric()
                    ->default($metadata['sample_size'] ?? $sampleSize)
                    ->disabled()
                    ->dehydrated(false)
                    ->hint("(Fixed at {$sampleSize})"),
                Forms\Components\TextInput::make("{$fieldName}_positive_count")
                    ->label('Positive Count')
                    ->numeric()
                    ->required()
                    ->default($metadata['positive_count'] ?? 0)
                    ->live()
                    ->minValue(0)
                    ->maxValue($sampleSize)
                    ->afterStateUpdated(function (Forms\Set $set, $state) use ($fieldName, $sampleSize) {
                        if (is_numeric($state) && $state >= 0 && $state <= $sampleSize) {
                            $proportion = ($state / $sampleSize) * 100;
                            $set("{$fieldName}_proportion", number_format($proportion, 1));
                        }
                    }),
                Forms\Components\TextInput::make("{$fieldName}_proportion")
                    ->label('Proportion (%)')
                    ->default($metadata['calculated_proportion'] ?? 0)
                    ->disabled()
                    ->dehydrated(false)
                    ->suffix('%'),
            ]),
        ])->columnSpanFull();
    }

    /**
     * Build NBU/Paediatric Unit Capacity Field
     */
    protected static function buildUnitCapacityField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        $metadata = $response?->metadata ?? [];
        $isNBU = $question->question_code === 'INFRA_NBU';

        $fields = [
            Forms\Components\Radio::make($fieldName)
                ->label($question->question_text)
                ->options(['Yes' => 'Yes', 'No' => 'No'])
                ->required()
                ->inline()
                ->live()
                ->default($response?->response_value),
        ];

        if ($isNBU) {
            $fields[] = Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make("{$fieldName}_nicu_beds")
                    ->label('NICU Beds')
                    ->numeric()
                    ->default($metadata['nicu_beds'] ?? 0)
                    ->visible(fn (Forms\Get $get) => $get($fieldName) === 'Yes'),
                Forms\Components\TextInput::make("{$fieldName}_general_cots")
                    ->label('General Cots')
                    ->numeric()
                    ->default($metadata['general_cots'] ?? 0)
                    ->visible(fn (Forms\Get $get) => $get($fieldName) === 'Yes'),
                Forms\Components\TextInput::make("{$fieldName}_kmc_beds")
                    ->label('KMC Beds')
                    ->numeric()
                    ->default($metadata['kmc_beds'] ?? 0)
                    ->visible(fn (Forms\Get $get) => $get($fieldName) === 'Yes'),
            ]);
        } else {
            $fields[] = Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make("{$fieldName}_general_beds")
                    ->label('General Beds')
                    ->numeric()
                    ->default($metadata['general_beds'] ?? 0)
                    ->visible(fn (Forms\Get $get) => $get($fieldName) === 'Yes'),
                Forms\Components\TextInput::make("{$fieldName}_picu_beds")
                    ->label('PICU Beds')
                    ->numeric()
                    ->default($metadata['picu_beds'] ?? 0)
                    ->visible(fn (Forms\Get $get) => $get($fieldName) === 'Yes'),
            ]);
        }

        return Forms\Components\Group::make($fields)->columnSpanFull();
    }

    /**
     * Save responses
     */
    public static function saveResponses(int $assessmentId, int $sectionId, array $data): void
    {
        $questions = AssessmentQuestion::where('assessment_section_id', $sectionId)
            ->where('is_active', true)
            ->get();

        foreach ($questions as $question) {
            $fieldName = "question_response_{$question->id}";

            // For proportion fields
            if ($question->question_type === 'proportion') {
                if (! array_key_exists("{$fieldName}_positive_count", $data)) {
                    continue;
                }
                $responseValue = null;
            } elseif ($question->question_type === 'mortality_three_month') {
                $monthKeys = array_values(static::mortalityMonthKeys());
                if (! array_key_exists("{$fieldName}_{$monthKeys[0]}", $data)) {
                    continue;
                }
                $responseValue = null;
            } else {
                if (! array_key_exists($fieldName, $data)) {
                    continue;
                }
                $responseValue = $data[$fieldName];
            }

            $explanation = $data["{$fieldName}_explanation"] ?? null;
            $metadata = null;

            // Proportion
            if ($question->question_type === 'proportion') {
                $positiveCount = $data["{$fieldName}_positive_count"] ?? 0;
                $sampleSize = $question->validation_rules['sample_size'] ?? 10;

                $proportion = $sampleSize > 0 ? ($positiveCount / $sampleSize) * 100 : 0;

                $metadata = [
                    'sample_size' => $sampleSize,
                    'positive_count' => $positiveCount,
                    'calculated_proportion' => round($proportion, 2),
                ];

                $responseValue = $positiveCount;
            }

            // Mortality 3-month register — build {month_key: count} JSON,
            // same shape AssessmentPdfReportService already parses.
            if ($question->question_type === 'mortality_three_month') {
                $counts = [];
                foreach (static::mortalityMonthKeys() as $key) {
                    $counts[$key] = (int) ($data["{$fieldName}_{$key}"] ?? 0);
                }
                $responseValue = json_encode($counts);
            }

            // NBU/Paediatric metadata
            if (in_array($question->question_code, ['INFRA_NBU', 'INFRA_PAED'])) {
                if ($responseValue === 'Yes') {
                    if ($question->question_code === 'INFRA_NBU') {
                        $metadata = [
                            'nicu_beds' => (int) ($data["{$fieldName}_nicu_beds"] ?? 0),
                            'general_cots' => (int) ($data["{$fieldName}_general_cots"] ?? 0),
                            'kmc_beds' => (int) ($data["{$fieldName}_kmc_beds"] ?? 0),
                        ];
                    } else {
                        $metadata = [
                            'general_beds' => (int) ($data["{$fieldName}_general_beds"] ?? 0),
                            'picu_beds' => (int) ($data["{$fieldName}_picu_beds"] ?? 0),
                        ];
                    }
                }
            }

            // Score — mortality_three_month is data-only for the PDF
            // report, never scored, regardless of the question's is_scored
            // flag (a 3-count value has no meaningful scoring_map entry).
            $score = null;
            if ($question->question_type !== 'mortality_three_month' && $question->is_scored && $question->scoring_map) {
                $score = $question->scoring_map[$responseValue] ?? 0;
            }

            AssessmentQuestionResponse::updateOrCreate(
                [
                    'assessment_id' => $assessmentId,
                    'assessment_question_id' => $question->id,
                ],
                [
                    'response_value' => $responseValue,
                    'explanation' => $explanation,
                    'metadata' => $metadata,
                    'score' => $score,
                ]
            );
        }

        app(\App\Services\DynamicScoringService::class)->recalculateSectionScore($assessmentId, $sectionId);
    }

    /**
     * Apply conditional logic (OR/AND/single/legacy show_if) using the
     * shared ConditionalLogicEvaluator — the value resolver here reads from
     * Filament's live form state via $get, keyed by the parent question's
     * response field name. Fields are hidden by default unless the
     * evaluator resolves the conditions to true.
     */
    protected static function applyConditionalLogic($field, array $conditionalLogic)
    {
        return $field->visible(function (Forms\Get $get) use ($conditionalLogic) {
            return ConditionalLogicEvaluator::isVisible($conditionalLogic, function (string $questionCode) use ($get) {
                $parentQuestion = AssessmentQuestion::where('question_code', $questionCode)->first();

                if (! $parentQuestion) {
                    return null;
                }

                return $get("question_response_{$parentQuestion->id}");
            });
        });
    }
}
