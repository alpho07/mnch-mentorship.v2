<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Services\TrainingReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class TrainingInsightsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $reportService = new TrainingReportService();
        $metrics = $reportService->generateDashboardMetrics();

        return [
            Stat::make('Active Trainings', $metrics['active_trainings'])
                ->description('Currently ongoing')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success')
                ->chart($this->getActiveTrainingsChart()),

            Stat::make('Monthly Participants', $metrics['total_participants_this_month'])
                ->description($this->getParticipantTrend())
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Completion Rate', $metrics['completion_rate_this_month'] . '%')
                ->description('This month')
                ->descriptionIcon($metrics['completion_rate_this_month'] >= 75 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($metrics['completion_rate_this_month'] >= 75 ? 'success' : 'warning'),

            Stat::make('Certificates Issued', $metrics['certificates_issued_this_month'])
                ->description('This month')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('warning'),

            Stat::make('Upcoming Trainings', $metrics['upcoming_trainings'])
                ->description('Scheduled')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Monthly Completions', $metrics['trainings_completed_this_month'])
                ->description('Trainings completed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }

    private function getActiveTrainingsChart(): array
    {
        return Training::where('status', 'ongoing')
            ->selectRaw('DATE(start_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->limit(7)
            ->pluck('count')
            ->toArray();
    }

    private function getParticipantTrend(): string
    {
        $currentMonth = TrainingParticipant::whereMonth('registration_date', now()->month)->count();
        $lastMonth = TrainingParticipant::whereMonth('registration_date', now()->subMonth()->month)->count();

        if ($lastMonth === 0) {
            return 'New metric';
        }

        $change = (($currentMonth - $lastMonth) / $lastMonth) * 100;
        $changeText = $change > 0 ? "+{$change}%" : "{$change}%";

        return $change > 0 ? "↗ {$changeText} from last month" : "↘ {$changeText} from last month";
    }
}

// Training Performance Chart Widget
namespace App\Filament\Widgets;

use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TrainingPerformanceChart extends ChartWidget
{
    protected static ?string $heading = 'Training Performance Trends';
    protected static string $color = 'info';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // Get completion rates for the last 6 months
        $data = DB::table('training_participants')
            ->join('trainings', 'training_participants.training_id', '=', 'trainings.id')
            ->selectRaw("
                DATE_FORMAT(trainings.end_date, '%Y-%m') as month,
                COUNT(*) as total_participants,
                SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END) as completed,
                ROUND((SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as completion_rate
            ")
            ->where('trainings.end_date', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Completion Rate (%)',
                    'data' => $data->pluck('completion_rate')->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Total Participants',
                    'data' => $data->pluck('total_participants')->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $data->pluck('month')->map(function ($month) {
                return date('M Y', strtotime($month . '-01'));
            })->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Completion Rate (%)',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Total Participants',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}

// Recent Trainings Table Widget
namespace App\Filament\Widgets;

use App\Models\Training;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentTrainingsTable extends BaseWidget
{
    protected static ?string $heading = 'Recent Training Activities';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Training::query()
                    ->with(['program', 'facility', 'mentor', 'participants'])
                    ->latest('created_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Training')
                    ->weight('bold')
                    ->limit(30)
                    ->tooltip(fn (Training $record): string => $record->title)
                    ->description(fn (Training $record): string => $record->program?->name ?? 'No program'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'global_training' => 'success',
                        'facility_mentorship' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string =>
                        ucfirst(str_replace('_', ' ', $state))
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ongoing' => 'success',
                        'completed' => 'info',
                        'upcoming' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Participants')
                    ->counts('participants')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('completion_rate')
                    ->label('Progress')
                    ->getStateUsing(fn (Training $record): string =>
                        number_format($record->completion_rate, 1) . '%'
                    )
                    ->badge()
                    ->color(fn (Training $record): string => match(true) {
                        $record->completion_rate >= 90 => 'success',
                        $record->completion_rate >= 70 => 'warning',
                        default => 'danger'
                    }),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('mentor.full_name')
                    ->label('Mentor')
                    ->limit(20)
                    ->tooltip(fn (Training $record): string =>
                        $record->mentor?->full_name ?? 'No mentor assigned'
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Training $record): string =>
                        route('filament.admin.resources.trainings.view', $record)
                    )
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No recent trainings')
            ->emptyStateDescription('Training activities will appear here once created.')
            ->emptyStateIcon('heroicon-o-academic-cap');
    }
}

// Training Analytics Widget
namespace App\Filament\Widgets;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Department;
use App\Models\Cadre;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TrainingAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Training Distribution by Department';
    protected static string $color = 'warning';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $data = DB::table('training_participants')
            ->join('departments', 'training_participants.department_id', '=', 'departments.id')
            ->selectRaw('departments.name, COUNT(*) as count')
            ->groupBy('departments.name')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => [
                        '#ef4444', '#f97316', '#f59e0b', '#eab308',
                        '#84cc16', '#22c55e', '#10b981', '#06b6d4',
                    ],
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}

// AI-Powered Training Recommendations Widget
namespace App\Filament\Widgets;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Models\Department;
use App\Models\Program;
use Filament\Widgets\Widget;

class TrainingRecommendationsWidget extends Widget
{
    protected static string $view = 'filament.widgets.training-recommendations';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'recommendations' => $this->generateRecommendations(),
            'insights' => $this->generateInsights(),
        ];
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];

        // Recommendation 1: Departments with low participation
        $lowParticipationDepts = Department::withCount('trainingParticipants')
            ->having('training_participants_count', '<', 5)
            ->orderBy('training_participants_count')
            ->limit(3)
            ->get();

        if ($lowParticipationDepts->isNotEmpty()) {
            $recommendations[] = [
                'type' => 'low_participation',
                'title' => 'Departments Need More Training',
                'description' => 'Consider organizing targeted training for departments with low participation.',
                'departments' => $lowParticipationDepts->pluck('name')->toArray(),
                'priority' => 'high',
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => 'warning',
            ];
        }

        // Recommendation 2: Programs with low completion rates
        $lowCompletionPrograms = Training::with('program')
            ->selectRaw('program_id, AVG(
                (SELECT COUNT(*) FROM training_participants tp
                 WHERE tp.training_id = trainings.id AND tp.completion_status = "completed") * 100.0 /
                (SELECT COUNT(*) FROM training_participants tp WHERE tp.training_id = trainings.id)
            ) as avg_completion_rate')
            ->groupBy('program_id')
            ->havingRaw('avg_completion_rate < 70')
            ->orderBy('avg_completion_rate')
            ->limit(3)
            ->get();

        if ($lowCompletionPrograms->isNotEmpty()) {
            $recommendations[] = [
                'type' => 'low_completion',
                'title' => 'Programs Need Content Review',
                'description' => 'These programs have low completion rates and may need content or delivery improvements.',
                'programs' => $lowCompletionPrograms->pluck('program.name')->toArray(),
                'priority' => 'high',
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'danger',
            ];
        }

        // Recommendation 3: Upcoming optimal scheduling
        $upcomingTrainings = Training::where('start_date', '>', now())
            ->where('start_date', '<=', now()->addDays(30))
            ->count();

        if ($upcomingTrainings < 2) {
            $recommendations[] = [
                'type' => 'scheduling',
                'title' => 'Schedule More Trainings',
                'description' => 'Consider scheduling more trainings for the next 30 days to maintain learning momentum.',
                'priority' => 'medium',
                'icon' => 'heroicon-o-calendar-plus',
                'color' => 'info',
            ];
        }

        // Recommendation 4: Mentorship opportunities
        $facilitiesWithoutMentorship = \App\Models\Facility::whereDoesntHave('trainings', function ($query) {
            $query->where('type', 'facility_mentorship')
                  ->where('created_at', '>=', now()->subMonths(3));
        })->count();

        if ($facilitiesWithoutMentorship > 0) {
            $recommendations[] = [
                'type' => 'mentorship',
                'title' => 'Expand Mentorship Programs',
                'description' => "{$facilitiesWithoutMentorship} facilities haven't had mentorship programs in the last 3 months.",
                'priority' => 'medium',
                'icon' => 'heroicon-o-users',
                'color' => 'success',
            ];
        }

        // Recommendation 5: Certificate follow-up
        $pendingCertificates = TrainingParticipant::where('completion_status', 'completed')
            ->where('certificate_issued', false)
            ->count();

        if ($pendingCertificates > 0) {
            $recommendations[] = [
                'type' => 'certificates',
                'title' => 'Issue Pending Certificates',
                'description' => "{$pendingCertificates} participants have completed training but haven't received certificates.",
                'priority' => 'high',
                'icon' => 'heroicon-o-trophy',
                'color' => 'warning',
            ];
        }

        return array_slice($recommendations, 0, 4); // Limit to top 4 recommendations
    }

    private function generateInsights(): array
    {
        $insights = [];

        // Insight 1: Best performing programs
        $bestPrograms = Training::with('program')
            ->selectRaw('program_id, AVG(
                (SELECT COUNT(*) FROM training_participants tp
                 WHERE tp.training_id = trainings.id AND tp.completion_status = "completed") * 100.0 /
                (SELECT COUNT(*) FROM training_participants tp WHERE tp.training_id = trainings.id)
            ) as avg_completion_rate')
            ->groupBy('program_id')
            ->havingRaw('avg_completion_rate >= 85')
            ->orderByDesc('avg_completion_rate')
            ->limit(2)
            ->get();

        if ($bestPrograms->isNotEmpty()) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Top Performing Programs',
                'description' => 'These programs have excellent completion rates and can serve as models.',
                'data' => $bestPrograms->map(fn($p) => [
                    'name' => $p->program?->name ?? 'Unknown',
                    'rate' => round($p->avg_completion_rate, 1) . '%'
                ])->toArray(),
                'icon' => 'heroicon-o-trophy',
                'color' => 'success',
            ];
        }

        // Insight 2: Training frequency trends
        $monthlyTrainings = Training::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $trend = $this->calculateTrend($monthlyTrainings->pluck('count')->toArray());

        $insights[] = [
            'type' => 'trend',
            'title' => 'Training Frequency Trend',
            'description' => "Training creation is {$trend['direction']} by {$trend['percentage']}% over the last 6 months.",
            'icon' => $trend['direction'] === 'increasing' ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down',
            'color' => $trend['direction'] === 'increasing' ? 'success' : 'warning',
        ];

        // Insight 3: Department engagement
        $mostEngagedDept = Department::withCount('trainingParticipants')
            ->orderByDesc('training_participants_count')
            ->first();

        if ($mostEngagedDept) {
            $insights[] = [
                'type' => 'engagement',
                'title' => 'Most Engaged Department',
                'description' => "{$mostEngagedDept->name} leads with {$mostEngagedDept->training_participants_count} participants.",
                'icon' => 'heroicon-o-star',
                'color' => 'info',
            ];
        }

        return $insights;
    }

    private function calculateTrend(array $data): array
    {
        if (count($data) < 2) {
            return ['direction' => 'stable', 'percentage' => 0];
        }

        $first = array_slice($data, 0, ceil(count($data) / 2));
        $second = array_slice($data, floor(count($data) / 2));

        $firstAvg = array_sum($first) / count($first);
        $secondAvg = array_sum($second) / count($second);

        if ($firstAvg == 0) {
            return ['direction' => 'increasing', 'percentage' => 100];
        }

        $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;

        return [
            'direction' => $change > 0 ? 'increasing' : 'decreasing',
            'percentage' => abs(round($change, 1)),
        ];
    }
}

