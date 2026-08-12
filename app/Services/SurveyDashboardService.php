<?php

namespace App\Services;

use App\Models\Cadre;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\SurveySectionScore;
use App\Services\FormKernel\ScoringEngine;
use Illuminate\Support\Collection;

/**
 * Pure, read-only dashboard aggregation. Never writes to any table and
 * never calls SurveyScoringService/ScoringEngine's scoring methods — only
 * ScoringEngine::calculateGrade() (a pure percentage→grade function) is
 * reused, for the completion meters. Every chart type is decided solely by
 * question_type; there is no per-survey configuration.
 */
class SurveyDashboardService
{
    public static function build(Survey $survey, ?SurveyEvent $event = null): array
    {
        $responsesQuery = SurveyResponse::where('survey_id', $survey->id)->submitted();

        if ($event) {
            $responsesQuery->where('survey_event_id', $event->id);
        }

        $responseIds = $responsesQuery->pluck('id');

        $sections = $survey->sections()->active()->orderBy('order')->get();

        if ($event) {
            $sections = $sections->filter(
                fn (SurveySection $section) => $section->events->isEmpty() || $section->events->contains($event->id)
            )->values();
        }

        $sectionsData = [];

        foreach ($sections as $section) {
            $questions = $section->questions()->active()->orderBy('order')->get();

            if ($questions->isEmpty()) {
                continue;
            }

            $sectionsData[] = [
                'id' => $section->id,
                'name' => $section->name,
                'order' => $section->order,
                'completion' => $section->is_scored ? static::sectionCompletion($section, $responseIds) : null,
                'questions' => $questions->map(
                    fn (SurveyQuestion $question) => static::buildQuestionData($question, $responseIds, $survey, $event)
                )->all(),
            ];
        }

        return [
            'overall_completion' => static::overallCompletion($responseIds),
            'response_count' => $responseIds->count(),
            'events' => $survey->events()->ordered()->get()->map(fn (SurveyEvent $e) => ['id' => $e->id, 'name' => $e->name])->all(),
            'sections' => $sectionsData,
        ];
    }

    protected static function overallCompletion(Collection $responseIds): array
    {
        $scores = SurveySectionScore::whereIn('survey_response_id', $responseIds)->get();

        $answered = (int) $scores->sum('answered_questions');
        $total = (int) $scores->sum('total_questions');
        $percentage = $total > 0 ? round(($answered / $total) * 100, 2) : 0.0;

        return [
            'percentage' => $percentage,
            'grade' => ScoringEngine::calculateGrade($percentage),
            'answered' => $answered,
            'total' => $total,
        ];
    }

    protected static function sectionCompletion(SurveySection $section, Collection $responseIds): ?array
    {
        $scores = SurveySectionScore::where('survey_section_id', $section->id)
            ->whereIn('survey_response_id', $responseIds)
            ->get();

        if ($scores->isEmpty()) {
            return null;
        }

        $percentage = round((float) $scores->avg('percentage'), 2);

        return [
            'percentage' => $percentage,
            'grade' => ScoringEngine::calculateGrade($percentage),
        ];
    }

    /**
     * Dispatches a question to exactly one chart-type aggregator, keyed
     * solely on question_type. Extended in Tasks 2-5 with the remaining
     * chart types; unmatched types (date, datetime, file_upload, signature)
     * fall through to the null/empty default and are still counted toward
     * the completion meters via SurveySectionScore, just never charted.
     */
    protected static function buildQuestionData(SurveyQuestion $question, Collection $responseIds, Survey $survey, ?SurveyEvent $event): array
    {
        [$chart, $data] = match ($question->question_type) {
            'select', 'radio', 'checkbox', 'cadre_select', 'yes_no', 'yes_no_partial', 'rating' => ['bar', static::buildBarData($question, $responseIds)],
            'group_completeness' => ['status_bar', static::buildStatusBarData($question, $responseIds)],
            'number', 'proportion' => ['histogram', static::buildHistogramData($question, $responseIds)],
            'matrix' => ['diverging_stack', static::buildDivergingStackData($question, $responseIds)],
            'repeater' => ['table', static::buildTableData($question, $responseIds)],
            'text', 'short_text', 'email', 'phone' => ['list', static::buildListData($question, $responseIds)],
            default => [null, []],
        };

        return [
            'id' => $question->id,
            'text' => $question->question_text,
            'type' => $question->question_type,
            'chart' => $chart,
            'data' => $data,
            'trend' => null,
        ];
    }

    /**
     * Options come from the question's own configured order wherever one
     * exists. cadre_select has no stored option list (QuestionFieldBuilder
     * renders it from the live Cadre table at form-fill time) so this reads
     * the same live source, in the same order, for consistency with what
     * respondents actually saw.
     */
    protected static function optionsForQuestion(SurveyQuestion $question): array
    {
        return match ($question->question_type) {
            'yes_no' => ['Yes', 'No'],
            'yes_no_partial' => ['Yes', 'No', 'Partially'],
            'rating' => array_map('strval', range(1, $question->validation_rules['max'] ?? 5)),
            'cadre_select' => Cadre::active()->ordered()->pluck('name')->all(),
            default => is_array($question->options) ? $question->options : [],
        };
    }

