<?php

namespace App\Services\Analytics;

use App\Models\Training;
use App\Services\CpdPointsService;
use App\Support\ProgramArea;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Query-builder implementation of MetricsSource against the real schema.
 *
 * Rules shared with the old dashboard so the numbers agree:
 *  - a "mentorship" is a facility_mentorship Training that is live (Training::liveMentorship(): not pilot,
 *    not draft, active/completed, with at least one enrolled/active/completed mentee);
 *  - a "training programme" is a global_training Training; participants are training_participants;
 *  - geography is facility → subcounty → county, and a mentee/participant sits at users.facility_id;
 *  - the programme area (newborn_child / emonc) is the trainings.program_area tag, so area admins only
 *    ever see their own area (ProgramArea::forUser).
 */
class DatabaseMetrics implements MetricsSource
{
    /** @var array<string, list<int>> */
    private array $idCache = [];

    /* ───────────── scope helpers ───────────── */

    /** Area a mode is about; an area admin is always pinned to their own area. */
    private function area(DashboardFilters $f): ?string
    {
        $forced = ProgramArea::forUser(auth()->user());
        if ($forced) {
            return $forced;
        }

        return match ($f->mode) {
            'emonc' => 'emonc',
            'mentorship' => 'newborn_child',
            default => null,
        };
    }

    /** @return list<int> live mentorship training ids for the area (memoised per request). */
    private function liveIds(?string $area): array
    {
        return $this->idCache['live:'.$area] ??= Training::where('type', 'facility_mentorship')
            ->liveMentorship()
            ->when($area, fn ($q) => $q->where('program_area', $area))
            ->pluck('id')->all();
    }

    /** @return list<int> global training ids for the area (null-area trainings count as shared). */
    private function globalIds(?string $area): array
    {
        return $this->idCache['global:'.$area] ??= Training::where('type', 'global_training')
            ->when($area, fn ($q) => $q->where(fn ($w) => $w->where('program_area', $area)->orWhereNull('program_area')))
            ->pluck('id')->all();
    }

    private function geo(Builder $q, string $facilityAlias): Builder
    {
        return $q->leftJoin('subcounties as sc', 'sc.id', '=', "$facilityAlias.subcounty_id")
            ->leftJoin('counties as co', 'co.id', '=', 'sc.county_id');
    }

    private function applyGeo(Builder $q, DashboardFilters $f, string $facilityAlias): Builder
    {
        if ($f->county) {
            $q->where('co.name', $f->county);
        }
        if ($f->subcounty) {
            $q->where('sc.name', $f->subcounty);
        }
        if ($f->facility) {
            $q->where("$facilityAlias.name", $f->facility);
        }

        return $q;
    }

    /** Live mentorships in the mode's area, at their facility, with location/programme/year filters. */
    private function trainings(DashboardFilters $f, ?string $area = null): Builder
    {
        $q = DB::table('trainings as t')
            ->join('facilities as fa', 'fa.id', '=', 't.facility_id')
            ->leftJoin('programs as p', 'p.id', '=', 't.program_id')
            ->whereIn('t.id', $this->liveIds($area ?? $this->area($f)));
        $this->geo($q, 'fa');
        $this->applyGeo($q, $f, 'fa');
        if ($f->programme) {
            $q->where('p.name', $f->programme);
        }
        if ($f->year) {
            $q->whereYear('t.start_date', $f->year);
        }
        if ($f->search && $f->mode !== 'mentor') {
            $q->where(fn ($w) => $w->where('p.name', 'like', '%'.$f->search.'%')->orWhere('fa.name', 'like', '%'.$f->search.'%'));
        }

        return $q;
    }

    private function classes(DashboardFilters $f, ?string $area = null): Builder
    {
        return $this->trainings($f, $area)->join('mentorship_classes as mc', function ($j) {
            $j->on('mc.training_id', '=', 't.id')->whereNull('mc.deleted_at');
        });
    }

    /** Mentee enrolments in live mentorships. The mentee sits at users.facility_id for geography-free counts. */
    private function mentees(DashboardFilters $f, ?string $area = null): Builder
    {
        return $this->classes($f, $area)
            ->join('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')
            ->join('users as u', 'u.id', '=', 'cp.user_id');
    }

    /** Global-training participants with the participant's own facility geography. */
    private function trainees(DashboardFilters $f): Builder
    {
        $q = DB::table('trainings as t')
            ->whereIn('t.id', $this->globalIds($this->area($f)))
            ->join('training_participants as tp', function ($j) {
                $j->on('tp.training_id', '=', 't.id')->whereNull('tp.deleted_at');
            })
            ->join('users as u', 'u.id', '=', 'tp.user_id')
            ->leftJoin('facilities as fa', 'fa.id', '=', 'u.facility_id');
        $this->geo($q, 'fa');
        $this->applyGeo($q, $f, 'fa');
        if ($f->programme) {
            $q->where('t.id', $f->programme);
        }
        if ($f->year) {
            $q->whereYear('t.start_date', $f->year);
        }
        if ($f->search) {
            $q->where('t.title', 'like', '%'.$f->search.'%');
        }

        return $q;
    }

