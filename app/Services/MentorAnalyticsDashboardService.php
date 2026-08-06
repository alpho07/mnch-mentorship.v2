<?php

namespace App\Services;

use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\MentorshipCoMentor;
use App\Models\RubricAssessment;
use App\Models\Training;
use App\Models\User;
use Carbon\Carbon;

class MentorAnalyticsDashboardService
{
    private const SENIOR_ROLES = ['super_admin', 'admin', 'division', 'national'];

    public function build(?User $viewer, array $filters = []): array
    {
        // Scope trainings visible to this viewer
        if ($viewer === null || $viewer->hasRole(self::SENIOR_ROLES)) {
            $trainingIds = Training::where('type', 'facility_mentorship')->where('is_pilot', false)->pluck('id');
        } else {
            $asLead = Training::where('mentor_id', $viewer->id)
                ->where('type', 'facility_mentorship')->where('is_pilot', false)->pluck('id');
            $asCo   = MentorshipCoMentor::where('user_id', $viewer->id)
                ->where('status', 'accepted')->pluck('training_id');
            $trainingIds = $asLead->merge($asCo)->unique()->values();
        }

        $trainings = Training::whereIn('id', $trainingIds)
            ->where('type', 'facility_mentorship')->where('is_pilot', false)
            ->whereNotNull('mentor_id')
            ->when(!empty($filters['program_id']),   fn ($q) => $q->where('program_id', $filters['program_id']))
            ->when(!empty($filters['county_id']),    fn ($q) => $q->whereHas('facility.subcounty', fn ($q2) => $q2->where('county_id', $filters['county_id'])))
            ->when(!empty($filters['subcounty_id']), fn ($q) => $q->whereHas('facility', fn ($q2) => $q2->where('subcounty_id', $filters['subcounty_id'])))
            ->when(!empty($filters['facility_id']),  fn ($q) => $q->where('facility_id', $filters['facility_id']))
            ->when(!empty($filters['mentor_id']),    fn ($q) => $q->where('mentor_id', $filters['mentor_id']))
            ->with(['mentor.cadre', 'mentor.department', 'facility.subcounty.county', 'program'])
            ->get();

        // Post-load filters (cadre/department not in DB query)
        $trainings = $trainings->filter(function ($t) use ($filters) {
            $mentor = $t->mentor;
            if (! $mentor) return false;
            if (! empty($filters['cadre_id']) && ($mentor->cadre_id ?? null) != $filters['cadre_id']) return false;
            if (! empty($filters['department_id']) && ($mentor->department_id ?? null) != $filters['department_id']) return false;
            return true;
        });

        // Live (active) classes for the matrix — only active classes
        $liveClasses = MentorshipClass::whereIn('training_id', $trainings->pluck('id'))
            ->where('status', 'active')
            ->with(['classModules.programModule', 'training.facility.subcounty.county'])
            ->get();

        // All classes (any status) for KPIs/charts
        $allClassIds = MentorshipClass::whereIn('training_id', $trainings->pluck('id'))->pluck('id');
        $allClasses  = MentorshipClass::whereIn('id', $allClassIds)
            ->with(['classModules', 'training'])
            ->get();

        $liveClassIds = $liveClasses->pluck('id');
        $participants = ClassParticipant::whereIn('mentorship_class_id', $liveClassIds)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->get();

        $mentorIds = $trainings->pluck('mentor_id')->filter()->unique()->values()->toArray();
        $mentors   = $trainings->pluck('mentor')->filter()->unique('id')->keyBy('id');

        $cpdData = app(CpdPointsService::class)->batchForMentors($mentorIds);

        // Matrix: one row per live class
        $matrix = $this->buildMatrix($liveClasses, $trainings, $mentors, $participants, $cpdData);

        $exceptions = app(CoordinatorExceptionResolver::class)->resolve($trainings, $liveClasses, $participants, $cpdData);

        $participantIds = $participants->pluck('id');
        $assessmentStats = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
            ->whereNotNull('assessment_score')
            ->selectRaw('AVG(assessment_score) as avg_score, SUM(assessment_status = "passed") as passed, COUNT(*) as total')
            ->first();

        $liveClassModuleIds = $liveClasses->pluck('classModules')->flatten()->pluck('id');
        $rubricStats = RubricAssessment::whereIn('class_module_id', $liveClassModuleIds)
            ->selectRaw('AVG(score) as avg_score, SUM(passed) as passed, COUNT(*) as total')
            ->first();

        $droppedMentees = ClassParticipant::whereIn('mentorship_class_id', $liveClassIds)
            ->where('status', 'dropped')
            ->count();

        $kpis      = $this->buildKpis($mentors, $allClasses, $liveClasses, $participants, $cpdData, $assessmentStats, $rubricStats, $droppedMentees, $exceptions);
        $chartData = $this->buildChartData($matrix, $allClasses, $mentors, $trainings, $cpdData);
        $insights  = $this->buildInsights($kpis, $matrix);

        return compact('kpis', 'matrix', 'chartData', 'insights', 'exceptions');
    }

