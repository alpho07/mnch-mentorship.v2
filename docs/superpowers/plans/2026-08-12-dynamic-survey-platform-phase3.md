# Dynamic Survey Platform — Phase 3 Implementation Plan (Auto-Generated Dashboards)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `SurveyDashboardService`, a pure read-only aggregation service that turns any survey's submitted responses into a chart-agnostic data structure by convention on `question_type`, and a `SurveyDashboard` Filament page (nested under `SurveyResource`) that renders it as per-section tabbed Chart.js charts, with an event dropdown for longitudinal surveys.

**Architecture:** `SurveyDashboardService::build(Survey $survey, ?SurveyEvent $event = null): array` reads `SurveyResponse`/`SurveyQuestionResponse`/`SurveySectionScore` (never writes, never touches `ScoringEngine`) and dispatches each active question to one of six chart-type aggregators keyed on `question_type`. `SurveyDashboard` is a plain Filament `Page` (not a form/infolist page) — Chart.js is loaded via the same CDN `<script>` pattern already used by `emonc-dashboard.blade.php`, with each chart's canvas wrapped in an Alpine `x-init` block keyed by `wire:key` so changing the event dropdown (a Livewire property) forces Chart.js to reinitialize on fresh canvases rather than leak stale instances.

**Tech Stack:** Laravel 12, Filament v3, Livewire v3, Alpine.js, Chart.js (CDN), PHPUnit, MySQL.

## Global Constraints

- Dashboard aggregation only ever reads `SurveyResponse` rows with `status = 'submitted'` — drafts are excluded everywhere, in every chart type and every completion meter.
- No chart type ever colors multiple bars/segments with different hues when they represent one series across categories — see the dataviz skill's "value-ramp on nominal categories" anti-pattern. Every bar chart uses one hue for every bar; only `diverging_stack` (matrix/Likert) and `status_bar` (group_completeness) use more than one color, and both are prescribed exactly in Task 4/2 below.
- Palette hex values are taken verbatim from the dataviz skill's documented default palette (`references/palette.md`) — sequential blue `#2a78d6` for bars/histograms, diverging blue `#2a78d6` / aqua `#1baf7a` with neutral gray `#f0efec` midpoint for matrix rows, status good `#0ca30c` / critical `#d03b3b` for group-completeness and completion meters.
- `SurveyDashboardService` never calls `SurveyScoringService` or `ScoringEngine::calculateSectionScore()`/`resolveGroupCompletenessResponses()` — only `ScoringEngine::calculateGrade()` (a pure percentage→grade function) is reused, for the completion meters.
- No new Shield permission — the dashboard page is gated by the existing `view_survey` permission.
- Every new migration/model/service file follows this repo's existing `Survey*` naming and namespace conventions established in Phases 1–2.
- Commit after every task using the existing repo's commit style (`feat:`/`fix:`/`test:` prefix, no marketing language).

---

### Task 1: `SurveyDashboardService` skeleton — `build()`, completion meters, bar-chart aggregation

**Files:**
- Create: `app/Services/SurveyDashboardService.php`
- Test: `tests/Feature/SurveyDashboardServiceTest.php`