    /** Last 12 whole calendar months as 'Y-m' => 0. */
    private function emptyMonths(): array
    {
        $out = [];
        $start = Carbon::now()->startOfMonth()->subMonths(11);
        for ($i = 0; $i < 12; $i++) {
            $out[$start->copy()->addMonths($i)->format('Y-m')] = 0;
        }

        return $out;
    }

    private function byMonth(Builder $q, string $dateCol, string $countExpr = 'COUNT(*)'): array
    {
        $months = $this->emptyMonths();
        $rows = $q->where($dateCol, '>=', array_key_first($months).'-01')
            ->selectRaw("DATE_FORMAT($dateCol, '%Y-%m') as ym, $countExpr as n")
            ->groupBy('ym')->pluck('n', 'ym');
        foreach ($rows as $ym => $n) {
            if (isset($months[$ym])) {
                $months[$ym] = (int) $n;
            }
        }

        return $months;
    }

    private function top(Builder $q, string $labelExpr, string $countExpr, int $limit): array
    {
        return $q->selectRaw("$labelExpr as label, $countExpr as n")
            ->whereRaw("$labelExpr IS NOT NULL AND $labelExpr <> ''")
            ->groupBy('label')->orderByDesc('n')->limit($limit)
            ->pluck('n', 'label')->map(fn ($n) => (int) $n)->all();
    }

    /* ───────────── shared ───────────── */

    public function filterOptions(DashboardFilters $f): array
    {
        $area = $this->area($f);
        if ($f->mode === 'training') {
            $programmes = Training::whereIn('id', $this->globalIds($area))
                ->whereIn('id', fn ($q) => $q->select('training_id')->from('training_participants')->whereNull('deleted_at'))
                ->orderBy('title')->pluck('title', 'id')->all();
            $years = Training::whereIn('id', array_keys($programmes))->whereNotNull('start_date')->selectRaw('DISTINCT YEAR(start_date) y')->orderByDesc('y')->pluck('y')->map(fn ($y) => (int) $y)->all();
        } elseif ($f->mode === 'assessment') {
            $programmes = [];
            $years = DB::table('assessments')->whereNull('deleted_at')->whereNotNull('assessment_date')->selectRaw('DISTINCT YEAR(assessment_date) y')->orderByDesc('y')->pluck('y')->map(fn ($y) => (int) $y)->all();
        } else {
            $programmes = DB::table('programs')->whereNull('deleted_at')
                ->when($f->mode === 'mentorship', fn ($q) => $q->where('program_area', 'newborn_child'))
                ->when($f->mode === 'emonc', fn ($q) => $q->where('program_area', 'emonc'))
                ->when($area && $f->mode === 'mentor', fn ($q) => $q->where('program_area', $area))
                ->orderBy('name')->pluck('name', 'name')->all();
            $years = Training::whereIn('id', $this->liveIds($area))->whereNotNull('start_date')->selectRaw('DISTINCT YEAR(start_date) y')->orderByDesc('y')->pluck('y')->map(fn ($y) => (int) $y)->all();
        }
        $subcounties = $f->county
            ? DB::table('subcounties as sc')->join('counties as co', 'co.id', '=', 'sc.county_id')->where('co.name', $f->county)->orderBy('sc.name')->pluck('sc.name')->all()
            : [];
        $facilities = [];
        if ($f->county) {
            $fq = DB::table('facilities as fa')->join('subcounties as sc', 'sc.id', '=', 'fa.subcounty_id')->join('counties as co', 'co.id', '=', 'sc.county_id')
                ->where('co.name', $f->county)->whereNull('fa.deleted_at');
            if ($f->subcounty) {
                $fq->where('sc.name', $f->subcounty);
            }
            if ($f->mode === 'assessment') {
                $fq->whereIn('fa.id', DB::table('assessments')->whereNull('deleted_at')->select('facility_id'));
            } elseif ($f->mode === 'training') {
                $fq->whereIn('fa.id', DB::table('users')->whereIn('id', DB::table('training_participants')->select('user_id'))->select('facility_id'));
            } else {
                $fq->whereIn('fa.id', DB::table('trainings')->whereIn('id', $this->liveIds($area))->select('facility_id'));
            }
            $facilities = $fq->distinct()->orderBy('fa.name')->pluck('fa.name')->all();
        }
        $cadres = DB::table('assessment_cadres')->orderBy('name')->pluck('name')->all();

        return compact('programmes', 'years', 'subcounties', 'facilities', 'cadres');
    }