// Smart Participant Suggestions Service
namespace App\Services;

use App\Models\User;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\Facility;
use Illuminate\Support\Collection;

class SmartParticipantSuggestionService
{
    public function suggestParticipants(Training $training, int $limit = 20): Collection
    {
        $suggestions = collect();

        // Get users who haven't participated in this training
        $excludeUserIds = $training->participants()->pluck('user_id')->toArray();

        $query = User::whereNotIn('id', $excludeUserIds)
            ->where('status', 'active')
            ->with(['facility', 'department', 'cadre']);

        // If it's facility mentorship, only suggest users from that facility
        if ($training->isFacilityMentorship() && $training->facility_id) {
            $query->where('facility_id', $training->facility_id);
        }

        // Filter by target departments if specified
        if ($training->departments()->exists()) {
            $departmentIds = $training->departments()->pluck('department_id')->toArray();
            $query->whereIn('department_id', $departmentIds);
        }

        $candidateUsers = $query->get();

        // Score and rank candidates
        foreach ($candidateUsers as $user) {
            $score = $this->calculateUserScore($user, $training);

            if ($score > 0) {
                $suggestions->push([
                    'user' => $user,
                    'score' => $score,
                    'reasons' => $this->getSelectionReasons($user, $training),
                ]);
            }
        }

        return $suggestions->sortByDesc('score')->take($limit);
    }

