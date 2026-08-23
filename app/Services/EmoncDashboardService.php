<?php

namespace App\Services;

use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\MentorshipCoMentor;
use App\Models\Training;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmoncDashboardService
{
    private const SENIOR_ROLES = ['super_admin', 'admin', 'division', 'national'];

    public function build(?User $user): array
    {
        $trainingIds = $this->trainingIdsFor($user);

        $trainings = Training::whereIn('id', $trainingIds)
            ->where('type', 'facility_mentorship')
            ->dashboardVisible()
            ->with(['facility.subcounty.county', 'facility.facilityType', 'program', 'mentor'])
            ->get();

        $emoncTrainings   = $trainings->filter(fn ($t) => $this->isEmonc($t));
        $emoncTrainingIds = $emoncTrainings->pluck('id');

        $classes = MentorshipClass::whereIn('training_id', $emoncTrainingIds)
            ->with([
                'classModules.programModule.parent',
                'classModules.programModule.activities',
                'training.facility.subcounty.county',
                'training.facility.facilityType',
            ])
            ->get();

        $classIds      = $classes->pluck('id');
        $classModuleIds = $classes->flatMap(fn ($c) => $c->classModules->pluck('id'));

        $participants = ClassParticipant::with(['user.department', 'user.cadre'])
            ->whereIn('mentorship_class_id', $classIds)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->get();

        $progress = MenteeModuleProgress::whereIn('class_module_id', $classModuleIds)
            ->get()
            ->keyBy(fn ($p) => "{$p->class_participant_id}:{$p->class_module_id}");

        $activityParticipants = ClassModuleActivityParticipant::whereIn('class_module_id', $classModuleIds)
            ->get()
            ->groupBy(fn ($ap) => "{$ap->class_participant_id}:{$ap->class_module_id}");

        $kpis             = $this->computeKpis($classes, $participants, $progress);
        $pendingActions   = $this->computePendingActions($classes, $participants, $progress);
        $completionMatrix = $this->buildCompletionMatrix($classes, $participants, $progress, $activityParticipants);
        $chartData        = $this->buildChartData($classes, $participants, $progress);
        $extendedStats    = $this->buildExtendedStats($classes, $participants, $progress, $emoncTrainingIds->toArray());
        $insights         = $this->buildInsights($kpis, $extendedStats);

        return compact('kpis', 'pendingActions', 'completionMatrix', 'chartData', 'extendedStats', 'insights');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function trainingIdsFor(?User $user): array
    {
        if ($user === null || $user->hasRole(self::SENIOR_ROLES)) {
            return Training::where('type', 'facility_mentorship')
                ->dashboardVisible()
                ->pluck('id')
                ->toArray();
        }

        $asLead = Training::where('mentor_id', $user->id)
            ->where('type', 'facility_mentorship')
            ->dashboardVisible()
            ->pluck('id');

        $asCo = MentorshipCoMentor::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('training_id');

        return $asLead->merge($asCo)->unique()->values()->toArray();
    }

    private function isEmonc(Training $training): bool
    {
        $name = strtolower($training->program?->name ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }

    // ── KPIs ─────────────────────────────────────────────────────────────────

    private function computeKpis($classes, $participants, $progress): array
    {
        $classModuleIds = $classes->flatMap(fn ($c) => $c->classModules->pluck('id'));

        $totalMentees     = $participants->pluck('user_id')->unique()->count();
        $activeClasses    = $classes->where('status', 'active')->count();
        $completedClasses = $classes->where('status', 'completed')->count();
        $totalModules     = $classModuleIds->count();
        $completedModules = $classes->sum(fn ($c) => $c->classModules->where('status', 'completed')->count());

        $videoPending  = $progress->where('video_review_status', 'pending')->count();
        $mentorPending = $participants->whereNull('mentor_approved_at')->where('status', '!=', 'dropped')->count();
        $drmhPending   = $participants->whereNotNull('mentor_approved_at')->whereNull('head_drmh_approved_at')->count();
        $certified     = $participants->whereNotNull('head_drmh_approved_at')->count();

        return [
            'active_mentees'           => $totalMentees,
            'active_classes'           => $activeClasses,
            'completed_classes'        => $completedClasses,
            'total_modules'            => $totalModules,
            'completed_modules'        => $completedModules,
            'pending_video_reviews'    => $videoPending,
            'pending_mentor_approvals' => $mentorPending,
            'pending_drmh_approvals'   => $drmhPending,
            'certificates_issued'      => $certified,
        ];
    }

    // ── Pending actions ───────────────────────────────────────────────────────

    private function computePendingActions($classes, $participants, $progress): array
    {
        return [
            [
                'label' => 'Video reviews',
                'count' => $progress->where('video_review_status', 'pending')->count(),
                'url'   => '/admin',
                'color' => 'amber',
            ],
            [
                'label' => 'Mentor approvals',
                'count' => $participants->whereNull('mentor_approved_at')->where('status', '!=', 'dropped')->count(),
                'url'   => '/admin',
                'color' => 'blue',
            ],
            [
                'label' => 'Head DRMH certifications',
                'count' => $participants->whereNotNull('mentor_approved_at')->whereNull('head_drmh_approved_at')->count(),
                'url'   => '/admin',
                'color' => 'violet',
            ],
        ];
    }

    // ── Chart data ────────────────────────────────────────────────────────────

    private function buildChartData($classes, $participants, $progress): array
    {
        // Module progress (simple 3-bucket for the donut)
        $completionDistribution = [
            ['label' => 'Completed',   'value' => $progress->where('status', 'completed')->count()],
            ['label' => 'In Progress', 'value' => $progress->where('status', 'in_progress')->count()],
            ['label' => 'Not Started', 'value' => $progress->where('status', 'not_started')->count()],
        ];

        // Class lifecycle status
        $classStatusBreakdown = $classes
            ->groupBy(fn ($c) => $c->status ?? 'draft')
            ->map->count()
            ->toArray();

        // Mentee status
        $menteeStatus = $participants
            ->groupBy(fn ($p) => $p->status ?? 'enrolled')
            ->map->count()
            ->toArray();

        // Departments (top 10 by mentee count)
        $departments = $participants
            ->groupBy(fn ($p) => $p->user?->department?->name ?? 'Unknown')
            ->map->count()
            ->sortDesc()
            ->take(10)
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
            ->values()
            ->toArray();

        // Cadres (top 10)
        $cadres = $participants
            ->groupBy(fn ($p) => $p->user?->cadre?->name ?? 'Unknown')
            ->map->count()
            ->sortDesc()
            ->take(10)
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
            ->values()
            ->toArray();

        // Facility types (from EmONC classes' facilities)
        $facilityTypes = $classes
            ->groupBy(fn ($c) => $c->training->facility?->facilityType?->name ?? 'Unknown')
            ->map(fn ($group, $name) => [
                'name'                   => $name,
                'facilities_with_training' => $group->pluck('training.facility_id')->unique()->count(),
                'total_facilities'         => $group->pluck('training.facility_id')->unique()->count(),
            ])
            ->values()
            ->toArray();

        // Module completion % buckets (like mentorship mode)
        $buckets = ['0-25%' => 0, '26-50%' => 0, '51-75%' => 0, '76-99%' => 0, '100%' => 0];
        $perMenteeProgress = $participants->map(function ($p) use ($classes, $progress) {
            $classModuleIds = $classes
                ->where('id', $p->mentorship_class_id)
                ->flatMap(fn ($c) => $c->classModules->pluck('id'));

            $total     = $classModuleIds->count();
            $completed = $classModuleIds->filter(
                fn ($cmId) => $progress->get("{$p->id}:{$cmId}")?->status === 'completed'
            )->count();

            return $total > 0 ? ($completed / $total) * 100 : null;
        })->filter(fn ($pct) => $pct !== null);

        foreach ($perMenteeProgress as $pct) {
            if ($pct >= 100)     $buckets['100%']++;
            elseif ($pct >= 76)  $buckets['76-99%']++;
            elseif ($pct >= 51)  $buckets['51-75%']++;
            elseif ($pct >= 26)  $buckets['26-50%']++;
            else                 $buckets['0-25%']++;
        }

        return compact(
            'completionDistribution', 'classStatusBreakdown', 'menteeStatus',
            'departments', 'cadres', 'facilityTypes', 'buckets'
        );
    }

    // ── Extended stats ────────────────────────────────────────────────────────

    private function buildExtendedStats($classes, $participants, $progress, array $emoncTrainingIds): array
    {
        // 12-month trend: count of mentorship classes started each month
        $trend12 = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $ms   = $date->copy()->startOfMonth();
            $me   = $date->copy()->endOfMonth();

            $cnt = $classes->filter(
                fn ($c) => $c->created_at && $c->created_at->between($ms, $me)
            )->count();

            $trend12[] = ['month' => $date->format('M y'), 'short' => $date->format('M'), 'count' => $cnt];
        }

        // Top counties by mentee count
        $topCounties = $classes
            ->flatMap(function ($class) use ($participants) {
                $county = $class->training->facility?->subcounty?->county?->name ?? 'Unknown';
                return $participants
                    ->where('mentorship_class_id', $class->id)
                    ->map(fn ($p) => ['county' => $county, 'user_id' => $p->user_id]);
            })
            ->groupBy('county')
            ->map(fn ($group, $name) => [
                'name'  => $name,
                'count' => $group->pluck('user_id')->unique()->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->toArray();

        // Top programs (trainings) by mentee count
        $topPrograms = $classes
            ->groupBy('training_id')
            ->map(function ($classGroup) use ($participants) {
                $training = $classGroup->first()->training;
                $classIds = $classGroup->pluck('id');
                $mentees  = $participants->whereIn('mentorship_class_id', $classIds->toArray())
                    ->pluck('user_id')->unique()->count();
                return [
                    'title'  => $training?->title ?? '(Untitled)',
                    'count'  => $mentees,
                    'status' => $training?->status ?? 'unknown',
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->toArray();

        // YoY: classes started this year vs last year
        $curYear   = Carbon::now()->year;
        $prevYear  = $curYear - 1;
        $curCount  = $classes->filter(fn ($c) => $c->created_at?->year === $curYear)->count();
        $prevCount = $classes->filter(fn ($c) => $c->created_at?->year === $prevYear)->count();
        $yoyChange = $prevCount > 0 ? round((($curCount - $prevCount) / $prevCount) * 100, 1) : 0;
        $yearComparison = ['current' => $curCount, 'previous' => $prevCount, 'change' => $yoyChange, 'year' => $curYear];

        return compact('trend12', 'topCounties', 'topPrograms', 'yearComparison');
    }

    // ── Insights ─────────────────────────────────────────────────────────────

    private function buildInsights(array $kpis, array $extendedStats): array
    {
        $insights = [];

        $certified    = $kpis['certificates_issued'];
        $totalMentees = $kpis['active_mentees'];
        $certRate     = $totalMentees > 0 ? round(($certified / $totalMentees) * 100, 1) : 0;

        if ($certRate >= 70) {
            $insights[] = ['type' => 'success', 'icon' => 'award',
                'text' => "Excellent certification rate of {$certRate}% — {$certified} mentees have earned their EmONC certificate."];
        } elseif ($certRate >= 30) {
            $insights[] = ['type' => 'warning', 'icon' => 'clock',
                'text' => "Certification rate is {$certRate}% — {$kpis['pending_drmh_approvals']} mentees are awaiting Head DRMH sign-off."];
        } elseif ($totalMentees > 0) {
            $insights[] = ['type' => 'danger', 'icon' => 'exclamation-circle',
                'text' => "Only {$certRate}% of mentees are certified. Check pipeline blockers — video reviews and approvals outstanding."];
        }

        if ($kpis['pending_video_reviews'] > 0) {
            $insights[] = ['type' => 'warning', 'icon' => 'video',
                'text' => "{$kpis['pending_video_reviews']} hands-on video review(s) are pending mentor assessment."];
        }

        if ($kpis['active_classes'] > 0) {
            $insights[] = ['type' => 'info', 'icon' => 'chalkboard-teacher',
                'text' => "{$kpis['active_classes']} EmONC class(es) currently active with {$totalMentees} enrolled mentees across all sites."];
        }

        $topCounty = $extendedStats['topCounties'][0] ?? null;
        if ($topCounty && $topCounty['count'] > 0) {
            $insights[] = ['type' => 'primary', 'icon' => 'map-marker-alt',
                'text' => "{$topCounty['name']} County leads EmONC participation with {$topCounty['count']} mentee(s)."];
        }

        $yoy = $extendedStats['yearComparison'];
        if ($yoy['change'] > 0) {
            $insights[] = ['type' => 'success', 'icon' => 'chart-line',
                'text' => "EmONC classes grew by {$yoy['change']}% year-on-year ({$yoy['previous']} → {$yoy['current']} classes)."];
        } elseif ($yoy['change'] < 0) {
            $insights[] = ['type' => 'danger', 'icon' => 'chart-line',
                'text' => "EmONC classes declined by " . abs($yoy['change']) . "% year-on-year — consider expanding programme reach."];
        }

        return $insights;
    }

    // ── Completion matrix ─────────────────────────────────────────────────────

    private function buildCompletionMatrix($classes, $participants, $progress, $activityParticipants): array
    {
        $matrix = [];

        // Batch-load CPD data for all unique mentees (cross-program totals)
        $userIds = $participants->pluck('user_id')->unique()->values()->toArray();
        $cpdData = app(\App\Services\CpdPointsService::class)->batchForMentees($userIds);

        foreach ($classes as $class) {
            foreach ($class->classModules as $classModule) {
                $programModule = $classModule->programModule;
                $moduleName    = $programModule?->name ?? 'Module';
                $parentName    = $programModule?->parent?->name;
                $activities    = $programModule?->activities ?? collect();

                foreach ($participants->where('mentorship_class_id', $class->id) as $participant) {
                    $key            = "{$participant->id}:{$classModule->id}";
                    $prog           = $progress->get($key);
                    $activityStatus = $this->activityStatusFor($activities, $activityParticipants->get($key, collect()));
                    $cpd            = $cpdData[$participant->user_id] ?? ['total' => 0, 'level' => ['name' => 'Foundation', 'short' => 'F', 'color' => '#6B7280']];

                    $matrix[] = [
                        'class_id'           => $class->id,
                        'class_name'         => $class->name,
                        'facility_name'      => $class->training->facility?->name ?? '—',
                        'county_name'        => $class->training->facility?->subcounty?->county?->name ?? '—',
                        'participant_id'     => $participant->id,
                        'user_id'            => $participant->user_id,
                        'mentee_name'        => $participant->user?->name ?? 'Unknown',
                        'module_id'          => $classModule->id,
                        'module_name'        => $moduleName,
                        'parent_module_name' => $parentName,
                        'activities'         => $activityStatus,
                        'module_progress'    => $prog?->status ?? 'not_started',
                        'video_review'       => $prog?->video_review_status ?? 'not_submitted',
                        'mentor_approved'    => ! empty($participant->mentor_approved_at),
                        'head_drmh_approved' => ! empty($participant->head_drmh_approved_at),
                        'certificate_ready'  => $participant->isCertified(),
                        'blocked_reasons'    => $this->blockedReasons($participant, $prog, $activityStatus),
                        'cpd_total'          => $cpd['total'],
                        'cpd_level'          => $cpd['level']['name'],
                        'cpd_level_short'    => $cpd['level']['short'],
                        'cpd_level_color'    => $cpd['level']['color'],
                    ];
                }
            }
        }

        // Certified rows first, then alphabetically by mentee name
        usort($matrix, function ($a, $b) {
            if ($a['certificate_ready'] !== $b['certificate_ready']) {
                return $b['certificate_ready'] <=> $a['certificate_ready'];
            }

            return strcmp($a['mentee_name'], $b['mentee_name']);
        });

        return $matrix;
    }

    private function activityStatusFor($activities, $activityParticipants): array
    {
        $status = [];
        foreach ($activities as $activity) {
            $ap       = $activityParticipants->firstWhere('activity_id', $activity->id);
            $status[] = [
                'name'   => $activity->name,
                'status' => $ap?->status ?? 'not_enrolled',
            ];
        }

        return $status;
    }

    private function blockedReasons(ClassParticipant $participant, ?MenteeModuleProgress $progress, array $activityStatus): array
    {
        $reasons = [];
        foreach ($activityStatus as $a) {
            if ($a['status'] !== 'completed') {
                $reasons[] = "{$a['name']} not completed";
            }
        }
        if ($progress && $progress->video_review_status !== 'passed') {
            $reasons[] = 'Hands-on video not passed';
        }
        if (empty($participant->mentor_approved_at)) {
            $reasons[] = 'Pending mentor approval';
        }
        if (empty($participant->head_drmh_approved_at)) {
            $reasons[] = 'Pending Head DRMH certification';
        }

        return $reasons;
    }
}