    public function registeredFacilities(DashboardFilters $f): int
    {
        return (int) DB::table('facilities')->whereNull('deleted_at')->count();
    }

    public function countyReach(string $kind, DashboardFilters $f): array
    {
        $out = DB::table('counties as co')
            ->leftJoin('subcounties as sc', 'sc.county_id', '=', 'co.id')
            ->leftJoin('facilities as fa', function ($j) {
                $j->on('fa.subcounty_id', '=', 'sc.id')->whereNull('fa.deleted_at');
            })
            ->whereNull('co.deleted_at')
            ->groupBy('co.name')->selectRaw('co.name, COUNT(fa.id) as total')->pluck('total', 'co.name')
            ->map(fn ($t) => ['programmes' => 0, 'people' => 0, 'facilities_reached' => 0, 'facilities_total' => (int) $t])->all();

        // The comparison across counties is never narrowed to the selected county.
        $g = DashboardFilters::fromArray(array_diff_key($f->toQuery(), ['county' => 1, 'subcounty' => 1, 'facility' => 1]));

        if ($kind === 'training') {
            $rows = $this->trainees($g)->whereNotNull('co.name')->groupBy('co.name')
                ->selectRaw('co.name, COUNT(DISTINCT t.id) as programmes, COUNT(DISTINCT tp.user_id) as people, COUNT(DISTINCT u.facility_id) as reached')->get();
        } else {
            // People counted where the mentorship runs (the facility), as the old map did.
            $rows = $this->mentees($g)->groupBy('co.name')
                ->selectRaw('co.name, COUNT(DISTINCT t.id) as programmes, COUNT(DISTINCT cp.user_id) as people, COUNT(DISTINCT t.facility_id) as reached')->get();
        }
        foreach ($rows as $r) {
            if (! isset($out[$r->name])) {
                continue;
            }
            $out[$r->name] = ['programmes' => (int) $r->programmes, 'people' => (int) $r->people, 'facilities_reached' => (int) $r->reached, 'facilities_total' => $out[$r->name]['facilities_total']];
        }

        return $out;
    }

    public function facilityTypeReach(string $kind, DashboardFilters $f): array
    {
        $totals = DB::table('facility_types as ft')
            ->leftJoin('facilities as fa', function ($j) {
                $j->on('fa.facility_type_id', '=', 'ft.id')->whereNull('fa.deleted_at');
            })
            ->groupBy('ft.name')->selectRaw('ft.name, COUNT(fa.id) as total')->pluck('total', 'ft.name');

        $g = DashboardFilters::fromArray(array_diff_key($f->toQuery(), ['county' => 1, 'subcounty' => 1, 'facility' => 1]));
        $reached = $kind === 'training'
            ? $this->trainees($g)->join('facility_types as ft', 'ft.id', '=', 'fa.facility_type_id')->groupBy('ft.name')->selectRaw('ft.name, COUNT(DISTINCT u.facility_id) as n')->pluck('n', 'ft.name')
            : $this->trainings($g)->join('facility_types as ft', 'ft.id', '=', 'fa.facility_type_id')->groupBy('ft.name')->selectRaw('ft.name, COUNT(DISTINCT t.facility_id) as n')->pluck('n', 'ft.name');

        return $totals->map(fn ($t, $type) => ['type' => $type, 'reached' => (int) ($reached[$type] ?? 0), 'total' => (int) $t])->values()->all();
    }

    public function classLifecycle(DashboardFilters $f): array
    {
        $rows = $this->classes($f)->groupBy('mc.status')->selectRaw('mc.status, COUNT(*) as n')->pluck('n', 'mc.status');

        return ['draft' => (int) ($rows['draft'] ?? 0), 'active' => (int) ($rows['active'] ?? 0), 'completed' => (int) ($rows['completed'] ?? 0)];
    }

    /* ───────────── Newborn & Child ───────────── */

    public function mentorshipSummary(DashboardFilters $f): array
    {
        $t = $this->trainings($f)->selectRaw("COUNT(*) as total, SUM(t.status = 'completed') as completed, SUM(t.status = 'active') as active, COUNT(DISTINCT t.facility_id) as facilities")->first();
        $mentees = (int) $this->mentees($f)->distinct()->count('cp.user_id');
        $att = $this->mentees($f)
            ->join('mentee_module_progress as mp', 'mp.class_participant_id', '=', 'cp.id')
            ->whereNotNull('mp.attendance_percentage')->avg('mp.attendance_percentage');

        return [
            'mentorships' => (int) $t->total, 'completed' => (int) $t->completed, 'active' => (int) $t->active,
            'unique_mentees' => $mentees, 'facilities' => (int) $t->facilities,
            'module_attendance_pct' => $att === null ? null : round((float) $att, 1),
        ];
    }

    public function mentorshipsStartedByMonth(DashboardFilters $f): array
    {
        return $this->byMonth($this->trainings($f), 't.start_date');
    }

