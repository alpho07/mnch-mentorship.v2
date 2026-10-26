<?php

namespace App\Services\Analytics;

/**
 * Every filter the five dashboards understand, read from the query string.
 * Unknown or empty values become null so they never reach a query.
 *
 *   /analytics/dashboard?mode=mentorship&county=Vihiga&year=2026
 */
final class DashboardFilters
{
    public const MODES = ['mentorship', 'emonc', 'mentor', 'assessment', 'training'];

    public const ASSESSMENT_TYPES = ['baseline', 'midline', 'endline'];

    public const MENTOR_LEVELS = ['AP', 'P', 'F'];

    public function __construct(
        public readonly string $mode = 'mentorship',
        public readonly ?string $county = null,
        public readonly ?string $subcounty = null,
        public readonly ?string $facility = null,
        public readonly ?string $programme = null,
        public readonly ?int $year = null,
        public readonly ?string $search = null,
        public readonly ?string $cadre = null,
        public readonly ?string $level = null,
        public readonly ?string $assessmentType = null,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return self::fromArray($request->query());
    }

    public static function fromArray(array $q): self
    {
        $str = static function ($v, int $max = 120): ?string {
            if (! is_string($v)) {
                return null;
            }
            $v = trim($v);

            return $v === '' ? null : mb_substr($v, 0, $max);
        };

        $mode = in_array($q['mode'] ?? null, self::MODES, true) ? $q['mode'] : 'mentorship';
        $year = isset($q['year']) && ctype_digit((string) $q['year']) ? (int) $q['year'] : null;
        if ($year !== null && ($year < 2000 || $year > 2100)) {
            $year = null;
        }
        $level = in_array($q['level'] ?? null, self::MENTOR_LEVELS, true) ? $q['level'] : null;
        $type = in_array($q['type'] ?? null, self::ASSESSMENT_TYPES, true) ? $q['type'] : null;

        return new self(
            mode: $mode,
            county: $str($q['county'] ?? null, 60),
            subcounty: $str($q['subcounty'] ?? null, 60),
            facility: $str($q['facility'] ?? null),
            programme: $str($q['programme'] ?? null),
            year: $year,
            search: $str($q['q'] ?? null, 80),
            cadre: $str($q['cadre'] ?? null),
            level: $level,
            assessmentType: $type,
        );
    }

    /** Query-string array for links (nulls removed). */
    public function toQuery(array $override = []): array
    {
        $q = [
            'mode' => $this->mode,
            'county' => $this->county,
            'subcounty' => $this->subcounty,
            'facility' => $this->facility,
            'programme' => $this->programme,
            'year' => $this->year,
            'q' => $this->search,
            'cadre' => $this->cadre,
            'level' => $this->level,
            'type' => $this->assessmentType,
        ];

        return array_filter(array_merge($q, $override), static fn ($v) => $v !== null && $v !== '');
    }

    public function isFiltered(): bool
    {
        return count($this->toQuery()) > 1;
    }

    public function cacheKey(): string
    {
        return 'analytics:dashboard:'.md5(json_encode($this->toQuery()));
    }
}
