<?php

namespace App\Services\Rag\Lexicon;

use Illuminate\Support\Str;
use Normalizer;

class Tokenizer
{
    public function tokens(string $text): array
    {
        $text = Str::lower($this->foldDiacritics($text));
        preg_match_all('/[\p{L}\p{N}]+(?:[-\'’][\p{L}\p{N}]+)*/u', $text, $matches);

        return array_values($matches[0] ?? []);
    }

    public function contentTerms(string $text, Lexicon $lexicon): array
    {
        $min = (int) $lexicon->setting('min_term_length', 3);

        return collect($this->tokens($text))
            ->reject(fn (string $term): bool => mb_strlen($term) < $min && ! $lexicon->isAcronym($term))
            ->reject(fn (string $term): bool => $lexicon->isStopword($term))
            ->unique()
            ->values()
            ->all();
    }

    public function trigrams(string $term): array
    {
        $padded = '  '.$term.' ';
        $out = [];

        for ($i = 0, $n = max(0, mb_strlen($padded) - 2); $i < $n; $i++) {
            $out[] = mb_substr($padded, $i, 3);
        }

        return array_values(array_unique($out));
    }

    private function foldDiacritics(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_D) ?: $text;
            $text = preg_replace('/\p{Mn}+/u', '', $text) ?? $text;
        }

        return $text;
    }
}
