<?php

namespace App\Services\FormKernel;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use Filament\Forms;

/**
 * Renders an indicator section as a three-column table — question text on
 * the left, the count in the middle, and a "N/A" checkbox on the right —
 * rather than as the stacked label-above-input fields the generic
 * question_group layout produces.
 *
 * Indicator sections are long runs of same-shaped numeric questions (the
 * newborn/paediatric section is 36 of them), which read as a register far
 * better than as a column of individually-labelled inputs.
 *
 * The third column writes AssessmentQuestionResponse::not_applicable,
 * which is a distinct state from both "blank" and "0": blank means the
 * count hasn't been collected yet, 0 means it was collected and was zero,
 * and not_applicable means this facility will never have a count here.
 * Only the last renders as N/A in the report.
 */
class IndicatorTableRenderer
{
    /**
     * Section codes rendered this way. Kept as a list rather than a column
     * on assessment_sections because exactly one section wants this today —
     * the same way DynamicFormBuilder already special-cases
     * `emonc_facility_context` and the INFRA_NBU/INFRA_PAED questions. If a
     * second template needs it, this becomes a section attribute.
     */
    public const SECTION_CODES = ['newborn_paediatric_indicators'];

    public static function handles(?string $sectionCode): bool
    {
        return $sectionCode !== null && in_array($sectionCode, self::SECTION_CODES, true);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AssessmentQuestion>  $questions
     * @return array<int, mixed>
     */
    public static function build($questions, ?int $assessmentId): array
    {
        $responses = $assessmentId
            ? AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                ->whereIn('assessment_question_id', $questions->pluck('id'))
                ->get()
                ->keyBy('assessment_question_id')
            : collect();

        $rows = [
            Forms\Components\View::make('filament.pages.assessment.indicator-table-styles')
                ->columnSpanFull(),
            static::noteRow(),
            static::headerRow(),
        ];

        // Restarts at 1 under each heading band, so the numbering matches
        // how the Newborn and Paediatric registers are read as two separate
        // lists — same per-group numbering the commodity matrix uses.
        $number = 0;

        foreach ($questions as $question) {
            if ($question->question_type === 'heading') {
                $rows[] = static::bandRow($question);
                $number = 0;

                continue;
            }

            $number++;
            $rows[] = static::questionRow($question, $responses->get($question->id), $number);
        }

        return [
            Forms\Components\Section::make()
                ->schema($rows)
                ->extraAttributes(['class' => 'aqs-ind-table'])
                ->columnSpanFull(),
        ];
    }

    /**
     * Explains the third column before the assessor meets it. The
     * blank/zero/N-A distinction is the whole point of the column and is
     * not self-evident from a checkbox with no label, so it's stated on the
     * form rather than left to training.
     */
    private static function noteRow()
    {
        return Forms\Components\Placeholder::make('ind_note')
            ->label('')
            ->content(new \Illuminate\Support\HtmlString(
                '<strong>About the N/A column:</strong> leave it unticked and enter the count for every '
                .'indicator this facility collects — enter <strong>0</strong> where the count really was zero. '
                .'Tick <strong>N/A</strong> only when the indicator does not apply to this facility at all '
                .'(for example a service it does not offer). A ticked row clears its count, is reported as '
                .'<strong>N/A</strong> rather than 0, and is left out of any percentage calculated from it.'
            ))
            ->extraAttributes(['class' => 'aqs-ind-note'])
            ->columnSpanFull();
    }

    private static function headerRow()
    {
        return Forms\Components\Grid::make(12)
            ->schema([
                static::cell('ind_head_question', 'Indicator', 7),
                static::cell('ind_head_value', 'Number', 3),
                static::cell('ind_head_na', 'N/A', 2),
            ])
            ->extraAttributes(['class' => 'aqs-ind-row aqs-ind-head'])
            ->columnSpanFull();
    }

    private static function bandRow(AssessmentQuestion $question)
    {
        return Forms\Components\Grid::make(1)
            ->schema([
                Forms\Components\Placeholder::make("question_response_{$question->id}")
                    ->label('')
                    ->content($question->question_text)
                    ->columnSpanFull(),
            ])
            ->extraAttributes(['class' => 'aqs-ind-row aqs-ind-band'])
            ->columnSpanFull();
    }

    private static function questionRow(AssessmentQuestion $question, ?AssessmentQuestionResponse $response, int $number)
    {
        $fieldName = "question_response_{$question->id}";
        $naField = "{$fieldName}_not_applicable";

        $value = Forms\Components\TextInput::make($fieldName)
            ->label('')
            ->numeric()
            ->integer()
            ->minValue(0)
            ->default($response?->response_value)
            ->placeholder('—')
            ->disabled(fn (Forms\Get $get) => (bool) $get($naField))
            // Must come after disabled(): Filament derives dehydration from
            // the disabled state, so setting this first just gets
            // overwritten and the ticked row vanishes from the submitted
            // data. saveResponses() also keys off the N/A field for the
            // same reason, so this is belt and braces.
            ->dehydrated(true)
            ->columnSpan(3);

        if ($question->help_text) {
            $value->helperText($question->help_text);
        }

        $notApplicable = Forms\Components\Checkbox::make($naField)
            ->label('')
            ->default((bool) $response?->not_applicable)
            ->live()
            // Clearing on tick means the stored count and the checkbox can
            // never disagree about what this row says.
            ->afterStateUpdated(function (bool $state, Forms\Set $set) use ($fieldName) {
                if ($state) {
                    $set($fieldName, null);
                }
            })
            ->columnSpan(2);

        return Forms\Components\Grid::make(12)
            ->schema([
                static::cell("ind_label_{$question->id}", "{$number}. {$question->question_text}", 7),
                $value,
                $notApplicable,
            ])
            ->extraAttributes(['class' => 'aqs-ind-row'])
            ->columnSpanFull();
    }

    private static function cell(string $name, string $text, int $span)
    {
        return Forms\Components\Placeholder::make($name)
            ->label('')
            ->content($text)
            ->columnSpan($span);
    }
}
