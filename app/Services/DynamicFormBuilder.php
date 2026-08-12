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
        $fields = [];
        $tableBuffer = [];
        $tableTitle = null;

        $flushTable = function () use (&$fields, &$tableBuffer, &$tableTitle) {
            if ($tableBuffer !== []) {
                $fields[] = static::buildTableFieldset($tableTitle, $tableBuffer);
            }
            $tableBuffer = [];
            $tableTitle = null;
        };

        foreach ($runs as $run) {
            if ($run['group'] === null) {
                $flushTable();
                array_push($fields, ...$run['fields']);

                continue;
            }

            $parts = explode('|', $run['group']);

            if (count($parts) !== 3) {
                // A plain small/large group (no table-row convention) —
                // unaffected by table merging.
                $flushTable();
                $fields[] = static::buildGroupFieldset($run['group'], $run['fields']);

                continue;
            }

            [$title, $rowLabelHeader, $rowLabel] = $parts;

            if ($tableTitle !== null && $tableTitle !== $title) {
                $flushTable();
            }

            $tableTitle = $title;
            $tableBuffer[] = ['header' => $rowLabelHeader, 'label' => $rowLabel, 'fields' => $run['fields']];
        }

        $flushTable();

        return $fields;
    }

    /**
     * A plain group: small groups (<=7 fields — a handful of details about
     * one row, e.g. "Name"/"Contact", or a wider one-row summary like
     * "Total + 6 department counts") lay out side by side, table-style.
     * Larger groups (a kit's many checklist items — the smallest of which
     * has 9 members, safely above this threshold) stay a single readable
     * column — laying out 10+ fields side by side would be unusable.
     */
    protected static function buildGroupFieldset(string $label, array $fields)
    {
        $columns = count($fields) <= 7 ? count($fields) : 1;

        if ($columns > 1) {
            static::normalizeColumnSpans($fields);
        }

        $fieldset = Forms\Components\Fieldset::make($label)
            ->schema($fields)
            // Fieldset's own constructor already calls columns(2) (setting
            // 'lg' => 2 internally), and columns() merges rather than
            // replaces — so setting only 'default' here would leave that
            // stale 'lg' => 2 in place. Every breakpoint must be set
            // explicitly to fully override it and apply $columns at every
            // width, not just >=1024px (which, behind this admin panel's
            // sidebar, the content area frequently doesn't reach anyway).
            ->columns(['default' => $columns, 'sm' => $columns, 'md' => $columns, 'lg' => $columns, 'xl' => $columns, '2xl' => $columns])
            ->columnSpanFull();

        // Only the compact, side-by-side groups (Person In Charge,
        // Respondent) get the bordered "info table" look — a single-column
        // kit checklist reads better as a plain list, not a 1-wide table.
        if ($columns > 1) {
            $fieldset->extraAttributes(['class' => 'aqs-info-table']);
        }

        return $fieldset;
    }

    /**
     * Some field builders (buildTextField(), for one) call
     * ->columnSpanFull() unconditionally — correct for a field standing
     * alone in the main form, but it overrides a table Fieldset's own
     * column count, forcing every field back to full width regardless of
     * how many columns were configured. Resetting to a single-column span
     * here undoes that override so the fields actually sit side by side.
     */
    protected static function normalizeColumnSpans(array $fields): void
    {
        foreach ($fields as $field) {
            if (method_exists($field, 'columnSpan')) {
                $field->columnSpan(1);
            }
        }
    }

    /**
     * Renders $rows as one table with a genuine, dedicated header row (row
     * labels + each column's own field label, all styled via
     * .aqs-header-cell) followed by one full data row per entry in $rows —
     * every data row, including the first, has its own labels hidden and
     * shows only its values. Earlier this let the first data row double as
     * the header (its own visible label serving as the shared column
     * title), which read wrong: that row's row-label cell showed its
     * value (e.g. "Nurses") styled exactly like a column heading. This is
     * how CadreMatrixSyncService's per-cadre Human Resources rows collapse
     * into one table instead of N separate boxed groups.
     */
    protected static function buildTableFieldset(string $title, array $rows)
    {
        $cells = [];

        // Header row: the row-label column's header (e.g. "Cadre") plus
        // one header cell per data column, using the first row's fields
        // purely to read off their label text before every field's own
        // label gets hidden below.
        $cells[] = Forms\Components\Placeholder::make('table_header_rowlabel_'.md5($title))
            ->label($rows[0]['header'])
            ->content('')
            ->extraAttributes(['class' => 'aqs-header-cell']);

        foreach ($rows[0]['fields'] as $field) {
            $cells[] = Forms\Components\Placeholder::make('table_header_col_'.md5($title).'_'.count($cells))
                ->label(method_exists($field, 'getLabel') ? $field->getLabel() : '')
                ->content('')
                ->extraAttributes(['class' => 'aqs-header-cell']);
        }

        foreach ($rows as $index => $row) {
            $rowLabelCell = Forms\Components\Placeholder::make('table_row_label_'.md5($title.$row['label'].$index))
                ->hiddenLabel()
                ->content($row['label']);

            $cells[] = $rowLabelCell;

            foreach ($row['fields'] as $field) {
                if (method_exists($field, 'hiddenLabel')) {
                    $field->hiddenLabel();
                }

                // See normalizeColumnSpans() — undoes any per-field-type
                // forced ->columnSpanFull() so the row's cells actually
                // sit side by side under the shared header.
                if (method_exists($field, 'columnSpan')) {
                    $field->columnSpan(1);
                }

                $cells[] = $field;
            }
        }

        $columnsPerRow = 1 + count($rows[0]['fields']);

        return Forms\Components\Fieldset::make($title)
            ->schema($cells)
            // See buildGroupFieldset()'s comment — every breakpoint must be
            // set explicitly, or Fieldset's own constructor default
            // ('lg' => 2) survives the merge and this collapses to 1
            // column below 1024px.
            ->columns(['default' => $columnsPerRow, 'sm' => $columnsPerRow, 'md' => $columnsPerRow, 'lg' => $columnsPerRow, 'xl' => $columnsPerRow, '2xl' => $columnsPerRow])
            ->extraAttributes(['class' => 'aqs-data-table'])
            ->columnSpanFull();
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
