<?php

namespace App\Services\Site;

/**
 * Builds the view models for the public pages from SiteDataSource.
 * Everything derived (totals, "x days left", category totals incl. subtopics, filter counts) is computed here.
 */
final class SitePresenter
{
    /** Topic colours by top-level category slug: [strong, tint, ink, cover] */
    public const TOPICS = [
        'newborn-care' => ['#0097A7', '#E5F6F8', '#00505A', '#00838F', 'heart'],
        'infant-health' => ['#12805C', '#E7F5EF', '#0E6A4C', '#12805C', 'child'],
        'clinical-protocols' => ['#6A4FC7', '#F0EDFB', '#4E3A9E', '#5B3FB8', 'clipboard'],
        'training-education' => ['#1D6FB8', '#EAF1FA', '#174F82', '#1D6FB8', 'cap'],
        'maternal-health' => ['#C23B54', '#FCEBEE', '#A12A41', '#B23149', 'pulse'],
        'diabetes-management' => ['#E89B1C', '#FDF3E3', '#8A5000', '#B56A00', 'drop'],
        'child-health' => ['#2E9E4F', '#E7F5EF', '#0E6A4C', '#1F7A3D', 'star'],
        'quality-improvement' => ['#556B6E', '#F3F6F6', '#3D5558', '#556B6E', 'gauge'],
    ];

    private const DEFAULT_TOPIC = ['#1D6FB8', '#EAF1FA', '#174F82', '#1D6FB8', 'star'];

    public const FORMATS = [
        'Slides' => ['Slides', '#EAF1FA', '#1D6FB8'],
        'Manual' => ['Manual', '#FCEBEE', '#B23149'],
        'PDF' => ['PDF protocol', '#F0EDFB', '#6A4FC7'],
        'Guideline' => ['Guideline', '#E7F5EF', '#12805C'],
        'Online' => ['Online manual', '#E5F6F8', '#00838F'],
    ];

    /** Category photos on /categories (slug => [image, alt, object-position]). */
    public const PHOTOS = [
        'newborn-care' => ['images/site/mother-newborn.jpg', 'A mother holding her newborn', 'center 30%'],
        'infant-health' => ['images/site/child-smile.jpg', 'A smiling toddler', 'center'],
    ];

    public const PER_PAGE = 12;

    /** @param array{home:string, resources:string, categories:string, resource:string, dashboard:string, asset:string} $urls */
    public function __construct(private SiteDataSource $data, private array $urls, private string $mapFile) {}

    private function url(string $key, array $query = []): string
    {
        return $this->urls[$key].($query ? '?'.http_build_query($query) : '');
    }

    private function asset(string $path): string
    {
        return str_starts_with($path, 'http') || str_starts_with($path, '/') ? $path : rtrim($this->urls['asset'], '/').'/'.$path;
    }

    public static function fmt(int|float $n): string
    {
        return number_format($n);
    }

    /** "8 months ago", "3 weeks ago", "today". */
    public static function ago(string $date, \DateTimeInterface $today): string
    {
        $d = new \DateTimeImmutable($date);
        $diff = $d->diff(\DateTimeImmutable::createFromInterface($today));
        if ($diff->y) {
            return $diff->y.' '.($diff->y === 1 ? 'year' : 'years').' ago';
        }
        if ($diff->m) {
            return $diff->m.' '.($diff->m === 1 ? 'month' : 'months').' ago';
        }
        if ($diff->d >= 7) {
            $w = intdiv($diff->d, 7);

            return $w.' '.($w === 1 ? 'week' : 'weeks').' ago';
        }

        return $diff->d === 0 ? 'today' : $diff->d.' '.($diff->d === 1 ? 'day' : 'days').' ago';
    }

    /** subcategory or category name => [top-level category name, slug]. */
    private function topicIndex(array $cats): array
    {
        $idx = [];
        foreach ($cats as $c) {
            $idx[mb_strtolower($c['name'])] = [$c['name'], $c['slug']];
            foreach ($c['subcategories'] as $s) {
                $idx[mb_strtolower($s['name'])] = [$c['name'], $c['slug']];
            }
        }

        return $idx;
    }

    private function topic(string $slug): array
    {
        return self::TOPICS[$slug] ?? self::DEFAULT_TOPIC;
    }

    /** Category totals = filed directly + filed under each subtopic. */
    private function categoryTotals(array $cats): array
    {
        $out = [];
        foreach ($cats as $c) {
            $out[$c['slug']] = $c['direct'] + array_sum(array_column($c['subcategories'], 'count'));
        }

        return $out;
    }

