<?php

namespace App\Services\Rag\Lexicon;

use App\Models\RagLexiconEdge;
use App\Models\RagLexiconTerm;
use App\Services\Rag\Settings\RagSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Lexicon
{
    public function __construct(
        private readonly RagSettings $settings,
        private readonly Tokenizer $tokenizer,
        private readonly VariantMatcher $variants,
    ) {}

    public function expand(string $question): array
    {
        if (! (bool) $this->settings->get('lexicon.enabled', true)) {
            return ['queries' => [$question], 'edges' => []];
        }

        $terms = $this->tokenizer->contentTerms($question, $this);
        $resolved = collect($terms)
            ->flatMap(fn (string $term): array => $this->variants->resolve($term, $this))
            ->unique()
            ->values();

        if ($resolved->isEmpty()) {
            return ['queries' => [$question], 'edges' => []];
        }

        $edges = RagLexiconEdge::query()
            ->whereIn('from_term', $resolved)
            ->where('enabled', true)
            ->orderBy('priority')
            ->orderByDesc('weight')
            ->limit((int) $this->setting('expansion_per_query', 6) * 3)
            ->get();

        $queries = collect([$question]);
        foreach (['manual', 'acronym_expansion', 'expansion_acronym', 'curriculum_alias', 'heading_child', 'planner_distilled', 'cooccurrence', 'variant'] as $kind) {
            foreach ($edges->where('kind', $kind) as $edge) {
                $queries->push($this->compose($question, $edge->to_term));
            }
        }

        return [
            'queries' => $queries
                ->map(fn (string $query): string => Str::limit(trim(preg_replace('/\s+/', ' ', $query) ?? $query), 180, ''))
                ->filter()
                ->unique(fn (string $query): string => Str::lower($query))
                ->take((int) $this->setting('expansion_per_query', 6))
                ->values()
                ->all(),
            'edges' => $edges
                ->map(fn (RagLexiconEdge $edge): array => $edge->only(['id', 'from_term', 'to_term', 'kind', 'weight']))
                ->values()
                ->all(),
        ];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->get('lexicon.'.$key, $default);
    }

    public function isStopword(string $term): bool
    {
        return Cache::remember("rag:lexicon:stopword:{$term}", 900, fn (): bool => RagLexiconTerm::query()
            ->where('normalised', $term)
            ->where('corpus_version', $this->settings->corpusVersion())
            ->value('is_stopword') ?? false);
    }

    public function isAcronym(string $term): bool
    {
        return preg_match('/^[a-z0-9]{2,8}$/i', $term) === 1 && Str::upper($term) === $term
            || (bool) RagLexiconTerm::query()
                ->where('normalised', Str::lower($term))
                ->where('corpus_version', $this->settings->corpusVersion())
                ->value('is_acronym');
    }

    public function hasTerm(string $term): bool
    {
        return RagLexiconTerm::query()
            ->where('normalised', Str::lower($term))
            ->where('corpus_version', $this->settings->corpusVersion())
            ->exists();
    }

    public function termsWithPrefix(string $prefix, int $limit): array
    {
        return RagLexiconTerm::query()
            ->where('corpus_version', $this->settings->corpusVersion())
            ->where('normalised', 'like', Str::lower($prefix).'%')
            ->orderByDesc('chunk_frequency')
            ->limit($limit)
            ->pluck('normalised')
            ->all();
    }

    public function trigramNeighbours(string $term, float $min, int $limit): array
    {
        $needle = collect($this->tokenizer->trigrams(Str::lower($term)));
        if ($needle->isEmpty()) {
            return [];
        }

        return RagLexiconTerm::query()
            ->where('corpus_version', $this->settings->corpusVersion())
            ->whereNotNull('trigrams')
            ->orderByDesc('chunk_frequency')
            ->limit(500)
            ->get(['normalised', 'trigrams'])
            ->map(function (RagLexiconTerm $candidate) use ($needle): array {
                $trigrams = collect($candidate->trigrams ?? []);
                $union = $needle->merge($trigrams)->unique()->count();
                $score = $union > 0 ? $needle->intersect($trigrams)->count() / $union : 0;

                return ['term' => $candidate->normalised, 'score' => $score];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] >= $min)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('term')
            ->all();
    }

    private function compose(string $question, string $toTerm): string
    {
        return Str::contains(Str::lower($question), Str::lower($toTerm))
            ? $question
            : trim($question.' '.$toTerm);
    }
}
