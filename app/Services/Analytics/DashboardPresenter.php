<?php

namespace App\Services\Analytics;

/**
 * Turns MetricsSource numbers into everything the Blade views print: KPI cards, insight sentences,
 * bar widths, donut dashes, map fills and links. All derived figures (percentages, averages,
 * "unique ÷ total" ratios) are computed HERE from raw counts, never taken from the old page.
 */
final class DashboardPresenter
{
    public const PALETTE = [
        'teal' => ['#E5F6F8', '#00838F'], 'blue' => ['#EAF1FA', '#1D6FB8'], 'violet' => ['#F0EDFB', '#6A4FC7'],
        'amber' => ['#FDF3E3', '#A86200'], 'green' => ['#E7F5EF', '#12805C'], 'rose' => ['#FCEBEE', '#B23149'],
        'grey' => ['#F3F6F6', '#556B6E'],
    ];

    private const TAGS = [
        'action' => ['Action needed', '#FCEBEE', '#A12A41', 'alert'],
        'leader' => ['Leading county', '#E5F6F8', '#00505A', 'pin'],
        'up' => ['Momentum', '#E7F5EF', '#0E6A4C', 'trend-up'],
        'down' => ['Slowing', '#FDF3E3', '#8A5000', 'trend-down'],
        'watch' => ['Watch', '#FDF3E3', '#8A5000', 'clock'],
        'good' => ['On track', '#E7F5EF', '#0E6A4C', 'check-circle'],
        'missed' => ['Missed opportunity', '#FDF3E3', '#8A5000', 'alert'],
        'follow' => ['Follow up', '#EAF1FA', '#174F82', 'clock'],
        'check' => ['Check', '#F0EDFB', '#4E3A9E', 'alert'],
        'best' => ['Best coverage', '#E7F5EF', '#0E6A4C', 'trend-up'],
    ];

    private string $base;

    public function __construct(private MetricsSource $m, private string $mapFile = '') {}

    /** Build the view model for one dashboard. $base is the page URL without query string. */
    public function present(DashboardFilters $f, string $base, string $asOf): array
    {
        $this->base = $base;
        $data = match ($f->mode) {
            'emonc' => $this->emonc($f),
            'mentor' => $this->mentor($f),
            'assessment' => $this->assessment($f),
            'training' => $this->training($f),
            default => $this->mentorship($f),
        };

        return array_merge($this->shell($f, $asOf), $data);
    }

    /* ───────────────────────────── shared ───────────────────────────── */

    private function link(DashboardFilters $f, array $override = []): string
    {
        $q = http_build_query($f->toQuery($override));

        return $this->base.($q ? '?'.$q : '');
    }

    private function shell(DashboardFilters $f, string $asOf): array
    {
        $tabs = [
            ['mentorship', 'Newborn & Child', 'child'], ['emonc', 'EmONC', 'activity'], ['mentor', 'Mentors', 'award'],
            ['assessment', 'Assessments', 'clipboard'], ['training', 'Trainings', 'cap'],
        ];
        $opts = $this->m->filterOptions($f);

        return [
            'f' => $f,
            'asOf' => $asOf,
            'tabs' => array_map(fn ($t) => ['label' => $t[1], 'icon' => $t[2], 'on' => $t[0] === $f->mode, 'href' => $this->base.'?mode='.$t[0]], $tabs),
            'resetHref' => $this->base.'?mode='.$f->mode,
            'options' => $opts + ['counties' => array_column($this->geometry(), 'n')],
        ];
    }

    private function kpi(string $label, string $value, string $sub, string $icon, string $tone, ?string $chip = null, ?float $bar = null, ?string $suffix = null): array
    {
        [$bg, $ink] = self::PALETTE[$tone];

        return compact('label', 'value', 'sub', 'icon', 'chip', 'bar', 'suffix', 'bg', 'ink');
    }

    private function insight(string $tag, string $title, string $body): array
    {
        [$label, $bg, $ink, $icon] = self::TAGS[$tag];

        return compact('label', 'bg', 'ink', 'icon', 'title', 'body');
    }

    private function monthLabel(string $ym, bool $withYear = true): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m', $ym);

