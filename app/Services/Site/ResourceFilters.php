<?php

namespace App\Services\Site;

/** Query-string filters for /resources. Unknown values are dropped. */
final class ResourceFilters
{
    public const SORTS = ['views', 'downloads', 'recent', 'az'];

    public const FORMATS = ['Slides', 'Manual', 'PDF', 'Guideline', 'Online'];

    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $topic = null,
        public readonly ?string $format = null,
        public readonly ?string $sub = null,
        public readonly string $sort = 'views',
        public readonly string $view = 'grid',
        public readonly int $page = 1,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return self::fromArray($request->query());
    }

    public static function fromArray(array $q): self
    {
        $s = static fn ($v, $max = 80) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;

        return new self(
            q: $s($q['q'] ?? null),
            topic: $s($q['topic'] ?? null),
            format: in_array($q['format'] ?? null, self::FORMATS, true) ? $q['format'] : null,
            sub: $s($q['sub'] ?? null),
            sort: in_array($q['sort'] ?? null, self::SORTS, true) ? $q['sort'] : 'views',
            view: ($q['view'] ?? null) === 'list' ? 'list' : 'grid',
            page: max(1, min(999, (int) ($q['page'] ?? 1))),
        );
    }

    /** Query array for links; page resets to 1 unless given. */
    public function toQuery(array $override = []): array
    {
        $q = ['q' => $this->q, 'topic' => $this->topic, 'format' => $this->format, 'sub' => $this->sub,
            'sort' => $this->sort === 'views' ? null : $this->sort, 'view' => $this->view === 'grid' ? null : $this->view, 'page' => null];

        return array_filter(array_merge($q, $override), static fn ($v) => $v !== null && $v !== '' && $v !== 1);
    }

    public function isFiltered(): bool
    {
        return (bool) ($this->q || $this->topic || $this->format || $this->sub);
    }
}