    private function calculateUserScore(User $user, Training $training): float
    {
        $score = 0;

        // Base score for active users
        $score += 10;

        // Bonus for target department match
        if ($training->departments()->where('department_id', $user->department_id)->exists()) {
            $score += 20;
        }

        // Bonus for users with limited training history (encourage broader participation)
        $trainingCount = $user->trainingParticipations()->count();
        if ($trainingCount == 0) {
            $score += 15; // New to training
        } elseif ($trainingCount < 3) {
            $score += 10; // Limited experience
        }

        // Bonus for users who completed previous trainings successfully
        $completedTrainings = $user->trainingParticipations()
            ->where('completion_status', 'completed')
            ->count();

        if ($completedTrainings > 0) {
            $score += min($completedTrainings * 5, 15); // Up to 15 bonus points
        }

        // Penalty for recent training participation (avoid overloading)
        $recentParticipations = $user->trainingParticipations()
            ->where('registration_date', '>=', now()->subMonth())
            ->count();

        if ($recentParticipations > 2) {
            $score -= 10;
        }

        // Bonus for specific cadres that might benefit from this program
        if ($this->isCadreRelevantToProgram($user->cadre, $training->program)) {
            $score += 15;
        }

        // Geographic proximity bonus (for global trainings)
        if ($training->isGlobalTraining() && $training->facility_id && $user->facility_id) {
            if ($this->areNearbyFacilities($user->facility, $training->facility)) {
                $score += 10;
            }
        }

        return max(0, $score);
    }

