<?php

namespace App\Services\FormKernel;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\Cadre;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use Filament\Forms;

/**
 * Subject-agnostic question-type-to-Filament-field builders, shared between
 * the facility-assessment engine (AssessmentQuestion) and the generic survey
 * engine (SurveyQuestion) — both models expose identical attribute names for
 * everything referenced here, so a union type is enough; no DTO/adapter
 * layer needed. Two question types stay out of this kernel because they're
 * facility-assessment domain logic, not generic form concerns: NBU/Paediatric
 * unit-capacity (INFRA_NBU/INFRA_PAED question codes) and the 3-month
 * mortality register — both remain in DynamicFormBuilder.
 */
class QuestionFieldBuilder
{
    /**
     * Single dispatch entry point. Returns null for any question_type this
     * kernel doesn't know — callers with their own domain-specific types
     * (DynamicFormBuilder's NBU/mortality handling) check those first and
     * only fall back to this for everything else.
     */
    public static function buildField(AssessmentQuestion|SurveyQuestion $question, AssessmentQuestionResponse|SurveyQuestionResponse|null $response): mixed
    {
        $fieldName = "question_response_{$question->id}";

        return match ($question->question_type) {
            'yes_no' => static::buildYesNoField($question, $fieldName, $response),
            'yes_no_partial' => static::buildYesNoPartialField($question, $fieldName, $response),
            'proportion' => static::buildProportionField($question, $fieldName, $response),
            'number' => static::buildNumberField($question, $fieldName, $response),
            'text' => static::buildTextField($question, $fieldName, $response),
            'select' => static::buildSelectField($question, $fieldName, $response),
            'multi_select' => static::buildMultiSelectField($question, $fieldName, $response),
            'radio' => static::buildRadioField($question, $fieldName, $response),
            'group_completeness' => static::buildGroupCompletenessField($question, $fieldName, $response),
            'repeater' => static::buildRepeaterField($question, $fieldName, $response),
            'cadre_select' => static::buildCadreSelectField($question, $fieldName, $response),
            'short_text' => static::buildShortTextField($question, $fieldName, $response),
            'date' => static::buildDateField($question, $fieldName, $response),
            'datetime' => static::buildDatetimeField($question, $fieldName, $response),
            'email' => static::buildEmailField($question, $fieldName, $response),
            'phone' => static::buildPhoneField($question, $fieldName, $response),
            'checkbox' => static::buildCheckboxField($question, $fieldName, $response),
            'rating' => static::buildRatingField($question, $fieldName, $response),
            'file_upload' => static::buildFileUploadField($question, $fieldName, $response),
            'signature' => static::buildSignatureField($question, $fieldName, $response),
            'matrix' => static::buildMatrixField($question, $fieldName, $response),
            default => null,
        };
    }

