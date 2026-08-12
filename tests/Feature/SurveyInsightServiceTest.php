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
