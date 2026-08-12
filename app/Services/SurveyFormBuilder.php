<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveySection;
use App\Services\FormKernel\GroupedFieldRenderer;
use App\Services\FormKernel\QuestionFieldBuilder;
use Filament\Forms;

class SurveyFormBuilder
{
    public static function buildForSection(int $surveySectionId, ?int $surveyResponseId = null): array
    {
        $questions = SurveyQuestion::where('survey_section_id', $surveySectionId)
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

        $runs = [];
        $currentGroup = null;
        $currentRun = null;
        $started = false;

        foreach ($questions as $question) {
            $existingResponse = null;

            if ($surveyResponseId) {
                $existingResponse = SurveyQuestionResponse::where('survey_response_id', $surveyResponseId)
                    ->where('survey_question_id', $question->id)
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

        return GroupedFieldRenderer::renderRuns($runs);
    }

    /**
     * One Filament Section per active survey section, in order — used for
     * a single-page fill-out (admin panel and public link both render the
     * whole survey at once; there's no per-section wizard in Phase 1).
     */
    public static function buildForSurvey(Survey $survey, ?int $surveyResponseId = null, ?\App\Models\SurveyEvent $event = null): array
    {
        $sections = $survey->sections()->active()->orderBy('order')->get();

        if ($event) {
            $sections = $sections->filter(
                fn (SurveySection $section) => $section->events->isEmpty() || $section->events->contains($event->id)
            )->values();
        }

        return $sections->map(fn (SurveySection $section) => Forms\Components\Section::make($section->name)
            ->description($section->description)
            ->schema(static::buildForSection($section->id, $surveyResponseId))
            ->collapsible())->all();
    }

    protected static function buildFieldForQuestion(SurveyQuestion $question, ?SurveyQuestionResponse $existingResponse): mixed
    {
        $field = QuestionFieldBuilder::buildField($question, $existingResponse);

        $conditions = $question->display_conditions;

        if ($field && $conditions) {
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
     * checkbox and matrix are the two survey-only types needing special
     * save handling beyond "read the raw submitted value" — checkbox
     * because its state is an array (JSON-encode it, same convention
     * `repeater` already uses), matrix because it's really N sub-fields
     * (one radio per row) that must be collected back into a single JSON
     * object keyed by row — the same "sub-field" convention
     * mortality_three_month established in DynamicFormBuilder.
     */
    public static function saveResponses(int $surveyResponseId, int $surveySectionId, array $data): void
    {
        $questions = SurveyQuestion::where('survey_section_id', $surveySectionId)
            ->where('is_active', true)
            ->get();

        foreach ($questions as $question) {
            $fieldName = "question_response_{$question->id}";

            if ($question->question_type === 'matrix') {
                $rows = is_array($question->options) ? ($question->options['rows'] ?? []) : [];
                $firstRowKey = $rows[0]['key'] ?? null;

                if ($firstRowKey === null || ! array_key_exists("{$fieldName}_{$firstRowKey}", $data)) {
                    continue;
                }

                $answers = [];
                foreach ($rows as $row) {
                    $answers[$row['key']] = $data["{$fieldName}_{$row['key']}"] ?? null;
                }

                $responseValue = json_encode($answers);
            } else {
                if (! array_key_exists($fieldName, $data)) {
                    continue;
                }

                $responseValue = $data[$fieldName];

                if (in_array($question->question_type, ['repeater', 'checkbox'], true)) {
                    $responseValue = json_encode(array_values($responseValue ?? []));
                }
            }

            $explanation = $data["{$fieldName}_explanation"] ?? null;

            $score = null;
            if (! in_array($question->question_type, ['repeater', 'checkbox', 'matrix', 'file_upload', 'signature'], true)
                && $question->is_scored && $question->scoring_map) {
                $score = $question->scoring_map[$responseValue] ?? 0;
            }

            SurveyQuestionResponse::updateOrCreate(
                ['survey_response_id' => $surveyResponseId, 'survey_question_id' => $question->id],
                ['response_value' => $responseValue, 'explanation' => $explanation, 'score' => $score]
            );
        }

        app(SurveyScoringService::class)->recalculateSectionScore($surveyResponseId, $surveySectionId);
    }

    protected static function applyConditionalLogic($field, array $conditionalLogic)
    {
        return $field->visible(function (Forms\Get $get) use ($conditionalLogic) {
            return ConditionalLogicEvaluator::isVisible($conditionalLogic, function (string $questionCode) use ($get) {
                $parentQuestion = SurveyQuestion::where('question_code', $questionCode)->first();

                if (! $parentQuestion) {
                    return null;
                }

                return $get("question_response_{$parentQuestion->id}");
            });
        });
    }
}