    private function resourceCard(array $r, array $idx, \DateTimeInterface $today, int $i = 0): array
    {
        [$topicName, $topicSlug] = $idx[mb_strtolower($r['subcategory'])] ?? [$r['subcategory'], ''];
        $t = $this->topic($topicSlug);
        $f = self::FORMATS[$r['format']] ?? self::FORMATS['PDF'];

        return [
            'slug' => $r['slug'], 'url' => rtrim($this->urls['resource'], '/').'/'.$r['slug'],
            'title' => $r['title'], 'format' => $r['format'], 'formatLabel' => $f[0], 'sub' => $r['subcategory'],
            'topic' => $topicName, 'topicSlug' => $topicSlug, 'excerpt' => $r['excerpt'],
            'views' => self::fmt($r['views']), 'downloads' => self::fmt($r['downloads']), 'hasDownloads' => $r['downloads'] > 0,
            'age' => self::ago($r['created_at'], $today), 'advanced' => $r['advanced'],
            'img' => $r['thumbnail'] ? $this->asset($r['thumbnail']) : null, 'imgPos' => $r['format'] === 'Guideline' ? 'top' : 'center',
            'cover' => $t[3], 'catBg' => $t[1], 'catInk' => $t[2], 'icon' => $r['format'],
            'delay' => number_format($i * 0.035, 3).'s',
        ];
    }

    /* ───────────────────────── Home ───────────────────────── */

    public function home(\DateTimeInterface $today): array
    {
        $s = $this->data->homeStats();
        $cats = $this->data->categories();
        $idx = $this->topicIndex($cats);
        $totals = $this->categoryTotals($cats);

        // map + county list
        $live = $this->data->liveMentorshipsByCounty();
        $byCounty = [];
        foreach ($live as $county => $rows) {
            usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);
            $sum = array_sum(array_column($rows, 'count'));
            $max = max(1, $rows[0]['count'] ?? 1);
            $byCounty[$county] = ['total' => $sum, 'facilities' => array_map(fn ($r) => ['n' => $r['facility'], 'v' => $r['count'], 'w' => round($r['count'] / $max * 100).'%'], $rows)];
        }
        uksort($byCounty, fn ($a, $b) => $byCounty[$b]['total'] <=> $byCounty[$a]['total'] ?: strcmp($a, $b));
        $norm = fn ($n) => preg_replace('/[^a-z]/', '', strtolower($n));
        $liveNorm = [];
        foreach ($byCounty as $n => $v) {
            $liveNorm[$norm($n)] = [$n, $v['total']];
        }
        $fill = fn (int $v) => $v >= 10 ? '#00838F' : ($v >= 6 ? '#26C6DA' : ($v >= 2 ? '#7FDCE6' : ($v >= 1 ? '#C8F0F4' : '#3E5F63')));
        $map = [];
        foreach (json_decode((string) file_get_contents($this->mapFile), true) ?: [] as $g) {
            [$name, $v] = $liveNorm[$norm($g['n'])] ?? [$g['n'], 0];
            $map[] = ['name' => $name, 'd' => $g['d'], 'fill' => $fill($v), 'label' => $g['n'].' — '.($v ? $v.' live '.($v === 1 ? 'mentorship' : 'mentorships') : 'no live mentorship'), 'live' => $v > 0];
        }

        // calendar
        $todayD = \DateTimeImmutable::createFromInterface($today)->setTime(0, 0);
        $calendar = ['live' => [], 'soon' => [], 'done' => []];
        $progStyle = ['Newborn Care' => ['#E5F6F8', '#00505A', '#0097A7'], 'Infant & Child Care' => ['#E7F5EF', '#0E6A4C', '#12805C'], 'EmONC' => ['#FCEBEE', '#A12A41', '#C23B54']];
        foreach ($this->data->mentorshipCalendar($today) as $m) {
            $start = new \DateTimeImmutable($m['starts_on']);
            $end = new \DateTimeImmutable($m['ends_on']);
            $days = max(1, (int) $start->diff($end)->days);
            $weeks = max(1, (int) round($days / 7));
            $status = $m['closed'] ? 'done' : ($todayD < $start ? 'soon' : 'live');
            $p = $progStyle[$m['programme']] ?? ['#EAF1FA', '#174F82', '#1D6FB8'];
            if ($status === 'live') {
                $left = (int) $todayD->diff($end)->format('%r%a');
                $done = min($days, max(0, (int) $start->diff($todayD)->days));
                [$when, $progress, $label] = ['ends '.$end->format('j M'), round($done / $days * 100).'%', $left <= 0 ? 'Final day' : ($left === 1 ? '1 day left' : $left.' days left')];
            } elseif ($status === 'soon') {
                $in = max(1, (int) $todayD->diff($start)->days);
                [$when, $progress, $label] = ['starts '.$start->format('j M'), '0%', 'In '.$in.($in === 1 ? ' day' : ' days')];
            } else {
                [$when, $progress, $label] = ['ended '.$end->format('j M'), '100%', 'Completed'];
            }
            $calendar[$status][] = [
                'programme' => $m['programme'], 'facility' => $m['facility'], 'county' => $m['county'], 'cohort' => $start->format('M Y'),
                'when' => $when, 'duration' => $weeks.' '.($weeks === 1 ? 'week' : 'weeks').' programme', 'progress' => $progress, 'label' => $label,
                'bg' => $status === 'done' ? '#F3F6F6' : $p[0], 'ink' => $status === 'done' ? '#556B6E' : $p[1],
                'bar' => $status === 'done' ? '#9AA9AB' : ($status === 'soon' ? '#E89B1C' : $p[2]),
            ];
        }

