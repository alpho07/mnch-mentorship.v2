<?php

namespace App\Filament\Widgets;

use App\Models\County;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Facility;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * This widget maintains backward compatibility while adding enhanced features
 */
class KenyaTrainingHeatmapWidget extends Widget
{
    protected static string $view = 'filament.widgets.enhanced-kenya-training-heatmap';

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Enhanced Training Coverage Across Kenya';

    // Filter properties
    public ?array $filterData = [];

    // Listen to filter changes
    protected $listeners = [
        'filtersUpdated' => 'updateFilters',
    ];

    public function mount()
    {
        // Initialize with empty filters
        $this->filterData = [
            'program_id' => [],
            'period' => [],
            'county_id' => [],
            'subcounty_id' => [],
            'facility_id' => [],
            'department_id' => null,
            'cadre_id' => null,
            'training_type' => [],
            'status' => [],
            'date_range' => null,
        ];
    }

    public function updateFilters($filterData = [])
    {
        $this->filterData = array_merge($this->filterData, $filterData ?: []);
    }

    private function getFilteredTrainings(): Builder
    {
        $query = Training::query();

        // Apply program filter (for many-to-many relationship)
        if (isset($this->filterData['program_id']) && !empty($this->filterData['program_id'])) {
            $query->whereHas('programs', function ($q) {
                $q->whereIn('programs.id', $this->filterData['program_id']);
            });
        }

        // Apply period filter
        if (isset($this->filterData['period']) && !empty($this->filterData['period'])) {
            $query->where(function ($q) {
                foreach ($this->filterData['period'] as $period) {
                    try {
                        $date = Carbon::createFromFormat('Y-m', $period);
                        $q->orWhereBetween('start_date', [
                            $date->startOfMonth(),
                            $date->endOfMonth()
                        ]);
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            });
        }

        // Apply training type filter
        if (isset($this->filterData['training_type']) && !empty($this->filterData['training_type'])) {
            $query->whereIn('type', $this->filterData['training_type']);
        }

        // Apply status filter
        if (isset($this->filterData['status']) && !empty($this->filterData['status'])) {
            $query->whereIn('status', $this->filterData['status']);
        }

        // Apply date range filter
        if (isset($this->filterData['date_range']) && !empty($this->filterData['date_range'])) {
            switch ($this->filterData['date_range']) {
                case 'last-6-months':
                    $query->where('start_date', '>=', now()->subMonths(6));
                    break;
                case '2024':
                    $query->whereYear('start_date', 2024);
                    break;
                case '2023':
                    $query->whereYear('start_date', 2023);
                    break;
            }
        }

        // Apply geographic filters
        if (isset($this->filterData['facility_id']) && !empty($this->filterData['facility_id'])) {
            $query->where(function ($q) {
                $q->whereIn('facility_id', $this->filterData['facility_id'])
                  ->orWhereHas('participants.user', function ($participantQuery) {
                      $participantQuery->whereIn('facility_id', $this->filterData['facility_id']);
                  });
            });
        } elseif (isset($this->filterData['subcounty_id']) && !empty($this->filterData['subcounty_id'])) {
            $query->where(function ($q) {
                $q->whereHas('facility', fn(Builder $subQ) => $subQ->whereIn('subcounty_id', $this->filterData['subcounty_id']))
                  ->orWhereHas('participants.user.facility', fn(Builder $subQ) => $subQ->whereIn('subcounty_id', $this->filterData['subcounty_id']));
            });
        } elseif (isset($this->filterData['county_id']) && !empty($this->filterData['county_id'])) {
            $query->where(function ($q) {
                $q->whereHas('facility.subcounty', fn(Builder $subQ) => $subQ->whereIn('county_id', $this->filterData['county_id']))
                  ->orWhereHas('participants.user.facility.subcounty', fn(Builder $subQ) => $subQ->whereIn('county_id', $this->filterData['county_id']));
            });
        }

        return $query;
    }

    public function getTrainingDataByCounty()
    {
        try {
            // Get all counties first
            $allCounties = County::all(['id', 'name']);

            if ($allCounties->isEmpty()) {
                return $this->getSampleCountyData();
            }

            // Initialize county data
            $countyStats = [];
            foreach ($allCounties as $county) {
                $countyStats[$county->name] = [
                    'name' => $county->name,
                    'total_trainings' => 0,
                    'total_participants' => 0,
                    'total_facilities' => 0,
                    'training_details' => []
                ];
            }

            // Get filtered GLOBAL trainings only
            $globalTrainings = $this->getFilteredTrainings()
                ->where('type', 'global_training')
                ->with([
                    'participants.user.facility.subcounty.county',
                    'programs'
                ])
                ->get();

            // Process each global training
            foreach ($globalTrainings as $training) {
                $this->processGlobalTrainingForCounties($training, $countyStats);
            }

            // Convert to final format and calculate metrics
            $finalCountyData = [];
            foreach ($countyStats as $countyName => $stats) {
                $finalCountyData[] = [
                    'name' => $countyName,
                    'trainings' => $stats['total_trainings'],
                    'participants' => $stats['total_participants'],
                    'facilities' => $stats['total_facilities'],
                    'intensity' => $this->calculateIntensity($stats['total_trainings'], $stats['total_participants'], $stats['total_facilities']),
                    'training_details' => $stats['training_details']
                ];
            }

            return collect($finalCountyData);

        } catch (\Exception $e) {
            \Log::error('Enhanced Heatmap data error: ' . $e->getMessage());
            return $this->getSampleCountyData();
        }
    }

    private function processGlobalTrainingForCounties($training, &$countyStats)
    {
        // Group participants by county
        $participantsByCounty = $training->participants
            ->filter(function ($participant) {
                return $participant->user && 
                       $participant->user->facility && 
                       $participant->user->facility->subcounty && 
                       $participant->user->facility->subcounty->county;
            })
            ->groupBy(function ($participant) {
                return $participant->user->facility->subcounty->county->name;
            });

        // Process each county that had participants in this training
        foreach ($participantsByCounty as $countyName => $countyParticipants) {
            if (isset($countyStats[$countyName])) {
                $participantCount = $countyParticipants->count();
                
                // Get unique facilities from this county that participated
                $uniqueFacilities = $countyParticipants
                    ->pluck('user.facility_id')
                    ->unique()
                    ->count();

                // Get facility names for display
                $facilityNames = $countyParticipants
                    ->pluck('user.facility.name')
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

                // Update county totals
                $countyStats[$countyName]['total_trainings']++;
                $countyStats[$countyName]['total_participants'] += $participantCount;
                $countyStats[$countyName]['total_facilities'] += $uniqueFacilities;

                // Store detailed training information
                $countyStats[$countyName]['training_details'][] = [
                    'training_title' => $training->title,
                    'training_id' => $training->identifier,
                    'facilities_count' => $uniqueFacilities,
                    'participants_count' => $participantCount,
                    'facility_names' => $facilityNames,
                    'programs' => $training->programs->pluck('name')->implode(', '),
                    'start_date' => $training->start_date?->format('M j, Y'),
                    'end_date' => $training->end_date?->format('M j, Y'),
                    'status' => ucfirst($training->status)
                ];
            }
        }
    }

    private function calculateIntensity($trainings, $participants, $facilities)
    {
        if ($trainings == 0) return 0;

        // Enhanced intensity calculation
        $trainingScore = $trainings * 2;
        $participantScore = ($participants / max(1, $trainings)) * 1.5;
        $facilityScore = $facilities * 3;

        return ($trainingScore + $participantScore + $facilityScore) / 6.5;
    }

    public function getIntensityLevels()
    {
        $data = $this->getTrainingDataByCounty();
        $maxIntensity = $data->max('intensity') ?: 100;

        return [
            'low' => $maxIntensity * 0.25,
            'medium' => $maxIntensity * 0.5,
            'high' => $maxIntensity * 0.75,
            'max' => $maxIntensity
        ];
    }

    public function getMapData()
    {
        $countyData = $this->getTrainingDataByCounty();

        return [
            'countyData' => $countyData,
            'intensityLevels' => $this->getIntensityLevels(),
            'totalTrainings' => $countyData->sum('trainings'),
            'totalParticipants' => $countyData->sum('participants'),
            'totalFacilities' => $countyData->sum('facilities'),
            'hasData' => $countyData->sum('trainings') > 0,
            'summary' => [
                'counties_with_training' => $countyData->filter(fn($c) => $c['trainings'] > 0)->count(),
                'avg_participants_per_training' => $countyData->sum('trainings') > 0 
                    ? round($countyData->sum('participants') / $countyData->sum('trainings'), 1) 
                    : 0,
                'top_counties' => $countyData->sortByDesc('intensity')->take(5)->pluck('name'),
                'completion_rate' => $this->calculateOverallCompletionRate(),
                'performance_metrics' => $this->getPerformanceMetrics(),
            ],
            'debug' => [
                'filterData' => $this->filterData,
                'dataCount' => $countyData->count(),
                'activeCounties' => $countyData->filter(fn($c) => $c['trainings'] > 0)->count()
            ]
        ];
    }

    private function calculateOverallCompletionRate(): float
    {
        $totalParticipants = TrainingParticipant::count();
        if ($totalParticipants === 0) return 0;

        $completedParticipants = TrainingParticipant::where('completion_status', 'completed')->count();
        return round(($completedParticipants / $totalParticipants) * 100, 1);
    }

    private function getPerformanceMetrics(): array
    {
        return [
            'total_mentorships' => Training::where('type', 'facility_mentorship')->count(),
            'average_score' => $this->getAverageScore(),
            'pass_rate' => $this->calculatePassRate(),
            'attrition_rate' => $this->calculateAttritionRate(),
        ];
    }

    private function getAverageScore(): float
    {
        // Safe method to get average score
        $scores = TrainingParticipant::whereNotNull('overall_score')
            ->where('overall_score', '>', 0)
            ->avg('overall_score');
        
        return round($scores ?: 0, 1);
    }

    private function calculatePassRate(): float
    {
        $totalAssessed = TrainingParticipant::whereNotNull('overall_score')
            ->where('overall_score', '>', 0)
            ->count();
            
        if ($totalAssessed === 0) return 0;

        $passed = TrainingParticipant::where('overall_score', '>=', 70)->count();
        return round(($passed / $totalAssessed) * 100, 1);
    }

    private function calculateAttritionRate(): float
    {
        // Safe calculation without requiring status logs
        $totalMentees = User::whereHas('trainingParticipations')->count();
        if ($totalMentees === 0) return 0;

        // Use a simple inactive status calculation
        $inactive = User::where('status', 'inactive')
            ->orWhere('status', 'resigned')
            ->orWhere('status', 'transferred')
            ->count();

        return round(($inactive / $totalMentees) * 100, 1);
    }

    private function getSampleCountyData()
    {
        // Enhanced sample data for testing when no real data exists
        $kenyanCounties = [
            'Nairobi', 'Mombasa', 'Kwale', 'Kilifi', 'Tana River', 'Lamu', 'Taita Taveta',
            'Garissa', 'Wajir', 'Mandera', 'Marsabit', 'Isiolo', 'Meru', 'Tharaka Nithi',
            'Embu', 'Kitui', 'Machakos', 'Makueni', 'Nyandarua', 'Nyeri', 'Kirinyaga',
            'Murang\'a', 'Kiambu', 'Turkana', 'West Pokot', 'Samburu', 'Trans Nzoia',
            'Uasin Gishu', 'Elgeyo Marakwet', 'Nandi', 'Baringo', 'Laikipia', 'Nakuru',
            'Narok', 'Kajiado', 'Kericho', 'Bomet', 'Kakamega', 'Vihiga', 'Bungoma',
            'Busia', 'Siaya', 'Kisumu', 'Homa Bay', 'Migori', 'Kisii', 'Nyamira'
        ];

        return collect($kenyanCounties)->map(function ($county) {
            $trainings = rand(0, 8);
            return [
                'name' => $county,
                'trainings' => $trainings,
                'participants' => $trainings > 0 ? rand(25, 200) : 0,
                'facilities' => $trainings > 0 ? rand(5, 25) : 0,
                'intensity' => $trainings > 0 ? rand(15, 100) : 0,
                'training_details' => []
            ];
        });
    }

    public function getAIInsights()
    {
        try {
            $countyData = $this->getTrainingDataByCounty();
            $totalCounties = $countyData->count();
            $activeCounties = $countyData->filter(fn($c) => $c['trainings'] > 0)->count();
            $coveragePercentage = $totalCounties > 0 ? round(($activeCounties / $totalCounties) * 100, 1) : 0;
            
            // Get performance data
            $performanceMetrics = $this->getPerformanceMetrics();
            
            // Get top and bottom performers
            $topPerformers = $countyData
                ->filter(fn($c) => $c['trainings'] > 0)
                ->sortByDesc('intensity')
                ->take(3)
                ->pluck('name')
                ->toArray();
            
            $bottomPerformers = $countyData
                ->filter(fn($c) => $c['trainings'] > 0)
                ->sortBy('intensity')
                ->take(3)
                ->pluck('name')
                ->toArray();
            
            $zeroTrainingCounties = $countyData
                ->filter(fn($c) => $c['trainings'] == 0)
                ->count();
            
            // Calculate participation efficiency
            $totalTrainings = $countyData->sum('trainings');
            $totalParticipants = $countyData->sum('participants');
            $avgParticipantsPerTraining = $totalTrainings > 0 ? round($totalParticipants / $totalTrainings, 1) : 0;
            
            // Generate enhanced insights
            $insights = [
                'coverage' => $this->generateEnhancedCoverageInsight($coveragePercentage, $zeroTrainingCounties, $activeCounties, $performanceMetrics),
                'participation' => $this->generateEnhancedParticipationInsight($topPerformers, $avgParticipantsPerTraining, $totalParticipants, $performanceMetrics),
                'recommendations' => $this->generateEnhancedRecommendations($bottomPerformers, $zeroTrainingCounties, $topPerformers, $performanceMetrics)
            ];
            
            return $insights;
            
        } catch (\Exception $e) {
            \Log::error('AI Insights generation error: ' . $e->getMessage());
            return [
                'coverage' => 'Enhanced training coverage analysis in progress. Drill down into counties for detailed insights.',
                'participation' => 'Advanced participation trend analysis with individual profiles being computed.',
                'recommendations' => 'Strategic recommendations with drill-down capabilities will be available shortly.'
            ];
        }
    }

    private function generateEnhancedCoverageInsight($coveragePercentage, $zeroTrainingCounties, $activeCounties, $performanceMetrics)
    {
        $baseInsight = '';
        
        if ($coveragePercentage >= 80) {
            $baseInsight = "Excellent coverage achieved at {$coveragePercentage}%! Only {$zeroTrainingCounties} counties remain without training programs.";
        } elseif ($coveragePercentage >= 60) {
            $baseInsight = "Good coverage at {$coveragePercentage}%, but {$zeroTrainingCounties} counties still need attention.";
        } elseif ($coveragePercentage >= 40) {
            $baseInsight = "Moderate coverage at {$coveragePercentage}%. {$zeroTrainingCounties} counties lack training programs.";
        } else {
            $baseInsight = "Low coverage at {$coveragePercentage}%. {$zeroTrainingCounties} counties without training programs need urgent intervention.";
        }

        $enhancement = " Click on counties to explore training programs, facilities, and individual participant profiles.";
        
        if ($performanceMetrics['average_score'] > 0) {
            $enhancement .= " Overall average score: {$performanceMetrics['average_score']}%.";
        }

        return $baseInsight . $enhancement;
    }

    private function generateEnhancedParticipationInsight($topPerformers, $avgParticipantsPerTraining, $totalParticipants, $performanceMetrics)
    {
        $performance = '';
        if (count($topPerformers) > 0) {
            $performance = "Top performing counties: " . implode(', ', $topPerformers) . ". ";
        }
        
        $engagementLevel = '';
        if ($avgParticipantsPerTraining >= 50) {
            $engagementLevel = "High engagement with {$avgParticipantsPerTraining} avg participants per training.";
        } elseif ($avgParticipantsPerTraining >= 30) {
            $engagementLevel = "Moderate engagement with {$avgParticipantsPerTraining} avg participants per training.";
        } else {
            $engagementLevel = "Low engagement with {$avgParticipantsPerTraining} avg participants per training.";
        }

        $enhancement = " Drill down to view individual participant profiles and training outcomes.";
        
        if ($performanceMetrics['pass_rate'] > 0) {
            $enhancement .= " Current pass rate: {$performanceMetrics['pass_rate']}%.";
        }

        return $performance . $engagementLevel . $enhancement;
    }

    private function generateEnhancedRecommendations($bottomPerformers, $zeroTrainingCounties, $topPerformers, $performanceMetrics)
    {
        $recommendations = [];
        
        if ($zeroTrainingCounties > 10) {
            $recommendations[] = "Immediate priority: Launch training programs in {$zeroTrainingCounties} underserved counties";
        } elseif ($zeroTrainingCounties > 0) {
            $recommendations[] = "Target {$zeroTrainingCounties} counties without training coverage";
        }
        
        if (count($bottomPerformers) > 0) {
            $recommendations[] = "Strengthen programs in: " . implode(', ', $bottomPerformers);
        }
        
        if (count($topPerformers) > 0) {
            $recommendations[] = "Leverage " . implode(', ', $topPerformers) . " as regional training hubs";
        }

        if ($performanceMetrics['attrition_rate'] > 15) {
            $recommendations[] = "Address high attrition rate of {$performanceMetrics['attrition_rate']}% through enhanced support";
        }

        if ($performanceMetrics['pass_rate'] < 70) {
            $recommendations[] = "Improve training quality to increase pass rate from {$performanceMetrics['pass_rate']}%";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Maintain current training quality and explore expansion opportunities";
        }

        $enhancement = " Use drill-down features to identify specific facilities and participants needing support.";
        
        return implode('. ', $recommendations) . '.' . $enhancement;
    }
}