    // ── Matrix: one row per live class ───────────────────────────────────────

    private function buildMatrix($liveClasses, $trainings, $mentors, $participants, array $cpdData): array
    {
        $rows = [];
        foreach ($liveClasses as $class) {
            $training = $trainings->firstWhere('id', $class->training_id);
            if (! $training) continue;

            $mentor   = $mentors->get($training->mentor_id);
            $cpd      = $cpdData[$training->mentor_id] ?? ['total' => 0, 'level' => ['name' => 'Foundation', 'short' => 'F', 'color' => '#6B7280']];

            $classMentees   = $participants->where('mentorship_class_id', $class->id);
            $moduleNames    = $class->classModules
                ->map(fn ($cm) => $cm->programModule?->name)
                ->filter()
                ->values()
                ->toArray();

            // Modules completed/total for this class
            $modulesTotal     = $class->classModules->count();
            $modulesCompleted = $class->classModules->where('status', 'completed')->count();

            $rows[] = [
                'class_id'          => $class->id,
                'class_name'        => $class->name,
                'mentor_id'         => $training->mentor_id,
                'mentor_name'       => $mentor?->name ?? '—',
                'cadre'             => $mentor?->cadre?->name ?? '—',
                'department'        => $mentor?->department?->name ?? '—',
                'facility'          => $training->facility?->name ?? '—',
                'county'            => $training->facility?->subcounty?->county?->name ?? '—',
                'modules'           => $moduleNames,
                'modules_count'     => count($moduleNames),
                'mentees'           => $classMentees->count(),
                'certified'         => $classMentees->whereNotNull('head_drmh_approved_at')->count(),
                'cpd_total'         => $cpd['total'],
                'cpd_level'         => $cpd['level']['name'],
                'cpd_level_short'   => $cpd['level']['short'],
                'cpd_level_color'   => $cpd['level']['color'],
                'modules_completed' => $modulesCompleted,
                'modules_total'     => $modulesTotal,
            ];
        }

        usort($rows, fn ($a, $b) => $b['cpd_total'] <=> $a['cpd_total']);

        return $rows;
    }

    // ── KPIs ─────────────────────────────────────────────────────────────────

    private function buildKpis($mentors, $allClasses, $liveClasses, $participants, array $cpdData, $assessmentStats, $rubricStats, int $droppedMentees, array $exceptions): array
    {
        $totalCpd          = array_sum(array_column($cpdData, 'total'));
        $avgCpd            = count($cpdData) > 0 ? round($totalCpd / count($cpdData), 1) : 0;
        $modulesFacilitated = $liveClasses->sum(fn ($c) => $c->classModules->count());
        $totalMentors      = $mentors->count();
        $totalMentees      = $participants->pluck('user_id')->unique()->count();

        return [
            'total_mentors'       => $totalMentors,
            'active_classes'      => $liveClasses->count(),
            'completed_classes'   => $allClasses->where('status', 'completed')->count(),
            'modules_facilitated' => $modulesFacilitated,
            'total_mentees'       => $totalMentees,
            'certified_mentees'      => $participants->whereNotNull('head_drmh_approved_at')->count(),
            'total_cpd_awarded'   => $totalCpd,
            'avg_cpd_per_mentor'  => $avgCpd,
            'mentor_to_mentee_ratio' => $totalMentors > 0 ? round($totalMentees / $totalMentors, 1) : 0,
            'avg_assessment_score' => $assessmentStats && $assessmentStats->total > 0 ? round((float) $assessmentStats->avg_score, 1) : null,
            'assessment_pass_rate' => $assessmentStats && $assessmentStats->total > 0 ? round(($assessmentStats->passed / $assessmentStats->total) * 100, 1) : null,
            'avg_rubric_score' => $rubricStats && $rubricStats->total > 0 ? round((float) $rubricStats->avg_score, 1) : null,
            'rubric_pass_rate' => $rubricStats && $rubricStats->total > 0 ? round(($rubricStats->passed / $rubricStats->total) * 100, 1) : null,
            'inactive_mentors' => collect($exceptions)->where('tier', 2)->count(),
            'dropped_mentees' => $droppedMentees,
        ];
    }

    // ── Chart data ────────────────────────────────────────────────────────────

