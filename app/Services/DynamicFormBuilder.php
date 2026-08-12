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

        // First pass: collapse consecutive same-`group` questions into
        // "runs" — one run per group occurrence, carrying its built fields
        // alongside the raw `group` string (parsed by buildGroupedField()
        // below into either a plain small-group label or a repeating
        // table row — see its docblock for the `group` string convention).
        $runs = [];
        $currentGroup = null;
        $currentRun = null;
        // A question's own `group` can legitimately be null (ungrouped),
        // which is indistinguishable from the "no run started yet"
        // sentinel above by equality alone — this flag disambiguates the
        // very first question so its run always gets initialized.
        $started = false;

        foreach ($questions as $question) {
            $existingResponse = null;

            if ($assessmentId) {
                $existingResponse = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                    ->where('assessment_question_id', $question->id)
                    ->first();
            }

            $field = static::buildFieldForQuestion($question, $existingResponse);

            if (! $field) {
                continue;
            }

            if (! $started || $question->group !== $currentGroup) {
                if ($currentRun !== null) {
                    $runs[] = $currentRun;
                }
                $currentGroup = $question->group;
                $currentRun = ['group' => $currentGroup, 'fields' => []];
                $started = true;
            }

            $currentRun['fields'][] = $field;
        }

        if ($currentRun !== null) {
            $runs[] = $currentRun;
        }

        return static::renderRuns($runs);
    }

    /**
     * Second pass: turns each run into a rendered field/layout component.
     * Ungrouped runs (`group === null`) render their fields directly.
     * Table-row runs (see buildGroupedField()) that share the same table
     * title AND appear consecutively merge into one table with a single
     * shared header row — everything else renders as its own component.
     */
    protected static function renderRuns(array $runs): array
    {
        return \App\Services\FormKernel\GroupedFieldRenderer::renderRuns($runs);
    }

    protected static function buildGroupFieldset(string $label, array $fields)
    {
        return \App\Services\FormKernel\GroupedFieldRenderer::buildGroupFieldset($label, $fields);
    }

    protected static function normalizeColumnSpans(array $fields): void
    {
        \App\Services\FormKernel\GroupedFieldRenderer::normalizeColumnSpans($fields);
    }

    protected static function buildTableFieldset(string $title, array $rows)
    {
        return \App\Services\FormKernel\GroupedFieldRenderer::buildTableFieldset($title, $rows);
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

        if ($question->question_type === 'mortality_three_month') {
            return static::buildMortalityThreeMonthField($question, $fieldName, $existingResponse);
        }

        $field = \App\Services\FormKernel\QuestionFieldBuilder::buildField($question, $existingResponse);

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

            // Repeater — the field's raw value is already the array of row
            // objects Filament's Repeater submits; store it as one JSON
            // blob, same shape read back by buildRepeaterField() above.
            if ($question->question_type === 'repeater') {
                $responseValue = json_encode(array_values($responseValue ?? []));
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

            // Score — mortality_three_month and repeater are data-only,
            // never scored regardless of the question's is_scored flag
            // (neither a 3-count value nor a row array has a meaningful
            // scoring_map entry).
            $score = null;
            if (! in_array($question->question_type, ['mortality_three_month', 'repeater'], true) && $question->is_scored && $question->scoring_map) {
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
