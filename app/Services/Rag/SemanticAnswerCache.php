<?php

namespace App\Services\Rag;

use App\Models\RagAnswerCache;
use App\Services\Rag\Lexicon\Tokenizer;
use App\Services\Rag\Settings\RagSettings;
use Illuminate\Support\Str;

class SemanticAnswerCache
{
    public function __construct(
        private readonly RagSettings $settings,
        private readonly Tokenizer $tokenizer,
    ) {}

    public function lookup(string $question): ?RagAnswerCache
    {
        if (! (bool) $this->settings->get('answer_cache.enabled', true)) {
            return null;
        }

        if ((bool) $this->settings->get('answer_cache.exact', true)) {
            $hit = RagAnswerCache::query()
                ->where('question_hash', $this->hash($question))
                ->where('corpus_version', $this->settings->corpusVersion())
                ->first();

            if ($hit) {
                return $this->touch($hit);
            }
        }

        return null;
    }

    public function store(string $question, array $response, ?array $gate = null, ?array $grounding = null): void
    {
        if (! (bool) $this->settings->get('answer_cache.enabled', true)) {
            return;
        }

        if (($response['model'] ?? null) === 'local-corpus-listing' || ($grounding['unsupported_count'] ?? 0) > 0) {
            return;
        }

        $answer = trim((string) ($response['answer'] ?? ''));
        if ($answer === '') {
            return;
        }

        RagAnswerCache::query()->updateOrCreate(
            [
                'question_hash' => $this->hash($question),
                'corpus_version' => $this->settings->corpusVersion(),
            ],
            [
                'question' => $question,
                'question_normalised' => $this->normalise($question),
                'answer' => $answer,
                'citations' => $response['citations'] ?? [],
                'retrieved_sources' => $response['retrieved_sources'] ?? [],
                'answer_model' => $response['model'] ?? null,
                'answer_route' => $this->routeFromModel((string) ($response['model'] ?? '')),
                'gate_score' => $gate['score'] ?? null,
                'last_hit_at' => now(),
            ]
        );
    }

    public function hash(string $question): string
    {
        return hash('sha256', $this->normalise($question));
    }

    public function normalise(string $question): string
    {
        return collect($this->tokenizer->tokens($question))
            ->map(fn (string $term): string => Str::lower($term))
            ->unique()
            ->implode(' ');
    }

    private function touch(RagAnswerCache $hit): RagAnswerCache
    {
        $hit->forceFill([
            'hits' => $hit->hits + 1,
            'last_hit_at' => now(),
        ])->save();

        return $hit;
    }

    private function routeFromModel(string $model): string
    {
        return match (true) {
            Str::startsWith($model, 'local-curriculum') => 'listing',
            Str::startsWith($model, 'local') => 'local',
            default => 'remote',
        };
    }
}