        $featured = array_values(array_filter($this->data->resources(), fn ($r) => $r['featured']));
        usort($featured, fn ($a, $b) => $b['views'] <=> $a['views']);

        $topics = [];
        foreach ($cats as $c) {
            $t = $this->topic($c['slug']);
            $n = $totals[$c['slug']];
            $topics[] = ['name' => $c['name'], 'count' => $n ? $n.' '.($n === 1 ? 'resource' : 'resources') : 'Coming soon', 'icon' => $t[4], 'bg' => $n ? $t[1] : '#F3F6F6', 'c' => $n ? $t[0] : '#556B6E', 'ink' => $n ? $t[2] : '#556B6E', 'href' => $this->url('categories').'#'.$c['slug'], 'n' => $n];
        }
        usort($topics, fn ($a, $b) => $b['n'] <=> $a['n']);

        $first = array_key_first($byCounty);

        return [
            'stats' => $s,
            'heroStats' => [['layers', self::fmt($s['mentorships']), 'mentorships · '.$s['counties'].' counties'], ['dot', self::fmt($s['mentees']), 'mentees reached']],
            'intel' => [
                ['Learning pipeline', self::fmt($s['pipeline_30d']), 'Scheduled in the next 30 days across '.$s['pipeline_counties'].' '.($s['pipeline_counties'] === 1 ? 'county' : 'counties').'.', 'calendar', 'rgba(127,220,230,.16)', '#7FDCE6'],
                ['Counties covered', self::fmt($s['counties']), self::fmt($s['active_mentorships']).' active mentorships and '.self::fmt($s['active_classes']).' active classes running now.', 'pin', 'rgba(143,217,168,.16)', '#8FD9A8'],
                ['Facilities reached', self::fmt($s['facilities']), self::fmt($s['mentees']).' mentees reached across facility mentorships.', 'building', 'rgba(201,184,255,.16)', '#C9B8FF'],
                ['County coverage', round($s['counties'] / 47 * 100).'%', $s['counties'].' of 47 counties have active mentorship.', 'gauge', 'rgba(255,208,138,.16)', '#FFD08A'],
            ],
            'map' => $map,
            'legend' => [['10+', '#00838F'], ['6–9', '#26C6DA'], ['2–5', '#7FDCE6'], ['1', '#C8F0F4'], ['None', '#3E5F63']],
            'byCounty' => $byCounty,
            'firstCounty' => $first,
            'countyList' => array_slice(array_keys($byCounty), 0, 8),
            'calendar' => $calendar,
            'featured' => array_map(fn ($r, $i) => $this->resourceCard($r, $idx, $today, $i), array_slice($featured, 0, 6), array_keys(array_slice($featured, 0, 6))),
            'topics' => $topics,
            'libraryCount' => count($this->data->resources()),
            'popular' => array_map(fn ($q) => ['label' => $q[0], 'href' => $this->url('resources', ['q' => $q[1]])], [['EmONC mentor manual', 'EmONC'], ['Basic Paediatric Protocol', 'Paediatric Protocol'], ['Neonatal resuscitation', 'resuscitation'], ['CPAP', 'CPAP']]),
            'programmes' => [
                ['tag' => 'Maternal health', 'name' => 'EmONC Mentorship', 'body' => 'Emergency obstetric and newborn care for maternity teams, built on the national EmONC Training & Mentorship Package (3rd edition, 2026).', 'icon' => 'pulse', 'bg' => '#FCEBEE', 'c' => '#C23B54', 'ink' => '#A12A41', 'library' => $this->url('resources', ['topic' => 'maternal-health']), 'dash' => $this->url('dashboard', ['mode' => 'emonc']), 'delay' => '.05s'],
                ['tag' => 'Neonatal care', 'name' => 'Newborn Mentorship', 'body' => 'Essential and sick newborn care on the unit — thermoregulation, resuscitation, jaundice, CPAP and care of the preterm baby.', 'icon' => 'heart', 'bg' => '#E5F6F8', 'c' => '#00838F', 'ink' => '#00505A', 'library' => $this->url('resources', ['topic' => 'newborn-care']), 'dash' => $this->url('dashboard', ['mode' => 'mentorship']), 'delay' => '.12s'],
                ['tag' => 'Paediatric care', 'name' => 'Infant & Child Mentorship', 'body' => 'Triage, emergency care and common childhood illness for paediatric teams, following the Basic Paediatric Protocol.', 'icon' => 'child', 'bg' => '#E7F5EF', 'c' => '#12805C', 'ink' => '#0E6A4C', 'library' => $this->url('resources', ['topic' => 'infant-health']), 'dash' => $this->url('dashboard', ['mode' => 'mentorship']), 'delay' => '.19s'],
            ],
        ];
    }

    /* ───────────────────────── /resources ───────────────────────── */

    public function resources(ResourceFilters $f, \DateTimeInterface $today): array
    {
        $cats = $this->data->categories();
        $idx = $this->topicIndex($cats);
        $all = array_map(function ($r) use ($idx) {
            [$r['topic'], $r['topicSlug']] = $idx[mb_strtolower($r['subcategory'])] ?? [$r['subcategory'], ''];

            return $r;
        }, $this->data->resources());

        $byQ = array_values(array_filter($all, fn ($r) => ! $f->q || mb_stripos($r['title'].' '.$r['subcategory'].' '.$r['topic'], $f->q) !== false));
        $byTopic = array_values(array_filter($byQ, fn ($r) => ! $f->topic || $r['topicSlug'] === $f->topic));
        $list = array_values(array_filter($byTopic, fn ($r) => (! $f->format || $r['format'] === $f->format) && (! $f->sub || $r['subcategory'] === $f->sub)));
        usort($list, match ($f->sort) {
            'downloads' => fn ($a, $b) => $b['downloads'] <=> $a['downloads'] ?: $b['views'] <=> $a['views'],
            'recent' => fn ($a, $b) => strcmp($b['created_at'], $a['created_at']) ?: $b['views'] <=> $a['views'],
            'az' => fn ($a, $b) => strcasecmp($a['title'], $b['title']),
            default => fn ($a, $b) => $b['views'] <=> $a['views'] ?: strcasecmp($a['title'], $b['title']),
        });

        $pages = max(1, (int) ceil(count($list) / self::PER_PAGE));
        $page = min($f->page, $pages);
        $slice = array_slice($list, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $topicCount = [];
        foreach ($byQ as $r) {
            $topicCount[$r['topicSlug']] = ($topicCount[$r['topicSlug']] ?? 0) + 1;
        }
        $pills = [['name' => 'All topics', 'n' => count($byQ), 'c' => '#9AA9AB', 'on' => ! $f->topic, 'href' => $this->url('resources', $f->toQuery(['topic' => null, 'sub' => null]))]];
        foreach ($cats as $c) {
            if (empty($topicCount[$c['slug']])) {
                continue;
            }
            $pills[] = ['name' => $c['name'], 'n' => $topicCount[$c['slug']], 'c' => $this->topic($c['slug'])[0], 'on' => $f->topic === $c['slug'],
                'href' => $this->url('resources', $f->toQuery(['topic' => $f->topic === $c['slug'] ? null : $c['slug'], 'sub' => null]))];
        }
        usort($pills, fn ($a, $b) => ($b['name'] === 'All topics') <=> ($a['name'] === 'All topics') ?: $b['n'] <=> $a['n']);
        $formats = [];
        foreach (self::FORMATS as $key => [$label, $bg, $c]) {
            $n = count(array_filter($byTopic, fn ($r) => $r['format'] === $key));
            if ($n || $f->format === $key) {
                $formats[] = ['name' => $label, 'n' => $n, 'icon' => $key, 'bg' => $bg, 'c' => $c, 'on' => $f->format === $key, 'href' => $this->url('resources', $f->toQuery(['format' => $f->format === $key ? null : $key]))];
            }
        }
        $subs = [];
        foreach ($byTopic as $r) {
            $subs[$r['subcategory']] = ($subs[$r['subcategory']] ?? 0) + 1;
        }
        uksort($subs, fn ($a, $b) => $subs[$b] <=> $subs[$a] ?: strcmp($a, $b));
        $topicName = $f->topic ? (array_values(array_filter($cats, fn ($c) => $c['slug'] === $f->topic))[0]['name'] ?? null) : null;
        $pageLinks = [];
        for ($p = 1; $p <= $pages; $p++) {
            $pageLinks[] = ['n' => $p, 'on' => $p === $page, 'href' => $this->url('resources', $f->toQuery(['page' => $p]))];
        }

        return [
            'f' => $f,
            'heroStats' => [[self::fmt(count($all)), 'resources'], [(string) count(array_filter($this->categoryTotals($cats))), 'clinical topics'], [self::fmt(array_sum(array_column($all, 'views'))), 'total views']],
            'pills' => $pills, 'formats' => $formats,
            'subs' => array_map(fn ($n, $c) => ['name' => $n, 'n' => $c, 'on' => $f->sub === $n, 'href' => $this->url('resources', $f->toQuery(['sub' => $f->sub === $n ? null : $n]))], array_keys($subs), $subs),
            'items' => array_map(fn ($r, $i) => $this->resourceCard($r, $idx, $today, $i), $slice, array_keys($slice)),
            'total' => count($list),
            'countLabel' => count($list).' '.(count($list) === 1 ? 'resource' : 'resources').($topicName ? ' in '.$topicName : ''),
            'pageLabel' => $list ? 'Showing '.(($page - 1) * self::PER_PAGE + 1).'–'.min(count($list), $page * self::PER_PAGE).' of '.count($list) : '',
            'pageLinks' => $pages > 1 ? $pageLinks : [],
            'prevHref' => $page > 1 ? $this->url('resources', $f->toQuery(['page' => $page - 1])) : null,
            'nextHref' => $page < $pages ? $this->url('resources', $f->toQuery(['page' => $page + 1])) : null,
            'clearHref' => $this->url('resources', array_filter(['sort' => $f->sort === 'views' ? null : $f->sort, 'view' => $f->view === 'grid' ? null : $f->view])),
            'gridHref' => $this->url('resources', $f->toQuery(['view' => null, 'page' => $page])),
            'listHref' => $this->url('resources', $f->toQuery(['view' => 'list', 'page' => $page])),
            'hidden' => array_filter(['q' => $f->q, 'topic' => $f->topic, 'format' => $f->format, 'sub' => $f->sub, 'view' => $f->view === 'list' ? 'list' : null]),
        ];
    }

    /* ───────────────────────── /categories ───────────────────────── */

    public function categories(): array
    {
        $cats = $this->data->categories();
        $totals = $this->categoryTotals($cats);
        $cards = [];
        foreach ($cats as $i => $c) {
            $t = $this->topic($c['slug']);
            $max = max(1, ...array_column($c['subcategories'], 'count') ?: [1]);
            $subs = $c['subcategories'];
            usort($subs, fn ($a, $b) => $b['count'] <=> $a['count']);
            $total = $totals[$c['slug']];
            $photo = self::PHOTOS[$c['slug']] ?? null;
            $cards[] = [
                'name' => $c['name'], 'slug' => $c['slug'], 'desc' => $c['description'], 'total' => $total,
                'unit' => $total === 1 ? 'resource' : 'resources',
                'meta' => count($c['subcategories']).' subtopics · '.$c['direct'].' filed directly',
                'href' => $this->url('resources', ['topic' => $c['slug']]),
                'subs' => array_map(fn ($s) => ['name' => $s['name'], 'n' => $s['count'], 'v' => $s['count'] ?: '—', 'empty' => $s['count'] === 0, 'ink' => $s['count'] ? '#0F2A2E' : '#8A9A9C',
                    'w' => round($s['count'] / $max * 100).'%', 'href' => $this->url('resources', ['topic' => $c['slug'], 'sub' => $s['name']])], $subs),
                'search' => mb_strtolower($c['name'].' '.$c['description']),
                'icon' => $t[4], 'bg' => $t[1], 'c' => $t[0], 'ink' => $t[2],
                'img' => $photo ? $this->asset($photo[0]) : null, 'alt' => $photo[1] ?? '', 'pos' => $photo[2] ?? 'center',
                'delay' => number_format(0.05 + $i * 0.06, 2).'s',
            ];
        }
        $featured = array_values(array_filter($cards, fn ($c) => $c['img']));
        $rest = array_values(array_filter($cards, fn ($c) => ! $c['img']));
        usort($rest, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'stats' => [[(string) array_sum($totals), 'resources'], [(string) count($cats), 'clinical areas'], [(string) array_sum(array_map(fn ($c) => count($c['subcategories']), $cats)), 'subtopics']],
            'featured' => $featured,
            'rest' => $rest,
        ];
    }
}