    private function getSelectionReasons(User $user, Training $training): array
    {
        $reasons = [];

        if ($training->departments()->where('department_id', $user->department_id)->exists()) {
            $reasons[] = 'Target department match';
        }

        $trainingCount = $user->trainingParticipations()->count();
        if ($trainingCount == 0) {
            $reasons[] = 'New to training programs';
        } elseif ($trainingCount < 3) {
            $reasons[] = 'Limited training history';
        }

        $completedCount = $user->trainingParticipations()
            ->where('completion_status', 'completed')
            ->count();

        if ($completedCount > 0) {
            $reasons[] = "Successfully completed {$completedCount} training(s)";
        }

        if ($this->isCadreRelevantToProgram($user->cadre, $training->program)) {
            $reasons[] = 'Relevant professional background';
        }

        if (empty($reasons)) {
            $reasons[] = 'Active staff member';
        }

        return $reasons;
    }

    private function isCadreRelevantToProgram($cadre, $program): bool
    {
        if (!$cadre || !$program) return false;

        // Define program-cadre relevance mapping
        $relevanceMap = [
            'clinical' => ['nurse', 'clinical officer', 'doctor', 'midwife'],
            'laboratory' => ['lab technician', 'lab technologist', 'pathologist'],
            'pharmacy' => ['pharmacist', 'pharmaceutical technician'],
            'management' => ['manager', 'supervisor', 'administrator'],
        ];

        $programName = strtolower($program->name);
        $cadreName = strtolower($cadre->name);

        foreach ($relevanceMap as $programType => $relevantCadres) {
            if (str_contains($programName, $programType)) {
                foreach ($relevantCadres as $relevantCadre) {
                    if (str_contains($cadreName, $relevantCadre)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function areNearbyFacilities($facility1, $facility2): bool
    {
        if (!$facility1 || !$facility2) return false;

        // Same subcounty = nearby
        return $facility1->subcounty_id === $facility2->subcounty_id;
    }
}
             