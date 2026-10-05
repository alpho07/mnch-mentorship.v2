<?php

namespace App\Services;

use App\Models\Training;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the national executive brief on facility mentorship.
 *
 * Every figure is derived from Training::liveMentorship() — non-pilot,
 * active/completed, with at least one genuinely enrolled mentee — so the
 * brief reports the same universe as the analytics dashboard and never
 * counts pilot or abandoned setups as delivery.
 */
class ExecutiveBriefService 
{
    /**
     * IDs of the facility mentorships the brief reports on. Resolved once
     * per instance because every section below joins against it.
     *
     * @var array<int, int>|null
     */
    private ?array $trainingIds = null;

    /**
     * The whole brief as one array, ready to hand to either the screen
     * view or the PDF view.
     */
    public function build(): array
    {
        $ids = $this->trainingIds();

        return [
            'meta' => $this->meta(),
            'headline' => $this->headline($ids),
            'programmes' => $this->programmes($ids),
            'coverage' => $this->coverage($ids),
            'depth' => $this->depth($ids),
            'curriculum' => $this->curriculum($ids),
            'skillsLab' => $this->skillsLab(),
            'pulseOximeters' => $this->pulseOximeters(),
            'indicators' => $this->indicators(),
            'monthly' => $this->monthly($ids),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function trainingIds(): array
    {
        return $this->trainingIds ??= Training::query()
            ->where('type', 'facility_mentorship')
            ->liveMentorship()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function meta(): array
    {
        $start = CarbonImmutable::parse(config('executive_brief.window.start'));
        $end = CarbonImmutable::parse(config('executive_brief.window.end'));

        return [
            'ministry' => config('executive_brief.ministry'),
            'division' => config('executive_brief.division'),
            'programme' => config('executive_brief.programme'),
            'audience' => config('executive_brief.audience'),
            'contact' => config('executive_brief.contact'),
            'definition' => config('executive_brief.definition'),
            'windowStart' => $start,
            'windowEnd' => $end,
            'windowLabel' => strtoupper($start->format('M')).' — '.strtoupper($end->format('M Y')),
            // Whole calendar months, inclusive of both ends — "month 6 of 6",
            // not the 6.51 a float diff would produce.
            'monthsElapsed' => (int) $start->startOfMonth()
                ->diffInMonths(min($end, CarbonImmutable::now())->startOfMonth()) + 1,
            'monthsTotal' => (int) $start->startOfMonth()->diffInMonths($end->startOfMonth()) + 1,
            'generatedAt' => CarbonImmutable::now(),
        ];
    }

    /**
     * The four numbers across the top, plus the supporting line beneath.
     */
    private function headline(array $ids): array
    {
        $priority = $this->priorityCounties();
        $reached = $this->countiesReached($ids);

        return [
            'mentorships' => count($ids),
            'mentorshipsActive' => $this->trainingCountByStatus($ids, 'active'),
            'mentorshipsCompleted' => $this->trainingCountByStatus($ids, 'completed'),
            'countiesReached' => $reached->count(),
            'countiesPriority' => $priority->count(),
            'countiesPriorityReached' => $reached->intersect($priority)->count(),
            'mentees' => $this->distinctMentees($ids),
            'mentors' => $this->distinctMentors($ids),
            'facilities' => $this->distinctFacilities($ids),
            'classes' => $this->classCount($ids),
        ];
    }

    /**
     * One block per curriculum — the brief's equivalent of separating
     * EmONC from newborn mentorship.
     */
    private function programmes(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return DB::table('trainings as t')
            ->join('programs as p', 'p.id', '=', 't.program_id')
            ->leftJoin('mentorship_classes as mc', function ($join) {
                $join->on('mc.training_id', '=', 't.id')->whereNull('mc.deleted_at');
            })
            ->leftJoin('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')
            ->leftJoin('class_modules as cm', 'cm.mentorship_class_id', '=', 'mc.id')
            ->whereIn('t.id', $ids)
            ->groupBy('p.id', 'p.name')
            ->orderByDesc(DB::raw('count(distinct t.id)'))
            ->select([
                'p.name',
                DB::raw('count(distinct t.id) as mentorships'),
                DB::raw('count(distinct t.facility_id) as facilities'),
                DB::raw('count(distinct mc.id) as classes'),
                DB::raw('count(distinct cp.user_id) as mentees'),
                DB::raw('count(distinct cm.id) as modules'),
                DB::raw("count(distinct case when cm.status = 'completed' then cm.id end) as modules_completed"),
            ])
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'mentorships' => (int) $row->mentorships,
                'facilities' => (int) $row->facilities,
                'classes' => (int) $row->classes,
                'mentees' => (int) $row->mentees,
                'modules' => (int) $row->modules,
                'modulesCompleted' => (int) $row->modules_completed,
            ]);
    }

    /**
     * County-level reach, plus the priority counties still untouched —
     * the section the brief exists to deliver.
     */
    private function coverage(array $ids): array
    {
        $priority = $this->priorityCounties();

        $rows = $ids === [] ? collect() : DB::table('trainings as t')
            ->join('facilities as f', 'f.id', '=', 't.facility_id')
            ->join('subcounties as s', 's.id', '=', 'f.subcounty_id')
            ->join('counties as c', 'c.id', '=', 's.county_id')
            ->leftJoin('mentorship_classes as mc', function ($join) {
                $join->on('mc.training_id', '=', 't.id')->whereNull('mc.deleted_at');
            })
            ->leftJoin('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')
            ->whereIn('t.id', $ids)
            ->groupBy('c.name')
            ->orderByDesc(DB::raw('count(distinct t.id)'))
            ->orderBy('c.name')
            ->select([
                'c.name',
                DB::raw('count(distinct t.id) as mentorships'),
                DB::raw('count(distinct f.id) as facilities'),
                DB::raw('count(distinct cp.user_id) as mentees'),
                DB::raw("count(distinct case when cp.status = 'completed' then cp.user_id end) as mentees_completed"),
            ])
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'mentorships' => (int) $row->mentorships,
                'facilities' => (int) $row->facilities,
                'mentees' => (int) $row->mentees,
                'menteesCompleted' => (int) $row->mentees_completed,
                'isPriority' => $priority->contains($row->name),
            ]);

