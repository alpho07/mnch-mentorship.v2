# Dynamic Survey Platform — Phase 4 Implementation Plan (AI Narrative Layer)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `SurveyInsightService::summarize()`, a pure function that turns `SurveyDashboardService::build()`'s already-computed output into a plain-English narrative via the Claude API, and wire an on-demand "Generate Summary" button into the existing `SurveyDashboard` Filament page.

**Architecture:** `SurveyInsightService` never queries the database — its only input is the array `SurveyDashboardService::build()` already produced. It serializes that array into compact plain text, sends it to Claude using the exact call shape already proven in `app/Http/Controllers/Api/ChatController.php` (same timeout, `max_tokens`, and resilience contract), and always returns a string, never throwing. The `SurveyDashboard` page gains ephemeral Livewire state (`?string $summary`) — nothing is persisted; the summary clears whenever the event dropdown changes so it can never describe a data slice the visible charts have moved past.

**Tech Stack:** Laravel 12, Filament v3, Livewire v3, PHPUnit, `Illuminate\Support\Facades\Http` (faked in tests via `Http::fake()`, the same pattern already used in `tests/Unit/LlmMentorshipAssistantServiceTest.php`).

## Global Constraints

- `SurveyInsightService::summarize()` never throws — every failure path (no responses yet, missing API key, failed HTTP response, thrown exception) returns a friendly string, matching `ChatController::assistant()`'s existing resilience contract exactly.
- The Claude call uses `'model' => 'claude-sonnet-4-20250514'`, `'max_tokens' => 1000`, and a 30-second timeout — identical to `ChatController`'s existing values, not new ones.
- `response_count === 0` short-circuits before any HTTP call is made — verified in tests via `Http::assertNothingSent()`.
- No new migration, no new table column — the summary lives only in `SurveyDashboard`'s Livewire component state for that page load.
- `SurveyDashboardService`, `SurveyScoringService`, `ScoringEngine`, `SurveyFormBuilder`, and every earlier phase's file are out of scope — this plan only adds one new service and extends `SurveyDashboard`'s page class + Blade view.
- Commit after every task using the existing repo's commit style (`feat:`/`fix:`/`test:` prefix, no marketing language).

---

### Task 1: `SurveyInsightService` — serialization, Claude call, full resilience

**Files:**
- Create: `app/Services/SurveyInsightService.php`
- Test: `tests/Feature/SurveyInsightServiceTest.php`