    /**
     * checkbox stores its answer as a JSON-encoded array (see
     * SurveyFormBuilder::saveResponses()) — every selected option in that
     * array is counted independently, since a respondent can pick more than
     * one. Every other bar-chart type stores a single scalar value.
     */
    protected static function buildBarData(SurveyQuestion $question, Collection $responseIds): array
    {
        $options = static::optionsForQuestion($question);
        $counts = array_fill_keys($options, 0);

        $values = SurveyQuestionResponse::where('survey_question_id', $question->id)
            ->whereIn('survey_response_id', $responseIds)
            ->whereNotNull('response_value')
            ->pluck('response_value');

        foreach ($values as $value) {
            if ($question->question_type === 'checkbox') {
                $decoded = json_decode($value, true);
                foreach (is_array($decoded) ? $decoded : [] as $selected) {
                    if (array_key_exists($selected, $counts)) {
                        $counts[$selected]++;
                    }
                }

                continue;
            }

            if (array_key_exists($value, $counts)) {
                $counts[$value]++;
            }
        }

        return collect($counts)->map(fn (int $count, string $label) => ['label' => $label, 'count' => $count])->values()->all();
    }

    protected static function buildStatusBarData(SurveyQuestion $question, Collection $responseIds): array
    {
        $values = SurveyQuestionResponse::where('survey_question_id', $question->id)
            ->whereIn('survey_response_id', $responseIds)
            ->pluck('response_value');

        return [
            'complete' => $values->filter(fn ($v) => $v === 'Yes')->count(),
            'incomplete' => $values->filter(fn ($v) => $v === 'No')->count(),
        ];
    }

    protected static function buildHistogramData(SurveyQuestion $question, Collection $responseIds): array
    {
        $values = SurveyQuestionResponse::where('survey_question_id', $question->id)
            ->whereIn('survey_response_id', $responseIds)
            ->whereNotNull('response_value')
            ->pluck('response_value')
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (float) $v)
            ->values();

        if ($values->isEmpty()) {
            return ['bins' => [], 'avg' => 0.0, 'min' => 0.0, 'max' => 0.0];
        }

        $min = $values->min();
        $max = $values->max();
        $avg = round($values->avg(), 2);

        if ($max === $min) {
            return ['bins' => [['range' => (string) $min, 'count' => $values->count()]], 'avg' => $avg, 'min' => $min, 'max' => $max];
        }

        $binCount = 5;
        $binWidth = ($max - $min) / $binCount;
        $bins = [];

        for ($i = 0; $i < $binCount; $i++) {
            $lower = $min + $i * $binWidth;
            $upper = $i === $binCount - 1 ? $max : $min + ($i + 1) * $binWidth;

            $count = $values->filter(function (float $v) use ($lower, $upper, $i, $binCount) {
                return $i === $binCount - 1 ? ($v >= $lower && $v <= $upper) : ($v >= $lower && $v < $upper);
            })->count();

            $bins[] = ['range' => round($lower, 1).'–'.round($upper, 1), 'count' => $count];
        }

        return ['bins' => $bins, 'avg' => $avg, 'min' => $min, 'max' => $max];
    }

    /**
     * neutral_index = intdiv(count($columns) - 1, 2). For an odd column
     * count this is the exact middle column (e.g. 3 columns -> index 1,
     * the true center). For an even column count this is the column
     * immediately left of center (e.g. 4 columns -> index 1; the diverging
     * boundary sits between index 1 and index 2). Both cases use the same
     * formula — no odd/even branching needed.
     */
    protected static function buildDivergingStackData(SurveyQuestion $question, Collection $responseIds): array
    {
        $config = is_array($question->options) ? $question->options : [];
        $columns = $config['columns'] ?? [];
        $rows = $config['rows'] ?? [];

        $decodedResponses = SurveyQuestionResponse::where('survey_question_id', $question->id)
            ->whereIn('survey_response_id', $responseIds)
            ->whereNotNull('response_value')
            ->pluck('response_value')
            ->map(fn ($v) => json_decode($v, true))
            ->filter(fn ($decoded) => is_array($decoded));

        $rowsData = [];

        foreach ($rows as $row) {
            $counts = array_fill_keys($columns, 0);

            foreach ($decodedResponses as $decoded) {
                $answer = $decoded[$row['key']] ?? null;

                if ($answer !== null && array_key_exists($answer, $counts)) {
                    $counts[$answer]++;
                }
            }

            $rowsData[] = ['label' => $row['label'], 'counts' => $counts];
        }

        return [
            'rows' => $rowsData,
            'columns' => $columns,
            'neutral_index' => count($columns) > 0 ? intdiv(count($columns) - 1, 2) : 0,
        ];
    }

    protected static function buildListData(SurveyQuestion $question, Collection $responseIds): array
    {
        $responses = SurveyQuestionResponse::where('survey_question_id', $question->id)
            ->whereIn('survey_response_id', $responseIds)
            ->whereNotNull('response_value')
            ->where('response_value', '!=', '')
            ->join('survey_responses', 'survey_responses.id', '=', 'survey_question_responses.survey_response_id')
            ->orderByDesc('survey_responses.submitted_at')
            ->limit(20)
            ->pluck('survey_question_responses.response_value');

        return ['responses' => $responses->all()];
    }

    /**
     * Rows across every response are flattened into one list, capped at 50
     * for page size — the response-level SurveyResponseResource remains the
     * place to see any one response's full, uncapped repeater data.
     */
    protected static function buildTableData(SurveyQuestion $question, Collection $responseIds): array
    {
        $values = SurveyQuestionResponse::where('survey_question_id', $question->id)
            ->whereIn('survey_response_id', $responseIds)
            ->whereNotNull('response_value')
            ->pluck('response_value');

        $allRows = [];
        $responseCount = 0;

        foreach ($values as $value) {
            $decoded = json_decode($value, true);

            if (! is_array($decoded) || empty($decoded)) {
                continue;
            }

            $responseCount++;

            foreach ($decoded as $row) {
                if (is_array($row)) {
                    $allRows[] = $row;
                }
            }
        }

        return [
            'row_count' => count($allRows),
            'response_count' => $responseCount,
            'rows' => array_slice($allRows, 0, 50),
        ];
    }
}
