<?php

namespace App\Services;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\Cadre;
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
     * Renders $rows as one table: the first row's fields keep their real
     * labels (acting as the shared column header row, plus $rowLabelHeader
     * from that row for the leading row-label column); every subsequent
     * row's field labels are hidden so they read as bare data cells under
     * that same header. This is how CadreMatrixSyncService's per-cadre
     * Human Resources rows collapse into one table instead of N separate
     * boxed groups.
     */
    protected static function buildTableFieldset(string $title, array $rows)
    {
        $cells = [];

        foreach ($rows as $index => $row) {
            $isFirstRow = $index === 0;

            $rowLabelCell = Forms\Components\Placeholder::make('table_row_label_'.md5($title.$row['label'].$index))
                ->label($isFirstRow ? $row['header'] : '')
                ->hiddenLabel(! $isFirstRow)
                ->content($row['label']);

            // Deliberately NOT tagged .aqs-header-cell even on row 0: this
            // cell's CONTENT is real data (the first cadre's name, e.g.
            // "Nurses") — only its LABEL ("Cadre") is header-like, and
            // Filament already renders that label the same understated way
            // on every field. Darkening the whole cell would make the
            // first cadre's name itself look like a column title.
            $cells[] = $rowLabelCell;

            foreach ($row['fields'] as $field) {
                if (! $isFirstRow) {
                    if (method_exists($field, 'hiddenLabel')) {
                        $field->hiddenLabel();
                    }
                } elseif (method_exists($field, 'extraAttributes')) {
                    $field->extraAttributes(['class' => 'aqs-header-cell']);
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

        // Build the appropriate field based on question type
        $field = match ($question->question_type) {
            'yes_no' => static::buildYesNoField($question, $fieldName, $existingResponse),
            'yes_no_partial' => static::buildYesNoPartialField($question, $fieldName, $existingResponse),
            'proportion' => static::buildProportionField($question, $fieldName, $existingResponse),
            'number' => static::buildNumberField($question, $fieldName, $existingResponse),
            'text' => static::buildTextField($question, $fieldName, $existingResponse),
            'select' => static::buildSelectField($question, $fieldName, $existingResponse),
            'radio' => static::buildRadioField($question, $fieldName, $existingResponse),
            'group_completeness' => static::buildGroupCompletenessField($question, $fieldName, $existingResponse),
            'mortality_three_month' => static::buildMortalityThreeMonthField($question, $fieldName, $existingResponse),
            'repeater' => static::buildRepeaterField($question, $fieldName, $existingResponse),
            'cadre_select' => static::buildCadreSelectField($question, $fieldName, $existingResponse),
            'short_text' => static::buildShortTextField($question, $fieldName, $existingResponse),
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
     * A dynamic add/remove-row table. Column definitions live in
     * $question->options as [{key, label, type, options?}, ...] (type is
     * text|select|date|number; options required for type=select). Every
     * row is stored as one JSON object; the whole set is one JSON array in
     * response_value — see saveResponses() for the write side. Not scored:
     * used for free-form repeating data (action plans, monthly counts,
     * gaps, success stories), never a scoreable answer.
     */
    protected static function buildRepeaterField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        $columns = is_array($question->options) ? $question->options : [];

        $rows = [];
        if ($response?->response_value) {
            $decoded = json_decode($response->response_value, true);
            if (is_array($decoded)) {
                $rows = $decoded;
            }
        }

        $itemSchema = collect($columns)->map(function (array $column) {
            $key = $column['key'];
            $label = $column['label'];

            return match ($column['type'] ?? 'text') {
                'select' => Forms\Components\Select::make($key)
                    ->label($label)
                    ->options(array_combine($column['options'] ?? [], $column['options'] ?? [])),
                'date' => Forms\Components\DatePicker::make($key)->label($label),
                'number' => Forms\Components\TextInput::make($key)->label($label)->numeric(),
                default => Forms\Components\TextInput::make($key)->label($label),
            };
        })->all();

        return Forms\Components\Repeater::make($fieldName)
            ->label($question->question_text)
            ->schema($itemSchema)
            ->columns(max(count($columns), 1))
            ->default($rows)
            ->addActionLabel('Add row')
            ->reorderable(false)
            ->extraAttributes(['class' => 'aqs-repeater-table'])
            ->columnSpanFull();
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
     * A single-line text field (e.g. a person's Name or Contact) — unlike
     * buildTextField()'s Textarea, doesn't force ->columnSpanFull(), so it
     * sits naturally in a compact table row without needing
     * normalizeColumnSpans() to undo anything.
     */
    protected static function buildShortTextField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        return Forms\Components\TextInput::make($fieldName)
            ->label($question->question_text)
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
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
     * A dropdown of live, active Cadre records (the same admin-managed
     * assessment_cadres table CadreMatrixSyncService reads from) — every
     * active cadre regardless of category, since the person answering a
     * survey section could plausibly be any cadre in the system, not just
     * one of a specific template's own buckets (e.g. EmONC's 4). Queried
     * fresh on every render rather than a static seeded options list, so
     * a cadre added after this question was created still shows up
     * without needing to re-seed anything.
     */
    protected static function buildCadreSelectField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        return Forms\Components\Select::make($fieldName)
            ->label($question->question_text)
            ->options(fn () => Cadre::active()->ordered()->pluck('name', 'name'))
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
     * Group-completeness questions aren't user-answerable — their response
     * is derived by DynamicScoringService from sibling questions sharing
     * the same `group`. Rendered as a disabled placeholder; never submitted
     * (no form key), so saveResponses() needs no changes to skip it.
     */
    protected static function buildGroupCompletenessField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response)
    {
        $content = match ($response?->response_value) {
            'Yes' => '✓ Complete',
            'No' => '✗ Incomplete',
            default => 'Not yet calculated — save this section to compute',
        };

        return Forms\Components\Placeholder::make($fieldName)
            ->label($question->question_text)
            ->content($content)
            ->columnSpanFull();
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
