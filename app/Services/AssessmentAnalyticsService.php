<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\Facility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssessmentAnalyticsService
{
    public function getSummaryStats(array $filters = []): array
    {
        $year           = $filters['year'] ?? null;
        $assessmentType = $filters['assessment_type'] ?? null;
        $countyId       = $filters['county_id'] ?? null;
        $subcountyId    = $filters['subcounty_id'] ?? null;
        $facilityId     = $filters['facility_id'] ?? null;

        // Resolve geographic scope to a facility ID list for raw DB queries
        $facilityIds = null;
        if ($facilityId) {
            $facilityIds = [(int) $facilityId];
        } elseif ($subcountyId || $countyId) {
            $facilityIds = Facility::whereNull('deleted_at')
                ->when($subcountyId, fn($q) => $q->where('subcounty_id', $subcountyId))
                ->when($countyId && !$subcountyId, fn($q) => $q->whereHas('subcounty', fn($s) => $s->where('county_id', $countyId)))
                ->pluck('id')->all();
        }

        $base = fn() => Assessment::whereNull('deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessment_type', $assessmentType))
            ->when($facilityIds !== null, fn($q) => $q->whereIn('facility_id', $facilityIds));

        // Resolve question IDs from codes so we query actual responses, not section scores
        $skillsMasterQId  = DB::table('assessment_questions')->where('question_code', 'SKILLS_MASTER')->value('id');
        $skillsRoomQId    = DB::table('assessment_questions')->where('question_code', 'SKILLS_ROOM_SPACE')->value('id');

        // Unique (facility, period) pairs — with rule enforced this equals total count
        $uniqueAssessments  = DB::table(
            $base()->select('facility_id', 'assessment_type')->distinct()->toBase(),
            'u'
        )->count();
        $facilitiesAssessed = $base()->distinct('facility_id')->count('facility_id');
        $allFacilities      = Facility::whereNull('deleted_at')->count();

        // Avg score = average of per-assessment section averages (each section weighted equally)
        // Computed live from assessment_section_scores so it matches DynamicScoringService formula
        $avgScoreRaw = DB::table(
            DB::table('assessment_section_scores')
                ->join('assessments', 'assessments.id', '=', 'assessment_section_scores.assessment_id')
                ->where('assessments.status', 'completed')
                ->whereNull('assessments.deleted_at')
                ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
                ->when($assessmentType, fn($q) => $q->where('assessments.assessment_type', $assessmentType))
                ->when($facilityIds !== null, fn($q) => $q->whereIn('assessments.facility_id', $facilityIds))
                ->select('assessments.id', DB::raw('AVG(assessment_section_scores.percentage) as section_avg'))
                ->groupBy('assessments.id'),
            'per_assessment'
        )->avg('section_avg');
        $avgScore = round((float) ($avgScoreRaw ?? 0), 1);

        // Facilities with a dedicated skills lab (SKILLS_MASTER = Yes)
        $withSkillsLab = (int) DB::table('assessments')
            ->join('assessment_question_responses as aqr', 'aqr.assessment_id', '=', 'assessments.id')
            ->where('aqr.assessment_question_id', (int) $skillsMasterQId)
            ->where('aqr.response_value', 'Yes')
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessments.assessment_type', $assessmentType))
            ->when($facilityIds !== null, fn($q) => $q->whereIn('assessments.facility_id', $facilityIds))
            ->distinct('assessments.facility_id')
            ->count('assessments.facility_id');

        // Eligible = has skills lab OR room/space, AND feedback has been given
        $eligible = (int) DB::table('assessments')
            ->where('assessments.status', 'completed')
            ->where('assessments.feedback_given', true)
            ->whereNull('assessments.deleted_at')
            ->where(function ($q) use ($skillsMasterQId, $skillsRoomQId) {
                $q->whereExists(function ($sub) use ($skillsMasterQId) {
                    $sub->select(DB::raw(1))
                        ->from('assessment_question_responses as aqr')
                        ->whereColumn('aqr.assessment_id', 'assessments.id')
                        ->where('aqr.assessment_question_id', (int) $skillsMasterQId)
                        ->where('aqr.response_value', 'Yes');
                })->orWhereExists(function ($sub) use ($skillsRoomQId) {
                    $sub->select(DB::raw(1))
                        ->from('assessment_question_responses as aqr')
                        ->whereColumn('aqr.assessment_id', 'assessments.id')
                        ->where('aqr.assessment_question_id', (int) $skillsRoomQId)
                        ->where('aqr.response_value', 'Yes');
                });
            })
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessments.assessment_type', $assessmentType))
            ->when($facilityIds !== null, fn($q) => $q->whereIn('assessments.facility_id', $facilityIds))
            ->distinct('assessments.facility_id')
            ->count('assessments.facility_id');

        // Ready = has skills lab OR room/space, regardless of whether
        // feedback has been given yet (the physical-readiness superset that
        // $eligible narrows down with the feedback_given requirement — use
        // this, not $withSkillsLab, to compute "pending feedback" so the
        // math can't go negative: $withSkillsLab only counts a dedicated
        // lab and excludes room-only facilities that $eligible does count).
        $readyForMentorship = (int) DB::table('assessments')
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->where(function ($q) use ($skillsMasterQId, $skillsRoomQId) {
                $q->whereExists(function ($sub) use ($skillsMasterQId) {
                    $sub->select(DB::raw(1))
                        ->from('assessment_question_responses as aqr')
                        ->whereColumn('aqr.assessment_id', 'assessments.id')
                        ->where('aqr.assessment_question_id', (int) $skillsMasterQId)
                        ->where('aqr.response_value', 'Yes');
                })->orWhereExists(function ($sub) use ($skillsRoomQId) {
                    $sub->select(DB::raw(1))
                        ->from('assessment_question_responses as aqr')
                        ->whereColumn('aqr.assessment_id', 'assessments.id')
                        ->where('aqr.assessment_question_id', (int) $skillsRoomQId)
                        ->where('aqr.response_value', 'Yes');
                });
            })
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessments.assessment_type', $assessmentType))
            ->when($facilityIds !== null, fn($q) => $q->whereIn('assessments.facility_id', $facilityIds))
            ->distinct('assessments.facility_id')
            ->count('assessments.facility_id');

        $facilityCoveragePercent = $allFacilities > 0
            ? round(($facilitiesAssessed / $allFacilities) * 100, 1)
            : 0;

        // Facilities with at least one live mentorship (non-pilot, active/
        // completed, and actually has an enrolled mentee — matches the
        // facilities readiness table's mentorship_count definition)
        $withMentorships = (int) DB::table('assessments')
            ->join('trainings', 'trainings.facility_id', '=', 'assessments.facility_id')
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->where('trainings.type', 'facility_mentorship')
            ->whereNull('trainings.deleted_at')
            ->where('trainings.is_pilot', false)
            ->whereIn('trainings.status', ['active', 'completed'])
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('mentorship_classes as mc')
                    ->join('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')
                    ->whereColumn('mc.training_id', 'trainings.id')
                    ->whereIn('cp.status', ['enrolled', 'active', 'completed']);
            })
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessments.assessment_type', $assessmentType))
            ->when($facilityIds !== null, fn($q) => $q->whereIn('assessments.facility_id', $facilityIds))
            ->distinct('assessments.facility_id')
            ->count('assessments.facility_id');

        $mentorshipCoverage = $facilitiesAssessed > 0
            ? round(($withMentorships / $facilitiesAssessed) * 100, 1)
            : 0;

        // YoY
        $curYear   = Carbon::now()->year;
        $prevYear  = $curYear - 1;
        $curCount  = Assessment::whereNull('deleted_at')->whereYear('assessment_date', $curYear)->count();
        $prevCount = Assessment::whereNull('deleted_at')->whereYear('assessment_date', $prevYear)->count();
        $yoyChange = $prevCount > 0
            ? round((($curCount - $prevCount) / $prevCount) * 100, 1)
            : 0;

        return compact(
            'uniqueAssessments', 'facilitiesAssessed', 'allFacilities',
            'avgScore', 'withSkillsLab', 'eligible', 'readyForMentorship',
            'facilityCoveragePercent', 'yoyChange', 'curYear',
            'withMentorships', 'mentorshipCoverage'
        );
    }

    public function generateInsights(array $stats): array
    {
        $insights           = [];
        $facilitiesAssessed = $stats['facilitiesAssessed'] ?? 0;
        $allFacilities      = $stats['allFacilities'] ?? 0;
        $coverage           = $stats['facilityCoveragePercent'] ?? 0;
        $withSkillsLab      = $stats['withSkillsLab'] ?? 0;
        $eligible           = $stats['eligible'] ?? 0;
        $readyForMentorship = $stats['readyForMentorship'] ?? 0;
        $avgScore           = $stats['avgScore'] ?? 0;

        if ($coverage >= 60) {
            $insights[] = ['type' => 'success', 'icon' => 'check-circle', 'text' =>
                "Strong coverage at {$coverage}% — {$facilitiesAssessed} of {$allFacilities} facilities have been assessed."];
        } elseif ($coverage >= 30) {
            $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'text' =>
                "Moderate coverage at {$coverage}% — " . ($allFacilities - $facilitiesAssessed) . " facilities still need assessment."];
        } elseif ($facilitiesAssessed > 0) {
            $insights[] = ['type' => 'danger', 'icon' => 'exclamation-circle', 'text' =>
                "Low coverage at {$coverage}% — significant outreach needed; " . ($allFacilities - $facilitiesAssessed) . " facilities unassessed."];
        }

        $noSkillsLab = $facilitiesAssessed - $readyForMentorship;
        if ($noSkillsLab > 0) {
            $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'text' =>
                "{$noSkillsLab} assessed " . str('facility')->plural($noSkillsLab) . " lack a skills lab or room — not eligible for mentorship training."];
        }

        $pendingFeedback = $readyForMentorship - $eligible;
        if ($pendingFeedback > 0) {
            $insights[] = ['type' => 'info', 'icon' => 'clock', 'text' =>
                "{$pendingFeedback} " . str('facility')->plural($pendingFeedback) . " have a skills lab/room but feedback not given — partially eligible for mentorship."];
        }

        if ($avgScore > 0) {
            $grade = $avgScore >= 80 ? 'Good' : ($avgScore >= 50 ? 'Fair' : 'Needs Improvement');
            $type  = $avgScore >= 80 ? 'success' : ($avgScore >= 50 ? 'warning' : 'danger');
            $insights[] = ['type' => $type, 'icon' => 'chart-bar', 'text' =>
                "National average score is {$avgScore}% ({$grade}). {$eligible} " . str('facility')->plural($eligible) . " fully eligible for mentorship."];
        }

        return array_slice($insights, 0, 4);
    }

    public function getChartData(array $filters = []): array
    {
        $year           = $filters['year'] ?? null;
        $assessmentType = $filters['assessment_type'] ?? null;

        // ── 1. Monthly trend (12 months) grouped by type ──────────────────────
        $monthlyTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $date  = Carbon::now()->subMonths($i);
            $ms    = $date->copy()->startOfMonth();
            $me    = $date->copy()->endOfMonth();

            $counts = Assessment::whereNull('deleted_at')
                ->whereBetween('assessment_date', [$ms, $me])
                ->when($assessmentType, fn($q) => $q->where('assessment_type', $assessmentType))
                ->selectRaw('assessment_type, COUNT(*) as count')
                ->groupBy('assessment_type')
                ->pluck('count', 'assessment_type');

            $monthlyTrend[] = [
                'month'    => $date->format('M y'),
                'short'    => $date->format('M'),
                'baseline' => (int) ($counts['baseline'] ?? 0),
                'midline'  => (int) ($counts['midline']  ?? 0),
                'endline'  => (int) ($counts['endline']  ?? 0),
            ];
        }

        // ── 2. Grade distribution ─────────────────────────────────────────────
        $gradeRows = Assessment::where('status', 'completed')
            ->whereNull('deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
            ->selectRaw('overall_grade, COUNT(*) as count')
            ->groupBy('overall_grade')
            ->pluck('count', 'overall_grade');

        $gradeDistribution = [
            'green'  => (int) ($gradeRows['green']  ?? 0),
            'yellow' => (int) ($gradeRows['yellow'] ?? 0),
            'red'    => (int) ($gradeRows['red']    ?? 0),
        ];

        // ── 3. Section score averages ─────────────────────────────────────────
        $sectionAverages = DB::table('assessment_section_scores')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_section_scores.assessment_section_id')
            ->join('assessments', 'assessments.id', '=', 'assessment_section_scores.assessment_id')
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->select(
                'assessment_sections.name',
                'assessment_sections.code',
                DB::raw('ROUND(AVG(assessment_section_scores.percentage), 1) as avg_percentage')
            )
            ->groupBy('assessment_sections.id', 'assessment_sections.name', 'assessment_sections.code')
            ->orderByDesc('avg_percentage')
            ->get()
            ->map(fn($row) => [
                'name'       => $row->name,
                'code'       => $row->code,
                'percentage' => (float) $row->avg_percentage,
                'color'      => $row->avg_percentage >= 80 ? '#10B981' : ($row->avg_percentage >= 50 ? '#F59E0B' : '#EF4444'),
            ]);

        // ── 4. Status breakdown ───────────────────────────────────────────────
        $statusRows = Assessment::whereNull('deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statusBreakdown = [
            'completed'   => (int) ($statusRows['completed']   ?? 0),
            'in_progress' => (int) ($statusRows['in_progress'] ?? 0),
            'draft'       => (int) ($statusRows['draft']       ?? 0),
        ];

        return compact('monthlyTrend', 'gradeDistribution', 'sectionAverages', 'statusBreakdown');
    }

    public function getFacilitiesReadiness(array $filters = []): Collection
    {
        $year           = $filters['year']            ?? null;
        $assessmentType = $filters['assessment_type'] ?? null;
        $countyId       = $filters['county_id']       ?? null;
        $subcountyId    = $filters['subcounty_id']    ?? null;
        $facilityId     = $filters['facility_id']     ?? null;

        $skillsLabSectionId = (int) AssessmentSection::where('code', 'skills_lab')->value('id');

        // Subquery: latest completed assessment date per facility
        $latestDates = DB::table('assessments')
            ->select('facility_id', DB::raw('MAX(assessment_date) as max_date'))
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessment_type', $assessmentType))
            ->when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
            ->when($subcountyId && !$facilityId, fn($q) => $q->whereIn(
                'facility_id',
                Facility::whereNull('deleted_at')->where('subcounty_id', $subcountyId)->pluck('id')
            ))
            ->when($countyId && !$subcountyId && !$facilityId, fn($q) => $q->whereIn(
                'facility_id',
                Facility::whereNull('deleted_at')
                    ->whereHas('subcounty', fn($s) => $s->where('county_id', $countyId))
                    ->pluck('id')
            ))
            ->groupBy('facility_id');

        // Resolve question IDs from codes (SKILLS_MASTER = dedicated lab, SKILLS_ROOM_SPACE = fallback room)
        $skillsMasterQId = (int) DB::table('assessment_questions')->where('question_code', 'SKILLS_MASTER')->value('id');
        $skillsRoomQId   = (int) DB::table('assessment_questions')->where('question_code', 'SKILLS_ROOM_SPACE')->value('id');

        $assessments = Assessment::query()
            ->joinSub($latestDates, 'latest', function ($join) {
                $join->on('assessments.facility_id', '=', 'latest.facility_id')
                     ->on('assessments.assessment_date', '=', 'latest.max_date');
            })
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->when($countyId && !$subcountyId && !$facilityId, fn($q) => $q->whereHas(
                'facility.subcounty', fn($s) => $s->where('county_id', $countyId)
            ))
            ->when($subcountyId && !$facilityId, fn($q) => $q->whereHas(
                'facility', fn($f) => $f->where('subcounty_id', $subcountyId)
            ))
            ->when($facilityId, fn($q) => $q->where('assessments.facility_id', $facilityId))
            ->with(['assessor', 'feedbackGivenBy', 'facility.facilityLevel', 'facility.subcounty.county'])
            ->addSelect([
                'assessments.*',
                DB::raw(
                    "(SELECT aqr.response_value FROM assessment_question_responses aqr
                      WHERE aqr.assessment_id = assessments.id
                      AND aqr.assessment_question_id = {$skillsMasterQId}
                      LIMIT 1) as skills_lab_answer"
                ),
                DB::raw(
                    "(SELECT aqr.response_value FROM assessment_question_responses aqr
                      WHERE aqr.assessment_id = assessments.id
                      AND aqr.assessment_question_id = {$skillsRoomQId}
                      LIMIT 1) as room_answer"
                ),
                DB::raw(
                    '(SELECT COUNT(*) FROM trainings t
                      WHERE t.facility_id = assessments.facility_id
                      AND t.type = "facility_mentorship"
                      AND t.deleted_at IS NULL
                      AND t.status IN ("active", "completed")
                      AND t.is_pilot = 0
                      AND EXISTS (
                          SELECT 1 FROM mentorship_classes mc
                          INNER JOIN class_participants cp ON cp.mentorship_class_id = mc.id
                          WHERE mc.training_id = t.id
                          AND cp.status IN ("enrolled", "active", "completed")
                      )) as mentorship_count'
                ),
            ])
            ->orderBy('assessments.assessment_date', 'desc')
            ->get()
            ->map(fn ($assessment) => $this->hydrateReadiness($assessment));

        return $assessments;
    }

    /**
     * Crosses skills-lab/room readiness against live mentorship presence
     * for each assessed facility's latest completed assessment (one row
     * per facility, sourced from getFacilitiesReadiness()):
     *
     *  - Skills lab/room + mentorship  -> good progress
     *  - Skills lab/room, no mentorship -> missed opportunity
     *  - No skills lab/room at all      -> urgent setup needed
     */
    public function summarizeSkillsLabMentorshipStatus(Collection $facilitiesReadiness): array
    {
        $goodProgress    = 0;
        $needsMentorship = 0;
        $needsSetup      = 0;

        foreach ($facilitiesReadiness as $assessment) {
            $hasFacility   = $assessment->has_skills_lab || $assessment->has_room;
            $hasMentorship = ($assessment->mentorship_count ?? 0) > 0;

            if (! $hasFacility) {
                $needsSetup++;
            } elseif ($hasMentorship) {
                $goodProgress++;
            } else {
                $needsMentorship++;
            }
        }

        $total = $facilitiesReadiness->count();
        $pct   = fn (int $n) => $total > 0 ? round(($n / $total) * 100, 1) : 0;

        return [
            'total'                  => $total,
            'goodProgress'           => $goodProgress,
            'needsMentorship'        => $needsMentorship,
            'needsSetup'             => $needsSetup,
            'goodProgressPercent'    => $pct($goodProgress),
            'needsMentorshipPercent' => $pct($needsMentorship),
            'needsSetupPercent'      => $pct($needsSetup),
        ];
    }

    private function hydrateReadiness(Assessment $assessment): Assessment
    {
        $hasSkillsLab     = strtolower($assessment->skills_lab_answer ?? '') === 'yes';
        $hasRoom          = strtolower($assessment->room_answer ?? '') === 'yes';
        $feedbackGiven    = (bool) $assessment->feedback_given;
        $hasPriorTraining = (bool) $assessment->trained_before_mentorship;

        $assessment->has_skills_lab     = $hasSkillsLab;
        $assessment->has_room           = $hasRoom;
        $assessment->has_prior_training = $hasPriorTraining;
        $hasFacility = $hasSkillsLab || $hasRoom;
        $assessment->eligibility_status = match (true) {
            $hasFacility && $feedbackGiven => 'eligible',
            $hasFacility                   => 'partial',
            default                        => 'not_eligible',
        };

        return $assessment;
    }
}
