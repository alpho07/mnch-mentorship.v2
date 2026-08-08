<?php

namespace App\Services\Rag;

use App\Services\Rag\Lexicon\Tokenizer;
use App\Services\Rag\Settings\RagSettings;
use App\Support\RagSourceFormatter;
use Illuminate\Support\Str;

class GroundednessVerifier
{
    public function __construct(
        private readonly RagSettings $settings,
        private readonly Tokenizer $tokenizer,
    ) {}

    public function verify(string $sentence, array $sources): array
    {
        $chunks = $this->citedChunks($sentence, $sources) ?: $sources;
        $support = $this->lexicalSupport($sentence, $chunks);
        $numericOk = ! (bool) $this->settings->get('grounding.numeric_guard', true)
            || $this->numbersAttested($sentence, $chunks);
        $cited = preg_match('/\[\d{1,2}\]/', $sentence) === 1;
        $needsCite = (bool) $this->settings->get('grounding.require_citations', true) && $this->isFactual($sentence);
        $fails = ! $numericOk || $support < (float) $this->settings->get('grounding.min_support', 0.34) || ($needsCite && ! $cited);

        return [
            'support' => round($support, 4),
            'numeric_ok' => $numericOk,
            'cited' => $cited,
            'action' => match ($this->settings->get('grounding.mode', 'shadow')) {
                'strip' => $fails ? 'strip' : 'emit',
                'warn' => $fails ? 'warn' : 'emit',
                default => 'emit',
            },
            'fails' => $fails,
        ];
    }

    public function report(string $answer, array $sources): array
    {
        $sentences = collect(preg_split('/(?<=[.!?])\s+|\n+/', trim($answer)) ?: [])
            ->map(fn (string $sentence): string => trim($sentence))
            ->filter()
            ->values();

        $unsupported = [];
        $numericViolations = 0;

        foreach ($sentences as $sentence) {
            $result = $this->verify($sentence, $sources);
            if ($result['fails']) {
                $unsupported[] = [
                    'sentence' => Str::limit($sentence, 500, ''),
                    'support' => $result['support'],
                    'numeric_ok' => $result['numeric_ok'],
                    'cited' => $result['cited'],
                ];
            }

            if (! $result['numeric_ok']) {
                $numericViolations++;
            }
        }

        return [
            'sentence_count' => $sentences->count(),
            'unsupported_count' => count($unsupported),
            'numeric_violation_count' => $numericViolations,
            'unsupported_sentences' => $unsupported,
            'grounding_min_support' => (float) $this->settings->get('grounding.min_support', 0.34),
        ];
    }

    private function citedChunks(string $sentence, array $sources): array
    {
        preg_match_all('/\[(\d{1,2})\]/', $sentence, $matches);
        $indices = collect($matches[1] ?? [])->map(fn (string $value): int => (int) $value - 1)->unique();

        return $indices
            ->map(fn (int $index): ?array => $sources[$index] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function lexicalSupport(string $sentence, array $sources): float
    {
        $terms = collect($this->tokenizer->tokens($sentence))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 4 || is_numeric($term))
            ->unique()
            ->values();

        if ($terms->isEmpty()) {
            return 1.0;
        }

        $best = 0.0;
        foreach ($sources as $source) {
            $content = Str::lower(RagSourceFormatter::plain((string) ($source['content'] ?? '')));
            $matched = $terms->filter(fn (string $term): bool => Str::contains($content, $term))->count();
            $best = max($best, $matched / $terms->count());
        }

        return $best;
    }

    private function numbersAttested(string $sentence, array $sources): bool
    {
        preg_match_all('/\b(?:module\s*)?\d+(?:\.\d+)?\b/i', $sentence, $matches);
        $numbers = collect($matches[0] ?? [])
            ->map(fn (string $value): string => Str::lower(preg_replace('/\s+/', '', $value) ?? $value))
            ->unique()
            ->values();

        if ($numbers->isEmpty()) {
            return true;
        }

        $content = Str::lower(preg_replace('/\s+/', '', collect($sources)->pluck('content')->implode(' ')) ?? '');

        return $numbers->every(fn (string $number): bool => Str::contains($content, $number));
    }

    private function isFactual(string $sentence): bool
    {
        return preg_match('/\b(is|are|takes|contains|includes|should|must|module|\d)\b/i', $sentence) === 1;
    }
}