        $reachedNames = $rows->pluck('name');

        return [
            'counties' => $rows,
            'gap' => $priority->reject(fn ($name) => $reachedNames->contains($name))->values(),
            'outsidePriority' => $reachedNames->reject(fn ($name) => $priority->contains($name))->values(),
            'priorityTotal' => $priority->count(),
        ];
    }

    /**
     * How far mentorship actually goes once it reaches a facility —
     * reach without depth is not skill transfer.
     */
    private function depth(array $ids): array
    {
        $modules = $this->classModuleCounts($ids);
        $progress = $this->menteeProgressCounts($ids);
        $participants = $this->participantCounts($ids);

        $moduleTotal = array_sum($modules);
        $progressTotal = array_sum($progress);
        $participantTotal = array_sum($participants);

        return [
            'modules' => $modules,
            'modulesTotal' => $moduleTotal,
            'modulesCompletionRate' => $this->rate($modules['completed'] ?? 0, $moduleTotal),

            'menteeModules' => $progress,
            'menteeModulesTotal' => $progressTotal,
            'menteeModulesCompletionRate' => $this->rate($progress['completed'] ?? 0, $progressTotal),

            'participants' => $participants,
            'participantsTotal' => $participantTotal,
            'cohortCompletionRate' => $this->rate($participants['completed'] ?? 0, $participantTotal),

            'classes' => $this->classCountsByStatus($ids),
        ];
    }

    /**
     * Ranks the causes of newborn and child death against what the
     * programme is actually teaching, so the brief can name which modules
     * — among those already in the curriculum — deserve priority.
     *
     * A cause is flagged when the classes covering it are scarce or are
     * not being carried to completion. Burden ordering comes from config,
     * not from this platform; uptake comes entirely from live mentorships.
     *
     * @return array{rows: Collection, recommend: Collection}
     */
    private function clinicalPriority(array $ids): array
    {
        $modules = $this->moduleUptake($ids);
        $classes = max($this->classCount($ids), 1);

        $rows = collect(config('executive_brief.mortality_priorities', []))
            ->map(function (array $cause) use ($modules, $classes) {
                $matched = $modules->filter(fn ($m) => collect($cause['modules'])
                    ->contains(fn ($needle) => str_contains(mb_strtolower($m['name']), mb_strtolower($needle))));

                $assigned = (int) $matched->sum('assigned');
                $completed = (int) $matched->sum('completed');

                return [
                    'cause' => $cause['cause'],
                    'rank' => $cause['rank'],
                    // Only modules actually in use are named, so the brief
                    // never recommends a module no class has ever opened.
                    'moduleNames' => $matched->pluck('name')->values(),
                    'assigned' => $assigned,
                    'completed' => $completed,
                    'reach' => round($assigned / $classes * 100, 1),
                    'completionRate' => $assigned > 0 ? round($completed / $assigned * 100, 1) : 0.0,
                    // Untaught outranks half-taught: a cause no class covers
                    // is a bigger gap than one being covered slowly.
                    'gap' => $assigned === 0 ? 'Not taught' : ($completed / $assigned < 0.5 ? 'Stalling' : 'On track'),
                ];
            })
            ->sortBy('rank')
            ->values();

        // Highest-burden causes that are either untaught or stalling. These
        // are the modules to prioritise among those already in use.
        $recommend = $rows
            ->filter(fn ($r) => $r['gap'] !== 'On track')
            ->sortBy([['rank', 'asc']])
            ->take(3)
            ->values();

        return ['rows' => $rows, 'recommend' => $recommend];
    }

    /**
     * The curriculum modules mentorship is actually being delivered on,
     * split newborn / infant so the two streams read separately.
     *
     * Replaces the former roll-call of priority counties with no mentorship:
     * the Division asked what is being taught where delivery exists, rather
     * than a list of absence.
     */
    private function curriculum(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return DB::table('trainings as t')
            ->join('programs as p', 'p.id', '=', 't.program_id')
            ->join('mentorship_classes as mc', function ($join) {
                $join->on('mc.training_id', '=', 't.id')->whereNull('mc.deleted_at');
            })
            ->join('class_modules as cm', 'cm.mentorship_class_id', '=', 'mc.id')
            ->join('program_modules as pm', 'pm.id', '=', 'cm.program_module_id')
            ->whereIn('t.id', $ids)
            ->whereNull('pm.deleted_at')
            ->groupBy('p.id', 'p.name', 'pm.id', 'pm.name', 'pm.order_sequence')
            ->orderBy('p.name')
            ->orderBy('pm.order_sequence')
            ->select([
                'p.name as programme',
                'pm.name as module',
                DB::raw('count(distinct cm.id) as classes'),
                DB::raw("count(distinct case when cm.status = 'completed' then cm.id end) as completed"),
                DB::raw('count(distinct t.facility_id) as facilities'),
            ])
            ->get()
            ->groupBy('programme')
            ->map(function (Collection $rows) {
                $modules = $rows->map(fn ($r) => [
                    'module' => $r->module,
                    'classes' => (int) $r->classes,
                    'completed' => (int) $r->completed,
                    'facilities' => (int) $r->facilities,
                    // Share of the classes carrying this module that have finished it.
                    'rate' => (int) round($this->rate((int) $r->completed, (int) $r->classes)),
                ])->values();

                $classes = (int) $modules->sum('classes');
                $completed = (int) $modules->sum('completed');

                return [
                    'modules' => $modules,
                    'classes' => $classes,
                    'completed' => $completed,
                    'rate' => (int) round($this->rate($completed, $classes)),
                ];
            });
    }

    /**
     * Skills lab roll-out: where each site has reached, and what it holds.
     *
     * Reads the Division's site tracker (config/skills_lab.php), which carries
     * the readiness stages — assessment, sensitisation, roll-out, lab,
     * equipment — alongside what each site holds. Pulse oximeters are reported
     * separately from their own county tracker, so nothing here touches the
     * database.
     */
    private function skillsLab(): array
    {
        $sites = collect(config('skills_lab.sites'))
            ->map(fn (array $s) => $s + [
                // Reported nothing at all for equipment, as distinct from
                // having reported holding none.
                'unreported' => $s['preemie'] === null,
                'manikins' => ($s['preemie'] ?? 0) + ($s['neo'] ?? 0) + ($s['anne'] ?? 0),
            ])
            ->sortBy([['county', 'asc'], ['facility', 'asc']])
            ->values();

        $count = fn (string $key, $value) => $sites->where($key, $value)->count();

        return [
            'rows' => $sites,
            'sites' => $sites->count(),
            'counties' => $sites->pluck('county')->unique()->count(),

            // Roll-out pipeline
            'assessed' => $count('assessment', 1),
            'sensitized' => $count('sensitization', 1),
            'rolledOut' => $count('rollout', 1),
            'functional' => $count('lab', 'functional'),
            'roomOnly' => $count('lab', 'room'),
            'noLab' => $count('lab', 'none'),
            'labUnknown' => $count('lab', null),
            'active' => $count('active', true),

            // Equipment actually on site
            'manikinSites' => $sites->where('manikins', '>', 0)->count(),
            'manikinUnits' => $sites->sum('manikins'),
            'airSites' => $sites->where('air', '>', 0)->count(),
            'airUnits' => $sites->sum('air'),
            'poxSites' => $sites->whereNotNull('poxTotal')->count(),
            'poxUnits' => $sites->sum('poxTotal'),
            'unreported' => $count('unreported', true),
        ];
    }

    /**
     * Pulse oximeter dispatch, by county.
     *
     * Reads the Division's county dispatch tracker. This is a different view
     * from the facility allocation list in skills_lab_devices — dispatch to
     * counties versus units tagged to named facilities — so the two totals
     * are reported separately rather than reconciled.
     */
    private function pulseOximeters(): array
    {
        $counties = collect(config('pulse_oximeters.counties'))
            ->sortBy('county')
            ->values();

        $status = fn (string $s) => $counties->where('status', $s)->count();

        return [
            'rows' => $counties,
            'counties' => $counties->count(),
            'full' => $status('full'),
            'partial' => $status('partial'),
            'none' => $status('none'),
            'devices' => (int) $counties->sum('devices'),
            'facilities' => (int) $counties->sum('facilities'),
            'tagged' => $counties->where('tagged', 1)->count(),
            // Counties with receiving facilities identified but nothing sent yet.
            'awaiting' => $counties->where('status', 'none')->whereNotNull('facilities')->count(),
            'reached' => $counties->whereIn('status', ['full', 'partial'])->count(),
        ];
    }

    /**
     * The indicator framework the programme reports against, by module.
     *
     * Values are deliberately not aggregated: most indicators carry no
     * submissions yet, and the Division asked to see what is being tracked
     * rather than a table of empty results.
     */
    private function indicators(): array
    {
        $rows = DB::table('indicators as i')
            ->join('indicator_groups as g', 'g.id', '=', 'i.group_id')
            ->join('indicator_report_types as rt', 'rt.id', '=', 'g.report_type_id')
            // Top level only. The ten mortality bands hang off 7a and 7b as
            // children; listing them separately would bury the other measures.
            ->whereNull('i.parent_indicator_id')
            ->where('i.is_active', 1)
            ->orderBy('rt.id')
            ->orderBy('g.sort_order')
            ->orderBy('i.sort_order')
            ->select([
                'rt.name as stream',
                'i.id',
                'i.code',
                'i.short_name',
                'i.name',
                DB::raw('(select count(*) from indicators c where c.parent_indicator_id = i.id) as bands'),
            ])
            ->get();

        $streams = $rows->groupBy('stream')->map(fn (Collection $items) => [
            'items' => $items->map(fn ($r) => [
                'code' => $r->code,
                'label' => $r->short_name ?: $r->name,
                'bands' => (int) $r->bands,
            ])->values(),
            'total' => $items->count(),
        ]);

        return [
            'streams' => $streams,
            'total' => $rows->count(),
            'bands' => (int) $rows->sum('bands'),
        ];
    }

    /**
     * Assigned/completed counts per curriculum module across live
     * mentorships — the raw material for the priority ranking.
     */
    private function moduleUptake(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return DB::table('class_modules as cm')
            ->join('mentorship_classes as mc', 'mc.id', '=', 'cm.mentorship_class_id')
            ->join('program_modules as pm', 'pm.id', '=', 'cm.program_module_id')
            ->join('programs as p', 'p.id', '=', 'pm.program_id')
            ->whereIn('mc.training_id', $ids)
            ->whereNull('mc.deleted_at')
            ->groupBy('pm.id', 'pm.name', 'p.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->select([
                'pm.name',
                'p.name as programme',
                DB::raw('count(*) as assigned'),
                DB::raw("sum(case when cm.status = 'completed' then 1 else 0 end) as completed"),
            ])
            ->get()
            ->map(fn ($row) => [
                'name' => $this->stripModulePrefix($row->name),
                'programme' => $row->programme,
                'assigned' => (int) $row->assigned,
                'completed' => (int) $row->completed,
            ]);
    }

    /**
     * Month-by-month delivery across the reporting window, with a total
     * column — the eshot's trend table.
     */
    private function monthly(array $ids): array
    {
        $start = CarbonImmutable::parse(config('executive_brief.window.start'))->startOfMonth();
        $end = CarbonImmutable::parse(config('executive_brief.window.end'))->endOfMonth();

        $months = [];
        for ($m = $start; $m <= $end; $m = $m->addMonth()) {
            $months[$m->format('Y-m')] = $m->format('M Y');
        }

        $series = [
            'Mentorships started' => $this->monthlySeries(
                DB::table('trainings')->whereIn('id', $ids), 'start_date'
            ),
            'Health workers enrolled' => $this->firstEnrolmentSeries($ids),
            'Curriculum modules completed' => $this->monthlySeries(
                DB::table('class_modules as cm')
                    ->join('mentorship_classes as mc', 'mc.id', '=', 'cm.mentorship_class_id')
                    ->whereIn('mc.training_id', $ids)
                    ->whereNull('mc.deleted_at')
                    ->where('cm.status', 'completed'),
                'cm.completed_at'
            ),
            'Mentee module completions' => $this->monthlySeries(
                DB::table('mentee_module_progress as mmp')
                    ->join('class_participants as cp', 'cp.id', '=', 'mmp.class_participant_id')
                    ->join('mentorship_classes as mc', 'mc.id', '=', 'cp.mentorship_class_id')
                    ->whereIn('mc.training_id', $ids)
                    ->whereNull('mc.deleted_at')
                    ->where('mmp.status', 'completed'),
                'mmp.completed_at'
            ),
        ];

        $rows = [];
        foreach ($series as $label => $counts) {
            $values = [];
            foreach (array_keys($months) as $key) {
                $values[$key] = (int) ($counts[$key] ?? 0);
            }
            $rows[] = [
                'label' => $label,
                'values' => $values,
                'total' => array_sum($values),
            ];
        }

        return ['months' => $months, 'rows' => $rows];
    }

    /**
     * Health workers bucketed by the month they FIRST enrolled, so a person
     * who joins a second class is not counted twice and the row sums to the
     * headline figure. Counting enrolment rows instead would total 255
     * against 198 actual people — the same person, counted again.
     *
     * @return array<string, int>
     */
    private function firstEnrolmentSeries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $first = DB::table('class_participants as cp')
            ->join('mentorship_classes as mc', 'mc.id', '=', 'cp.mentorship_class_id')
            ->whereIn('mc.training_id', $ids)
            ->whereNull('mc.deleted_at')
            ->groupBy('cp.user_id')
            ->selectRaw('cp.user_id, min(cp.enrolled_at) as first_at');

        return DB::query()
            ->fromSub($first, 'f')
            ->whereNotNull('f.first_at')
            ->whereBetween('f.first_at', [
                config('executive_brief.window.start').' 00:00:00',
                config('executive_brief.window.end').' 23:59:59',
            ])
            ->groupBy(DB::raw("date_format(f.first_at, '%Y-%m')"))
            ->selectRaw("date_format(f.first_at, '%Y-%m') as bucket, count(*) as aggregate")
            ->pluck('aggregate', 'bucket')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Counts of $dateColumn grouped by calendar month, keyed 'Y-m'.
     *
     * @return array<string, int>
     */
    private function monthlySeries($query, string $dateColumn): array
    {
        if ($this->trainingIds() === []) {
            return [];
        }

        return $query
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [
                config('executive_brief.window.start').' 00:00:00',
                config('executive_brief.window.end').' 23:59:59',
            ])
            ->groupBy(DB::raw("date_format($dateColumn, '%Y-%m')"))
            ->selectRaw("date_format($dateColumn, '%Y-%m') as bucket, count(*) as aggregate")
            ->pluck('aggregate', 'bucket')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    // ---------------------------------------------------------------- //
    // Shared counters                                                   //
    // ---------------------------------------------------------------- //

    private function priorityCounties(): Collection
    {
        return collect(config('executive_brief.priority_counties', []))->unique()->values();
    }

    private function countiesReached(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return DB::table('trainings as t')
            ->join('facilities as f', 'f.id', '=', 't.facility_id')
            ->join('subcounties as s', 's.id', '=', 'f.subcounty_id')
            ->join('counties as c', 'c.id', '=', 's.county_id')
            ->whereIn('t.id', $ids)
            ->distinct()
            ->pluck('c.name');
    }

    private function trainingCountByStatus(array $ids, string $status): int
    {
        return $ids === [] ? 0 : DB::table('trainings')->whereIn('id', $ids)->where('status', $status)->count();
    }

    private function distinctFacilities(array $ids): int
    {
        return $ids === [] ? 0 : DB::table('trainings')->whereIn('id', $ids)->distinct()->count('facility_id');
    }

    private function distinctMentors(array $ids): int
    {
        return $ids === [] ? 0 : DB::table('trainings')
            ->whereIn('id', $ids)->whereNotNull('mentor_id')->distinct()->count('mentor_id');
    }

    private function distinctMentees(array $ids): int
    {
        return $ids === [] ? 0 : DB::table('class_participants as cp')
            ->join('mentorship_classes as mc', 'mc.id', '=', 'cp.mentorship_class_id')
            ->whereIn('mc.training_id', $ids)
            ->whereNull('mc.deleted_at')
            ->distinct()
            ->count('cp.user_id');
    }

    private function classCount(array $ids): int
    {
        return $ids === [] ? 0 : DB::table('mentorship_classes')
            ->whereIn('training_id', $ids)->whereNull('deleted_at')->count();
    }

    /**
     * @return array<string, int>
     */
    private function classCountsByStatus(array $ids): array
    {
        $counts = $ids === [] ? collect() : DB::table('mentorship_classes')
            ->whereIn('training_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->selectRaw('status, count(*) as aggregate')
            ->pluck('aggregate', 'status');

        return $this->fill($counts, ['draft', 'active', 'completed', 'cancelled']);
    }

    /**
     * @return array<string, int>
     */
    private function classModuleCounts(array $ids): array
    {
        $counts = $ids === [] ? collect() : DB::table('class_modules as cm')
            ->join('mentorship_classes as mc', 'mc.id', '=', 'cm.mentorship_class_id')
            ->whereIn('mc.training_id', $ids)
            ->whereNull('mc.deleted_at')
            ->groupBy('cm.status')
            ->selectRaw('cm.status as status, count(*) as aggregate')
            ->pluck('aggregate', 'status');

        return $this->fill($counts, ['not_started', 'in_progress', 'completed']);
    }

    /**
     * @return array<string, int>
     */
    private function menteeProgressCounts(array $ids): array
    {
        $counts = $ids === [] ? collect() : DB::table('mentee_module_progress as mmp')
            ->join('class_participants as cp', 'cp.id', '=', 'mmp.class_participant_id')
            ->join('mentorship_classes as mc', 'mc.id', '=', 'cp.mentorship_class_id')
            ->whereIn('mc.training_id', $ids)
            ->whereNull('mc.deleted_at')
            ->groupBy('mmp.status')
            ->selectRaw('mmp.status as status, count(*) as aggregate')
            ->pluck('aggregate', 'status');

        return $this->fill($counts, ['not_started', 'in_progress', 'completed', 'exempted']);
    }

    /**
     * @return array<string, int>
     */
    private function participantCounts(array $ids): array
    {
        $counts = $ids === [] ? collect() : DB::table('class_participants as cp')
            ->join('mentorship_classes as mc', 'mc.id', '=', 'cp.mentorship_class_id')
            ->whereIn('mc.training_id', $ids)
            ->whereNull('mc.deleted_at')
            ->groupBy('cp.status')
            ->selectRaw('cp.status as status, count(*) as aggregate')
            ->pluck('aggregate', 'status');

        return $this->fill($counts, ['enrolled', 'active', 'completed', 'dropped']);
    }

    /**
     * Guarantees every status key exists so views never test isset().
     *
     * @param  array<int, string>  $keys
     * @return array<string, int>
     */
    private function fill(Collection $counts, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = (int) ($counts[$key] ?? 0);
        }

        return $out;
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    /**
     * "Module 6: Newborn Resuscitation" reads as "Newborn Resuscitation"
     * in a one-page brief — the numbering is curriculum bookkeeping.
     */
    private function stripModulePrefix(string $name): string
    {
        return trim(preg_replace('/^Module\s*\d+\s*:\s*/i', '', $name)) ?: $name;
    }
}