**Interfaces:**
- Consumes: the exact `SurveyDashboardService::build()` return shape from Phase 3 — `overall_completion` (`percentage`, `grade`, `answered`, `total`), `response_count`, `events`, and `sections[]` (each with `id`, `name`, `order`, `completion`, `questions[]`, and each question with `id`, `text`, `type`, `chart`, `data`, `trend`).
- Produces: `SurveyInsightService::summarize(array $dashboardData): string` — the single public entry point Task 2's `SurveyDashboard::generateSummary()` calls.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Services\SurveyInsightService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SurveyInsightServiceTest extends TestCase
{
    private function dashboardData(array $overrides = []): array
    {
        return array_merge([
            'overall_completion' => ['percentage' => 78.0, 'grade' => 'yellow', 'answered' => 39, 'total' => 50],
            'response_count' => 42,
            'events' => [],
            'sections' => [
                [
                    'id' => 1, 'name' => 'Infrastructure', 'order' => 1,
                    'completion' => ['percentage' => 85.0, 'grade' => 'green'],
                    'questions' => [
                        [
                            'id' => 10, 'text' => 'Does the facility have a delivery room?', 'type' => 'yes_no',
                            'chart' => 'bar', 'data' => [['label' => 'Yes', 'count' => 38], ['label' => 'No', 'count' => 4]],
                            'trend' => null,
                        ],
                    ],
                ],
            ],
        ], $overrides);
    }

    public function test_returns_a_fixed_message_when_there_are_no_responses_yet(): void
    {
        Http::fake();

        $result = SurveyInsightService::summarize($this->dashboardData(['response_count' => 0]));

        $this->assertSame('Not enough data yet to generate a summary — no responses have been submitted.', $result);
        Http::assertNothingSent();
    }

    public function test_returns_a_fallback_message_when_the_api_key_is_not_configured(): void
    {
        config(['services.anthropic.api_key' => null]);
        Http::fake();

        $result = SurveyInsightService::summarize($this->dashboardData());

        $this->assertSame('AI summary is not configured yet. Please ask the administrator to set the ANTHROPIC_API_KEY.', $result);
        Http::assertNothingSent();
    }

    public function test_returns_the_models_narrated_text_on_a_successful_response(): void
    {
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Completion is strong overall, with Infrastructure leading at 85%.']],
            ]),
        ]);

        $result = SurveyInsightService::summarize($this->dashboardData());

        $this->assertSame('Completion is strong overall, with Infrastructure leading at 85%.', $result);
    }

    public function test_returns_a_fallback_message_when_the_request_fails(): void
    {
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'server error'], 500),
        ]);

        $result = SurveyInsightService::summarize($this->dashboardData());

        $this->assertSame('Sorry, the summary is temporarily unavailable. Please try again later.', $result);
    }

    public function test_returns_a_fallback_message_when_the_request_throws(): void
    {
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $result = SurveyInsightService::summarize($this->dashboardData());

        $this->assertSame('Sorry, the summary is temporarily unavailable. Please try again later.', $result);
    }

    public function test_the_prompt_includes_the_dashboard_data(): void
    {
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']]]),
        ]);

        SurveyInsightService::summarize($this->dashboardData());

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $prompt = $request->data()['messages'][0]['content'];

            return str_contains($prompt, '42 submitted responses')
                && str_contains($prompt, 'Infrastructure')
                && str_contains($prompt, 'Does the facility have a delivery room?')
                && str_contains($prompt, 'Yes: 38')
                && $request->data()['model'] === 'claude-sonnet-4-20250514'
                && $request->data()['max_tokens'] === 1000;
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyInsightServiceTest`
Expected: FAIL — `App\Services\SurveyInsightService` doesn't exist.

- [ ] **Step 3: Create `SurveyInsightService`**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Never queries the database — the only input is the array
 * SurveyDashboardService::build() already produced. Always returns a
 * string; every failure path (no data yet, missing key, failed request,
 * thrown exception) returns a friendly fallback rather than throwing,
 * matching ChatController::assistant()'s existing resilience contract.
 */
class SurveyInsightService
{
    public static function summarize(array $dashboardData): string
    {
        if (($dashboardData['response_count'] ?? 0) === 0) {
            return 'Not enough data yet to generate a summary — no responses have been submitted.';
        }

        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            return 'AI summary is not configured yet. Please ask the administrator to set the ANTHROPIC_API_KEY.';
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 1000,
                'system' => static::systemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => static::formatDashboardData($dashboardData)],
                ],
            ]);

            if ($response->failed()) {
                return 'Sorry, the summary is temporarily unavailable. Please try again later.';
            }

            $content = $response->json('content', []);
            $text = collect($content)->pluck('text')->filter()->implode('');

            return $text ?: 'Sorry, the summary is temporarily unavailable. Please try again later.';
        } catch (\Exception $e) {
            return 'Sorry, the summary is temporarily unavailable. Please try again later.';
        }
    }

    protected static function systemPrompt(): string
    {
        return 'You are summarizing survey dashboard data for an administrator. '
            .'Narrate only using the exact numbers given below — never invent, estimate, '
            .'or recompute a statistic not present in the data. Call out sections with low '
            .'completion or a yellow/red grade, and any question whose distribution looks '
            .'skewed or otherwise worth attention. Keep your response to a few short paragraphs.';
    }

    /**
     * Plain-language text, not raw JSON — JSON's punctuation overhead
     * spends tokens the model doesn't need. Sections/questions with a null
     * chart type (Phase 3's explicitly uncharted types: date, datetime,
     * file_upload, signature) are skipped — they carry no chartable data
     * to narrate.
     */
    protected static function formatDashboardData(array $dashboardData): string
    {
        $lines = [];

        $overall = $dashboardData['overall_completion'] ?? ['percentage' => 0, 'grade' => 'red'];
        $lines[] = "Survey: {$dashboardData['response_count']} submitted responses. Overall completion: {$overall['percentage']}% ({$overall['grade']}).";
        $lines[] = '';

        foreach ($dashboardData['sections'] ?? [] as $section) {
            $completionText = $section['completion']
                ? " (completion: {$section['completion']['percentage']}%, {$section['completion']['grade']})"
                : '';
            $lines[] = "Section \"{$section['name']}\"{$completionText}:";

            foreach ($section['questions'] ?? [] as $question) {
                $line = static::formatQuestionLine($question);

                if ($line !== null) {
                    $lines[] = $line;
                }

                if (! empty($question['trend'])) {
                    $pairs = collect($question['trend']['labels'])
                        ->map(fn ($label, $i) => "{$label}: {$question['trend']['values'][$i]}")
                        ->implode(', ');
                    $lines[] = "  Trend across events: {$pairs}";
                }
            }

            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    protected static function formatQuestionLine(array $question): ?string
    {
        $text = $question['text'];

        return match ($question['chart']) {
            'bar' => '- "'.$text.'" (bar): '.collect($question['data'])->map(fn ($row) => "{$row['label']}: {$row['count']}")->implode(', '),
            'status_bar' => '- "'.$text.'" (status_bar): Complete: '.$question['data']['complete'].', Incomplete: '.$question['data']['incomplete'],
            'histogram' => '- "'.$text.'" (histogram): avg '.$question['data']['avg'].', min '.$question['data']['min'].', max '.$question['data']['max'],
            'diverging_stack' => '- "'.$text.'" (diverging_stack): '.collect($question['data']['rows'])->map(
                fn ($row) => 'row "'.$row['label'].'": '.collect($row['counts'])->map(fn ($count, $col) => "{$col}: {$count}")->implode(', ')
            )->implode('; '),
            'list' => '- "'.$text.'" (list): '.count($question['data']['responses']).' free-text response(s) on file',
            'table' => '- "'.$text.'" (table): '.$question['data']['row_count'].' row(s) across '.$question['data']['response_count'].' response(s)',
            default => null,
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyInsightServiceTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SurveyInsightService.php tests/Feature/SurveyInsightServiceTest.php
git commit -m "feat: add SurveyInsightService for AI-narrated dashboard summaries"
```