    public function menteesByDepartment(DashboardFilters $f, int $limit = 10): array
    {
        return $this->top($this->mentees($f)->join('departments as d', 'd.id', '=', 'u.department_id'), 'd.name', 'COUNT(DISTINCT cp.user_id)', $limit);
    }

    public function menteesByCadre(DashboardFilters $f, int $limit = 8): array
    {
        return $this->top($this->mentees($f)->join('assessment_cadres as ca', 'ca.id', '=', 'u.cadre_id'), 'ca.name', 'COUNT(DISTINCT cp.user_id)', $limit);
    }

    public function topMentorshipProgrammes(DashboardFilters $f, int $limit = 5): array
    {
        return $this->top($this->mentees($f), 'p.name', 'COUNT(DISTINCT cp.user_id)', $limit);
    }

    public function menteeEnrolmentStatus(DashboardFilters $f): array
    {
        $rows = $this->mentees($f)->groupBy('cp.status')->selectRaw('cp.status, COUNT(*) as n')->pluck('n', 'cp.status');

        return ['enrolled' => (int) ($rows['enrolled'] ?? 0), 'completed' => (int) ($rows['completed'] ?? 0)];
    }

    public function moduleCompletionBuckets(DashboardFilters $f): array
    {
        // Per enrolment: completed modules ÷ modules in the class (exempted ones count as done).
        $per = $this->mentees($f)
            ->leftJoin('mentee_module_progress as mp', 'mp.class_participant_id', '=', 'cp.id')
            ->groupBy('cp.id')
            ->selectRaw("cp.id, SUM(mp.status IN ('completed','exempted')) / NULLIF(COUNT(mp.id), 0) * 100 as pct")
            ->pluck('pct');
        $b = ['0-25' => 0, '26-50' => 0, '51-75' => 0, '76-99' => 0, '100' => 0];
        foreach ($per as $p) {
            $p = (float) $p;
            $b[$p >= 100 ? '100' : ($p > 75 ? '76-99' : ($p > 50 ? '51-75' : ($p > 25 ? '26-50' : '0-25')))]++;
        }

        return $b;
    }

    /* ───────────── EmONC ───────────── */

