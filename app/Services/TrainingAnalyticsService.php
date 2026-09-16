<?php

namespace App\Services;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingObjective;
use App\Models\ParticipantObjectiveResult;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\Facility;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TrainingAnalyticsService
{
    /**
     * Generate comprehensive training insights and recommendations
     */
    public function generateTrainingInsights(): array
    {
        return [
            'recommendations' => $this->generateRecommendations(),
            'performance_insights' => $this->generatePerformanceInsights(),
            'trend_analysis' => $this->generateTrendAnalysis(),
            'predictive_metrics' => $this->generatePredictiveMetrics(),
        ];
    }

    /**
     * Generate AI-powered recommendations
     */
    public function generateRecommendations(): array
    {
        $recommendations = [];

        // Recommendation 1: Departments with low participation
        $lowParticipationDepts = $this->identifyLowParticipationDepartments();
        if (!empty($lowParticipationDepts)) {
            $recommendations[] = [
                'type' => 'low_participation',
                'title' => 'Departments Need More Training',
                'description' => 'Consider organizing targeted training for departments with low participation rates.',
                'data' => $lowParticipationDepts,
                'priority' => 'high',
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => 'warning',
                'action_items' => [
                    'Schedule department-specific training sessions',
                    'Identify training champions in these departments',
                    'Review barriers to participation',
                ],
            ];
        }

        // Recommendation 2: Programs with low completion rates
        $lowCompletionPrograms = $this->identifyLowCompletionPrograms();
        if (!empty($lowCompletionPrograms)) {
            $recommendations[] = [
                'type' => 'low_completion',
                'title' => 'Programs Need Content Review',
                'description' => 'These programs have completion rates below 70% and may need content or delivery improvements.',
                'data' => $lowCompletionPrograms,
                'priority' => 'high',
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'danger',
                'action_items' => [
                    'Review program content and structure',
                    'Gather participant feedback',
                    'Consider shorter session formats',
                    'Improve assessment methods',
                ],
            ];
        }

        // Recommendation 3: Optimal scheduling opportunities
        $schedulingOpportunities = $this->identifySchedulingOpportunities();
        if (!empty($schedulingOpportunities)) {
            $recommendations[] = [
                'type' => 'scheduling',
                'title' => 'Optimal Training Schedule',
                'description' => $schedulingOpportunities['message'],
                'data' => $schedulingOpportunities,
                'priority' => 'medium',
                'icon' => 'heroicon-o-calendar-plus',
                'color' => 'info',
                'action_items' => [
                    'Plan trainings for identified optimal periods',
                    'Consider resource availability',
                    'Coordinate with departmental schedules',
                ],
            ];
        }

        // Recommendation 4: Mentorship expansion opportunities
        $mentorshipOpportunities = $this->identifyMentorshipOpportunities();
        if (!empty($mentorshipOpportunities)) {
            $recommendations[] = [
                'type' => 'mentorship',
                'title' => 'Expand Mentorship Programs',
                'description' => "Facilities without recent mentorship programs identified.",
                'data' => $mentorshipOpportunities,
                'priority' => 'medium',
                'icon' => 'heroicon-o-users',
                'color' => 'success',
                'action_items' => [
                    'Identify mentors in underserved facilities',
                    'Schedule facility visits',
                    'Develop facility-specific training plans',
                ],
            ];
        }

        // Recommendation 5: Certificate management
        $certificateAlerts = $this->identifyCertificateIssues();
        if (!empty($certificateAlerts)) {
            $recommendations[] = [
                'type' => 'certificates',
                'title' => 'Certificate Management Alert',
                'description' => $certificateAlerts['message'],
                'data' => $certificateAlerts,
                'priority' => 'high',
                'icon' => 'heroicon-o-trophy',
                'color' => 'warning',
                'action_items' => [
                    'Process pending certificate requests',
                    'Verify completion requirements',
                    'Update participant records',
                ],
            ];
        }

        return array_slice($recommendations, 0, 5); // Limit to top 5 recommendations
    }

    /**
     * Generate performance insights
     */
    public function generatePerformanceInsights(): array
    {
        $insights = [];

        // Best performing programs
        $bestPrograms = $this->identifyBestPerformingPrograms();
        if (!empty($bestPrograms)) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Top Performing Programs',
                'description' => 'These programs consistently achieve excellent results and can serve as best practice models.',
                'data' => $bestPrograms,
                'icon' => 'heroicon-o-trophy',
                'color' => 'success',
            ];
        }

        // Training frequency trends
        $frequencyTrend = $this->analyzeTrainingFrequencyTrend();
        $insights[] = [
            'type' => 'trend',
            'title' => 'Training Activity Trend',
            'description' => $frequencyTrend['description'],
            'data' => $frequencyTrend,
            'icon' => $frequencyTrend['direction'] === 'increasing' ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down',
            'color' => $frequencyTrend['direction'] === 'increasing' ? 'success' : 'warning',
        ];

        // Department engagement analysis
        $engagementAnalysis = $this->analyzeDepartmentEngagement();
        $insights[] = [
            'type' => 'engagement',
            'title' => 'Department Engagement Leaders',
            'description' => $engagementAnalysis['description'],
            'data' => $engagementAnalysis,
            'icon' => 'heroicon-o-star',
            'color' => 'info',
        ];

        // Skill gap analysis
        $skillGaps = $this->identifySkillGaps();
        if (!empty($skillGaps)) {
            $insights[] = [
                'type' => 'skill_gaps',
                'title' => 'Identified Skill Gaps',
                'description' => 'Analysis reveals skill areas needing attention across the organization.',
                'data' => $skillGaps,
                'icon' => 'heroicon-o-academic-cap',
                'color' => 'warning',
            ];
        }

        return $insights;
    }

    /**
     * Generate trend analysis
     */
    public function generateTrendAnalysis(): array
    {
        return [
            'participation_trends' => $this->analyzeParticipationTrends(),
            'completion_trends' => $this->analyzeCompletionTrends(),
            'performance_trends' => $this->analyzePerformanceTrends(),
            'geographic_trends' => $this->analyzeGeographicTrends(),
        ];
    }

    /**
     * Generate predictive metrics
     */
    public function generatePredictiveMetrics(): array
    {
        return [
            'projected_completion_rates' => $this->predictCompletionRates(),
            'resource_needs' => $this->predictResourceNeeds(),
            'optimal_timing' => $this->predictOptimalTiming(),
            'capacity_planning' => $this->predictCapacityNeeds(),
        ];
    }

    /**
     * Helper methods for recommendations
     */
    private function identifyLowParticipationDepartments(): array
    {
        $threshold = 5; // Minimum participations in last 6 months

        return Department::withCount([
            'trainingParticipants' => function ($query) {
                $query->where('registration_date', '>=', now()->subMonths(6));
            }
        ])
        ->having('training_participants_count', '<', $threshold)
        ->orderBy('training_participants_count')
        ->limit(5)
        ->get()
        ->map(function ($dept) {
            return [
                'name' => $dept->name,
                'participation_count' => $dept->training_participants_count,
                'last_training' => $this->getLastTrainingDate($dept),
            ];
        })
        ->toArray();
    }

    private function identifyLowCompletionPrograms(): array
    {
        return DB::table('trainings')
            ->join('programs', 'trainings.program_id', '=', 'programs.id')
            ->select('programs.name', 'programs.id')
            ->selectRaw('
                COUNT(DISTINCT trainings.id) as training_count,
                AVG(
                    (SELECT COUNT(*) FROM training_participants tp
                     WHERE tp.training_id = trainings.id AND tp.completion_status = "completed") * 100.0 /
                    NULLIF((SELECT COUNT(*) FROM training_participants tp WHERE tp.training_id = trainings.id), 0)
                ) as avg_completion_rate
            ')
            ->where('trainings.created_at', '>=', now()->subMonths(6))
            ->groupBy('programs.id', 'programs.name')
            ->havingRaw('avg_completion_rate < 70')
            ->havingRaw('training_count >= 2') // At least 2 trainings for statistical significance
            ->orderBy('avg_completion_rate')
            ->limit(5)
            ->get()
            ->map(function ($program) {
                return [
                    'name' => $program->name,
                    'completion_rate' => round($program->avg_completion_rate, 1),
                    'training_count' => $program->training_count,
                ];
            })
            ->toArray();
    }

    private function identifySchedulingOpportunities(): array
    {
        $upcomingTrainings = Training::where('start_date', '>', now())
            ->where('start_date', '<=', now()->addDays(30))
            ->count();

        $averageMonthlyTrainings = Training::where('created_at', '>=', now()->subMonths(6))
            ->count() / 6;

        if ($upcomingTrainings < ($averageMonthlyTrainings * 0.7)) {
            return [
                'message' => 'Consider scheduling more trainings for the next 30 days to maintain learning momentum.',
                'upcoming_count' => $upcomingTrainings,
                'recommended_count' => ceil($averageMonthlyTrainings),
                'gap' => ceil($averageMonthlyTrainings) - $upcomingTrainings,
            ];
        }

        return [];
    }

    private function identifyMentorshipOpportunities(): array
    {
        $facilitiesWithoutMentorship = Facility::whereDoesntHave('trainings', function ($query) {
            $query->where('type', 'facility_mentorship')
                  ->where('created_at', '>=', now()->subMonths(3));
        })
        ->with('subcounty.county')
        ->limit(10)
        ->get()
        ->map(function ($facility) {
            return [
                'name' => $facility->name,
                'county' => $facility->subcounty->county->name ?? 'Unknown',
                'last_mentorship' => $this->getLastMentorshipDate($facility),
            ];
        })
        ->toArray();

        return [
            'count' => count($facilitiesWithoutMentorship),
            'facilities' => $facilitiesWithoutMentorship,
        ];
    }

    private function identifyCertificateIssues(): array
    {
        $pendingCertificates = TrainingParticipant::where('completion_status', 'completed')
            ->where('certificate_issued', false)
            ->count();

        if ($pendingCertificates > 0) {
            $oldestPending = TrainingParticipant::where('completion_status', 'completed')
                ->where('certificate_issued', false)
                ->orderBy('completion_date')
                ->first();

            return [
                'message' => "{$pendingCertificates} participants have completed training but haven't received certificates.",
                'count' => $pendingCertificates,
                'oldest_pending_days' => $oldestPending ?
                    now()->diffInDays($oldestPending->completion_date) : 0,
            ];
        }

        return [];
    }

    /**
     * Helper methods for insights
     */
    private function identifyBestPerformingPrograms(): array
    {
        return DB::table('trainings')
            ->join('programs', 'trainings.program_id', '=', 'programs.id')
            ->select('programs.name')
            ->selectRaw('
                COUNT(DISTINCT trainings.id) as training_count,
                AVG(
                    (SELECT COUNT(*) FROM training_participants tp
                     WHERE tp.training_id = trainings.id AND tp.completion_status = "completed") * 100.0 /
                    NULLIF((SELECT COUNT(*) FROM training_participants tp WHERE tp.training_id = trainings.id), 0)
                ) as avg_completion_rate,
                AVG(
                    (SELECT AVG(final_score) FROM training_participants tp
                     WHERE tp.training_id = trainings.id AND tp.final_score IS NOT NULL)
                ) as avg_score
            ')
            ->where('trainings.created_at', '>=', now()->subMonths(6))
            ->groupBy('programs.id', 'programs.name')
            ->havingRaw('avg_completion_rate >= 85')
            ->havingRaw('training_count >= 2')
            ->orderByDesc('avg_completion_rate')
            ->limit(3)
            ->get()
            ->map(function ($program) {
                return [
                    'name' => $program->name,
                    'completion_rate' => round($program->avg_completion_rate, 1),
                    'average_score' => round($program->avg_score, 1),
                    'training_count' => $program->training_count,
                ];
            })
            ->toArray();
    }

    private function analyzeTrainingFrequencyTrend(): array
    {
        $monthlyData = Training::selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, COUNT(*) as count')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        if ($monthlyData->count() < 2) {
            return [
                'direction' => 'stable',
                'percentage' => 0,
                'description' => 'Insufficient data for trend analysis.',
            ];
        }

        $counts = $monthlyData->pluck('count')->toArray();
        $trend = $this->calculateTrend($counts);

        return [
            'direction' => $trend['direction'],
            'percentage' => $trend['percentage'],
            'description' => "Training creation is {$trend['direction']} by {$trend['percentage']}% over the last 6 months.",
            'monthly_data' => $monthlyData->toArray(),
        ];
    }

    private function analyzeDepartmentEngagement(): array
    {
        $topDepartment = Department::withCount('trainingParticipants')
            ->orderByDesc('training_participants_count')
            ->first();

        if (!$topDepartment) {
            return [
                'description' => 'No department engagement data available.',
                'top_department' => null,
            ];
        }

        return [
            'description' => "{$topDepartment->name} leads engagement with {$topDepartment->training_participants_count} participants.",
            'top_department' => [
                'name' => $topDepartment->name,
                'participants' => $topDepartment->training_participants_count,
            ],
        ];
    }

    /**
     * Helper methods for trend analysis
     */
    private function analyzeParticipationTrends(): array
    {
        return Cache::remember('participation_trends', 3600, function () {
            return DB::table('training_participants')
                ->selectRaw('DATE_FORMAT(registration_date, "%Y-%m") as month, COUNT(*) as participants')
                ->where('registration_date', '>=', now()->subMonths(12))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->toArray();
        });
    }

    private function analyzeCompletionTrends(): array
    {
        return Cache::remember('completion_trends', 3600, function () {
            return DB::table('training_participants')
                ->selectRaw('
                    DATE_FORMAT(registration_date, "%Y-%m") as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN completion_status = "completed" THEN 1 ELSE 0 END) as completed,
                    ROUND((SUM(CASE WHEN completion_status = "completed" THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as completion_rate
                ')
                ->where('registration_date', '>=', now()->subMonths(12))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->toArray();
        });
    }

    private function analyzePerformanceTrends(): array
    {
        return Cache::remember('performance_trends', 3600, function () {
            return DB::table('training_participants')
                ->selectRaw('
                    DATE_FORMAT(completion_date, "%Y-%m") as month,
                    AVG(final_score) as avg_score,
                    COUNT(*) as assessed_count
                ')
                ->whereNotNull('completion_date')
                ->whereNotNull('final_score')
                ->where('completion_date', '>=', now()->subMonths(12))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->toArray();
        });
    }

    private function analyzeGeographicTrends(): array
    {
        return Cache::remember('geographic_trends', 3600, function () {
            return DB::table('training_participants')
                ->join('facilities', 'training_participants.facility_id', '=', 'facilities.id')
                ->join('subcounties', 'facilities.subcounty_id', '=', 'subcounties.id')
                ->join('counties', 'subcounties.county_id', '=', 'counties.id')
                ->selectRaw('counties.name as county, COUNT(*) as participants')
                ->where('training_participants.registration_date', '>=', now()->subMonths(6))
                ->groupBy('counties.id', 'counties.name')
                ->orderByDesc('participants')
                ->limit(10)
                ->get()
                ->toArray();
        });
    }

    /**
     * Utility methods
     */
    private function calculateTrend(array $data): array
    {
        if (count($data) < 2) {
            return ['direction' => 'stable', 'percentage' => 0];
        }

        $firstHalf = array_slice($data, 0, ceil(count($data) / 2));
        $secondHalf = array_slice($data, floor(count($data) / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        if ($firstAvg == 0) {
            return ['direction' => 'increasing', 'percentage' => 100];
        }

        $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;

        return [
            'direction' => $change > 0 ? 'increasing' : 'decreasing',
            'percentage' => abs(round($change, 1)),
        ];
    }

    private function getLastTrainingDate(Department $department): ?string
    {
        $lastParticipation = $department->trainingParticipants()
            ->orderByDesc('registration_date')
            ->first();

        return $lastParticipation?->registration_date?->format('Y-m-d');
    }

    private function getLastMentorshipDate(Facility $facility): ?string
    {
        $lastMentorship = $facility->trainings()
            ->where('type', 'facility_mentorship')
            ->orderByDesc('created_at')
            ->first();

        return $lastMentorship?->created_at?->format('Y-m-d');
    }

    private function identifySkillGaps(): array
    {
        // This is a simplified skill gap analysis
        // In a real implementation, you might have more sophisticated skill mapping

        $programParticipation = DB::table('training_participants')
            ->join('trainings', 'training_participants.training_id', '=', 'trainings.id')
            ->join('programs', 'trainings.program_id', '=', 'programs.id')
            ->selectRaw('programs.name, COUNT(*) as participants')
            ->where('training_participants.registration_date', '>=', now()->subMonths(12))
            ->groupBy('programs.id', 'programs.name')
            ->orderBy('participants')
            ->limit(5)
            ->get();

        return $programParticipation->map(function ($program) {
            return [
                'skill_area' => $program->name,
                'participation_level' => 'low',
                'participants' => $program->participants,
            ];
        })->toArray();
    }

    private function predictCompletionRates(): array
    {
        // Simple prediction based on historical trends
        $historicalRates = $this->analyzeCompletionTrends();

        if (empty($historicalRates)) {
            return ['prediction' => 'insufficient_data'];
        }

        $recentRates = array_slice($historicalRates, -3); // Last 3 months
        $avgRecentRate = collect($recentRates)->avg('completion_rate');

        return [
            'predicted_rate' => round($avgRecentRate, 1),
            'confidence' => count($recentRates) >= 3 ? 'high' : 'low',
            'trend' => $this->calculateTrend(collect($recentRates)->pluck('completion_rate')->toArray()),
        ];
    }

    private function predictResourceNeeds(): array
    {
        $avgMonthlyParticipants = DB::table('training_participants')
            ->where('registration_date', '>=', now()->subMonths(6))
            ->count() / 6;

        return [
            'predicted_monthly_participants' => ceil($avgMonthlyParticipants),
            'recommended_trainers' => ceil($avgMonthlyParticipants / 20), // Assume 20 participants per trainer
            'estimated_training_days' => ceil($avgMonthlyParticipants / 15), // Assume 15 participants per day
        ];
    }

    private function predictOptimalTiming(): array
    {
        $monthlyData = DB::table('training_participants')
            ->selectRaw('MONTH(registration_date) as month, COUNT(*) as participants')
            ->where('registration_date', '>=', now()->subYear())
            ->groupBy('month')
            ->orderByDesc('participants')
            ->get();

        return [
            'peak_months' => $monthlyData->take(3)->pluck('month')->toArray(),
            'optimal_scheduling' => 'Schedule major trainings during months with historically high participation',
        ];
    }

    private function predictCapacityNeeds(): array
    {
        $currentCapacity = Training::where('status', 'ongoing')->sum('max_participants');
        $avgDemand = TrainingParticipant::where('registration_date', '>=', now()->subMonths(3))->count() / 3;

        return [
            'current_monthly_capacity' => $currentCapacity,
            'predicted_demand' => ceil($avgDemand),
            'capacity_utilization' => $currentCapacity > 0 ? round(($avgDemand / $currentCapacity) * 100, 1) : 0,
            'recommendation' => $avgDemand > $currentCapacity ? 'increase_capacity' : 'maintain_capacity',
        ];
    }
}