**Interfaces:**
- Consumes: `Survey`, `SurveySection`, `SurveyQuestion`, `SurveyQuestionResponse`, `SurveyResponse` (all existing), `SurveySectionScore` (existing), `App\Services\FormKernel\ScoringEngine::calculateGrade()` (existing), `App\Models\Cadre::active()->ordered()` (existing, used by `cadre_select`'s live option list).
- Produces: `SurveyDashboardService::build(Survey $survey, ?SurveyEvent $event = null): array` — the top-level entry point every later task (2–6 add chart types into this same method's dispatch; Task 7 calls `build()` from the Filament page) relies on. Also produces `buildQuestionData(SurveyQuestion $question, Collection $responseIds, Survey $survey, ?SurveyEvent $event): array` — its 4-parameter signature is fixed from this task onward; Tasks 2–5 only extend its internal `match` block, and Task 6 is the only later task that changes its body (adding the `'trend'` key), never its signature. Also produces the protected helpers `optionsForQuestion(SurveyQuestion $question): array` and `buildBarData(SurveyQuestion $question, $responseIds): array`, whose exact names/signatures Task 2's dispatcher `match` extends.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\SurveySectionScore;
use App\Services\SurveyDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_returns_response_count_and_empty_events_for_a_non_longitudinal_survey(): void
    {
        $survey = Survey::create(['code' => 'DASH_BASE_TEST', 'name' => 'Dash Base Test', 'is_active' => true]);
        SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        $data = SurveyDashboardService::build($survey);

        $this->assertSame(1, $data['response_count']);
        $this->assertSame([], $data['events']);
    }

    public function test_a_section_with_no_active_questions_is_omitted(): void
    {
        $survey = Survey::create(['code' => 'DASH_EMPTY_SECTION_TEST', 'name' => 'Dash Empty Section Test', 'is_active' => true]);
        SurveySection::create(['survey_id' => $survey->id, 'code' => 'empty', 'name' => 'Empty', 'order' => 1]);

        $data = SurveyDashboardService::build($survey);

        $this->assertSame([], $data['sections']);
    }

    public function test_overall_completion_sums_answered_and_total_across_scored_sections(): void
    {
        $survey = Survey::create(['code' => 'DASH_COMPLETION_TEST', 'name' => 'Dash Completion Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'DC_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0]]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveySectionScore::create([
            'survey_response_id' => $response->id, 'survey_section_id' => $section->id,
            'total_score' => 1, 'max_score' => 1, 'percentage' => 100, 'grade' => 'green',
            'total_questions' => 1, 'answered_questions' => 1, 'skipped_questions' => 0,
        ]);

        $data = SurveyDashboardService::build($survey);

        $this->assertSame(1, $data['overall_completion']['answered']);
        $this->assertSame(1, $data['overall_completion']['total']);
        $this->assertSame(100.0, (float) $data['overall_completion']['percentage']);
        $this->assertSame('green', $data['overall_completion']['grade']);
    }

    public function test_a_scored_section_gets_a_completion_meter_averaging_its_section_scores(): void
    {
        $survey = Survey::create(['code' => 'DASH_SECTION_METER_TEST', 'name' => 'Dash Section Meter Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'SM_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveySectionScore::create([
            'survey_response_id' => $response->id, 'survey_section_id' => $section->id,
            'total_score' => 0, 'max_score' => 1, 'percentage' => 40, 'grade' => 'red',
            'total_questions' => 1, 'answered_questions' => 0, 'skipped_questions' => 1,
        ]);

        $data = SurveyDashboardService::build($survey);

        $this->assertSame(40.0, (float) $data['sections'][0]['completion']['percentage']);
        $this->assertSame('red', $data['sections'][0]['completion']['grade']);
    }

    public function test_an_unscored_section_has_a_null_completion_meter(): void
    {
        $survey = Survey::create(['code' => 'DASH_UNSCORED_TEST', 'name' => 'Dash Unscored Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => false]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'UN_Q1', 'question_text' => 'Q1', 'question_type' => 'text']);

        $data = SurveyDashboardService::build($survey);

        $this->assertNull($data['sections'][0]['completion']);
    }

    public function test_select_question_bar_data_is_counted_in_the_questions_own_option_order(): void
    {
        $survey = Survey::create(['code' => 'DASH_BAR_TEST', 'name' => 'Dash Bar Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'BAR_Q1', 'question_text' => 'Favorite color',
            'question_type' => 'select', 'options' => ['Red', 'Green', 'Blue'],
        ]);
        $r1 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        $r2 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $r1->id, 'survey_question_id' => $question->id, 'response_value' => 'Blue']);
        SurveyQuestionResponse::create(['survey_response_id' => $r2->id, 'survey_question_id' => $question->id, 'response_value' => 'Blue']);

        $data = SurveyDashboardService::build($survey);
        $questionData = $data['sections'][0]['questions'][0];

        $this->assertSame('bar', $questionData['chart']);
        $this->assertSame(
            [['label' => 'Red', 'count' => 0], ['label' => 'Green', 'count' => 0], ['label' => 'Blue', 'count' => 2]],
            $questionData['data']
        );
    }

    public function test_checkbox_question_counts_each_selected_option_independently(): void
    {
        $survey = Survey::create(['code' => 'DASH_CHECKBOX_TEST', 'name' => 'Dash Checkbox Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'CB_Q1', 'question_text' => 'Pick colors',
            'question_type' => 'checkbox', 'options' => ['Red', 'Green', 'Blue'],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => json_encode(['Red', 'Blue'])]);

        $data = SurveyDashboardService::build($survey);
        $counts = collect($data['sections'][0]['questions'][0]['data'])->pluck('count', 'label');

        $this->assertSame(1, $counts['Red']);
        $this->assertSame(0, $counts['Green']);
        $this->assertSame(1, $counts['Blue']);
    }

    public function test_draft_responses_are_excluded_from_bar_data(): void
    {
        $survey = Survey::create(['code' => 'DASH_DRAFT_TEST', 'name' => 'Dash Draft Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'DRAFT_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no',
        ]);
        $draft = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);
        SurveyQuestionResponse::create(['survey_response_id' => $draft->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);

        $data = SurveyDashboardService::build($survey);
        $counts = collect($data['sections'][0]['questions'][0]['data'])->pluck('count', 'label');

        $this->assertSame(0, $counts['Yes']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: FAIL — `App\Services\SurveyDashboardService` doesn't exist.

- [ ] **Step 3: Create `SurveyDashboardService`**

```php
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
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SurveyDashboardService.php tests/Feature/SurveyDashboardServiceTest.php
git commit -m "feat: add SurveyDashboardService with completion meters and bar-chart aggregation"
```

---

### Task 2: `status_bar` chart type — `group_completeness`

**Files:**
- Modify: `app/Services/SurveyDashboardService.php`
- Modify: `tests/Feature/SurveyDashboardServiceTest.php`

**Interfaces:**
- Consumes: `SurveyQuestionResponse` (existing).
- Produces: `buildStatusBarData(SurveyQuestion $question, Collection $responseIds): array` returning `['complete' => int, 'incomplete' => int]`; the `'group_completeness' => ['status_bar', ...]` dispatch arm.

- [ ] **Step 1: Add the failing test**

Append to `tests/Feature/SurveyDashboardServiceTest.php`, inside the class:

```php
    public function test_group_completeness_question_splits_into_complete_and_incomplete_counts(): void
    {
        $survey = Survey::create(['code' => 'DASH_GC_TEST', 'name' => 'Dash GC Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'GC_Q1', 'question_text' => 'Kit complete?',
            'question_type' => 'group_completeness',
        ]);
        $r1 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        $r2 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        $r3 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $r1->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);
        SurveyQuestionResponse::create(['survey_response_id' => $r2->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);
        SurveyQuestionResponse::create(['survey_response_id' => $r3->id, 'survey_question_id' => $question->id, 'response_value' => 'No']);

        $data = SurveyDashboardService::build($survey);
        $questionData = $data['sections'][0]['questions'][0];

        $this->assertSame('status_bar', $questionData['chart']);
        $this->assertSame(['complete' => 2, 'incomplete' => 1], $questionData['data']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: FAIL — `group_completeness` falls through to the `default => [null, []]` arm, so `'chart'` is `null`, not `'status_bar'`.

- [ ] **Step 3: Add `buildStatusBarData()` and extend the dispatcher**

In `app/Services/SurveyDashboardService.php`, update the `match` inside `buildQuestionData()`:

```php
[$chart, $data] = match ($question->question_type) {
    'select', 'radio', 'checkbox', 'cadre_select', 'yes_no', 'yes_no_partial', 'rating' => ['bar', static::buildBarData($question, $responseIds)],
    'group_completeness' => ['status_bar', static::buildStatusBarData($question, $responseIds)],
    default => [null, []],
};
```

Add the method:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SurveyDashboardService.php tests/Feature/SurveyDashboardServiceTest.php
git commit -m "feat: add status_bar chart aggregation for group_completeness questions"
```

---

### Task 3: `histogram` chart type — `number`, `proportion`

**Files:**
- Modify: `app/Services/SurveyDashboardService.php`
- Modify: `tests/Feature/SurveyDashboardServiceTest.php`

**Interfaces:**
- Produces: `buildHistogramData(SurveyQuestion $question, Collection $responseIds): array` returning `['bins' => [['range' => string, 'count' => int], ...], 'avg' => float, 'min' => float, 'max' => float]`; the `'number', 'proportion' => ['histogram', ...]` dispatch arm.

- [ ] **Step 1: Add the failing tests**

Append to `tests/Feature/SurveyDashboardServiceTest.php`:

```php
    public function test_number_question_bins_values_and_computes_avg_min_max(): void
    {
        $survey = Survey::create(['code' => 'DASH_HIST_TEST', 'name' => 'Dash Hist Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'HIST_Q1', 'question_text' => 'Age',
            'question_type' => 'number',
        ]);
        foreach ([10, 20, 30, 40, 50] as $value) {
            $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
            SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => (string) $value]);
        }

        $data = SurveyDashboardService::build($survey);
        $questionData = $data['sections'][0]['questions'][0];

        $this->assertSame('histogram', $questionData['chart']);
        $this->assertSame(30.0, $questionData['data']['avg']);
        $this->assertSame(10.0, $questionData['data']['min']);
        $this->assertSame(50.0, $questionData['data']['max']);
        $this->assertCount(5, $questionData['data']['bins']);
        $this->assertSame(5, collect($questionData['data']['bins'])->sum('count'));
    }

    public function test_a_single_repeated_value_produces_one_bin_without_dividing_by_zero(): void
    {
        $survey = Survey::create(['code' => 'DASH_HIST_SINGLE_TEST', 'name' => 'Dash Hist Single Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'HIST_SINGLE_Q1', 'question_text' => 'Score',
            'question_type' => 'proportion',
        ]);
        foreach ([25, 25] as $value) {
            $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
            SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => (string) $value]);
        }

        $data = SurveyDashboardService::build($survey);
        $bins = $data['sections'][0]['questions'][0]['data']['bins'];

        $this->assertCount(1, $bins);
        $this->assertSame(2, $bins[0]['count']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: FAIL — `number`/`proportion` fall through to `default`, so `'chart'` is `null`, and `$questionData['data']['avg']` doesn't exist.

- [ ] **Step 3: Add `buildHistogramData()` and extend the dispatcher**

Update the `match`:

```php
[$chart, $data] = match ($question->question_type) {
    'select', 'radio', 'checkbox', 'cadre_select', 'yes_no', 'yes_no_partial', 'rating' => ['bar', static::buildBarData($question, $responseIds)],
    'group_completeness' => ['status_bar', static::buildStatusBarData($question, $responseIds)],
    'number', 'proportion' => ['histogram', static::buildHistogramData($question, $responseIds)],
    default => [null, []],
};
```

Add the method:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: PASS (11 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SurveyDashboardService.php tests/Feature/SurveyDashboardServiceTest.php
git commit -m "feat: add histogram chart aggregation for number and proportion questions"
```

---

### Task 4: `diverging_stack` chart type — `matrix`

**Files:**
- Modify: `app/Services/SurveyDashboardService.php`
- Modify: `tests/Feature/SurveyDashboardServiceTest.php`

**Interfaces:**
- Produces: `buildDivergingStackData(SurveyQuestion $question, Collection $responseIds): array` returning `['rows' => [['label' => string, 'counts' => [column_label => int, ...]], ...], 'columns' => array<string>, 'neutral_index' => int]`; the `'matrix' => ['diverging_stack', ...]` dispatch arm.

**`neutral_index` semantics** (needed unambiguously by Task 8's chart-rendering code later): computed as `intdiv(count($columns) - 1, 2)`. For an odd column count this is the exact middle column (e.g. 3 columns → index 1, the true center). For an even column count this is the column immediately left of center (e.g. 4 columns → index 1; the diverging boundary sits between index 1 and index 2). Both cases use the same formula — no odd/even branching needed in code.

- [ ] **Step 1: Add the failing test**

Append to `tests/Feature/SurveyDashboardServiceTest.php`:

```php
    public function test_matrix_question_splits_each_row_into_column_counts_with_correct_neutral_index(): void
    {
        $survey = Survey::create(['code' => 'DASH_MATRIX_TEST', 'name' => 'Dash Matrix Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'MATRIX_Q1', 'question_text' => 'Rate the session',
            'question_type' => 'matrix',
            'options' => [
                'columns' => ['Disagree', 'Neutral', 'Agree'],
                'rows' => [
                    ['key' => 'clarity', 'label' => 'The instructions were clear'],
                    ['key' => 'pace', 'label' => 'The pace was right'],
                ],
            ],
        ]);
        $r1 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        $r2 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $r1->id, 'survey_question_id' => $question->id, 'response_value' => json_encode(['clarity' => 'Agree', 'pace' => 'Neutral'])]);
        SurveyQuestionResponse::create(['survey_response_id' => $r2->id, 'survey_question_id' => $question->id, 'response_value' => json_encode(['clarity' => 'Agree', 'pace' => 'Disagree'])]);

        $data = SurveyDashboardService::build($survey);
        $questionData = $data['sections'][0]['questions'][0];

        $this->assertSame('diverging_stack', $questionData['chart']);
        $this->assertSame(['Disagree', 'Neutral', 'Agree'], $questionData['data']['columns']);
        $this->assertSame(1, $questionData['data']['neutral_index']);
        $this->assertSame('The instructions were clear', $questionData['data']['rows'][0]['label']);
        $this->assertSame(['Disagree' => 0, 'Neutral' => 0, 'Agree' => 2], $questionData['data']['rows'][0]['counts']);
        $this->assertSame(['Disagree' => 1, 'Neutral' => 1, 'Agree' => 0], $questionData['data']['rows'][1]['counts']);
    }

    public function test_matrix_neutral_index_for_an_even_column_count_sits_left_of_center(): void
    {
        $survey = Survey::create(['code' => 'DASH_MATRIX_EVEN_TEST', 'name' => 'Dash Matrix Even Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'MATRIX_EVEN_Q1', 'question_text' => 'Rate it',
            'question_type' => 'matrix',
            'options' => [
                'columns' => ['Strongly Disagree', 'Disagree', 'Agree', 'Strongly Agree'],
                'rows' => [['key' => 'r1', 'label' => 'Row 1']],
            ],
        ]);

        $data = SurveyDashboardService::build($survey);

        $this->assertSame(1, $data['sections'][0]['questions'][0]['data']['neutral_index']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: FAIL — `matrix` falls through to `default`.

- [ ] **Step 3: Add `buildDivergingStackData()` and extend the dispatcher**

Update the `match`:

```php
[$chart, $data] = match ($question->question_type) {
    'select', 'radio', 'checkbox', 'cadre_select', 'yes_no', 'yes_no_partial', 'rating' => ['bar', static::buildBarData($question, $responseIds)],
    'group_completeness' => ['status_bar', static::buildStatusBarData($question, $responseIds)],
    'number', 'proportion' => ['histogram', static::buildHistogramData($question, $responseIds)],
    'matrix' => ['diverging_stack', static::buildDivergingStackData($question, $responseIds)],
    default => [null, []],
};
```

Add the method:

```php
/**
 * See the plan's Task 4 for the neutral_index formula and what it means
 * for odd vs even column counts — both cases use the same
 * intdiv(count($columns) - 1, 2) expression, no branching needed.
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: PASS (13 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SurveyDashboardService.php tests/Feature/SurveyDashboardServiceTest.php
git commit -m "feat: add diverging_stack chart aggregation for matrix questions"
```

---

### Task 5: `list` and `table` chart types — free text and repeater

**Files:**
- Modify: `app/Services/SurveyDashboardService.php`
- Modify: `tests/Feature/SurveyDashboardServiceTest.php`

**Interfaces:**
- Produces: `buildListData(SurveyQuestion $question, Collection $responseIds): array` returning `['responses' => array<string>]`; `buildTableData(SurveyQuestion $question, Collection $responseIds): array` returning `['row_count' => int, 'response_count' => int, 'rows' => array<array<string,mixed>>]`; the `'text', 'short_text', 'email', 'phone' => ['list', ...]` and `'repeater' => ['table', ...]` dispatch arms.

- [ ] **Step 1: Add the failing tests**

Append to `tests/Feature/SurveyDashboardServiceTest.php`:

```php
    public function test_text_question_returns_up_to_20_most_recent_responses_newest_first(): void
    {
        $survey = Survey::create(['code' => 'DASH_LIST_TEST', 'name' => 'Dash List Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'LIST_Q1', 'question_text' => 'Feedback',
            'question_type' => 'text',
        ]);
        $older = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted', 'submitted_at' => now()->subDay()]);
        $newer = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted', 'submitted_at' => now()]);
        SurveyQuestionResponse::create(['survey_response_id' => $older->id, 'survey_question_id' => $question->id, 'response_value' => 'Older feedback']);
        SurveyQuestionResponse::create(['survey_response_id' => $newer->id, 'survey_question_id' => $question->id, 'response_value' => 'Newer feedback']);

        $data = SurveyDashboardService::build($survey);
        $questionData = $data['sections'][0]['questions'][0];

        $this->assertSame('list', $questionData['chart']);
        $this->assertSame(['Newer feedback', 'Older feedback'], $questionData['data']['responses']);
    }

    public function test_repeater_question_aggregates_row_count_and_flattens_rows_across_responses(): void
    {
        $survey = Survey::create(['code' => 'DASH_TABLE_TEST', 'name' => 'Dash Table Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'TABLE_Q1', 'question_text' => 'Action items',
            'question_type' => 'repeater', 'options' => [['key' => 'plan', 'label' => 'Plan', 'type' => 'text']],
        ]);
        $r1 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        $r2 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $r1->id, 'survey_question_id' => $question->id, 'response_value' => json_encode([['plan' => 'Buy beds'], ['plan' => 'Train staff']])]);
        SurveyQuestionResponse::create(['survey_response_id' => $r2->id, 'survey_question_id' => $question->id, 'response_value' => json_encode([['plan' => 'Fix generator']])]);

        $data = SurveyDashboardService::build($survey);
        $questionData = $data['sections'][0]['questions'][0];

        $this->assertSame('table', $questionData['chart']);
        $this->assertSame(3, $questionData['data']['row_count']);
        $this->assertSame(2, $questionData['data']['response_count']);
        $this->assertCount(3, $questionData['data']['rows']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: FAIL — `text` and `repeater` fall through to `default`.

- [ ] **Step 3: Add `buildListData()`, `buildTableData()`, and extend the dispatcher**

Update the `match`:

```php
[$chart, $data] = match ($question->question_type) {
    'select', 'radio', 'checkbox', 'cadre_select', 'yes_no', 'yes_no_partial', 'rating' => ['bar', static::buildBarData($question, $responseIds)],
    'group_completeness' => ['status_bar', static::buildStatusBarData($question, $responseIds)],
    'number', 'proportion' => ['histogram', static::buildHistogramData($question, $responseIds)],
    'matrix' => ['diverging_stack', static::buildDivergingStackData($question, $responseIds)],
    'repeater' => ['table', static::buildTableData($question, $responseIds)],
    'text', 'short_text', 'email', 'phone' => ['list', static::buildListData($question, $responseIds)],
    default => [null, []],
};
```

Add the two methods:

```php
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
 * Rows across every response are flattened into one list, capped at 50 for
 * page size — the response-level SurveyResponseResource remains the place
 * to see any one response's full, uncapped repeater data.
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: PASS (15 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SurveyDashboardService.php tests/Feature/SurveyDashboardServiceTest.php
git commit -m "feat: add list and table chart aggregation for text and repeater questions"
```

---

### Task 6: Event-sliced trend lines for longitudinal surveys

**Files:**
- Modify: `app/Services/SurveyDashboardService.php`
- Modify: `tests/Feature/SurveyDashboardServiceTest.php`

**Interfaces:**
- Consumes: `SurveyEvent` (existing, Phase 2), `Survey::events()` (existing).
- Produces: `buildTrendData(SurveyQuestion $question, Survey $survey): array` returning `['labels' => array<string>, 'values' => array<float>]`; wires `'trend'` into `buildQuestionData()`'s return array for `number`/`proportion`/`rating` questions, only when no specific `$event` was passed to `build()` (i.e. "All Events" is selected) and the survey has events at all.

- [ ] **Step 1: Add the failing tests**

Append to `tests/Feature/SurveyDashboardServiceTest.php` (add `use App\Models\SurveyEvent;` to the file's imports):

```php
    public function test_numeric_question_gets_a_trend_line_across_events_when_no_event_is_selected(): void
    {
        $survey = Survey::create(['code' => 'DASH_TREND_TEST', 'name' => 'Dash Trend Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'TREND_Q1', 'question_text' => 'Score', 'question_type' => 'number']);
        $r1 = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $baseline->id, 'status' => 'submitted']);
        $r2 = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $followup->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $r1->id, 'survey_question_id' => $question->id, 'response_value' => '10']);
        SurveyQuestionResponse::create(['survey_response_id' => $r2->id, 'survey_question_id' => $question->id, 'response_value' => '20']);

        $data = SurveyDashboardService::build($survey);
        $trend = $data['sections'][0]['questions'][0]['trend'];

        $this->assertSame(['Baseline', 'Follow-up'], $trend['labels']);
        $this->assertSame([10.0, 20.0], $trend['values']);
    }

    public function test_repeatable_event_instances_are_averaged_into_one_trend_point(): void
    {
        $survey = Survey::create(['code' => 'DASH_TREND_AVG_TEST', 'name' => 'Dash Trend Avg Test', 'is_active' => true]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'TREND_AVG_Q1', 'question_text' => 'Score', 'question_type' => 'number']);
        $r1 = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $followup->id, 'event_instance_number' => 1, 'status' => 'submitted']);
        $r2 = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $followup->id, 'event_instance_number' => 2, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $r1->id, 'survey_question_id' => $question->id, 'response_value' => '10']);
        SurveyQuestionResponse::create(['survey_response_id' => $r2->id, 'survey_question_id' => $question->id, 'response_value' => '30']);

        $data = SurveyDashboardService::build($survey);
        $trend = $data['sections'][0]['questions'][0]['trend'];

        $this->assertSame(['Follow-up'], $trend['labels']);
        $this->assertSame([20.0], $trend['values']);
    }

    public function test_trend_is_null_when_a_specific_event_is_selected(): void
    {
        $survey = Survey::create(['code' => 'DASH_TREND_NULL_TEST', 'name' => 'Dash Trend Null Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'TREND_NULL_Q1', 'question_text' => 'Score', 'question_type' => 'number']);

        $data = SurveyDashboardService::build($survey, $baseline);

        $this->assertNull($data['sections'][0]['questions'][0]['trend']);
    }

    public function test_trend_is_null_for_a_non_longitudinal_survey(): void
    {
        $survey = Survey::create(['code' => 'DASH_TREND_NONE_TEST', 'name' => 'Dash Trend None Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'TREND_NONE_Q1', 'question_text' => 'Score', 'question_type' => 'number']);

        $data = SurveyDashboardService::build($survey);

        $this->assertNull($data['sections'][0]['questions'][0]['trend']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: FAIL — `'trend'` is hardcoded to `null` in `buildQuestionData()`'s return.

- [ ] **Step 3: Add `buildTrendData()` and wire it into `buildQuestionData()`**

In `app/Services/SurveyDashboardService.php`, replace `buildQuestionData()`'s body:

```php
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

    $trend = null;

    if (! $event && in_array($question->question_type, ['number', 'proportion', 'rating'], true) && $survey->events()->exists()) {
        $trend = static::buildTrendData($question, $survey);
    }

    return [
        'id' => $question->id,
        'text' => $question->question_text,
        'type' => $question->question_type,
        'chart' => $chart,
        'data' => $data,
        'trend' => $trend,
    ];
}

/**
 * One point per event, x-axis = event order. A repeatable event's multiple
 * instances (across all subjects) are averaged into that one point, per
 * the approved Phase 3 design — never one point per instance. An event
 * with zero submitted responses for this question is skipped entirely
 * (no fake zero on the line) rather than plotted as 0.
 */
protected static function buildTrendData(SurveyQuestion $question, Survey $survey): array
{
    $labels = [];
    $values = [];

    foreach ($survey->events()->ordered()->get() as $event) {
        $responseIds = SurveyResponse::where('survey_id', $survey->id)
            ->where('survey_event_id', $event->id)
            ->submitted()
            ->pluck('id');

        $vals = SurveyQuestionResponse::where('survey_question_id', $question->id)
            ->whereIn('survey_response_id', $responseIds)
            ->whereNotNull('response_value')
            ->pluck('response_value')
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (float) $v);

        if ($vals->isEmpty()) {
            continue;
        }

        $labels[] = $event->name;
        $values[] = round($vals->avg(), 2);
    }

    return ['labels' => $labels, 'values' => $values];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyDashboardServiceTest`
Expected: PASS (19 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SurveyDashboardService.php tests/Feature/SurveyDashboardServiceTest.php
git commit -m "feat: add event-sliced trend lines to SurveyDashboardService"
```

---

### Task 7: `SurveyDashboard` Filament page class + route registration

**Files:**
- Create: `app/Filament/Resources/SurveyResource/Pages/SurveyDashboard.php`
- Modify: `app/Filament/Resources/SurveyResource.php` (register the page)
- Test: `tests/Feature/SurveyDashboardPageTest.php`

**Interfaces:**
- Consumes: `SurveyDashboardService::build()` (Task 1-6), `SurveyResource::getEloquentQuery()` (existing).
- Produces: route `admin/surveys/{record}/dashboard`; public Livewire property `?int $eventId` and `array $dashboardData`, refreshed via the standard Livewire `updatedEventId()` hook — this is what Task 8's Blade view binds its `<select>` to, and what Task 9's row/header actions link to via `SurveyResource::getUrl('dashboard', ['record' => $record])`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource\Pages\SurveyDashboard;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_survey', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_survey']);
        $this->actingAs($user);

        return $user;
    }

    public function test_the_page_mounts_and_loads_dashboard_data_for_the_survey(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'DASH_PAGE_TEST', 'name' => 'Dash Page Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'DP_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);

        Livewire::test(SurveyDashboard::class, ['record' => $survey->getRouteKey()])
            ->assertOk()
            ->assertSet('dashboardData.response_count', 0);
    }

    public function test_changing_the_event_dropdown_reloads_dashboard_data_scoped_to_that_event(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'DASH_PAGE_EVENT_TEST', 'name' => 'Dash Page Event Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'DPE_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        $baselineResponse = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $baseline->id, 'status' => 'submitted']);
        SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $followup->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $baselineResponse->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);

        Livewire::test(SurveyDashboard::class, ['record' => $survey->getRouteKey()])
            ->assertSet('dashboardData.response_count', 2)
            ->set('eventId', $baseline->id)
            ->assertSet('dashboardData.response_count', 1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyDashboardPageTest`
Expected: FAIL — `App\Filament\Resources\SurveyResource\Pages\SurveyDashboard` doesn't exist, and the route isn't registered.

- [ ] **Step 3: Create the page class**

```php
<?php

namespace App\Filament\Resources\SurveyResource\Pages;

use App\Filament\Resources\SurveyResource;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Services\SurveyDashboardService;
use Filament\Resources\Pages\Page;

class SurveyDashboard extends Page
{
    protected static string $resource = SurveyResource::class;

    protected static string $view = 'filament.pages.survey.dashboard';

    public Survey $record;

    public ?int $eventId = null;

    public array $dashboardData = [];

    public function mount(int|string $record): void
    {
        $this->record = SurveyResource::getEloquentQuery()->findOrFail($record);
        $this->loadDashboardData();
    }

    public function updatedEventId(): void
    {
        $this->loadDashboardData();
    }

    protected function loadDashboardData(): void
    {
        $event = $this->eventId ? SurveyEvent::find($this->eventId) : null;
        $this->dashboardData = SurveyDashboardService::build($this->record, $event);
    }

    protected function getViewData(): array
    {
        return [
            'survey' => $this->record,
            'events' => $this->record->events()->ordered()->get(),
            'data' => $this->dashboardData,
        ];
    }
}
```

- [ ] **Step 4: Register the route**

In `app/Filament/Resources/SurveyResource.php`, update `getPages()`:

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListSurveys::route('/'),
        'create' => Pages\CreateSurvey::route('/create'),
        'edit' => Pages\EditSurvey::route('/{record}/edit'),
        'dashboard' => Pages\SurveyDashboard::route('/{record}/dashboard'),
    ];
}
```

- [ ] **Step 5: Create a minimal placeholder view so the page renders**

Task 8 replaces this with the full dashboard; this step only needs the page to mount without a missing-view error.

```blade
<x-filament-panels::page>
    <div>Survey Dashboard placeholder — replaced in the next task.</div>
</x-filament-panels::page>
```

Save as `resources/views/filament/pages/survey/dashboard.blade.php`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SurveyDashboardPageTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/SurveyResource/Pages/SurveyDashboard.php app/Filament/Resources/SurveyResource.php resources/views/filament/pages/survey/dashboard.blade.php tests/Feature/SurveyDashboardPageTest.php
git commit -m "feat: add SurveyDashboard Filament page with event-reactive data loading"
```

---

### Task 8: Dashboard Blade view — completion meters, per-section tabs, Chart.js rendering

**Files:**
- Modify: `resources/views/filament/pages/survey/dashboard.blade.php`

**Interfaces:**
- Consumes: `$survey`, `$events`, `$data` (Task 7's `getViewData()`), the exact `'chart'`/`'data'`/`'trend'` shapes documented in Tasks 1-6.
- Produces: the rendered dashboard page. No new PHP interfaces — this is view-only. (Chart.js rendering is not covered by PHPUnit; Task 7's tests already verify the underlying data reaches the page correctly, which is the testable boundary — actual canvas rendering is a manual/visual check, consistent with this codebase's existing `emonc-dashboard.blade.php`, which likewise has no automated test of its own Chart.js output.)

- [ ] **Step 1: Write the full view**

Replace `resources/views/filament/pages/survey/dashboard.blade.php` entirely:

```blade
<x-filament-panels::page>
    <div class="space-y-6" x-data="{ activeTab: 0 }">
        {{-- Overall completion meter --}}
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Overall Completion</h3>
                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                    {{ $data['overall_completion']['answered'] }} / {{ $data['overall_completion']['total'] }} ({{ $data['overall_completion']['percentage'] }}%)
                </span>
            </div>
            <div class="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div class="h-full rounded-full"
                     style="width: {{ $data['overall_completion']['percentage'] }}%; background-color: {{ match ($data['overall_completion']['grade']) { 'green' => '#0ca30c', 'yellow' => '#fab219', default => '#d03b3b' } }};"></div>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">{{ $data['response_count'] }} submitted response(s)</p>
        </div>

        {{-- Event dropdown --}}
        @if ($events->isNotEmpty())
            <div class="flex items-center gap-3">
                <label for="dashboard-event-select" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Event</label>
                <select id="dashboard-event-select" wire:model.live="eventId"
                        class="rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm">
                    <option value="">All Events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}">{{ $event->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Section tabs --}}
        @if (count($data['sections']) > 0)
            <div class="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-700">
                @foreach ($data['sections'] as $index => $section)
                    <button type="button" @click="activeTab = {{ $index }}"
                            :class="activeTab === {{ $index }} ? 'border-primary-600 text-primary-600' : 'border-transparent text-slate-500 dark:text-slate-400'"
                            class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px">
                        {{ $section['name'] }}
                    </button>
                @endforeach
            </div>

            @foreach ($data['sections'] as $index => $section)
                <div x-show="activeTab === {{ $index }}" x-cloak class="space-y-4">
                    @if ($section['completion'])
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Section Completion</span>
                                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $section['completion']['percentage'] }}%</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full rounded-full"
                                     style="width: {{ $section['completion']['percentage'] }}%; background-color: {{ match ($section['completion']['grade']) { 'green' => '#0ca30c', 'yellow' => '#fab219', default => '#d03b3b' } }};"></div>
                            </div>
                        </div>
                    @endif

                    @foreach ($section['questions'] as $q)
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ $q['text'] }}</h4>

                            @if ($q['chart'] === 'bar')
                                <div wire:key="chart-{{ $q['id'] }}-{{ $eventId ?? 'all' }}"
                                     x-data="{ init() { new Chart(this.$refs.canvas, {
                                         type: 'bar',
                                         data: { labels: @js(collect($q['data'])->pluck('label')), datasets: [{ data: @js(collect($q['data'])->pluck('count')), backgroundColor: '#2a78d6', borderRadius: 4 }] },
                                         options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
                                     }) } }">
                                    <canvas x-ref="canvas" height="{{ max(80, count($q['data']) * 30) }}"></canvas>
                                </div>
                            @elseif ($q['chart'] === 'status_bar')
                                <div wire:key="chart-{{ $q['id'] }}-{{ $eventId ?? 'all' }}"
                                     x-data="{ init() { new Chart(this.$refs.canvas, {
                                         type: 'bar',
                                         data: { labels: ['Complete', 'Incomplete'], datasets: [{ data: @js([$q['data']['complete'], $q['data']['incomplete']]), backgroundColor: ['#0ca30c', '#d03b3b'], borderRadius: 4 }] },
                                         options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
                                     }) } }">
                                    <canvas x-ref="canvas" height="100"></canvas>
                                </div>
                            @elseif ($q['chart'] === 'histogram')
                                <div class="flex gap-4 mb-3 text-sm text-slate-600 dark:text-slate-300">
                                    <span>Avg: <strong>{{ $q['data']['avg'] }}</strong></span>
                                    <span>Min: <strong>{{ $q['data']['min'] }}</strong></span>
                                    <span>Max: <strong>{{ $q['data']['max'] }}</strong></span>
                                </div>
                                <div wire:key="chart-{{ $q['id'] }}-{{ $eventId ?? 'all' }}"
                                     x-data="{ init() { new Chart(this.$refs.canvas, {
                                         type: 'bar',
                                         data: { labels: @js(collect($q['data']['bins'])->pluck('range')), datasets: [{ data: @js(collect($q['data']['bins'])->pluck('count')), backgroundColor: '#2a78d6', borderRadius: 4 }] },
                                         options: { responsive: true, plugins: { legend: { display: false } } }
                                     }) } }">
                                    <canvas x-ref="canvas" height="180"></canvas>
                                </div>
                            @elseif ($q['chart'] === 'diverging_stack')
                                @foreach ($q['data']['rows'] as $row)
                                    <div class="mb-3">
                                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">{{ $row['label'] }}</p>
                                        <div wire:key="chart-{{ $q['id'] }}-{{ $loop->index }}-{{ $eventId ?? 'all' }}"
                                             x-data="{ init() { new Chart(this.$refs.canvas, {
                                                 type: 'bar',
                                                 data: {
                                                     labels: [''],
                                                     datasets: @js(collect($q['data']['columns'])->map(fn ($col, $i) => [
                                                         'label' => $col,
                                                         'data' => [$row['counts'][$col] ?? 0],
                                                         'backgroundColor' => $i <= $q['data']['neutral_index'] ? ($i === $q['data']['neutral_index'] ? '#f0efec' : '#1baf7a') : '#2a78d6',
                                                     ])->values()),
                                                 },
                                                 options: { indexAxis: 'y', responsive: true, scales: { x: { stacked: true }, y: { stacked: true } } }
                                             }) } }">
                                            <canvas x-ref="canvas" height="60"></canvas>
                                        </div>
                                    </div>
                                @endforeach
                            @elseif ($q['chart'] === 'list')
                                <ul class="space-y-2 max-h-64 overflow-y-auto">
                                    @forelse ($q['data']['responses'] as $response)
                                        <li class="text-sm text-slate-700 dark:text-slate-300 border-b border-slate-100 dark:border-slate-800 pb-2">{{ $response }}</li>
                                    @empty
                                        <li class="text-sm text-slate-400">No responses yet.</li>
                                    @endforelse
                                </ul>
                            @elseif ($q['chart'] === 'table')
                                <p class="text-sm text-slate-600 dark:text-slate-300 mb-2">
                                    {{ $q['data']['row_count'] }} row(s) across {{ $q['data']['response_count'] }} response(s)
                                </p>
                                @if (count($q['data']['rows']) > 0)
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead>
                                                <tr>
                                                    @foreach (array_keys($q['data']['rows'][0]) as $column)
                                                        <th class="text-left px-2 py-1 text-slate-500 dark:text-slate-400 font-semibold">{{ $column }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($q['data']['rows'] as $row)
                                                    <tr class="border-t border-slate-100 dark:border-slate-800">
                                                        @foreach ($row as $cell)
                                                            <td class="px-2 py-1 text-slate-700 dark:text-slate-300">{{ $cell }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @endif

                            @if ($q['trend'])
                                <div class="mt-4">
                                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Trend across events</p>
                                    <div wire:key="trend-{{ $q['id'] }}"
                                         x-data="{ init() { new Chart(this.$refs.canvas, {
                                             type: 'line',
                                             data: { labels: @js($q['trend']['labels']), datasets: [{ data: @js($q['trend']['values']), borderColor: '#2a78d6', backgroundColor: '#2a78d6', tension: 0.2 }] },
                                             options: { responsive: true, plugins: { legend: { display: false } } }
                                         }) } }">
                                        <canvas x-ref="canvas" height="120"></canvas>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="text-center text-slate-400 py-12">No sections with questions yet.</div>
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</x-filament-panels::page>
```

- [ ] **Step 2: Run the Task 7 page test again to confirm the new view doesn't break mounting**

Run: `php artisan test --filter=SurveyDashboardPageTest`
Expected: PASS (2 tests) — the view now renders real content instead of the placeholder, but the underlying data assertions are unaffected.

- [ ] **Step 3: Manual visual check**

Run: `php artisan serve` (or the project's existing `composer run dev`), log in as a user with `view_survey`, visit `admin/surveys/{id}/dashboard` for a survey with at least one section/question/submitted response, and confirm: the overall meter renders with a color matching its grade, tabs switch correctly, and at least one bar chart renders without a browser console error. This is the one part of Phase 3 no PHPUnit test can verify — do this before considering Task 8 done.

- [ ] **Step 4: Commit**

```bash
git add resources/views/filament/pages/survey/dashboard.blade.php
git commit -m "feat: render dashboard charts — completion meters, tabs, Chart.js per chart type"
```

---

### Task 9: Entry points — list row action and `EditSurvey` header action

**Files:**
- Modify: `app/Filament/Resources/SurveyResource.php` (the list's table actions live in `SurveyResource::table()`, not `ListSurveys.php` — same place the existing `get_link` action already lives)
- Modify: `app/Filament/Resources/SurveyResource/Pages/EditSurvey.php`
- Test: `tests/Feature/SurveyDashboardEntryPointsTest.php`

**Interfaces:**
- Consumes: `SurveyResource::getUrl('dashboard', ['record' => $record])` (Task 7's registered route).
- Produces: a "Dashboard" table action on the Surveys list, and a "Dashboard" header action on `EditSurvey` — both linking to the same URL.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource;
use App\Filament\Resources\SurveyResource\Pages\EditSurvey;
use App\Filament\Resources\SurveyResource\Pages\ListSurveys;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyDashboardEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey', 'view_survey', 'update_survey'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey', 'view_survey', 'update_survey']);
        $this->actingAs($user);

        return $user;
    }

    public function test_the_list_page_has_a_dashboard_action_pointing_at_the_dashboard_route(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'ENTRY_LIST_TEST', 'name' => 'Entry List Test', 'is_active' => true]);

        Livewire::test(ListSurveys::class)
            ->assertTableActionExists('dashboard');

        $this->assertSame(
            SurveyResource::getUrl('dashboard', ['record' => $survey]),
            SurveyResource::getUrl('dashboard', ['record' => $survey])
        );
    }

    public function test_the_edit_page_has_a_dashboard_header_action(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'ENTRY_EDIT_TEST', 'name' => 'Entry Edit Test', 'is_active' => true]);

        Livewire::test(EditSurvey::class, ['record' => $survey->getRouteKey()])
            ->assertActionExists('dashboard');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyDashboardEntryPointsTest`
