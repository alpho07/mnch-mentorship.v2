<?php

namespace App\Services\Rag;

use App\Services\Rag\Lexicon\Lexicon;
use App\Services\Rag\Lexicon\Tokenizer;
use App\Services\Rag\Settings\RagSettings;
use App\Support\RagSourceFormatter;
use Illuminate\Support\Str;

class Answerability
{
    public function __construct(
        private readonly RagSettings $settings,
        private readonly Tokenizer $tokenizer,
        private readonly Lexicon $lexicon,
    ) {}

    public function assess(string $question, array $sources): array
    {
        $terms = $this->tokenizer->contentTerms($question, $this->lexicon);
        $signals = [
            'top_score' => $this->topScore($sources),
            'margin' => $this->margin($sources),
            'term_coverage' => $this->termCoverage($terms, $sources),
            'content_density' => $this->contentDensity($sources),
            'agreement' => $this->agreement($terms, $sources),
            'source_count' => min(count($sources) / max(1, (int) $this->settings->get('ladder.max_sources', 10)), 1),
        ];

        $weights = (array) $this->settings->get('gate.weights', config('rag.gate.weights', []));
        $score = 0.0;
        foreach ($weights as $name => $weight) {
            $score += (float) $weight * max(0, min(1, (float) ($signals[$name] ?? 0)));
        }
        $decision = match (true) {
            $score >= (float) $this->settings->get('gate.sufficient', 0.62) => 'answer',
            $score >= (float) $this->settings->get('gate.expand', 0.28) => 'expand',
            default => 'abstain',
        };

        return [
            'score' => round($score, 4),
            'decision' => $decision,
            'signals' => array_map(fn ($value): float => round((float) $value, 4), $signals),
        ];
    }

    private function topScore(array $sources): float
    {
        return collect($sources)->map(function (array $source): float {
            if (is_numeric($source['distance'] ?? null)) {
                return max(0, min(1, 1 - ((float) $source['distance'] / 2)));
            }

            if (is_numeric($source['score'] ?? null)) {
                $score = (float) $source['score'];

                return $score / ($score + 4);
            }

            $content = RagSourceFormatter::prose((string) ($source['content'] ?? ''));

            return min(1, mb_strlen($content) / 800);
        })->max() ?? 0.0;
    }

    private function margin(array $sources): float
    {
        $scores = collect($sources)
            ->map(fn (array $source): float => is_numeric($source['distance'] ?? null)
                ? max(0, min(1, 1 - ((float) $source['distance'] / 2)))
                : min(1, mb_strlen(RagSourceFormatter::prose((string) ($source['content'] ?? ''))) / 800))
            ->sortDesc()
            ->values();

        $top = (float) ($scores[0] ?? 0);
        $third = (float) ($scores[2] ?? 0);

        return $top > 0 ? max(0, min(1, ($top - $third) / $top)) : 0;
    }

    private function termCoverage(array $terms, array $sources): float
    {
        if ($terms === []) {
            return 0;
        }

        $haystack = Str::lower(collect($sources)->pluck('content')->implode(' '));
        $matched = collect($terms)->filter(fn (string $term): bool => Str::contains($haystack, $term))->count();

        return $matched / count($terms);
    }

    private function contentDensity(array $sources): float
    {
        if ($sources === []) {
            return 0;
        }

        return collect($sources)
            ->map(function (array $source): float {
                $plain = RagSourceFormatter::plain((string) ($source['content'] ?? ''));
                if ($plain === '') {
                    return 0;
                }

                return min(1, mb_strlen(RagSourceFormatter::prose($plain)) / max(1, mb_strlen($plain)));
            })
            ->avg() ?? 0.0;
    }

    private function agreement(array $terms, array $sources): float
    {
        if ($terms === []) {
            return 0;
        }

        $locators = collect($sources)->map(function (array $source): array {
            return [
                'key' => implode('|', [$source['document'] ?? '', $source['locator_type'] ?? '', $source['locator'] ?? '']),
                'content' => Str::lower((string) ($source['content'] ?? '')),
            ];
        });

        $agreed = collect($terms)->filter(fn (string $term): bool => $locators
            ->filter(fn (array $locator): bool => Str::contains($locator['content'], $term))
            ->pluck('key')
            ->unique()
            ->count() >= 2)->count();

        return $agreed / count($terms);
    }
}
