<?php

namespace App\Services\Rag\Lexicon;

class VariantMatcher
{
    public function resolve(string $term, Lexicon $lexicon): array
    {
        if ($lexicon->hasTerm($term)) {
            return [$term];
        }

        $prefixLength = max(4, (int) floor(mb_strlen($term) * 0.7));
        $prefix = mb_substr($term, 0, $prefixLength);

        return collect([$term])
            ->merge($lexicon->termsWithPrefix($prefix, 5))
            ->merge($lexicon->trigramNeighbours($term, (float) $lexicon->setting('trigram_min_score', 0.62), 5))
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }
}