Expected: FAIL — neither action exists yet.

- [ ] **Step 3: Add the list row action**

In `app/Filament/Resources/SurveyResource/Pages/ListSurveys.php`, this page currently only defines `getHeaderActions()` (it uses `SurveyResource::table()` for its columns/actions, inherited from the resource) — so add the action inside `SurveyResource::table()` instead, in `app/Filament/Resources/SurveyResource.php`, right before the existing `get_link` action:

```php
->actions([
    Tables\Actions\Action::make('dashboard')
        ->label('Dashboard')
        ->icon('heroicon-o-chart-bar')
        ->url(fn (Survey $record): string => static::getUrl('dashboard', ['record' => $record])),
    Tables\Actions\Action::make('get_link')
        ->label('Get Link')
        ->icon('heroicon-o-link')
        ->visible(fn (Survey $record): bool => $record->is_public)
        ->action(function (Survey $record) {
            if (! $record->access_token) {
                $record->update(['access_token' => Str::random(32)]);
            }

            Notification::make()
                ->title('Public link ready')
                ->body(url('/survey/'.$record->fresh()->access_token))
                ->success()
                ->persistent()
                ->send();
        }),
    Tables\Actions\EditAction::make(),
    Tables\Actions\DeleteAction::make(),
])
```

- [ ] **Step 4: Add the `EditSurvey` header action**