    public function emoncSummary(DashboardFilters $f): array
    {
        $c = $this->classes($f, 'emonc')->selectRaw("SUM(mc.status = 'active') as active, SUM(mc.status = 'completed') as completed")->first();
        $p = $this->mentees($f, 'emonc')->selectRaw("
            COUNT(DISTINCT CASE WHEN cp.status = 'enrolled' THEN cp.user_id END) as active_mentees,
            SUM(cp.mentor_approved_at IS NULL AND cp.status <> 'completed') as awaiting_mentor,
            SUM(cp.mentor_approved_at IS NOT NULL AND cp.head_drmh_approved_at IS NULL) as drmh,
            SUM(cp.head_drmh_approved_at IS NOT NULL) as certs")->first();
        $modules = $this->mentees($f, 'emonc')->join('mentee_module_progress as mp', 'mp.class_participant_id', '=', 'cp.id')->where('mp.status', 'completed')->count();
        $video = $this->mentees($f, 'emonc')->join('mentee_module_progress as mp', 'mp.class_participant_id', '=', 'cp.id')
            ->whereNotNull('mp.hands_on_video_url')->where('mp.video_review_status', 'pending')->count();

        return [
            'active_mentees' => (int) $p->active_mentees, 'active_classes' => (int) $c->active, 'completed_classes' => (int) $c->completed,
            'modules_done' => (int) $modules, 'pending_video_reviews' => (int) $video, 'awaiting_mentor_approval' => (int) $p->awaiting_mentor,
            'pending_drmh' => (int) $p->drmh, 'certificates_issued' => (int) $p->certs,
        ];
    }

    public function emoncClassesByMonth(DashboardFilters $f): array
    {
        return $this->byMonth($this->classes($f, 'emonc'), 'mc.start_date');
    }

    public function emoncMenteeMatrix(DashboardFilters $f): array
    {
        return $this->mentees($f, 'emonc')
            ->leftJoin('mentee_module_progress as mp', 'mp.class_participant_id', '=', 'cp.id')
            ->leftJoin('facilities as uf', 'uf.id', '=', 'u.facility_id')
            ->groupBy('cp.id', 'u.name', 'uf.name', 'fa.name', 'mc.name', 'cp.mentor_approved_at', 'cp.head_drmh_approved_at')
            ->selectRaw("u.name as mentee, COALESCE(uf.name, fa.name) as facility, mc.name as class, COUNT(mp.id) as total,
                SUM(mp.status = 'completed') as done, SUM(mp.video_review_status = 'pending' AND mp.hands_on_video_url IS NOT NULL) as video_pending,
                SUM(mp.video_review_status = 'passed') as video_passed, cp.mentor_approved_at, cp.head_drmh_approved_at")
            ->orderBy('u.name')->get()
            ->map(fn ($r) => [
                'mentee' => (string) $r->mentee, 'facility' => (string) $r->facility, 'class' => (string) $r->class,
                'modules_done' => (int) $r->done, 'modules_total' => (int) $r->total,
                'video_review' => $r->video_pending ? 'Pending' : ($r->video_passed ? 'Passed' : '—'),
                'mentor_approval' => $r->mentor_approved_at ? 'Approved' : 'Pending',
                'drmh' => $r->head_drmh_approved_at ? 'Certified' : 'Pending',
                'certificate' => $r->head_drmh_approved_at ? 'Issued' : '—',
            ])->all();
    }

    /* ───────────── Mentors ───────────── */

    /** Live mentorships' classes with the lead mentor; cadre, level and name filters applied. */
    private function mentorClasses(DashboardFilters $f): Builder
    {
        $q = $this->classes($f)
            ->join('users as m', 'm.id', '=', 't.mentor_id')
            ->leftJoin('assessment_cadres as mca', 'mca.id', '=', 'm.cadre_id');
        if ($f->cadre) {
            $q->where('mca.name', $f->cadre);
        }
        if ($f->search) {
            $q->where('m.name', 'like', '%'.$f->search.'%');
        }
        if ($f->level) {
            $q->whereIn('t.mentor_id', $this->mentorIdsAtLevel($f));
        }

        return $q;
    }

    /** E (Expert) and MP (Master) fold into the "AP" bucket the design shows. */
    private function levelBucket(string $short): string
    {
        return in_array($short, ['F', 'P'], true) ? $short : 'AP';
    }

    private function mentorIdsAtLevel(DashboardFilters $f): array
    {
        $ids = $this->classes(DashboardFilters::fromArray(array_diff_key($f->toQuery(), ['level' => 1, 'cadre' => 1, 'q' => 1])))
            ->distinct()->pluck('t.mentor_id')->filter()->values()->all();
        $cpd = app(CpdPointsService::class)->batchForMentors($ids);

        return array_values(array_filter($ids, fn ($id) => $this->levelBucket($cpd[$id]['level']['short'] ?? 'F') === $f->level)) ?: [0];
    }

    private function cpdFor(array $mentorIds): array
    {
        return app(CpdPointsService::class)->batchForMentors(array_values(array_unique(array_filter($mentorIds))));
    }

    public function mentorSummary(DashboardFilters $f): array
    {
        $c = $this->mentorClasses($f)->selectRaw("COUNT(DISTINCT t.mentor_id) as mentors, SUM(mc.status = 'active') as active, SUM(mc.status = 'completed') as completed, SUM(mc.status = 'draft') as draft")->first();
        $mentorIds = $this->mentorClasses($f)->distinct()->pluck('t.mentor_id')->all();
        $cpd = $this->cpdFor($mentorIds);
        $levels = ['AP' => 0, 'P' => 0, 'F' => 0];
        foreach ($mentorIds as $id) {
            $levels[$this->levelBucket($cpd[$id]['level']['short'] ?? 'F')]++;
        }
        $modules = $this->mentorClasses($f)->join('class_modules as cm', 'cm.mentorship_class_id', '=', 'mc.id')->where('cm.status', 'completed')->count();
        $mentees = (int) $this->mentorClasses($f)->join('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')
            ->whereIn('cp.status', ['enrolled', 'active', 'completed'])->distinct()->count('cp.user_id');
        $dropped = (int) $this->mentorClasses($f)->join('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')->whereNotNull('cp.dropped_at')->count();

        return [
            'mentors' => (int) $c->mentors, 'active_classes' => (int) $c->active, 'completed_classes' => (int) $c->completed, 'draft_classes' => (int) $c->draft,
            'modules_facilitated' => (int) $modules, 'mentees_supported' => $mentees, 'cpd_points' => (int) array_sum(array_column($cpd, 'total')),
            'inactive_mentors' => count($this->inactiveMentors($f)), 'dropped_mentees' => $dropped,
            'avg_assessment_score' => $this->avgScore($f, 'assessment_score'),
            'avg_practical_score' => $this->avgPractical($f),
            'levels' => $levels,
        ];
    }

    private function avgScore(DashboardFilters $f, string $col): ?float
    {
        $v = $this->mentorClasses($f)->join('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')
            ->join('mentee_module_progress as mp', 'mp.class_participant_id', '=', 'cp.id')->whereNotNull("mp.$col")->avg("mp.$col");

        return $v === null ? null : round((float) $v, 1);
    }

    private function avgPractical(DashboardFilters $f): ?float
    {
        // Practical (rubric) scores exist only where a rubric assessment was recorded.
        return null;
    }

    public function facilityExceptions(DashboardFilters $f): array
    {
        $rows = $this->mentorClasses($f)
            ->leftJoin('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')
            ->leftJoin('mentee_module_progress as mp', 'mp.class_participant_id', '=', 'cp.id')
            ->groupBy('fa.id', 'fa.name')
            ->selectRaw("fa.name, COUNT(DISTINCT mc.id) as classes,
                COALESCE(SUM(mp.status IN ('completed','exempted')) / NULLIF(COUNT(mp.id), 0) * 100, 0) as comp,
                COALESCE(AVG(mp.attendance_percentage), 0) as att")
            ->get();

        // Needs attention: finished modules with no/impossible attendance, or attendance beyond 100 %.
        return $rows->filter(fn ($r) => ($r->comp >= 100 && $r->att <= 0) || $r->att > 100 || ($r->comp > 0 && $r->att > 0 && $r->att < 40))
            ->sortByDesc('att')->take(12)
            ->map(fn ($r) => ['facility' => (string) $r->name, 'classes' => (int) $r->classes, 'completion_pct' => round((float) $r->comp, 1), 'attendance_pct' => round((float) $r->att, 1)])
            ->values()->all();
    }

    public function inactiveMentors(DashboardFilters $f): array
    {
        // No sign-in for 14+ days while leading a live class; mentors who never signed in count from their class start.
        $cut = Carbon::now()->subDays(14);

        return $this->mentorClasses($f)->where('mc.status', 'active')
            ->groupBy('m.id', 'm.name', 'm.last_seen_at')
            ->selectRaw('m.name, COALESCE(m.last_seen_at, MIN(mc.start_date), MIN(mc.created_at)) as last_active')
            ->havingRaw('last_active < ?', [$cut])->get()
            ->map(fn ($r) => ['name' => (string) $r->name, 'days' => (int) Carbon::parse($r->last_active)->diffInDays(now())])
            ->sortByDesc('days')->values()->all();
    }

    public function zeroCpdMentors(DashboardFilters $f): array
    {
        $rows = $this->mentorClasses($f)->where('mc.status', 'completed')
            ->groupBy('m.id', 'm.name', 'fa.name')->selectRaw('m.id, m.name, fa.name as facility, COUNT(DISTINCT mc.id) as classes')->get();
        $cpd = $this->cpdFor($rows->pluck('id')->all());

        return $rows->filter(fn ($r) => ($cpd[$r->id]['total'] ?? 0) === 0)
            ->map(fn ($r) => ['name' => (string) $r->name, 'facility' => (string) $r->facility, 'classes' => (int) $r->classes])->values()->all();
    }

    public function cpdLeaders(DashboardFilters $f, int $limit = 10): array
    {
        $names = $this->mentorClasses($f)->distinct()->pluck('m.name', 't.mentor_id')->all();
        $cpd = $this->cpdFor(array_keys($names));
        $out = [];
        foreach ($names as $id => $name) {
            $out[$name] = ($out[$name] ?? 0) + (int) ($cpd[$id]['total'] ?? 0);
        }
        arsort($out);

        return array_slice(array_filter($out), 0, $limit, true);
    }

    public function reachLeaders(DashboardFilters $f, int $limit = 10): array
    {
        return $this->top($this->mentorClasses($f)->join('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')->whereIn('cp.status', ['enrolled', 'active', 'completed']),
            'm.name', 'COUNT(DISTINCT cp.user_id)', $limit);
    }

    public function classesStartedByMonth(DashboardFilters $f): array
    {
        return $this->byMonth($this->mentorClasses($f), 'mc.start_date', 'COUNT(DISTINCT mc.id)');
    }

    public function mentorsByFacility(DashboardFilters $f, int $limit = 10): array
    {
        return $this->top($this->mentorClasses($f), 'fa.name', 'COUNT(DISTINCT t.mentor_id)', $limit);
    }

    public function mentorsByDepartment(DashboardFilters $f, int $limit = 10): array
    {
        return $this->top($this->mentorClasses($f)->join('departments as d', 'd.id', '=', 'm.department_id'), 'd.name', 'COUNT(DISTINCT t.mentor_id)', $limit);
    }

    public function mentorsByModule(DashboardFilters $f, int $limit = 12): array
    {
        return $this->top($this->mentorClasses($f)->join('class_modules as cm', 'cm.mentorship_class_id', '=', 'mc.id')
            ->join('program_modules as pm', 'pm.id', '=', 'cm.program_module_id')->where('cm.status', 'completed'),
            'pm.name', 'COUNT(DISTINCT t.mentor_id)', $limit);
    }

    public function mentorClassMatrix(DashboardFilters $f): array
    {
        $rows = $this->mentorClasses($f)->whereIn('mc.status', ['active', 'completed'])
            ->leftJoin('departments as md', 'md.id', '=', 'm.department_id')
            ->leftJoin('counties as mco', 'mco.id', '=', 'co.id')
            ->selectRaw("mc.id as class_id, t.mentor_id, m.name as mentor, COALESCE(co.name, '') as county, COALESCE(mca.name, '') as cadre,
                COALESCE(md.name, '') as department, fa.name as facility, mc.name as class,
                (SELECT COUNT(*) FROM class_modules x WHERE x.mentorship_class_id = mc.id) as modules_total,
                (SELECT COUNT(*) FROM class_modules x WHERE x.mentorship_class_id = mc.id AND x.status = 'completed') as modules_done,
                (SELECT COUNT(DISTINCT y.user_id) FROM class_participants y WHERE y.mentorship_class_id = mc.id AND y.status IN ('enrolled','active','completed')) as mentees")
            ->get();
        $cpd = $this->cpdFor($rows->pluck('mentor_id')->all());

        return $rows->map(fn ($r) => [
            'mentor' => (string) $r->mentor, 'county' => $r->county, 'cadre' => $r->cadre, 'department' => $r->department,
            'facility' => (string) $r->facility, 'class' => (string) $r->class,
            'modules_done' => (int) $r->modules_done, 'modules_total' => (int) $r->modules_total, 'mentees' => (int) $r->mentees,
            'cpd' => (int) ($cpd[$r->mentor_id]['total'] ?? 0), 'level' => $this->levelBucket($cpd[$r->mentor_id]['level']['short'] ?? 'F'),
        ])->sortByDesc('cpd')->values()->all();
    }

    /* ───────────── Assessments ───────────── */

    private function assessments(DashboardFilters $f): Builder
    {
        $areaTypes = ($area = ProgramArea::forUser(auth()->user()))
            ? DB::table('assessment_types')->where('program_area', $area)->pluck('id')->all()
            : null;
        $q = DB::table('assessments as a')->join('facilities as fa', 'fa.id', '=', 'a.facility_id')->whereNull('a.deleted_at');
        if ($areaTypes !== null) {
            $q->whereIn('a.assessment_type_id', $areaTypes ?: [0]);
        }
        $this->geo($q, 'fa');
        $this->applyGeo($q, $f, 'fa');
        if ($f->assessmentType) {
            $q->where('a.assessment_type', $f->assessmentType);
        }
        if ($f->year) {
            $q->whereYear('a.assessment_date', $f->year);
        }
        if ($f->search) {
            $q->where('fa.name', 'like', '%'.$f->search.'%');
        }

        return $q;
    }

    public function assessmentSummary(DashboardFilters $f): array
    {
        $r = $this->assessments($f)->selectRaw("SUM(a.status = 'completed') as completed, SUM(a.status = 'in_progress') as in_progress, SUM(a.status = 'draft') as draft,
            AVG(CASE WHEN a.status = 'completed' THEN a.overall_percentage END) as avg_score")->first();

        $yoy = null;
        if (! $f->year) {
            $this_ = (int) $this->assessments($f)->where('a.status', 'completed')->whereYear('a.assessment_date', now()->year)->count();
            $prev = (int) $this->assessments($f)->where('a.status', 'completed')->whereYear('a.assessment_date', now()->year - 1)->count();
            $yoy = $prev > 0 ? round(($this_ - $prev) / $prev * 100, 1) : null;
        }

        return [
            'completed' => (int) $r->completed, 'in_progress' => (int) $r->in_progress, 'draft' => (int) $r->draft,
            'yoy_pct' => $yoy, 'avg_score' => $r->avg_score === null ? null : round((float) $r->avg_score, 1),
        ];
    }

    public function assessmentsByMonth(DashboardFilters $f): array
    {
        $months = array_map(fn () => ['baseline' => 0, 'midline' => 0, 'endline' => 0], $this->emptyMonths());
        $rows = $this->assessments($f)->where('a.status', 'completed')
            ->where('a.assessment_date', '>=', array_key_first($months).'-01')
            ->selectRaw("DATE_FORMAT(a.assessment_date, '%Y-%m') as ym, a.assessment_type as type, COUNT(*) as n")->groupBy('ym', 'type')->get();
        foreach ($rows as $r) {
            if (isset($months[$r->ym][$r->type])) {
                $months[$r->ym][$r->type] = (int) $r->n;
            }
        }

        return $months;
    }

    public function gradeDistribution(DashboardFilters $f): array
    {
        $rows = $this->assessments($f)->where('a.status', 'completed')->groupBy('a.overall_grade')->selectRaw('a.overall_grade g, COUNT(*) n')->pluck('n', 'g');

        return ['good' => (int) ($rows['green'] ?? 0), 'fair' => (int) ($rows['yellow'] ?? 0), 'poor' => (int) ($rows['red'] ?? 0)];
    }

    public function sectionScores(DashboardFilters $f): array
    {
        return $this->assessments($f)->where('a.status', 'completed')
            ->join('assessment_section_scores as ss', 'ss.assessment_id', '=', 'a.id')
            ->join('assessment_sections as s', 's.id', '=', 'ss.assessment_section_id')
            ->where('s.is_scored', true)
            ->groupBy('s.id', 's.name', 's.order')->orderBy('s.order')
            ->selectRaw('s.name as section, AVG(ss.percentage) as score')->get()
            ->map(fn ($r) => ['section' => (string) $r->section, 'score' => round((float) $r->score, 1)])->all();
    }

    public function assessedFacilities(DashboardFilters $f): array
    {
        $ids = $this->assessments($f)->where('a.status', 'completed')->pluck('a.id')->all();
        if (! $ids) {
            return [];
        }
        $areaTypeIds = ($area = ProgramArea::forUser(auth()->user())) ? DB::table('assessment_types')->where('program_area', $area)->pluck('id')->all() : null;
        $rows = app(\App\Services\AssessmentAnalyticsService::class)->getFacilitiesReadiness([
            'year' => $f->year, 'assessment_type' => $f->assessmentType,
            'county_id' => $f->county ? DB::table('counties')->where('name', $f->county)->value('id') : null,
            'subcounty_id' => $f->subcounty ? DB::table('subcounties')->where('name', $f->subcounty)->value('id') : null,
            'facility_id' => $f->facility ? DB::table('facilities')->where('name', $f->facility)->value('id') : null,
        ]);

        return $rows->filter(fn ($a) => in_array($a->id, $ids, true) && ($areaTypeIds === null || in_array($a->assessment_type_id, $areaTypeIds, true)))
            ->unique('facility_id')
            ->map(fn ($a) => [
                'assessment_id' => (int) $a->id,
                'facility' => (string) ($a->facility?->name ?? ''),
                'mfl' => (string) ($a->facility?->mfl_code ?? ''),
                'subcounty' => (string) ($a->facility?->subcounty?->name ?? ''),
                'county' => (string) ($a->facility?->subcounty?->county?->name ?? ''),
                'date' => $a->assessment_date ? Carbon::parse($a->assessment_date)->format('d M Y') : '',
                'type' => ucfirst((string) $a->assessment_type),
                'assessor' => (string) ($a->assessor?->name ?? $a->assessor_name ?? ''),
                'skills_lab' => $a->has_skills_lab ? 'Yes' : 'No',
                'room' => $a->has_room ? 'Yes' : 'No',
                'feedback' => $a->feedback_given ? 'Given' : 'Pending',
                'eligibility' => match ($a->eligibility_status) { 'eligible' => 'Eligible', 'partial' => 'Partial', default => 'Not Eligible' },
                'trained' => $a->has_prior_training ? 'Yes' : 'No',
                'mentorships' => (int) ($a->mentorship_count ?? 0),
            ])->values()->all();
    }

    /* ───────────── Trainings ───────────── */

    public function trainingProgrammes(DashboardFilters $f): array
    {
        return $this->trainees(DashboardFilters::fromArray(array_diff_key($f->toQuery(), ['programme' => 1])))
            ->groupBy('t.id', 't.title', 't.start_date', 't.status')
            ->selectRaw('t.id, t.title as name, COUNT(tp.id) as participants, COUNT(DISTINCT u.facility_id) as facilities, YEAR(t.start_date) as year, t.status')
            ->orderByDesc('participants')->get()
            ->map(fn ($r) => ['id' => (string) $r->id, 'name' => (string) $r->name, 'participants' => (int) $r->participants, 'facilities' => (int) $r->facilities, 'year' => (int) $r->year, 'status' => (string) $r->status])->all();
    }

    public function trainingSummary(DashboardFilters $f): array
    {
        $r = $this->trainees($f)->selectRaw('COUNT(DISTINCT tp.user_id) as uniq, COUNT(tp.id) as enrol, COUNT(DISTINCT u.facility_id) as facs')->first();
        $recent = (int) $this->trainees($f)->where('tp.created_at', '>=', now()->subMonths(12))->count();

        return ['unique_participants' => (int) $r->uniq, 'enrolments' => (int) $r->enrol, 'facilities_reached' => (int) $r->facs, 'last_12_months_enrolments' => $recent];
    }

    public function participantsByDepartment(DashboardFilters $f, int $limit = 10): array
    {
        return $this->top($this->trainees($f)->join('departments as d', 'd.id', '=', DB::raw('COALESCE(tp.department_id, u.department_id)')), 'd.name', 'COUNT(DISTINCT tp.user_id)', $limit);
    }

    public function participantsByCadre(DashboardFilters $f, int $limit = 8): array
    {
        return $this->top($this->trainees($f)->join('assessment_cadres as ca', 'ca.id', '=', DB::raw('COALESCE(tp.cadre_id, u.cadre_id)')), 'ca.name', 'COUNT(DISTINCT tp.user_id)', $limit);
    }
}