---

### Task 2: Wire "Generate Summary" into `SurveyDashboard`

**Files:**
- Modify: `app/Filament/Resources/SurveyResource/Pages/SurveyDashboard.php`
- Modify: `resources/views/filament/pages/survey/dashboard.blade.php`
- Test: `tests/Feature/SurveyDashboardSummaryTest.php`

**Interfaces:**
- Consumes: `SurveyInsightService::summarize()` (Task 1), the page's existing `$dashboardData` property and `updatedEventId()` hook (Phase 3).
- Produces: public `?string $summary = null`, public `bool $generatingSummary = false`, and `generateSummary(): void` on `SurveyDashboard` — no other file depends on these, this is the final consumer in the chain.

- [ ] **Step 1: Write the failing tests**

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
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_survey', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_survey']);
        $this->actingAs($user);

        return $user;
    }

    public function test_generating_a_summary_sets_it_on_the_page(): void
    {
        $this->actingAdmin();
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'All sections are on track.']]]),
        ]);

        $survey = Survey::create(['code' => 'SUMMARY_TEST', 'name' => 'Summary Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'SUM_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);

        Livewire::test(SurveyDashboard::class, ['record' => $survey])
            ->assertSet('summary', null)
            ->call('generateSummary')
            ->assertSet('summary', 'All sections are on track.');
    }

    public function test_changing_the_event_dropdown_clears_an_existing_summary(): void
    {
        $this->actingAdmin();
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Summary text.']]]),
        ]);

        $survey = Survey::create(['code' => 'SUMMARY_CLEAR_TEST', 'name' => 'Summary Clear Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'SUM_CLEAR_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $event->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);

        Livewire::test(SurveyDashboard::class, ['record' => $survey])
            ->call('generateSummary')
            ->assertSet('summary', 'Summary text.')
            ->set('eventId', $event->id)
            ->assertSet('summary', null);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyDashboardSummaryTest`
Expected: FAIL — `generateSummary` method and `summary` property don't exist yet.

- [ ] **Step 3: Add the property and method to `SurveyDashboard`**

In `app/Filament/Resources/SurveyResource/Pages/SurveyDashboard.php`, add the import and extend the class:

```php
use App\Services\SurveyInsightService;
```

```php
public ?string $summary = null;

public bool $generatingSummary = false;

public function generateSummary(): void
{
    $this->generatingSummary = true;
    $this->summary = SurveyInsightService::summarize($this->dashboardData);
    $this->generatingSummary = false;
}
```

Update `updatedEventId()` to clear a stale summary:

```php
public function updatedEventId(): void
{
    $this->loadDashboardData();
    $this->summary = null;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyDashboardSummaryTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Add the button and summary block to the Blade view**

In `resources/views/filament/pages/survey/dashboard.blade.php`, insert this block immediately after the overall completion meter's closing `</div>` (before the `{{-- Event dropdown --}}` comment):

```blade
{{-- AI summary --}}
<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">AI Summary</h3>
        <button type="button" wire:click="generateSummary" wire:loading.attr="disabled" wire:target="generateSummary"
                class="fi-btn fi-btn-color-primary inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold text-white bg-primary-600 disabled:opacity-50">
            <span wire:loading.remove wire:target="generateSummary">Generate Summary</span>
            <span wire:loading wire:target="generateSummary">Generating…</span>
        </button>
    </div>
    @if ($summary)
        <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $summary }}</p>
    @else
        <p class="text-sm text-slate-400">No summary generated yet.</p>
    @endif
</div>
```

- [ ] **Step 6: Run the Task 1 and Task 2 tests together, plus the existing SurveyDashboardPageTest, to confirm no regression**

Run: `php artisan test --filter=SurveyInsightServiceTest`
Run: `php artisan test --filter=SurveyDashboardSummaryTest`
Run: `php artisan test --filter=SurveyDashboardPageTest`
Expected: all PASS — the new Blade block doesn't affect Phase 3's existing mount/event-reload assertions.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/SurveyResource/Pages/SurveyDashboard.php resources/views/filament/pages/survey/dashboard.blade.php tests/Feature/SurveyDashboardSummaryTest.php
git commit -m "feat: add Generate Summary button to SurveyDashboard"
```

---

### Task 3: Full regression pass

**Files:** none created — verification only.

**Interfaces:** none — confirms Tasks 1–2 compose correctly and nothing regressed.

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: PASS — every existing test (kernel, Phase 1–3 Survey* tests, facility-assessment tests) plus every new test from Tasks 1–2.

- [ ] **Step 2: Verify Pint formatting**

Run: `./vendor/bin/pint --test app/Services/SurveyInsightService.php app/Filament/Resources/SurveyResource/Pages/SurveyDashboard.php tests/Feature/SurveyInsightServiceTest.php tests/Feature/SurveyDashboardSummaryTest.php`
Expected: no formatting violations. If it reports fixable issues, run the same command without `--test` to apply them, then re-run Step 1.

- [ ] **Step 3: Check git status for any unintended file changes**

Run: `git status --short`
Expected: no changes beyond what Tasks 1–2 already committed (plus any Step 2 formatting fix, committed next).

- [ ] **Step 4: Commit any formatting fixes**

```bash
git add -A
git commit -m "chore: pint formatting pass for Phase 4 AI narrative code"
```

(Skip this commit entirely if Step 2 reported no changes.)

---

## Phase 4 Definition of Done

- [ ] All 3 tasks' steps checked off, each with its own commit.
- [ ] `php artisan test` green, including every pre-existing test — the AI narrative layer is strictly additive, reading only Phase 3's already-computed output.
- [ ] An admin viewing any survey's dashboard can click "Generate Summary" and see a plain-English narrative grounded in the exact numbers already on screen; switching the event dropdown clears that summary rather than leaving a stale one visible next to different data.
- [ ] All four phases of the dynamic survey platform (generic builder, longitudinal events, auto-generated dashboards, AI narrative layer) are now complete.