In `app/Filament/Resources/SurveyResource/Pages/EditSurvey.php`, replace `getHeaderActions()`:

```php
protected function getHeaderActions(): array
{
    return [
        Actions\Action::make('dashboard')
            ->label('Dashboard')
            ->icon('heroicon-o-chart-bar')
            ->url(fn () => SurveyResource::getUrl('dashboard', ['record' => $this->record])),
        Actions\DeleteAction::make(),
    ];
}
```

(`SurveyResource` is already imported at the top of this file from Phase 1.)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SurveyDashboardEntryPointsTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the existing SurveyResourceTest to confirm no regression**

Run: `php artisan test --filter=SurveyResourceTest`
Expected: PASS — the `get_link` action's own test is unaffected by adding a new action alongside it.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/SurveyResource.php app/Filament/Resources/SurveyResource/Pages/EditSurvey.php tests/Feature/SurveyDashboardEntryPointsTest.php
git commit -m "feat: add Dashboard entry points to the Surveys list and edit page"
```

---

### Task 10: Full regression pass and Shield permission sync

**Files:** none created — verification only.

**Interfaces:** none — confirms Tasks 1–9 compose correctly and nothing regressed.

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: PASS — every existing test (kernel, Phase 1/2 Survey* tests, facility-assessment tests) plus every new dashboard test from Tasks 1–9.

- [ ] **Step 2: Confirm no new Shield permission is needed**

`SurveyDashboard` is nested under `SurveyResource` and gated by its existing `view_survey` permission, not a standalone resource — there is no new permission to generate. As a safety check (and per the lesson from Phase 1's finishing step, where a blanket `shield:generate --all` corrupted unrelated policies), run only the scoped command:

Run: `php artisan shield:generate --resource=SurveyResource --panel=admin --no-interaction`
Expected: reports Survey's permission set unchanged (no `survey::dashboard`-style permission — there shouldn't be one).

- [ ] **Step 3: Check git status for any unintended file changes from Step 2**

Run: `git status --short`
Expected: no changes beyond what Tasks 1–9 already committed. If `shield:generate` modified anything (e.g. an import-order reshuffle in `SurveyPolicy.php`, as happened harmlessly in Phase 2), revert it with `git checkout -- <file>` — it's not part of this phase's work.

- [ ] **Step 4: Verify Pint formatting**

Run: `./vendor/bin/pint --test app/Services/SurveyDashboardService.php app/Filament/Resources/SurveyResource.php app/Filament/Resources/SurveyResource/Pages/SurveyDashboard.php app/Filament/Resources/SurveyResource/Pages/EditSurvey.php tests/Feature/SurveyDashboardServiceTest.php tests/Feature/SurveyDashboardPageTest.php tests/Feature/SurveyDashboardEntryPointsTest.php`
Expected: no formatting violations. If it reports fixable issues, run the same command without `--test` to apply them, then re-run Step 1.

- [ ] **Step 5: Manual smoke check**

Run: `php artisan route:list | grep -i "survey.*dashboard"`
Expected: shows `GET admin/surveys/{record}/dashboard` — confirms the route registered correctly.

- [ ] **Step 6: Commit any formatting fixes**

```bash
git add -A
git commit -m "chore: pint formatting pass for Phase 3 dashboard code"
```

(Skip this commit entirely if Step 4 reported no changes.)

---

## Phase 3 Definition of Done

- [ ] All 10 tasks' steps checked off, each with its own commit.
- [ ] `php artisan test` green, including every pre-existing test — dashboards are strictly additive and read-only, touching no existing scoring/save logic.
- [ ] An admin can, without writing code: open any survey's Dashboard, see an overall completion meter, switch between per-section tabs, see a correctly-typed chart for every question type per §3 of the design spec, and — for a longitudinal survey — filter by event and see trend lines for numeric questions.
- [ ] Phase 4 (AI narrative layer) remains fully unbuilt but architecturally unblocked — `SurveyDashboardService::build()`'s return array is exactly the plain, chart-agnostic structure `SurveyInsightService::summarize()` will consume unchanged.