    public static function buildYesNoField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return static::buildYesNoPartialField($question, $fieldName, $response, ['Yes', 'No']);
    }

    public static function buildYesNoPartialField(
        AssessmentQuestion|SurveyQuestion $question,
        string $fieldName,
        AssessmentQuestionResponse|SurveyQuestionResponse|null $response,
        array $options = ['Yes', 'No', 'Partially']
    ) {
        $field = Forms\Components\Radio::make($fieldName)
            ->label($question->question_text)
            ->options(array_combine($options, $options))
            ->required($question->is_required)
            ->inline()
            ->live()
            ->default($response?->response_value);

        if ($question instanceof AssessmentQuestion && $question->checklist_id) {
            $field->hintAction(
                Forms\Components\Actions\Action::make("checklist_{$question->id}")
                    ->label('View checklist')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->button()
                    ->outlined()
                    ->modalHeading($question->checklist?->title ?? 'Checklist')
                    ->modalContent(fn () => view('filament.assessment.checklist-modal', [
                        'checklist' => $question->checklist,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
            );
        }

        if ($question->help_text) {
            $field->helperText($question->help_text);
        }

        $fields = [$field];

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

    public static function normalizeExplanationArray($value): array
    {
        if (! $value) {
            return ['No', 'Partially'];
        }

        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            return [$value];
        }

        return $value;
    }

    public static function buildRepeaterField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
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

    public static function buildTextField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\Textarea::make($fieldName)
            ->label($question->question_text)
            ->rows(3)
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text)
            ->columnSpanFull();
    }

    public static function buildShortTextField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\TextInput::make($fieldName)
            ->label($question->question_text)
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    public static function buildNumberField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
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

    public static function buildSelectField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
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
     * A multi-select dropdown — same options shape as buildSelectField, but
     * lets more than one answer be picked (e.g. a facility running both a
     * generator and solar backup). Stored as a JSON array in
     * response_value, same shape read back here — see saveResponses()'s
     * matching multi_select branch for the write side.
     */
    public static function buildMultiSelectField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        $options = $question->options;
        if (is_string($options)) {
            $options = json_decode($options, true) ?? [];
        }

        $optionsArray = is_array($options) ? array_combine($options, $options) : [];

        $selected = [];
        if ($response?->response_value) {
            $decoded = json_decode($response->response_value, true);
            if (is_array($decoded)) {
                $selected = $decoded;
            }
        }

        return Forms\Components\Select::make($fieldName)
            ->label($question->question_text)
            ->options($optionsArray)
            ->multiple()
            ->required($question->is_required)
            ->searchable()
            ->default($selected)
            ->helperText($question->help_text)
            ->live();
    }

    public static function buildCadreSelectField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
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

    public static function buildRadioField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\Radio::make($fieldName)
            ->label($question->question_text)
            ->options(array_combine($question->options ?? [], $question->options ?? []))
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    public static function buildGroupCompletenessField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
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

    public static function buildProportionField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
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

    public static function buildDateField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\DatePicker::make($fieldName)
            ->label($question->question_text)
            ->native(false)
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    public static function buildDatetimeField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\DateTimePicker::make($fieldName)
            ->label($question->question_text)
            ->native(false)
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    public static function buildEmailField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\TextInput::make($fieldName)
            ->label($question->question_text)
            ->email()
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    public static function buildPhoneField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\TextInput::make($fieldName)
            ->label($question->question_text)
            ->tel()
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    /**
     * Multi-select. Selected options round-trip as a JSON-encoded array in
     * response_value, the same convention `repeater` already uses — see
     * SurveyFormBuilder::saveResponses() for the write side.
     */
    public static function buildCheckboxField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        $options = $question->options;
        if (is_string($options)) {
            $options = json_decode($options, true) ?? [];
        }
        $optionsArray = is_array($options) ? array_combine($options, $options) : [];

        $selected = [];
        if ($response?->response_value) {
            $decoded = json_decode($response->response_value, true);
            if (is_array($decoded)) {
                $selected = $decoded;
            }
        }

        return Forms\Components\CheckboxList::make($fieldName)
            ->label($question->question_text)
            ->options($optionsArray)
            ->required($question->is_required)
            ->default($selected)
            ->helperText($question->help_text)
            ->columns(2);
    }

    /**
     * A numeric scale (1..N, N from validation_rules.max, default 5) rendered
     * as inline radio buttons — plain integer response_value, scored the same
     * way any other radio/select question is (via scoring_map).
     */
    public static function buildRatingField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        $max = $question->validation_rules['max'] ?? 5;
        $options = collect(range(1, $max))->mapWithKeys(fn ($n) => [(string) $n => (string) $n])->all();

        return Forms\Components\Radio::make($fieldName)
            ->label($question->question_text)
            ->options($options)
            ->inline()
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    public static function buildFileUploadField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\FileUpload::make($fieldName)
            ->label($question->question_text)
            ->disk('public')
            ->directory('survey-uploads')
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    /**
     * A hand-drawn signature captured on an inline <canvas> (self-contained —
     * no new JS package), round-tripped as a base64 PNG data URI in
     * response_value via the same convention any other free-text-shaped answer
     * uses. See resources/views/filament/forms/components/signature-pad.blade.php.
     */
    public static function buildSignatureField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\ViewField::make($fieldName)
            ->label($question->question_text)
            ->view('filament.forms.components.signature-pad')
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text)
            ->columnSpanFull();
    }

    /**
     * A Likert-style grid: one radio group per row (question->options.rows),
     * all sharing the same column option set (question->options.columns).
     * response_value is a JSON object {rowKey: selectedColumnValue} — the same
     * "JSON object keyed by sub-field" convention mortality_three_month uses.
     */
    public static function buildMatrixField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        $config = is_array($question->options) ? $question->options : [];
        $columns = $config['columns'] ?? [];
        $rows = $config['rows'] ?? [];
        $columnOptions = array_combine($columns, $columns);

        $existing = [];
        if ($response?->response_value) {
            $decoded = json_decode($response->response_value, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $fields = [
            Forms\Components\Placeholder::make("{$fieldName}_label")
                ->label('')
                ->content($question->question_text)
                ->columnSpanFull(),
        ];

        foreach ($rows as $row) {
            $rowKey = $row['key'];
            $fields[] = Forms\Components\Radio::make("{$fieldName}_{$rowKey}")
                ->label($row['label'])
                ->options($columnOptions)
                ->inline()
                ->required($question->is_required)
                ->default($existing[$rowKey] ?? null);
        }

        if ($question->help_text) {
            $fields[0]->content($question->question_text."\n".$question->help_text);
        }

        return Forms\Components\Group::make($fields)->columnSpanFull();
    }
}
