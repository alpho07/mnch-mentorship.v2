<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\TrainingParticipant;
use App\Models\ClassParticipant;
use App\Models\County;
use App\Services\AssessmentAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsSummaryController extends Controller
{
    public function summary(Request $request)
    {
        $mode = $request->get('mode', 'training');  // training | mentorship | assessment
        $year = $request->get('year', '');

        if ($mode === 'assessment') {
            return $this->assessmentSummary($year);
        }

        $summaryStats = $this->summaryStats($mode, $year);
        $chartData    = $this->chartData($mode, $year);
        $statusData   = $this->statusBreakdown($mode, $year);
        $topCounties  = $this->topCounties($mode, $year);
        $countyMap    = $this->countyMap($mode, $year);
        $insights     = $this->insights($summaryStats, $mode);

        return response()->json([
            'mode'         => $mode,
            'year'         => $year,
            'summaryStats' => $summaryStats,
            'chartData'    => $chartData,
            'statusData'   => $statusData,
            'topCounties'  => $topCounties,
            'countyMap'    => $countyMap,
            'insights'     => $insights,
        ]);
    }

    // ── Assessment mode ───────────────────────────────────────────────────────

    private function assessmentSummary(string $year)
    {
        $svc     = app(AssessmentAnalyticsService::class);
        $filters = $year ? ['year' => $year] : [];

        $stats    = $svc->getSummaryStats($filters);
        $chart    = $svc->getChartData($filters);
        $insights = $svc->generateInsights($stats);

        $readiness = $svc->getFacilitiesReadiness($filters)
            ->take(100)
            ->map(fn($a) => [
                'facility'           => $a->facility?->name ?? 'Unknown',
                'mfl_code'           => $a->facility?->mfl_code ?? '',
                'subcounty'          => $a->facility?->subcounty?->name ?? '',
                'county'             => $a->facility?->subcounty?->county?->name ?? '',
                'level'              => $a->facility?->facilityLevel?->name ?? '',
                'assessment_date'    => $a->assessment_date
                    ? Carbon::parse($a->assessment_date)->format('d M Y')
                    : '',
                'assessment_type'    => ucfirst($a->assessment_type ?? ''),
                'has_skills_lab'     => (bool) $a->has_skills_lab,
                'has_room'           => (bool) $a->has_room,
                'eligibility_status' => $a->eligibility_status,
                'feedback_given'     => (bool) $a->feedback_given,
                'mentorship_count'   => (int) $a->mentorship_count,
                'overall_percentage' => $a->overall_percentage,
            ])->values();

        $countyMap  = $this->assessmentCountyMap($year);
        $topCounties = array_slice($countyMap, 0, 10);

        return response()->json([
            'mode'                => 'assessment',
            'year'                => $year,
            'summaryStats'        => $stats,
            'chartData'           => $chart,
            'insights'            => $insights,
            'facilitiesReadiness' => $readiness,
            'countyMap'           => $countyMap,
            'topCounties'         => $topCounties,
            'statusData'          => [],
        ]);
    }

    private function assessmentCountyMap(string $year): array
    {
        $rows = DB::table('assessments')
            ->join('facilities', 'facilities.id', '=', 'assessments.facility_id')
            ->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
            ->join('counties', 'counties.id', '=', 'subcounties.county_id')
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->select('counties.name', DB::raw('COUNT(DISTINCT assessments.facility_id) as count'))
            ->groupBy('counties.id', 'counties.name')
            ->get();

        $max = $rows->max(fn($r) => $r->count) ?: 1;

        return $rows->sortByDesc(fn($r) => $r->count)->values()->map(fn($r) => [
            'name'  => $r->name,
            'count' => (int) $r->count,
            'pct'   => (int) round(($r->count / $max) * 100),
        ])->toArray();
    }

    // ── Training / Mentorship ─────────────────────────────────────────────────

    private function summaryStats(string $mode, string $year): array
    {
        $type = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        $totalPrograms = Training::where('type', $type)
            ->when($year, fn($q) => $q->whereYear('start_date', $year))
            ->count();

        if ($mode === 'training') {
            $totalParticipants = TrainingParticipant::whereHas('training', function ($q) use ($type, $year) {
                $q->where('type', $type);
                if ($year) $q->whereYear('start_date', $year);
            })->distinct('user_id')->count();

            $totalFacilities = Facility::whereHas('users.trainingParticipations.training', function ($q) use ($year) {
                $q->where('type', 'global_training');
                if ($year) $q->whereYear('start_date', $year);
            })->count();
        } else {
            $totalParticipants = ClassParticipant::whereHas('mentorshipClass.training', function ($q) use ($year) {
                $q->where('type', 'facility_mentorship');
                if ($year) $q->whereYear('start_date', $year);
            })->distinct('user_id')->count('user_id');

            $totalFacilities = Facility::whereHas('trainings', function ($q) use ($year) {
                $q->where('type', 'facility_mentorship');
                if ($year) $q->whereYear('start_date', $year);
            })->count();
        }

        $allFacilities    = Facility::count();
        $facilityCoverage = $allFacilities > 0 ? round(($totalFacilities / $allFacilities) * 100, 1) : 0;
        $avgParticipants  = $totalPrograms > 0 ? round($totalParticipants / $totalPrograms, 1) : 0;

        return compact('totalPrograms', 'totalParticipants', 'totalFacilities', 'facilityCoverage', 'avgParticipants');
    }

    private function chartData(string $mode, string $year): array
    {
        $type = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        // ── Monthly trend (last 6 months) ──
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $date  = Carbon::now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();

            if ($mode === 'training') {
                $count = TrainingParticipant::whereHas('training', function ($q) use ($type, $year) {
                    $q->where('type', $type);
                    if ($year) $q->whereYear('start_date', $year);
                })->whereBetween('registration_date', [$start, $end])->count();
            } else {
                $count = ClassParticipant::whereHas('mentorshipClass.training', function ($q) use ($year) {
                    $q->where('type', 'facility_mentorship');
                    if ($year) $q->whereYear('start_date', $year);
                })->whereBetween('created_at', [$start, $end])->count();
            }

            $monthly[] = ['month' => $date->format('M'), 'count' => $count];
        }

        // ── Top departments ──
        if ($mode === 'training') {
            $departments = DB::table('training_participants')
                ->join('trainings', 'trainings.id', '=', 'training_participants.training_id')
                ->join('users', 'users.id', '=', 'training_participants.user_id')
                ->join('departments', 'departments.id', '=', 'users.department_id')
                ->where('trainings.type', 'global_training')
                ->when($year, fn($q) => $q->whereYear('trainings.start_date', $year))
                ->select('departments.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                ->groupBy('departments.id', 'departments.name')
                ->orderByDesc('count')
                ->limit(8)
                ->get()
                ->map(fn($r) => ['name' => $r->name, 'count' => (int) $r->count])
                ->toArray();
        } else {
            $departments = DB::table('class_participants')
                ->join('mentorship_classes', 'mentorship_classes.id', '=', 'class_participants.mentorship_class_id')
                ->join('trainings', 'trainings.id', '=', 'mentorship_classes.training_id')
                ->join('users', 'users.id', '=', 'class_participants.user_id')
                ->join('departments', 'departments.id', '=', 'users.department_id')
                ->where('trainings.type', 'facility_mentorship')
                ->when($year, fn($q) => $q->whereYear('trainings.start_date', $year))
                ->select('departments.name', DB::raw('COUNT(DISTINCT class_participants.user_id) as count'))
                ->groupBy('departments.id', 'departments.name')
                ->orderByDesc('count')
                ->limit(8)
                ->get()
                ->map(fn($r) => ['name' => $r->name, 'count' => (int) $r->count])
                ->toArray();
        }

        // ── Top cadres ──
        if ($mode === 'training') {
            $cadres = DB::table('training_participants')
                ->join('trainings', 'trainings.id', '=', 'training_participants.training_id')
                ->join('users', 'users.id', '=', 'training_participants.user_id')
                ->join('assessment_cadres', 'assessment_cadres.id', '=', 'users.cadre_id')
                ->where('trainings.type', 'global_training')
                ->when($year, fn($q) => $q->whereYear('trainings.start_date', $year))
                ->select('assessment_cadres.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                ->groupBy('assessment_cadres.id', 'assessment_cadres.name')
                ->orderByDesc('count')
                ->limit(8)
                ->get()
                ->map(fn($r) => ['name' => $r->name, 'count' => (int) $r->count])
                ->toArray();
        } else {
            $cadres = DB::table('class_participants')
                ->join('mentorship_classes', 'mentorship_classes.id', '=', 'class_participants.mentorship_class_id')
                ->join('trainings', 'trainings.id', '=', 'mentorship_classes.training_id')
                ->join('users', 'users.id', '=', 'class_participants.user_id')
                ->join('assessment_cadres', 'assessment_cadres.id', '=', 'users.cadre_id')
                ->where('trainings.type', 'facility_mentorship')
                ->when($year, fn($q) => $q->whereYear('trainings.start_date', $year))
                ->select('assessment_cadres.name', DB::raw('COUNT(DISTINCT class_participants.user_id) as count'))
                ->groupBy('assessment_cadres.id', 'assessment_cadres.name')
                ->orderByDesc('count')
                ->limit(8)
                ->get()
                ->map(fn($r) => ['name' => $r->name, 'count' => (int) $r->count])
                ->toArray();
        }

        // ── Facility type coverage ──
        $facilityTypes = FacilityType::withCount([
            'facilities as total',
            'facilities as covered' => function ($q) use ($mode, $year) {
                if ($mode === 'training') {
                    $q->whereHas('users.trainingParticipations.training', function ($qq) use ($year) {
                        $qq->where('type', 'global_training');
                        if ($year) $qq->whereYear('start_date', $year);
                    });
                } else {
                    $q->whereHas('trainings', function ($qq) use ($year) {
                        $qq->where('type', 'facility_mentorship');
                        if ($year) $qq->whereYear('start_date', $year);
                    });
                }
            },
        ])->get()->map(fn($t) => [
            'name'    => $t->name,
            'total'   => (int) $t->total,
            'covered' => (int) $t->covered,
            'pct'     => $t->total > 0 ? round(($t->covered / $t->total) * 100, 1) : 0,
        ])->sortByDesc('covered')->values()->toArray();

        return compact('monthly', 'departments', 'cadres', 'facilityTypes');
    }

    private function statusBreakdown(string $mode, string $year): array
    {
        $type = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        $rows = Training::where('type', $type)
            ->when($year, fn($q) => $q->whereYear('start_date', $year))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = ['status' => $row->status, 'count' => (int) $row->count];
        }
        return $result;
    }

    private function topCounties(string $mode, string $year): array
    {
        return array_slice($this->countyMap($mode, $year), 0, 10);
    }

    private function countyMap(string $mode, string $year): array
    {
        if ($mode === 'training') {
            $rows = DB::table('training_participants')
                ->join('trainings', 'trainings.id', '=', 'training_participants.training_id')
                ->join('users', 'users.id', '=', 'training_participants.user_id')
                ->join('facilities', 'facilities.id', '=', 'users.facility_id')
                ->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                ->join('counties', 'counties.id', '=', 'subcounties.county_id')
                ->where('trainings.type', 'global_training')
                ->when($year, fn($q) => $q->whereYear('trainings.start_date', $year))
                ->select('counties.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                ->groupBy('counties.id', 'counties.name')
                ->get();
        } else {
            $rows = DB::table('class_participants')
                ->join('mentorship_classes', 'mentorship_classes.id', '=', 'class_participants.mentorship_class_id')
                ->join('trainings', 'trainings.id', '=', 'mentorship_classes.training_id')
                ->join('facilities', 'facilities.id', '=', 'trainings.facility_id')
                ->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                ->join('counties', 'counties.id', '=', 'subcounties.county_id')
                ->where('trainings.type', 'facility_mentorship')
                ->when($year, fn($q) => $q->whereYear('trainings.start_date', $year))
                ->select('counties.name', DB::raw('COUNT(DISTINCT class_participants.user_id) as count'))
                ->groupBy('counties.id', 'counties.name')
                ->get();
        }

        $max = $rows->max(fn($r) => $r->count) ?: 1;

        return $rows->sortByDesc(fn($r) => $r->count)->values()->map(fn($r) => [
            'name'  => $r->name,
            'count' => (int) $r->count,
            'pct'   => (int) round(($r->count / $max) * 100),
        ])->toArray();
    }

    private function insights(array $stats, string $mode): array
    {
        $ins   = [];
        $label = $mode === 'training' ? 'training' : 'mentorship';

        if ($stats['totalPrograms'] === 0) {
            $ins[] = ['type' => 'warning', 'icon' => 'exclamation-triangle',
                'text' => "No {$label} programs found. Create your first program to start tracking progress."];
            return $ins;
        }

        $cov = $stats['facilityCoverage'];
        if ($cov >= 60) {
            $ins[] = ['type' => 'success', 'icon' => 'check-circle',
                'text' => "Excellent facility coverage at {$cov}% — the {$label} program is reaching a strong majority of health facilities."];
        } elseif ($cov >= 30) {
            $ins[] = ['type' => 'warning', 'icon' => 'exclamation-circle',
                'text' => "Moderate facility coverage at {$cov}%. There is room to expand the {$label} program to more facilities."];
        } else {
            $ins[] = ['type' => 'danger', 'icon' => 'times-circle',
                'text' => "Low facility coverage at {$cov}%. Consider expanding the {$label} program outreach to more health facilities."];
        }

        $avg = $stats['avgParticipants'];
        if ($avg >= 20) {
            $ins[] = ['type' => 'success', 'icon' => 'users',
                'text' => "Strong average of {$avg} participants per {$label}, indicating high engagement."];
        } elseif ($avg >= 5) {
            $ins[] = ['type' => 'primary', 'icon' => 'users',
                'text' => "Average of {$avg} participants per {$label}. Consider strategies to boost enrollment."];
        } elseif ($avg > 0) {
            $ins[] = ['type' => 'warning', 'icon' => 'user',
                'text' => "Low average of {$avg} participants per {$label}. Focused outreach may improve uptake."];
        }

        $total = $stats['totalParticipants'];
        if ($total > 0) {
            $ins[] = ['type' => 'info', 'icon' => 'chart-line',
                'text' => number_format($total) . " total " . ($mode === 'training' ? 'participants' : 'mentees') . " trained across {$stats['totalFacilities']} facilities."];
        }

        return $ins;
    }
}
