<?php

namespace App\Services\Chat;

/**
 * Fuzzy shortlist for CARDS slot options, backed by loilo/fuse (a PHP port
 * of Fuse.js). Used as the last resolution tier in
 * MentorshipSetupToolProvider/MentorshipModulesToolProvider, once exact and
 * substring matching have already failed — never picks a winner itself,
 * only ever returns a ranked shortlist for the caller to present.
 */
class FuzzyOptionMatcher
{
    public const MAX_CANDIDATES = 8;

    /**
     * @param  array<int|string, string>  $options  id => label
     * @return array<int, array{id: int|string, label: string}> best match first, capped at MAX_CANDIDATES, empty if nothing scores within Fuse's default threshold (0.6)
     */
    public static function search(array $options, string $query): array
    {
        if (trim($query) === '' || empty($options)) {
            return [];
        }

        $ids = array_keys($options);
        $labels = array_values($options);

        // Fuse's defaults (location=0, distance=100, threshold=0.6) are
        // used as-is: distance=100 is already generous enough to find a
        // match past a facility label's MFL-code prefix (verified: "Chuka"
        // still matches "12345 — Chuka County Referral Hospital" at a good
        // score). Setting ignoreLocation(true) was tried and rejected — it
        // makes matching too lenient, e.g. "Nairobi" spuriously matching
        // "Tharaka Nithi".
        $fuse = new \Fuse\Fuse($labels);

        return collect($fuse->search($query))
            ->take(self::MAX_CANDIDATES)
            ->map(fn (array $result) => [
                'id' => $ids[$result['refIndex']],
                'label' => $labels[$result['refIndex']],
            ])
            ->values()
            ->all();
    }
}