        return $d ? $d->format($withYear ? 'M y' : 'M') : $ym;
    }

    private function monthLong(string $ym): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m', $ym);

        return $d ? $d->format('M Y') : $ym;
    }

    public static function personName(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        if ($s === mb_strtoupper($s) || $s === mb_strtolower($s)) {
            $s = mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
        }

        return $s;
    }

    private static function norm(string $s): string
    {
        return preg_replace('/[^a-z]/', '', strtolower($s));
    }

    /** County shapes (400×507 viewBox) from resources/data/kenya-counties.json. */
    private function geometry(): array
    {
        static $geo = null;
        if ($geo === null) {
            $file = $this->mapFile ?: dirname(__DIR__, 3).'/resources/data/kenya-counties.json';
            $geo = json_decode((string) file_get_contents($file), true) ?: [];
        }

        return $geo;
    }

    /**
     * Map + focus panel + ranking for mentorship or training.
     * $tiers: list of [min, colour, legend label] ascending; the first entry is "none".
     */
    private function geo(DashboardFilters $f, array $reach, array $tiers, string $unit, int $rankLimit): array
    {
        $byNorm = [];
        foreach ($reach as $name => $row) {
            $byNorm[self::norm($name)] = $row + ['name' => $name];
        }
        $fill = function (int $v) use ($tiers) {
            $c = $tiers[0][1];
            foreach ($tiers as [$min, $col]) {
                if ($v >= $min && $min > 0) {
                    $c = $col;
                }
            }

            return $c;
        };
        $sel = $f->county ? self::norm($f->county) : null;
        $counties = [];
        $selD = null;
        foreach ($this->geometry() as $g) {
            $r = $byNorm[self::norm($g['n'])] ?? ['programmes' => 0, 'people' => 0, 'facilities_reached' => 0, 'facilities_total' => 0];
            $isSel = $sel === self::norm($g['n']);
            if ($isSel) {
                $selD = $g['d'];
            }
            $counties[] = [
                'name' => $g['n'], 'd' => $g['d'], 'fill' => $fill($r['people']), 'selected' => $isSel,
                'href' => $this->link($f, ['county' => $isSel ? null : $g['n']]),
                'label' => $g['n'].' — '.($r['people'] ? Viz::fmt($r['people']).' '.$unit : 'none yet'),
            ];
        }

        $active = array_filter($reach, fn ($r) => $r['people'] > 0);
        uksort($active, fn ($a, $b) => $active[$b]['people'] <=> $active[$a]['people'] ?: strcmp($a, $b));
        $max = max([1, ...array_column($active, 'people')]);
        $ranking = [];
        $i = 0;
        foreach ($active as $name => $r) {
            $tierColour = $fill($r['people']);
            $ranking[] = [
                'rank' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'name' => $name, 'v' => Viz::fmt($r['people']),
                'w' => number_format($r['people'] / $max * 100, 1).'%', 'color' => in_array($tierColour, [$tiers[0][1], $tiers[1][1]], true) ? $tiers[2][1] : $tierColour,
                'on' => $sel === self::norm($name), 'href' => $this->link($f, ['county' => $sel === self::norm($name) ? null : $name]),
                'extra' => $i >= $rankLimit, 'delay' => Viz::delay(min($i, 12), 0.2, 0.03),
            ];
            $i++;
        }
        $names = array_keys($active);

        $focus = null;
        if ($sel && isset($byNorm[$sel])) {
            $r = $byNorm[$sel];
            $pos = array_search($r['name'], $names, true);
            $focus = [
                'eyebrow' => 'County focus', 'name' => $r['name'],
                'people' => Viz::fmt($r['people']), 'programmes' => Viz::fmt($r['programmes']),
                'facilities' => $r['facilities_reached'].' / '.Viz::fmt($r['facilities_total']),
                'coverage' => Viz::pct($r['facilities_reached'], $r['facilities_total'], 1),
                'note' => $pos !== false
                    ? 'Ranked #'.($pos + 1).' of '.count($names).' counties by '.$unit.'.'
                    : 'Nothing recorded in this county yet — '.Viz::fmt($r['facilities_total']).' registered facilities.',
            ];
        }

        return [
            'map' => ['counties' => $counties, 'selD' => $selD],
            'legend' => array_map(fn ($t) => ['c' => $t[1], 't' => $t[2]], array_values(array_filter($tiers, fn ($t) => $t[2] !== null))),
            'ranking' => $ranking,
            'rankCount' => count($ranking),
            'rankLimit' => $rankLimit,
            'focus' => $focus,
            'hasCounty' => (bool) $focus,
            'resetCountyHref' => $this->link($f, ['county' => null]),
            'countiesActive' => count($names),
            'peopleMapped' => array_sum(array_column($reach, 'people')),
        ];
    }

    /** Share of facilities reached, per facility type (types with ≥30 facilities, or ≥5 and some reach). */
    private function levels(array $types, string $color, string $colorMid, string $ink): array
    {
        $names = [
            'TEACHING & REFERRAL HOSPITAL' => 'Teaching & referral hospital', 'COUNTY REFERRAL HOSPITAL' => 'County referral hospital',
            'SUB COUNTY HOSPITAL' => 'Sub-county hospital', 'HEALTH CENTRE' => 'Health centre', 'MISSION HOSPITAL' => 'Mission hospital',
            'FAITH BASED ORGANIZATION' => 'Faith-based organisation', 'DISPENSARY' => 'Dispensary', 'NOT CLASSIFIED' => 'Not classified',
        ];
        $rows = array_values(array_filter($types, fn ($t) => $t['total'] >= 30 || ($t['total'] >= 5 && $t['reached'] > 0)));
        usort($rows, fn ($a, $b) => ($b['reached'] / $b['total']) <=> ($a['reached'] / $a['total']) ?: $a['total'] <=> $b['total']);

        return array_map(function ($t, $i) use ($names, $color, $colorMid, $ink) {
            $p = $t['reached'] / $t['total'] * 100;
            $on = $t['reached'] > 0;

            return [
                'label' => $names[strtoupper($t['type'])] ?? ucfirst(strtolower($t['type'])),
                'pct' => Viz::pct($t['reached'], $t['total']), 'w' => number_format($on ? max(1.5, $p) : 0, 1).'%',
                'of' => Viz::fmt($t['reached']).' of '.Viz::fmt($t['total']).' facilities',
                'color' => ! $on ? '#C9D5D7' : ($p >= 10 ? $color : $colorMid), 'ink' => $on ? $ink : '#556B6E',
                'bg' => $on ? '#FFFFFF' : '#F8FAFA', 'delay' => Viz::delay($i, 0.2, 0.06),
            ];
        }, array_slice($rows, 0, 8), array_keys(array_slice($rows, 0, 8)));
    }

    /* ───────────────────────────── Newborn & Child ───────────────────────────── */

    private function mentorship(DashboardFilters $f): array
    {
        $s = $this->m->mentorshipSummary($f);
        $reg = $this->m->registeredFacilities($f);
        $reach = $this->m->countyReach('mentorship', $f);
        $types = $this->m->facilityTypeReach('mentorship', $f);
        $months = $this->m->mentorshipsStartedByMonth($f);
        $buckets = $this->m->moduleCompletionBuckets($f);
        $enrol = $this->m->menteeEnrolmentStatus($f);
        $life = $this->m->classLifecycle($f);

        $geo = $this->geo($f, $reach, [[0, '#E2EBEC', 'None'], [1, '#B2EBF2', '1–9'], [10, '#4FCFDD', '10–19'], [20, '#0097A7', '20–49'], [50, '#00505A', '50+']], 'mentees', 10);
        $counties = $geo['countiesActive'];
        $perM = $s['mentorships'] ? $s['unique_mentees'] / $s['mentorships'] : 0;
        $att = $s['module_attendance_pct'];
        $attRating = $att === null ? null : ($att < 60 ? 'Low' : ($att < 80 ? 'Fair' : 'Good'));

        $kpis = [
            $this->kpi('Mentorships', Viz::fmt($s['mentorships']), Viz::fmt($s['completed']).' completed · '.Viz::fmt($s['active']).' active', 'layers', 'teal'),
            $this->kpi('Unique mentees', Viz::fmt($s['unique_mentees']), 'Reached across '.$counties.' counties', 'users', 'blue'),
            $this->kpi('Facilities reached', Viz::fmt($s['facilities']), 'of '.Viz::fmt($reg).' registered facilities', 'building', 'violet', Viz::pct($s['facilities'], $reg)),
            $this->kpi('Counties reached', (string) $counties, '', 'pin', 'amber', Viz::pct($counties, 47), $counties / 47 * 100, ' / 47'),
            $this->kpi('Mentees per mentorship', Viz::fmt($perM, 1), Viz::fmt($s['unique_mentees']).' unique ÷ '.Viz::fmt($s['mentorships']).' mentorships', 'user-check', 'green'),
            $this->kpi('Module attendance', $att === null ? '—' : Viz::fmt($att).'%', $att === null ? 'Not recorded yet' : '', 'calendar-check', $attRating === 'Good' ? 'green' : ($attRating === 'Fair' ? 'amber' : 'rose'), $attRating, $att),
        ];

        // insights
        $refTypes = array_sum(array_map(fn ($t) => preg_match('/REFERRAL|TEACHING/i', $t['type']) ? $t['reached'] : 0, $types));
        $insights = [];
        $p = $reg ? $s['facilities'] / $reg * 100 : 0;
        $insights[] = $this->insight($p < 5 ? 'action' : 'good', 'Mentorship reaches '.Viz::pct($s['facilities'], $reg).' of facilities',
            Viz::fmt($s['facilities']).' of '.Viz::fmt($reg).' registered facilities host a mentorship; '.$refTypes.' of them are county or teaching referral hospitals.');
        $ranked = $geo['ranking'];
        if ($ranked) {
            $top = $reach[$ranked[0]['name']];
            $top3 = array_slice($ranked, 0, 3);
            $share = array_sum(array_map(fn ($r) => $reach[$r['name']]['people'], $top3));
            $follow = array_map(fn ($r) => $r['name'].' ('.$r['v'].')', array_slice($ranked, 1, 2));
            $insights[] = $this->insight('leader', $ranked[0]['name'].' leads with '.$ranked[0]['v'].' mentees',
                'Across '.$top['programmes'].' mentorships'.($follow ? ', followed by '.implode(' and ', $follow) : '').'. Together they hold '.Viz::pct($share, max(1, $s['unique_mentees']), 0).' of all mentees.');
        }
        $keys = array_keys($months);
        $last = end($months) ?: 0;
        $prev = count($months) > 1 ? $months[$keys[count($keys) - 2]] : 0;
        $total = array_sum($months);
        $isMax = $last > 0 && $last === max($months);
        $insights[] = $this->insight($last >= $prev ? 'up' : 'down', $last.' mentorships started in '.$this->monthLong((string) end($keys)),
            ($isMax ? 'The busiest month in the last year — ' : '').Viz::pct($last, max(1, $total), 0).' of the '.$total.' started in the last 12 months, against '.$prev.' the month before.');
        $tracked = array_sum($buckets);
        $insights[] = $this->insight('watch', Viz::pct($buckets['0-25'], max(1, $tracked), 0).' of mentees are below 25% completion',
            Viz::fmt($buckets['0-25']).' of '.Viz::fmt($tracked).' tracked mentees have completed under a quarter of their modules; '.Viz::fmt($buckets['100']).' ('.Viz::pct($buckets['100'], max(1, $tracked), 0).') have finished all of them.');

        $trendSeries = [];
        foreach ($months as $ym => $v) {
            $trendSeries[$this->monthLabel($ym)] = $v;
        }
        $compColors = ['0-25' => ['0–25%', '#D9465F'], '26-50' => ['26–50%', '#E89B1C'], '51-75' => ['51–75%', '#F2C14E'], '76-99' => ['76–99%', '#4FCFDD'], '100' => ['100%', '#0097A7']];
        $bmax = max([1, ...array_values($buckets)]);
        $completion = [];
        $i = 0;
        foreach ($compColors as $k => [$label, $c]) {
            $v = $buckets[$k] ?? 0;
            $completion[] = ['label' => $label, 'v' => Viz::fmt($v), 'h' => number_format(max(2, $v / $bmax * 100), 1).'%', 'pct' => Viz::pct($v, max(1, $tracked), 0), 'color' => $c, 'delay' => Viz::delay($i++, 0.2, 0.08)];
        }
        $lifeTotal = array_sum($life);
        $lc = [['Draft', $life['draft'], '#9AA9AB', '#F3F6F6'], ['Active', $life['active'], '#E89B1C', '#FDF3E3'], ['Completed', $life['completed'], '#0097A7', '#E5F6F8']];
        $cadres = $this->m->menteesByCadre($f);

        return $geo + [
            'title' => 'Infant, Child & Newborn Care',
            'pageTitle' => 'Newborn & Child Care Dashboard — MNCH',
            'eyebrow' => 'MNCH Analytics · Facility-based mentorship',
            'eyebrowColor' => '#7FDCE6',
            'lede' => "Progress of mentorship programmes, module completion and mentee outcomes across Kenya's counties.",
            'chips' => [['pin', $counties.' of 47 counties']],
            'scope' => $f->county ? 'County: '.$f->county : 'National · all counties',
            'kpis' => $kpis,
            'insights' => $insights,
            'trend' => Viz::trend($trendSeries),
            'trendRange' => $this->monthLong((string) $keys[0]).' – '.$this->monthLong((string) end($keys)),
            'trendTotal' => array_sum($months),
            'status' => Viz::donut([['Completed', $s['completed'], '#0097A7'], ['Active', $s['active'], '#E89B1C']]),
            'statusCentre' => Viz::pct($s['completed'], max(1, $s['completed'] + $s['active']), 0),
            'mentorships' => $s['mentorships'],
            'depts' => Viz::bars($this->m->menteesByDepartment($f), '#4FCFDD', '#00838F'),
            'cadres' => Viz::bars($cadres, '#7FA9DB', '#1D6FB8'),
            'cadreClash' => $this->caseClashes(array_keys($cadres)),
            'levels' => $this->levels($types, '#6A4FC7', '#B7A8EC', '#4E3A9E'),
            'facilities' => $s['facilities'], 'registered' => $reg,
            'completion' => $completion, 'tracked' => $tracked, 'fullyComplete' => $buckets['100'],
            'programmes' => Viz::bars($this->m->topMentorshipProgrammes($f), '#F2B85C', '#E08A00'),
            'enrolSegs' => Viz::donut([['Enrolled', $enrol['enrolled'], '#1D6FB8'], ['Completed', $enrol['completed'], '#0097A7']]),
            'enrolTotal' => array_sum($enrol),
            'lifecycle' => array_map(fn ($x, $i) => ['label' => $x[0], 'v' => Viz::fmt($x[1]), 'pct' => Viz::pct($x[1], max(1, $lifeTotal), 0), 'w' => number_format($lifeTotal ? $x[1] / $lifeTotal * 100 : 0, 2).'%', 'color' => $x[2], 'bg' => $x[3], 'delay' => Viz::delay($i, 0.2, 0.12)], $lc, array_keys($lc)),
            'lifeTotal' => $lifeTotal,
            'national' => ['people' => Viz::fmt($s['unique_mentees']), 'programmes' => Viz::fmt($s['mentorships']), 'facilities' => Viz::fmt($s['facilities']).' / '.Viz::fmt($reg), 'coverage' => Viz::pct($s['facilities'], $reg), 'note' => $counties.' of 47 counties have at least one mentee. Select a county to drill in.'],
        ];
    }

    /** Labels that differ only by letter case, e.g. "Clinical Officer" / "Clinical officer". */
    private function caseClashes(array $labels): array
    {
        $seen = [];
        foreach ($labels as $l) {
            $seen[mb_strtolower(trim($l))][] = $l;
        }

        return array_values(array_filter($seen, fn ($g) => count($g) > 1));
    }

    /* ───────────────────────────── EmONC ───────────────────────────── */

    private function emonc(DashboardFilters $f): array
    {
        $s = $this->m->emoncSummary($f);
        $months = $this->m->emoncClassesByMonth($f);
        $k = [
            ['Active mentees', 'active_mentees', 'Progress', 'users', 'teal'], ['Active classes', 'active_classes', 'Progress', 'play', 'blue'],
            ['Completed classes', 'completed_classes', 'Progress', 'check-circle', 'green'], ['Modules done', 'modules_done', 'Progress', 'book', 'violet'],
            ['Pending video reviews', 'pending_video_reviews', 'Queue', 'video', 'amber'], ['Awaiting mentor approval', 'awaiting_mentor_approval', 'Queue', 'user-check', 'amber'],
            ['Pending DRMH certifications', 'pending_drmh', 'Queue', 'shield-check', 'rose'], ['Certificates issued', 'certificates_issued', 'Outcome', 'award', 'teal'],
        ];
        $series = [];
        foreach ($months as $ym => $v) {
            $series[$this->monthLabel($ym, false)] = $v;
        }
        $empty = ($s['active_mentees'] + $s['active_classes'] + $s['completed_classes'] + $s['modules_done']) === 0;
        $queue = $s['pending_video_reviews'] + $s['awaiting_mentor_approval'] + $s['pending_drmh'];

        return [
            'title' => 'Emergency Obstetric & Newborn Care',
            'pageTitle' => 'EmONC Dashboard — MNCH',
            'eyebrow' => 'MNCH Analytics · Maternal EmONC', 'eyebrowColor' => '#FFB3BF',
            'lede' => 'Mentee progress, competency assessments and the certification pipeline for EmONC mentorship.',
            'chips' => [],
            'empty' => $empty,
            'kpis' => array_map(fn ($x) => $this->kpi($x[0], Viz::fmt($s[$x[1]]), '', $x[3], $x[4], $x[2]), $k),
            'steps' => [
                ['Modules completed', 'Mentees with all modules signed off', '#0097A7', $s['modules_done']],
                ['Video review', 'Practical videos awaiting review', '#1D6FB8', $s['pending_video_reviews']],
                ['Mentor approval', 'Reviewed, awaiting mentor sign-off', '#6A4FC7', $s['awaiting_mentor_approval']],
                ['DRMH certification', 'Approved, awaiting Head DRMH', '#C2560C', $s['pending_drmh']],
                ['Certified', 'Certificates issued', '#12805C', $s['certificates_issued']],
            ],
            'queueClear' => $queue === 0,
            'queue' => [
                ['Video reviews', 'video', 'amber', $s['pending_video_reviews']],
                ['Mentor approvals', 'user-check', 'violet', $s['awaiting_mentor_approval']],
                ['Head DRMH certifications', 'shield-check', 'rose', $s['pending_drmh']],
            ],
            'cols' => Viz::columns($series, '#F08497', '#C23B54', max(3, Viz::niceMax(max([0, ...array_values($series)])))),
            'trendEmpty' => array_sum($months) === 0,
            'breakdowns' => [['By department', 'Top departments by mentee count'], ['By cadre', 'Professional roles distribution'], ['Host facilities by type', 'EmONC host facilities by category'], ['Top counties', 'Leading counties for EmONC mentorship'], ['Mentee status', 'Current enrolment status'], ['Top EmONC programmes', 'By mentee count'], ['Module completion', 'Completed · in progress · not started'], ['Completion per mentee', 'Distribution of modules completed']],
            'matrix' => $this->m->emoncMenteeMatrix($f),
        ];
    }

    /* ───────────────────────────── Mentors ───────────────────────────── */

    private function mentor(DashboardFilters $f): array
    {
        $s = $this->m->mentorSummary($f);
        $lv = $s['levels'];
        $mentors = max(1, $s['mentors']);
        $classes = $s['active_classes'] + $s['completed_classes'] + $s['draft_classes'];
        $kpis = [
            $this->kpi('Total mentors', Viz::fmt($s['mentors']), $lv['F'].' Foundation · '.$lv['P'].' P · '.$lv['AP'].' AP', 'users', 'teal'),
            $this->kpi('Active classes', Viz::fmt($s['active_classes']), 'Live right now', 'play', 'blue'),
            $this->kpi('Completed classes', Viz::fmt($s['completed_classes']), 'Plus '.$s['draft_classes'].' in draft', 'check-circle', 'green'),
            $this->kpi('Modules facilitated', Viz::fmt($s['modules_facilitated']), 'Across live classes', 'book', 'violet'),
            $this->kpi('Mentees supported', Viz::fmt($s['mentees_supported']), 'Enrolled in live classes', 'hand', 'amber'),
            $this->kpi('Total CPD points', Viz::fmt($s['cpd_points']), 'Awarded to mentors', 'star', 'rose'),
            $this->kpi('Avg CPD per mentor', Viz::fmt($s['cpd_points'] / $mentors, 1), Viz::fmt($s['cpd_points']).' ÷ '.$s['mentors'].' mentors', 'gauge', 'teal'),
            $this->kpi('Mentees per mentor', Viz::fmt($s['mentees_supported'] / $mentors, 1), Viz::fmt($s['mentees_supported']).' ÷ '.$s['mentors'].' mentors', 'split', 'blue'),
            $this->kpi('Inactive mentors', Viz::fmt($s['inactive_mentors']), Viz::pct($s['inactive_mentors'], $mentors, 0).' of all mentors', 'pause', 'rose'),
            $this->kpi('Dropped mentees', Viz::fmt($s['dropped_mentees']), $s['dropped_mentees'] ? 'Left before completing' : 'No drop-outs recorded', 'exit', 'green'),
            $this->kpi('Avg assessment score', $s['avg_assessment_score'] === null ? '—' : Viz::fmt($s['avg_assessment_score'], 1).'%', $s['avg_assessment_score'] === null ? 'No scores recorded yet' : 'Across assessed mentees', 'clipboard', $s['avg_assessment_score'] === null ? 'grey' : 'violet'),
            $this->kpi('Avg practical skills', $s['avg_practical_score'] === null ? '—' : Viz::fmt($s['avg_practical_score'], 1).'%', $s['avg_practical_score'] === null ? 'No scores recorded yet' : 'Across assessed mentees', 'award', $s['avg_practical_score'] === null ? 'grey' : 'violet'),
        ];

        $fac = array_map(fn ($r) => [
            'name' => $r['facility'], 'classes' => $r['classes'].($r['classes'] === 1 ? ' class' : ' classes'),
            'comp' => Viz::fmt($r['completion_pct']).'%', 'cw' => number_format(max(1.5, $r['completion_pct']), 1).'%',
            'cc' => $r['completion_pct'] < 25 ? '#D9465F' : ($r['completion_pct'] < 75 ? '#E89B1C' : '#12805C'),
            'att' => Viz::fmt($r['attendance_pct']).'%', 'aw' => number_format(max(1.5, min(100, $r['attendance_pct'])), 1).'%',
            'flag' => $r['attendance_pct'] > 100,
        ], $this->m->facilityExceptions($f));
        $inactive = $this->m->inactiveMentors($f);
        $maxDays = max([1, ...array_column($inactive, 'days')]);
        $inactive = array_map(fn ($r, $i) => ['name' => self::personName($r['name']), 'days' => $r['days'], 'w' => number_format($r['days'] / $maxDays * 100, 1).'%', 'c' => $r['days'] >= 120 ? '#D9465F' : ($r['days'] >= 60 ? '#E89B1C' : '#F2C14E'), 'extra' => $i >= 10], $inactive, array_keys($inactive));
        $zero = array_map(fn ($r) => ['name' => self::personName($r['name']), 'facility' => $r['facility'], 'classes' => $r['classes']], $this->m->zeroCpdMentors($f));

        $matrix = $this->m->mentorClassMatrix($f);
        $zeroInMatrix = count(array_unique(array_map(fn ($r) => $r['mentor'], array_filter($matrix, fn ($r) => $r['cpd'] === 0))));
        $levelNames = ['AP' => ['Advanced Practitioner', '#F0EDFB', '#5B3FB8', '#6A4FC7'], 'P' => ['Practitioner', '#EAF1FA', '#174F82', '#1D6FB8'], 'F' => ['Foundation', '#E5F6F8', '#00505A', '#0097A7']];
        $rows = array_map(function ($r) use ($levelNames) {
            $p = $r['modules_total'] ? $r['modules_done'] / $r['modules_total'] : 0;
            $L = $levelNames[$r['level']] ?? $levelNames['F'];

            return [
                'n' => self::personName($r['mentor']), 'county' => $r['county'], 'cadre' => $r['cadre'], 'dept' => $r['department'],
                'fac' => $r['facility'], 'cls' => $r['class'], 'done' => $r['modules_done'].' / '.$r['modules_total'],
                'pw' => number_format(max(2, $p * 100), 0).'%', 'pc' => $p >= 1 ? '#12805C' : ($p > 0 ? '#E89B1C' : '#D9465F'),
                'm' => $r['mentees'], 'cpd' => $r['cpd'], 'l' => $r['level'], 'lname' => $L[0], 'lbg' => $L[1], 'link' => $L[2],
            ];
        }, $matrix);

        $leaders = function (array $pairs, string $top, string $rest) {
            $max = max([1, ...array_values($pairs)]);
            $medals = [['#F7D774', '#6B4E00'], ['#DCE3E5', '#3D5558'], ['#F0C4A0', '#7A3E12']];
            $out = [];
            $i = 0;
            foreach ($pairs as $name => $v) {
                $m = $medals[$i] ?? ['#F3F6F6', '#556B6E'];
                $out[] = ['rank' => $i + 1, 'name' => self::personName((string) $name), 'v' => $v, 'w' => number_format($v / $max * 100, 1).'%', 'c' => $i === 0 ? $top : $rest, 'medal' => $m[0], 'medalInk' => $m[1], 'delay' => Viz::delay($i, 0.1, 0.05)];
                $i++;
            }

            return $out;
        };

        $series = [];
        foreach ($this->m->classesStartedByMonth($f) as $ym => $v) {
            $series[$this->monthLabel($ym, false)] = $v;
        }
        $peakMonth = array_keys($series, max($series))[0] ?? '';
        $modules = [];
        foreach ($this->m->mentorsByModule($f) as $title => $v) {
            $short = preg_replace(['/^Module\s*(\d+)\s*:\s*/i', '/\s*\([^)]*\)\s*$/'], ['M$1 · ', ''], (string) $title);
            $modules[$short] = $v;
        }
        $lvTotal = max(1, array_sum($lv));
        $lvMax = max(1, ...array_values($lv));

        return [
            'title' => 'Mentors',
            'pageTitle' => 'Mentor Analytics Dashboard — MNCH',
            'eyebrow' => 'MNCH Analytics · Mentor performance', 'eyebrowColor' => '#C9B8FF',
            'lede' => 'Who is mentoring, how much they deliver, CPD points earned and where follow-up is needed.',
            'chips' => [['play', $s['active_classes'].' live classes']],
            'kpis' => $kpis,
            'exceptionsTotal' => count($fac) + count($inactive) + count($zero),
            'facEx' => $fac, 'inactive' => $inactive, 'zeroCpd' => $zero,
            'zeroCpdNote' => $zeroInMatrix > count($zero) ? 'The performance matrix shows '.$zeroInMatrix.' mentors on 0 CPD points; only those with a closed class are listed as exceptions.' : null,
            'cpdLeaders' => $leaders($this->m->cpdLeaders($f), '#B23149', '#E8899A'),
            'reachLeaders' => $leaders($this->m->reachLeaders($f), '#1D6FB8', '#7FA9DB'),
            'levelRows' => array_map(fn ($code) => ['code' => $code, 'label' => $levelNames[$code][0], 'v' => $lv[$code], 'pct' => Viz::pct($lv[$code], $lvTotal, 0), 'w' => number_format(max(2, $lv[$code] / $lvMax * 100), 1).'%', 'c' => $levelNames[$code][3], 'bg' => $levelNames[$code][1], 'ink' => $levelNames[$code][2]], ['AP', 'P', 'F']),
            'foundationShare' => Viz::pct($lv['F'], $lvTotal, 0),
            'cols' => Viz::columns($series, '#B7A8EC', '#6A4FC7'),
            'peak' => $peakMonth ? 'Peak: '.max($series).' in '.$peakMonth : null,
            'classTotal' => array_sum($series),
            'classSegs' => Viz::donut([['Active', $s['active_classes'], '#E89B1C'], ['Completed', $s['completed_classes'], '#0097A7'], ['Draft', $s['draft_classes'], '#9AA9AB']]),
            'classes' => $classes,
            'byFac' => Viz::bars($this->m->mentorsByFacility($f), '#4FCFDD', '#00838F'),
            'byDept' => Viz::bars($this->m->mentorsByDepartment($f), '#7FA9DB', '#1D6FB8'),
            'byMod' => Viz::bars($modules, '#B7A8EC', '#6A4FC7'),
            'rows' => $rows,
            'scope' => $f->isFiltered() ? count($rows).' of '.$s['active_classes'].' live classes' : 'All '.$s['active_classes'].' live classes',
        ];
    }

    /* ───────────────────────────── Assessments ───────────────────────────── */

    private function assessment(DashboardFilters $f): array
    {
        $s = $this->m->assessmentSummary($f);
        $reg = $this->m->registeredFacilities($f);

        // one row per facility (MFL code): duplicates of the same latest assessment are dropped
        $facilities = [];
        foreach ($this->m->assessedFacilities($f) as $r) {
            $facilities[$r['mfl'] ?: $r['facility']] ??= $r;
        }
        $facilities = array_values($facilities);
        $n = count($facilities);
        $has = fn ($r) => $r['skills_lab'] === 'Yes' || $r['room'] === 'Yes';
        $labs = count(array_filter($facilities, fn ($r) => $r['skills_lab'] === 'Yes'));
        $ready = count(array_filter($facilities, $has));
        $elig = count(array_filter($facilities, fn ($r) => $r['eligibility'] === 'Eligible'));
        $partial = count(array_filter($facilities, fn ($r) => $r['eligibility'] === 'Partial'));
        $withM = count(array_filter($facilities, fn ($r) => $r['mentorships'] > 0));
        $pending = count(array_filter($facilities, fn ($r) => $r['feedback'] === 'Pending'));
        $good = count(array_filter($facilities, fn ($r) => $has($r) && $r['mentorships'] > 0));
        $missed = array_values(array_filter($facilities, fn ($r) => $has($r) && $r['mentorships'] === 0));
        $none = $n - $good - count($missed);
        $nonEligWithM = array_values(array_filter($facilities, fn ($r) => ! $has($r) && $r['mentorships'] > 0));
        usort($nonEligWithM, fn ($a, $b) => $b['mentorships'] <=> $a['mentorships']);
        $eligNotTrained = count(array_filter($facilities, fn ($r) => $r['eligibility'] === 'Eligible' && $r['trained'] === 'No'));
        $avg = $s['avg_score'];
        $grade = $avg === null ? null : ($avg >= 80 ? 'Good' : ($avg >= 50 ? 'Fair' : 'Poor'));
        $g = $this->m->gradeDistribution($f);

        $kpis = [
            $this->kpi('Completed assessments', Viz::fmt($s['completed']), '+'.$s['in_progress'].' in progress', 'clipboard', 'teal', $s['yoy_pct'] === null ? null : Viz::fmt($s['yoy_pct']).'% YoY'),
            $this->kpi('Average score', $avg === null ? '—' : Viz::fmt($avg, 1).'%', '', 'gauge', 'amber', $grade, $avg),
            $this->kpi('Skills lab or room', (string) $ready, $labs.' skills labs · '.($ready - $labs).' rooms only', 'flask', 'violet', Viz::pct($ready, max(1, $n), 0), null, ' / '.$n),
            $this->kpi('Eligible for mentorship', (string) $elig, '+'.$partial.' partial, awaiting feedback', 'check-circle', 'green'),
            $this->kpi('With a mentorship', (string) $withM, ($n - $withM).' assessed facilities without', 'layers', 'blue', Viz::pct($withM, max(1, $n), 0)),
            $this->kpi('Assessment coverage', Viz::pct($n, $reg), $n.' of '.Viz::fmt($reg).' facilities', 'pin', 'rose', ($n / max(1, $reg) * 100) < 5 ? 'Low' : null),
        ];

        $insights = [
            $this->insight('action', Viz::fmt($g['poor']).' assessments score Poor (<50%)', 'Against '.$g['fair'].' Fair and '.$g['good'].' Good among '.array_sum($g).' completed assessments.'),
            $this->insight('missed', count($missed).' ready facilities have no mentorship', 'They have a skills lab or room'.($missed ? ' — including '.implode(', ', array_map(fn ($r) => preg_replace('/\s+(County|Sub|District|Level|Referr?al|Maternity|Hospital).*$/i', '', $r['facility']), array_slice($missed, 0, 3))) : '').'.'),
            $this->insight('follow', $pending.' facilities await feedback', 'Feedback unlocks eligibility for the '.$partial.' partial facilities with a room.'.($eligNotTrained ? ' '.$eligNotTrained.' eligible facilities are not yet trained.' : '')),
            $this->insight('check', count($nonEligWithM).' non-eligible sites already run mentorship', $nonEligWithM ? implode(' and ', array_map(fn ($r) => preg_replace('/\s+(County|Sub|District|Level|Referr?al|Provincial|Hospital).*$/i', '', $r['facility']).' ('.$r['mentorships'].')', array_slice($nonEligWithM, 0, 2))).' lead — confirm whether their lab status is current.' : 'None.'),
        ];

        $series = [];
        foreach ($this->m->assessmentsByMonth($f) as $ym => $t) {
            $series[$this->monthLabel($ym, false)] = $t;
        }
        $totals = array_map(fn ($t) => array_sum($t), $series);
        $ymax = Viz::niceMax(max([0, ...array_values($totals)]));
        $peak = max([0, ...array_values($totals)]);
        $months = [];
        $i = 0;
        foreach ($series as $label => $t) {
            $tot = array_sum($t);
            $months[] = ['label' => $label, 'v' => $tot, 'has' => $tot > 0, 'h' => $tot ? number_format($tot / $ymax * 100, 1).'%' : '3px',
                'segs' => array_values(array_filter([['#0097A7', $t['baseline']], ['#E89B1C', $t['midline']], ['#6A4FC7', $t['endline']]], fn ($x) => $x[1] > 0)),
                'peak' => $tot === $peak && $tot > 0, 'delay' => Viz::delay($i++)];
        }
        $typeTotals = ['baseline' => 0, 'midline' => 0, 'endline' => 0];
        foreach ($series as $t) {
            foreach ($t as $k => $v) {
                $typeTotals[$k] += $v;
            }
        }

        // pair duplicate section names ("Quality of Care" twice …)
        $groups = [];
        foreach ($this->m->sectionScores($f) as $r) {
            $words = array_filter(explode(' ', preg_replace('/[^a-z ]/', '', str_replace('&', ' and ', strtolower($r['section'])))), fn ($w) => $w !== '' && ! in_array($w, ['and', 'for', 'the', 'of'], true));
            $key = implode(' ', array_map(fn ($w) => rtrim($w, 's'), $words));
            $label = ucfirst(strtolower(trim(preg_replace('/\s+for\s+monitoring$/i', '', $r['section']))));
            $groups[$key] ??= ['label' => $label, 'scores' => []];
            $groups[$key]['scores'][] = (float) $r['score'];
        }
        $sections = [];
        foreach (array_values($groups) as $i => $gr) {
            $hi = max($gr['scores']);
            $lo = min($gr['scores']);
            $sections[] = ['label' => $gr['label'], 'pair' => count($gr['scores']) > 1, 'hv' => Viz::fmt($hi, 1).'%', 'lv' => Viz::fmt($lo, 1).'%', 'hi' => $hi.'%', 'lo' => $lo.'%', 'span' => number_format($hi - $lo, 1).'%', 'delay' => Viz::delay($i, 0.25, 0.12), 'delay2' => Viz::delay($i, 0.55, 0.12)];
        }
        usort($sections, fn ($a, $b) => (float) $b['hv'] <=> (float) $a['hv']);

        $readiness = [
            ['good', $good, 'Good progress', 'Skills lab or room + live mentorship', 'Ready, and mentorship is already running.', '#E7F5EF', '#0E6A4C', '#12805C'],
            ['missed', count($missed), 'Missed opportunity', 'Skills lab or room, no mentorship yet', 'Ready to host mentorship — start a programme here.', '#FDF3E3', '#8A5000', '#E89B1C'],
            ['none', $none, 'Urgent', 'No skills lab or room', 'Not eligible for mentorship training until one is set up.', '#FCEBEE', '#A12A41', '#D9465F'],
        ];

        $rows = array_map(fn ($r) => [
            'n' => $r['facility'], 'id' => $r['assessment_id'] ?? null, 'code' => $r['mfl'], 'sub' => $r['subcounty'], 'county' => $r['county'], 'date' => $r['date'], 'type' => $r['type'],
            'by' => self::personName($r['assessor']), 'lab' => $r['skills_lab'], 'room' => $r['room'], 'fb' => $r['feedback'],
            'el' => $r['eligibility'], 'trained' => $r['trained'], 'ment' => $r['mentorships'],
            'rd' => $has($r) ? ($r['mentorships'] > 0 ? 'good' : 'missed') : 'none',
        ], $facilities);

        return [
            'title' => 'Facility Assessments',
            'pageTitle' => 'Assessment Analytics Dashboard — MNCH',
            'eyebrow' => 'MNCH Analytics · Facility readiness', 'eyebrowColor' => '#FFD08A',
            'lede' => 'Readiness scores, skills lab availability and mentorship eligibility across assessed facilities.',
            'chips' => [['clipboard', $typeTotals['midline'] + $typeTotals['endline'] ? 'Baseline, midline & endline' : 'Baseline round']],
            'kpis' => $kpis, 'insights' => $insights,
            'readiness' => array_map(fn ($r) => ['key' => $r[0], 'v' => $r[1], 'pct' => Viz::pct($r[1], max(1, $n), 1), 'w' => number_format($n ? $r[1] / $n * 100 : 0, 1).'%', 'tag' => $r[2], 'title' => $r[3], 'note' => $r[4], 'bg' => $r[5], 'ink' => $r[6], 'bar' => $r[7]], $readiness),
            'months' => $months, 'ticks' => Viz::ticks($ymax), 'typeTotals' => $typeTotals, 'assessTotal' => array_sum($totals),
            'grades' => Viz::donut([['Good (≥80%)', $g['good'], '#12805C'], ['Fair (50–79%)', $g['fair'], '#E89B1C'], ['Poor (<50%)', $g['poor'], '#D9465F']]),
            'gradeTotal' => array_sum($g), 'avg' => $avg === null ? '—' : Viz::fmt($avg, 1).'%',
            'sections' => $sections, 'paired' => (bool) array_filter($sections, fn ($x) => $x['pair']),
            'status' => [['Completed', $s['completed'], '#E5F6F8'], ['In progress', $s['in_progress'], '#FDF3E3'], ['Draft', $s['draft'], '#F3F6F6']],
            'statusTotal' => $s['completed'] + $s['in_progress'] + $s['draft'],
            'rows' => $rows, 'facilityCount' => $n,
            'eligCounts' => ['' => $n, 'Eligible' => $elig, 'Partial' => $partial, 'Not Eligible' => $n - $elig - $partial],
        ];
    }

    /* ───────────────────────────── Trainings ───────────────────────────── */

    private function training(DashboardFilters $f): array
    {
        $progs = $this->m->trainingProgrammes($f);
        $s = $this->m->trainingSummary($f);
        $reg = $this->m->registeredFacilities($f);
        $reach = $this->m->countyReach('training', $f);
        $types = $this->m->facilityTypeReach('training', $f);
        $geo = $this->geo($f, $reach, [[0, '#E2EBEC', null], [1, '#C9DDF3', '1–49'], [50, '#7FA9DB', '50–99'], [100, '#1D6FB8', '100–149'], [150, '#0F3B63', '150+']], 'participants', 12);
        $counties = $geo['countiesActive'];
        $enrol = max(1, $s['enrolments']);
        usort($progs, fn ($a, $b) => $b['participants'] <=> $a['participants']);
        $largest = $progs[0] ?? null;
        $colours = [['#0097A7', '#E5F6F8', '#00505A'], ['#1D6FB8', '#EAF1FA', '#174F82'], ['#6A4FC7', '#F0EDFB', '#4E3A9E'], ['#E89B1C', '#FDF3E3', '#8A5000'], ['#C23B54', '#FCEBEE', '#A12A41']];
        $selected = null;
        $cards = [];
        foreach ($progs as $i => $p) {
            $c = $colours[$i % count($colours)];
            $on = $f->programme === $p['id'];
            if ($on) {
                $selected = $p;
            }
            $cards[] = ['name' => $p['name'], 'year' => $p['year'], 'status' => $p['status'], 'part' => Viz::fmt($p['participants']), 'fac' => Viz::fmt($p['facilities']), 'share' => Viz::pct($p['participants'], $enrol, 1), 'c' => $c[0], 'bg' => $c[1], 'ink' => $c[2], 'on' => $on, 'href' => $this->link($f, ['programme' => $on ? null : $p['id']]), 'delay' => Viz::delay($i, 0.1, 0.07)];
        }

        $kpis = [
            $this->kpi('Training programmes', (string) count($progs), count(array_filter($progs, fn ($p) => strcasecmp($p['status'], 'Completed') === 0)) === count($progs) ? 'All completed' : 'Some still running', 'cap', 'teal'),
            $this->kpi('Unique participants', Viz::fmt($s['unique_participants']), Viz::fmt($s['enrolments']).' programme enrolments', 'users', 'blue'),
            $this->kpi('Facilities reached', Viz::fmt($s['facilities_reached']), 'of '.Viz::fmt($reg).' registered', 'building', 'violet', Viz::pct($s['facilities_reached'], $reg)),
            $this->kpi('Counties reached', (string) $counties, $counties === 47 ? 'Every county has trained staff' : (47 - $counties).' counties without trained staff', 'pin', 'green', Viz::pct($counties, 47, 0), null, ' / 47'),
            $selected
                ? $this->kpi(preg_replace('/\s+trained HCWs$/i', '', $selected['name']).' enrolments', Viz::fmt($selected['participants']), Viz::pct($selected['participants'], $enrol, 1).' of all enrolments', 'gauge', 'amber', (string) $selected['year'])
                : $this->kpi('Enrolments per programme', Viz::fmt($s['enrolments'] / max(1, count($progs))), Viz::fmt($s['enrolments']).' ÷ '.count($progs).' programmes', 'gauge', 'amber'),
            $this->kpi('Largest programme', $largest ? Viz::fmt($largest['participants']) : '—', $largest ? preg_replace('/\s+trained HCWs$/i', '', $largest['name']).' · '.Viz::pct($largest['participants'], $enrol, 0).' of enrolments' : '', 'star', 'rose', $largest ? (string) $largest['year'] : null),
        ];

        $dispensary = array_values(array_filter($types, fn ($t) => strtoupper($t['type']) === 'DISPENSARY'))[0] ?? null;
        $cov = array_filter($reach, fn ($r) => $r['facilities_total'] >= 20);
        uasort($cov, fn ($a, $b) => ($b['facilities_reached'] / $b['facilities_total']) <=> ($a['facilities_reached'] / $a['facilities_total']));
        $covNames = array_keys($cov);
        $best = $covNames[0] ?? null;
        $lastYear = $progs ? max(array_column($progs, 'year')) : null;
        $ranked = $geo['ranking'];
        $insights = [
            $this->insight(($s['facilities_reached'] / max(1, $reg)) < 0.1 ? 'action' : 'good', 'Training reaches '.Viz::pct($s['facilities_reached'], $reg).' of facilities',
                Viz::fmt($s['facilities_reached']).' of '.Viz::fmt($reg).' registered facilities have a trained worker'.($dispensary ? ' — dispensaries are almost untouched ('.Viz::fmt($dispensary['reached']).' of '.Viz::fmt($dispensary['total']).').' : '.')),
        ];
        if ($ranked) {
            $follow = array_map(fn ($r) => $r['name'].' ('.$r['v'].')', array_slice($ranked, 1, 2));
            $insights[] = $this->insight('leader', $ranked[0]['name'].' leads with '.$ranked[0]['v'].' participants', ($follow ? 'Followed by '.implode(' and ', $follow).'. ' : '').($counties === 47 ? 'Every county has trained staff.' : $counties.' of 47 counties have trained staff.'));
        }
        if ($best) {
            $b = $reach[$best];
            $next = array_map(fn ($n) => $n.' ('.Viz::pct($reach[$n]['facilities_reached'], $reach[$n]['facilities_total'], 1).')', array_slice($covNames, 1, 2));
            $insights[] = $this->insight('best', $best.' covers '.Viz::pct($b['facilities_reached'], $b['facilities_total'], 0).' of its facilities', $b['facilities_reached'].' of '.$b['facilities_total'].' facilities'.($next ? ' — ahead of '.implode(' and ', $next).'.' : '.'));
        }
        $insights[] = $s['last_12_months_enrolments'] === 0
            ? $this->insight('watch', 'No new training since '.$lastYear, 'No enrolments were recorded in the last 12 months.')
            : $this->insight('up', Viz::fmt($s['last_12_months_enrolments']).' enrolments in the last 12 months', 'Training activity is ongoing.');

        $byYear = $progs;
        usort($byYear, fn ($a, $b) => $a['year'] <=> $b['year']);
        $ymax = max([1, ...array_column($byYear, 'participants')]);
        $colourOf = [];
        foreach ($progs as $i => $p) {
            $colourOf[$p['id']] = $colours[$i % count($colours)][0];
        }
        $cadres = $this->m->participantsByCadre($f);

        return $geo + [
            'title' => 'Trainings',
            'pageTitle' => 'Training Analytics Dashboard — MNCH',
            'eyebrow' => 'MNCH Analytics · Health-worker training', 'eyebrowColor' => '#9CC7F2',
            'lede' => 'Reach of national training programmes for healthcare workers — who was trained, where, and in which facilities.',
            'chips' => [['pin', $counties === 47 ? 'All 47 counties' : $counties.' of 47 counties']],
            'scope' => $f->county ? 'County: '.$f->county : 'National · all counties',
            'scopeProgramme' => $selected['name'] ?? 'All programmes',
            'kpis' => $kpis, 'cards' => $cards, 'insights' => $insights,
            'levels' => $this->levels($types, '#1D6FB8', '#7FA9DB', '#174F82'),
            'facilitiesReached' => $s['facilities_reached'], 'registered' => $reg,
            'depts' => Viz::bars($this->m->participantsByDepartment($f), '#4FCFDD', '#00838F'),
            'cadres' => Viz::bars($cadres, '#7FA9DB', '#1D6FB8'),
            'cadreSum' => array_sum($cadres), 'unique' => $s['unique_participants'],
            'years' => array_map(fn ($p, $i) => ['year' => $p['year'], 'name' => preg_replace('/\s+trained HCWs$/i', '', $p['name']), 'v' => Viz::fmt($p['participants']), 'h' => number_format($p['participants'] / $ymax * 100, 1).'%', 'c' => $colourOf[$p['id']], 'delay' => Viz::delay($i, 0.2, 0.1)], $byYear, array_keys($byYear)),
            'recent' => $s['last_12_months_enrolments'],
            'unmapped' => max(0, $s['unique_participants'] - $geo['peopleMapped']),
            'national' => ['people' => Viz::fmt($s['unique_participants']), 'programmes' => (string) count($progs), 'facilities' => Viz::fmt($s['facilities_reached']).' / '.Viz::fmt($reg), 'coverage' => Viz::pct($s['facilities_reached'], $reg),
                'note' => ($s['unique_participants'] - $geo['peopleMapped']) > 0
                    ? Viz::fmt($geo['peopleMapped']).' participants have a county recorded; '.Viz::fmt($s['unique_participants'] - $geo['peopleMapped']).' do not and are not mapped.'
                    : 'Every participant has a county recorded.'],
        ];
    }
}
