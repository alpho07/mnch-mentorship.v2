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