    private function buildChartData(array $matrix, $allClasses, $mentors, $trainings, array $cpdData): array
    {
        // CPD leaderboard — unique mentors from matrix, sorted desc
        $mentorCpd = [];
        foreach ($matrix as $row) {
            $mid = $row['mentor_id'];
            if (! isset($mentorCpd[$mid])) {
                $mentorCpd[$mid] = ['name' => $row['mentor_name'], 'cpd' => $row['cpd_total'], 'mentees' => 0];
            }
            $mentorCpd[$mid]['mentees'] += $row['mentees'];
        }

        $byCpd    = collect($mentorCpd)->sortByDesc('cpd')->take(10)->values();
        $byMentees= collect($mentorCpd)->sortByDesc('mentees')->take(10)->values();

        $leaderboardCpd    = ['labels' => $byCpd->pluck('name')->toArray(),    'data' => $byCpd->pluck('cpd')->toArray()];
        $leaderboardMentees= ['labels' => $byMentees->pluck('name')->toArray(),'data' => $byMentees->pluck('mentees')->toArray()];

        // CPD level distribution
        $levelCounts = [];
        foreach ($cpdData as $cpd) {
            $lvl = $cpd['level']['name'];
            $levelCounts[$lvl] = ($levelCounts[$lvl] ?? 0) + 1;
        }

        // By cadre — count unique mentors per cadre
        $byCadre = $mentors
            ->groupBy(fn ($m) => $m->cadre?->name ?? 'Unknown')
            ->map->count()
            ->sortDesc()
            ->take(10)
            ->map(fn ($cnt, $name) => ['name' => $name, 'count' => $cnt])
            ->values()
            ->toArray();

        // By department — unique mentors per department
        $byDepartment = $mentors
            ->groupBy(fn ($m) => $m->department?->name ?? 'Unknown')
            ->map->count()
            ->sortDesc()
            ->take(10)
            ->map(fn ($cnt, $name) => ['name' => $name, 'count' => $cnt])
            ->values()
            ->toArray();

        // By facility — unique mentors per facility
        $byFacility = $trainings
            ->groupBy(fn ($t) => $t->facility?->name ?? 'Unknown')
            ->map(fn ($group, $fname) => [
                'name'  => $fname,
                'count' => $group->pluck('mentor_id')->filter()->unique()->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->toArray();

        // By module — unique mentors facilitating each module (from live classes)
        $moduleMentors = [];
        foreach ($matrix as $row) {
            foreach ($row['modules'] as $mod) {
                $moduleMentors[$mod][$row['mentor_id']] = true;
            }
        }
        $byModule = collect($moduleMentors)
            ->map(fn ($mentorMap, $name) => ['name' => $name, 'count' => count($mentorMap)])
            ->sortByDesc('count')
            ->take(12)
            ->values()
            ->toArray();

        // 12-month trend (live classes started)
        $trend12 = [];
        for ($i = 11; $i >= 0; $i--) {
            $date  = Carbon::now()->subMonths($i);
            $ms    = $date->copy()->startOfMonth();
            $me    = $date->copy()->endOfMonth();
            $cnt   = $allClasses->filter(fn ($c) => $c->created_at && $c->created_at->between($ms, $me))->count();
            $trend12[] = ['short' => $date->format('M'), 'count' => $cnt];
        }

        // Class status breakdown
        $classStatus = $allClasses->groupBy(fn ($c) => $c->status ?? 'draft')->map->count()->toArray();

        return compact(
            'leaderboardCpd', 'leaderboardMentees', 'levelCounts',
            'byCadre', 'byDepartment', 'byFacility', 'byModule',
            'trend12', 'classStatus'
        );
    }

    // ── Insights ──────────────────────────────────────────────────────────────

    private function buildInsights(array $kpis, array $matrix): array
    {
        $insights = [];

        if ($kpis['total_mentors'] === 0) {
            return [['type' => 'info', 'icon' => 'info-circle',
                'text' => 'No mentors found in scope. Assign lead mentors to active mentorship classes.']];
        }

        if ($kpis['active_classes'] === 0) {
            $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle',
                'text' => 'No live (active) mentorship classes found. Activate a class to see live data here.'];
        } else {
            $insights[] = ['type' => 'info', 'icon' => 'chalkboard-teacher',
                'text' => "<strong>{$kpis['active_classes']}</strong> live class(es) across <strong>{$kpis['total_mentors']}</strong> mentor(s) with <strong>{$kpis['total_mentees']}</strong> enrolled mentees."];
        }

        if ($kpis['total_cpd_awarded'] > 0) {
            $insights[] = ['type' => 'success', 'icon' => 'star',
                'text' => "<strong>{$kpis['total_cpd_awarded']}</strong> total CPD points awarded. Average <strong>{$kpis['avg_cpd_per_mentor']}</strong> pts per mentor."];
        }

        if ($kpis['certified_mentees'] > 0) {
            $insights[] = ['type' => 'success', 'icon' => 'award',
                'text' => "<strong>{$kpis['certified_mentees']}</strong> mentee(s) in live classes have received their certificate."];
        }

        $zeroCpd = collect($matrix)->groupBy('mentor_id')
            ->filter(fn ($rows) => ($rows->first()['cpd_total'] ?? 0) === 0)->count();
        if ($zeroCpd > 0) {
            $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle',
                'text' => "{$zeroCpd} mentor(s) have 0 CPD points — completing modules in a closed class will start earning points."];
        }

        return array_slice($insights, 0, 5);
    }
}




