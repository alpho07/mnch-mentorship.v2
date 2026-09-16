<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProgressiveDashboardController extends Controller {

    public function index() {
        return view('analytics.progressive-dashboard.index');
    }

    /**
     * Get available years for filtering
     */
    public function getAvailableYears(Request $request) {
        $type = $request->get('type', 'global_training');

        $years = Training::where('type', $type)
                ->selectRaw('DISTINCT YEAR(start_date) as year')
                ->whereNotNull('start_date')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        return response()->json($years->values());
    }

    /**
     * Level 0: National Overview
     */
    public function getNationalOverview(Request $request) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', 'all');

        $cacheKey = "progressive_national_{$type}_{$year}";

        return Cache::remember($cacheKey, 1800, function () use ($type, $year) {
            // Get all counties with their coverage data
            $counties = County::with(['subcounties.facilities'])
                    ->get()
                    ->map(function ($county) use ($type, $year) {
                        $coverage = $this->calculateCountyCoverage($county, $type, $year);

                        return [
                            'id' => $county->id,
                            'name' => $county->name,
                            'total_facilities' => $coverage['total_facilities'],
                            'covered_facilities' => $coverage['covered_facilities'],
                            'coverage_percentage' => $coverage['coverage_percentage'],
                            'participant_count' => $coverage['participant_count'],
                            'program_count' => $coverage['program_count'],
                            'intensity' => $this->calculateIntensity($coverage),
                            'trend' => $this->calculateCountyTrend($county, $type, $year),
                            'priority' => $this->calculatePriority($coverage['total_facilities'], $coverage['coverage_percentage']),
                            'coordinates' => $this->getCountyCoordinates($county)
                        ];
                    });

            // Calculate national summary
            $nationalSummary = [
                'total_counties' => $counties->count(),
                'covered_counties' => $counties->where('coverage_percentage', '>', 0)->count(),
                'total_facilities' => $counties->sum('total_facilities'),
                'covered_facilities' => $counties->sum('covered_facilities'),
                'total_participants' => $counties->sum('participant_count'),
                'total_programs' => $counties->sum('program_count'),
                'average_coverage' => $counties->avg('coverage_percentage')
            ];

            // Generate insights
            $insights = $this->generateNationalInsights($counties, $type);

            return response()->json([
                        'counties' => $counties->values(),
                        'national_summary' => $nationalSummary,
                        'insights' => $insights,
                        'metadata' => [
                            'type' => $type,
                            'year' => $year,
                            'year_display' => $this->getYearDisplayText($year),
                            'is_all_years' => $year === 'all',
                            'generated_at' => now(),
                            'cache_duration' => 1800
                        ]
            ]);
        });
    }

    /**
     * Level 1: County Analysis
     */
    public function getCountyAnalysis(Request $request, $countyId) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', 'all');

        $county = County::with(['subcounties.facilities.facilityType'])->findOrFail($countyId);

        $cacheKey = "progressive_county_{$countyId}_{$type}_{$year}";

        return Cache::remember($cacheKey, 1800, function () use ($county, $type, $year) {

            // Coverage metrics
            $coverage = $this->calculateCountyCoverage($county, $type, $year);

            // Facility type breakdown
            $facilityTypes = $this->getFacilityTypeBreakdown($county, $type, $year);

            // Department analysis
            $departments = $this->getDepartmentAnalysis($county, $type, $year);

            // Cadre analysis
            $cadres = $this->getCadreAnalysis($county, $type, $year);

            // Geographic facility distribution
            $facilities = $this->getFacilityDistribution($county, $type, $year);

            // Generate insights and recommendations
            $insights = $this->generateCountyInsights($county, $facilityTypes, $departments, $type);
            $recommendedActions = $this->getRecommendedActions($county, $facilityTypes, $departments, $type);

            return response()->json([
                        'county' => [
                            'id' => $county->id,
                            'name' => $county->name,
                            'subcounties_count' => $county->subcounties->count(),
                            'facilities_count' => $county->facilities()->count()
                        ],
                        'coverage' => $coverage,
                        'facility_types' => $facilityTypes,
                        'departments' => $departments,
                        'cadres' => $cadres,
                        'facilities' => $facilities,
                        'insights' => $insights,
                        'recommended_actions' => $recommendedActions,
                        'metadata' => [
                            'type' => $type,
                            'year' => $year,
                            'year_display' => $this->getYearDisplayText($year),
                            'generated_at' => now(),
                            'is_all_years' => $year === 'all',
                        ]
            ]);
        });
    }

    /**
     * Level 2: Facility Type Analysis
     */
    public function getFacilityTypeAnalysis(Request $request, $countyId, $facilityTypeId) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', 'all');

        $county = County::findOrFail($countyId);
        $facilityType = FacilityType::findOrFail($facilityTypeId);

        $cacheKey = "progressive_facility_type_{$countyId}_{$facilityTypeId}_{$type}_{$year}";

        return Cache::remember($cacheKey, 900, function () use ($county, $facilityType, $type, $year) {

            // Get facilities of this type in the county
            $facilities = Facility::where('facility_type_id', $facilityType->id)
                    ->whereHas('subcounty', function ($query) use ($county) {
                        $query->where('county_id', $county->id);
                    })
                    ->with(['subcounty', 'users.department', 'users.cadre'])
                    ->get()
                    ->map(function ($facility) use ($type, $year) {
                        $coverage = $this->calculateFacilityCoverage($facility, $type, $year);

                        return [
                            'id' => $facility->id,
                            'name' => $facility->name,
                            'mfl_code' => $facility->mfl_code,
                            'subcounty' => $facility->subcounty->name,
                            'coordinates' => $this->getFacilityCoordinates($facility),
                            'is_covered' => $coverage['is_covered'],
                            'participant_count' => $coverage['participant_count'],
                            'program_count' => $coverage['program_count'],
                            'departments' => $coverage['departments'],
                            'completion_rate' => $coverage['completion_rate'],
                            'coverage_score' => $this->calculateCoverageScore($coverage)
                        ];
                    });

            // Calculate summary metrics
            $summary = [
                'total' => $facilities->count(),
                'covered' => $facilities->where('is_covered', true)->count(),
                'total_participants' => $facilities->sum('participant_count'),
                'total_programs' => $facilities->sum('program_count'),
                'average_completion_rate' => $facilities->where('is_covered', true)->avg('completion_rate')
            ];

            // Generate insights
            $insights = $this->generateFacilityTypeInsights($facilities, $facilityType->name, $type);

            return response()->json([
                        'facility_type' => [
                            'id' => $facilityType->id,
                            'name' => $facilityType->name
                        ],
                        'county' => [
                            'id' => $county->id,
                            'name' => $county->name
                        ],
                        'facilities' => $facilities->values(),
                        'summary' => $summary,
                        'insights' => $insights,
                        'metadata' => [
                            'type' => $type,
                            'year' => $year,
                            'generated_at' => now()
                        ]
            ]);
        });
    }

    /**
     * Level 3: Individual Facility Analysis
     */
    public function getFacilityAnalysis(Request $request, $facilityId) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', 'all');

        $facility = Facility::with([
                    'facilityType',
                    'subcounty.county',
                    'users.department',
                    'users.cadre',
                    'trainings'
                ])->findOrFail($facilityId);

        $cacheKey = "progressive_facility_{$facilityId}_{$type}_{$year}";

        return Cache::remember($cacheKey, 600, function () use ($facility, $type, $year) {

            // Participant analysis
            $participants = $this->getFacilityParticipants($facility, $type, $year);

            // Training program history
            $trainingHistory = $this->getTrainingHistory($facility, $type, $year);

            // Performance metrics
            $performance = $this->getFacilityPerformanceMetrics($facility, $type, $year);

            // Department and cadre breakdowns
            $departmentBreakdown = $this->getDepartmentBreakdown($participants);
            $cadreBreakdown = $this->getCadreBreakdown($participants);

            // Generate insights
            $insights = $this->generateFacilityInsights($facility, $participants, $performance, $type);

            return response()->json([
                        'facility' => [
                            'id' => $facility->id,
                            'name' => $facility->name,
                            'type' => $facility->facilityType->name,
                            'mfl_code' => $facility->mfl_code,
                            'county' => $facility->subcounty->county->name,
                            'subcounty' => $facility->subcounty->name,
                            'coordinates' => $this->getFacilityCoordinates($facility)
                        ],
                        'participants' => $participants,
                        'training_history' => $trainingHistory,
                        'performance' => $performance,
                        'department_breakdown' => $departmentBreakdown,
                        'cadre_breakdown' => $cadreBreakdown,
                        'insights' => $insights,
                        'metadata' => [
                            'type' => $type,
                            'year' => $year,
                            'generated_at' => now()
                        ]
            ]);
        });
    }

    /**
     * Level 4: Individual Participant Profile
     */
    public function getParticipantProfile(Request $request, $participantId) {
        $participant = TrainingParticipant::with([
                    'user.facility.facilityType',
                    'user.facility.subcounty.county',
                    'user.department',
                    'user.cadre',
                    'training',
                    'assessmentResults.assessmentCategory',
                    'statusLogs'
                ])->findOrFail($participantId);

        return response()->json([
                    'participant' => [
                        'id' => $participant->id,
                        'user' => [
                            'id' => $participant->user->id,
                            'name' => $participant->user->full_name,
                            'phone' => $participant->user->phone,
                            'email' => $participant->user->email,
                            'department' => $participant->user->department->name ?? 'Unknown',
                            'cadre' => $participant->user->cadre->name ?? 'Unknown',
                            'facility' => [
                                'name' => $participant->user->facility->name,
                                'type' => $participant->user->facility->facilityType->name,
                                'county' => $participant->user->facility->subcounty->county->name
                            ]
                        ],
                        'training' => [
                            'title' => $participant->training->title,
                            'type' => $participant->training->type,
                            'start_date' => $participant->training->start_date,
                            'end_date' => $participant->training->end_date
                        ],
                        'participation' => [
                            'registration_date' => $participant->registration_date,
                            'attendance_status' => $participant->attendance_status,
                            'completion_status' => $participant->completion_status,
                            'completion_date' => $participant->completion_date
                        ]
                    ],
                    'training_history' => $this->getParticipantTrainingHistory($participant->user),
                    'status_logs' => $participant->statusLogs()->latest()->get(),
                    'assessment_summary' => $this->getAssessmentSummary($participant),
                    'performance_metrics' => $this->getParticipantPerformanceMetrics($participant)
        ]);
    }

    // Private helper methods

    private function calculateCountyCoverage($county, $type, $year) {
        $baseQuery = $county->facilities();
        $totalFacilities = $baseQuery->count();

        if ($totalFacilities === 0) {
            return [
                'coverage_percentage' => 0,
                'participant_count' => 0,
                'covered_facilities' => 0,
                'total_facilities' => 0,
                'program_count' => 0
            ];
        }

        if ($type === 'global_training') {
            // For global training, participants are linked through user -> facility
            $participantQuery = TrainingParticipant::whereHas('user.facility.subcounty', function ($query) use ($county) {
                        $query->where('county_id', $county->id);
                    })->whereHas('training', function ($query) use ($type, $year) {
                $query->where('type', $type);
                if ($year !== 'all') {
                    $query->whereYear('start_date', $year);
                }
            });

            $participantCount = $participantQuery->count();
            $coveredFacilities = $participantQuery->with('user.facility')
                    ->get()
                    ->pluck('user.facility.id')
                    ->unique()
                    ->count();

            $programCount = Training::where('type', $type)
                    ->when($year !== 'all', function ($query) use ($year) {
                        $query->whereYear('start_date', $year);
                    })
                    ->whereHas('participants.user.facility.subcounty', function ($query) use ($county) {
                        $query->where('county_id', $county->id);
                    })
                    ->count();
        } else {
            // For facility mentorship, trainings are directly at facilities
            $participantQuery = TrainingParticipant::whereHas('training.facility.subcounty', function ($query) use ($county) {
                        $query->where('county_id', $county->id);
                    })->whereHas('training', function ($query) use ($type, $year) {
                $query->where('type', $type);
                if ($year !== 'all') {
                    $query->whereYear('start_date', $year);
                }
            });

            $participantCount = $participantQuery->count();
            $coveredFacilities = $participantQuery->with('training.facility')
                    ->get()
                    ->pluck('training.facility.id')
                    ->unique()
                    ->count();

            $programCount = Training::where('type', $type)
                    ->when($year !== 'all', function ($query) use ($year) {
                        $query->whereYear('start_date', $year);
                    })
                    ->whereHas('facility.subcounty', function ($query) use ($county) {
                        $query->where('county_id', $county->id);
                    })
                    ->count();
        }

        $coveragePercentage = $totalFacilities > 0 ?
                round(($coveredFacilities / $totalFacilities) * 100, 1) : 0;

        return [
            'coverage_percentage' => $coveragePercentage,
            'participant_count' => $participantCount,
            'covered_facilities' => $coveredFacilities,
            'total_facilities' => $totalFacilities,
            'program_count' => $programCount
        ];
    }

    private function calculateFacilityCoverage($facility, $type, $year) {
        if ($type === 'global_training') {
            $participantQuery = $facility->users()
                    ->whereHas('trainingParticipations.training', function ($query) use ($type, $year) {
                        $query->where('type', $type);
                        if ($year !== 'all') {
                            $query->whereYear('start_date', $year);
                        }
                    });
            $participantCount = $participantQuery->distinct()->count();

            $programQuery = Training::where('type', $type);
            if ($year !== 'all') {
                $programQuery->whereYear('start_date', $year);
            }
            $programCount = $programQuery->whereHas('participants.user', function ($query) use ($facility) {
                        $query->where('facility_id', $facility->id);
                    })->count();

            $completedQuery = $facility->users()
                    ->whereHas('trainingParticipations', function ($query) use ($type, $year) {
                        $query->whereHas('training', function ($q) use ($type, $year) {
                            $q->where('type', $type);
                            if ($year !== 'all') {
                                $q->whereYear('start_date', $year);
                            }
                        })->where('completion_status', 'completed');
                    });
            $completedCount = $completedQuery->count();
        } else {
            $participantQuery = TrainingParticipant::whereHas('training', function ($query) use ($facility, $type, $year) {
                $query->where('type', $type)
                        ->where('facility_id', $facility->id);
                if ($year !== 'all') {
                    $query->whereYear('start_date', $year);
                }
            });
            $participantCount = $participantQuery->distinct('user_id')->count();

            $programQuery = $facility->trainings()
                    ->where('type', $type);
            if ($year !== 'all') {
                $programQuery->whereYear('start_date', $year);
            }
            $programCount = $programQuery->count();

            $completedQuery = TrainingParticipant::whereHas('training', function ($query) use ($facility, $type, $year) {
                $query->where('type', $type)
                        ->where('facility_id', $facility->id);
                if ($year !== 'all') {
                    $query->whereYear('start_date', $year);
                }
            });
            $completedCount = $completedQuery->where('completion_status', 'completed')->count();
        }

        $completionRate = $participantCount > 0 ? round(($completedCount / $participantCount) * 100, 1) : 0;

        return [
            'is_covered' => $participantCount > 0 || $programCount > 0,
            'participant_count' => $participantCount,
            'program_count' => $programCount,
            'completion_rate' => $completionRate,
            'departments' => $this->getFacilityDepartmentBreakdown($facility, $type, $year)
        ];
    }

    private function getFacilityTypeBreakdown($county, $type, $year) {
        
        return FacilityType::withCount([
                            'facilities as total_in_county' => function ($query) use ($county) {
                                $query->whereHas('subcounty', function ($q) use ($county) {
                                    $q->where('county_id', $county->id);
                                });
                            }
                        ])
                        ->get()
                        ->filter(function ($facilityType) {
                            return $facilityType->total_in_county > 0;
                        })
                        ->map(function ($facilityType) use ($county, $type, $year) {
                            // Calculate covered facilities for this type
                            $coveredCount = 0;

                            if ($type === 'global_training') {
                                $coveredCount = Facility::where('facility_type_id', $facilityType->id)
                                        ->whereHas('subcounty', function ($q) use ($county) {
                                            $q->where('county_id', $county->id);
                                        })
                                        ->whereHas('users.trainingParticipations.training', function ($q) use ($type, $year) {
                                            $q->where('type', $type);
                                            if ($year !== 'all') {
                                                $q->whereYear('start_date', $year);
                                            }
                                        })
                                        ->count();
                            } else {
                                $coveredCount = Facility::where('facility_type_id', $facilityType->id)
                                        ->whereHas('subcounty', function ($q) use ($county) {
                                            $q->where('county_id', $county->id);
                                        })
                                        ->whereHas('trainings', function ($q) use ($type, $year) {
                                            $q->where('type', $type);
                                            if ($year !== 'all') {
                                                $q->whereYear('start_date', $year);
                                            }
                                        })
                                        ->count();
                            }

                            $coverage = $facilityType->total_in_county > 0 ? round(($coveredCount / $facilityType->total_in_county) * 100, 1) : 0;

                            return [
                                'id' => $facilityType->id,
                                'name' => $facilityType->name,
                                'total' => $facilityType->total_in_county,
                                'covered' => $coveredCount,
                                'uncovered' => $facilityType->total_in_county - $coveredCount,
                                'coverage_percentage' => $coverage,
                                'priority' => $this->calculatePriority($facilityType->total_in_county, $coverage)
                            ];
                        })
                        ->sortByDesc('coverage_percentage')
                        ->values();
    }

    private function getDepartmentAnalysis($county, $type, $year) {
        $facilities = $county->facilities()->pluck('facilities.id'); 

        return Department::whereHas('users', function ($query) use ($facilities) {
                            $query->whereIn('facility_id', $facilities); 
                        })
                        ->withCount([
                            'users as total_staff' => function ($query) use ($facilities) {
                                $query->whereIn('facility_id', $facilities);
                            },
                            'users as trained_staff' => function ($query) use ($facilities, $type, $year) {
                                $query->whereIn('facility_id', $facilities)
                                        ->whereHas('trainingParticipations.training', function ($q) use ($type, $year) {
                                            $q->where('type', $type);
                                            if ($year !== 'all') { 
                                                $q->whereYear('start_date', $year);
                                            }
                                        });
                            }
                        ])
                        ->get()
                        ->filter(function ($dept) {
                            return $dept->total_staff > 0;
                        })
                        ->map(function ($department) {
                            $coverage = $department->total_staff > 0 ?
                                    round(($department->trained_staff / $department->total_staff) * 100, 1) : 0;

                            return [
                                'name' => $department->name,
                                'total' => $department->total_staff,
                                'trained' => $department->trained_staff,
                                'untrained' => $department->total_staff - $department->trained_staff,
                                'coverage_percentage' => $coverage
                            ];
                        })
                        ->sortByDesc('coverage_percentage')
                        ->values();
    }

    private function getCadreAnalysis($county, $type, $year) {
        $facilities = $county->facilities()->pluck('facilities.id');

        return Cadre::whereHas('users', function ($query) use ($facilities) {
                            $query->whereIn('facility_id', $facilities);
                        })
                        ->withCount([
                            'users as total_staff' => function ($query) use ($facilities) {
                                $query->whereIn('facility_id', $facilities);
                            },
                            'users as trained_staff' => function ($query) use ($facilities, $type, $year) {
                                $query->whereIn('facility_id', $facilities)
                                        ->whereHas('trainingParticipations.training', function ($q) use ($type, $year) {
                                            $q->where('type', $type);
                                            if ($year !== 'all') {
                                                $q->whereYear('start_date', $year);
                                            }
                                        });
                            }
                        ])
                        ->get()
                        ->filter(function ($cadre) {
                            return $cadre->total_staff > 0;
                        })
                        ->map(function ($cadre) {
                            $coverage = $cadre->total_staff > 0 ?
                                    round(($cadre->trained_staff / $cadre->total_staff) * 100, 1) : 0;

                            return [
                                'name' => $cadre->name,
                                'total' => $cadre->total_staff,
                                'trained' => $cadre->trained_staff,
                                'untrained' => $cadre->total_staff - $cadre->trained_staff,
                                'coverage_percentage' => $coverage
                            ];
                        })
                        ->sortByDesc('coverage_percentage')
                        ->values();
    }

    private function getFacilityDistribution($county, $type, $year) {
        return $county->facilities()
                        ->with(['facilityType', 'subcounty'])
                        ->get()
                        ->map(function ($facility) use ($type, $year) {
                            $coverage = $this->calculateFacilityCoverage($facility, $type, $year);

                            return [
                                'id' => $facility->id,
                                'name' => $facility->name,
                                'type' => $facility->facilityType->name ?? 'Unknown',
                                'subcounty' => $facility->subcounty->name,
                                'mfl_code' => $facility->mfl_code,
                                'coordinates' => $this->getFacilityCoordinates($facility),
                                'is_covered' => $coverage['is_covered'],
                                'participant_count' => $coverage['participant_count'],
                                'program_count' => $coverage['program_count'],
                                'completion_rate' => $coverage['completion_rate']
                            ];
                        })
                        ->sortByDesc('participant_count')
                        ->values();
    }

    private function getFacilityParticipants($facility, $type, $year) {
        if ($type === 'global_training') {
            return $facility->users()
                            ->whereHas('trainingParticipations.training', function ($query) use ($type, $year) {
                                $query->where('type', $type);
                                if ($year !== 'all') {
                                    $query->whereYear('start_date', $year);
                                }
                            })
                            ->with(['department', 'cadre', 'trainingParticipations' => function ($query) use ($type, $year) {
                                    $query->whereHas('training', function ($q) use ($type, $year) {
                                        $q->where('type', $type);
                                        if ($year !== 'all') {
                                            $q->whereYear('start_date', $year);
                                        }
                                    });
                                }])
                            ->get()
                            ->map(function ($user) {
                                $participations = $user->trainingParticipations;
                                $completedCount = $participations->where('completion_status', 'completed')->count();

                                return [
                                    'id' => $user->id,
                                    'name' => $user->full_name,
                                    'department' => $user->department->name ?? 'Unknown',
                                    'cadre' => $user->cadre->name ?? 'Unknown',
                                    'phone' => $user->phone,
                                    'email' => $user->email,
                                    'trainings_count' => $participations->count(),
                                    'completed_count' => $completedCount,
                                    'completion_rate' => $participations->count() > 0 ?
                                    round(($completedCount / $participations->count()) * 100, 1) : 0,
                                    'latest_training' => $participations->sortByDesc('created_at')->first()?->training?->title,
                                    'status' => $completedCount > 0 ? 'completed' : 'in_progress'
                                ];
                            });
        } else {
            return TrainingParticipant::whereHas('training', function ($query) use ($facility, $type, $year) {
                                $query->where('type', $type)
                                        ->where('facility_id', $facility->id);
                                if ($year !== 'all') {
                                    $query->whereYear('start_date', $year);
                                }
                            })
                            ->with(['user.department', 'user.cadre', 'training'])
                            ->get()
                            ->map(function ($participant) {
                                return [
                                    'id' => $participant->id,
                                    'user_id' => $participant->user->id,
                                    'name' => $participant->user->full_name,
                                    'department' => $participant->user->department->name ?? 'Unknown',
                                    'cadre' => $participant->user->cadre->name ?? 'Unknown',
                                    'phone' => $participant->user->phone,
                                    'email' => $participant->user->email,
                                    'training' => $participant->training->title,
                                    'status' => $participant->completion_status,
                                    'enrollment_date' => $participant->registration_date,
                                    'completion_date' => $participant->completion_date
                                ];
                            });
        }
    }

    // API Routes and Additional Methods

    /**
     * Search facilities across counties with filters
     */
    public function searchFacilities(Request $request) {
        $query = $request->get('query');
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', 'all');
        $countyId = $request->get('county_id');
        $facilityTypeId = $request->get('facility_type_id');
        $coverageFilter = $request->get('coverage_filter', 'all'); // 'covered', 'uncovered', 'all'

        $facilitiesQuery = Facility::with(['facilityType', 'subcounty.county']);

        // Apply search query
        if ($query) {
            $facilitiesQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                        ->orWhere('mfl_code', 'like', "%{$query}%");
            });
        }

        // Apply filters
        if ($countyId) {
            $facilitiesQuery->whereHas('subcounty', function ($q) use ($countyId) {
                $q->where('county_id', $countyId);
            });
        }

        if ($facilityTypeId) {
            $facilitiesQuery->where('facility_type_id', $facilityTypeId);
        }

        $facilities = $facilitiesQuery->limit(100)->get()->map(function ($facility) use ($type, $year) {
            $coverage = $this->calculateFacilityCoverage($facility, $type, $year);

            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'type' => $facility->facilityType->name,
                'mfl_code' => $facility->mfl_code,
                'county' => $facility->subcounty->county->name,
                'subcounty' => $facility->subcounty->name,
                'is_covered' => $coverage['is_covered'],
                'participant_count' => $coverage['participant_count'],
                'completion_rate' => $coverage['completion_rate']
            ];
        });

        // Apply coverage filter
        if ($coverageFilter === 'covered') {
            $facilities = $facilities->where('is_covered', true);
        } elseif ($coverageFilter === 'uncovered') {
            $facilities = $facilities->where('is_covered', false);
        }

        return response()->json([
                    'facilities' => $facilities->values(),
                    'total_found' => $facilities->count(),
                    'filters_applied' => [
                        'query' => $query,
                        'county_id' => $countyId,
                        'facility_type_id' => $facilityTypeId,
                        'coverage_filter' => $coverageFilter,
                        'type' => $type,
                        'year' => $year
                    ]
        ]);
    }

    /**
     * Get performance comparison between counties
     */
    public function getCountyComparison(Request $request) {
        $countyIds = $request->get('county_ids', []);
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', 'all');

        if (count($countyIds) > 5) {
            return response()->json(['error' => 'Maximum 5 counties can be compared'], 400);
        }

        $counties = County::whereIn('id', $countyIds)->get();

        $comparison = $counties->map(function ($county) use ($type, $year) {
            $coverage = $this->calculateCountyCoverage($county, $type, $year);

            return [
                'county_id' => $county->id,
                'county_name' => $county->name,
                'coverage_percentage' => $coverage['coverage_percentage'],
                'participant_count' => $coverage['participant_count'],
                'covered_facilities' => $coverage['covered_facilities'],
                'total_facilities' => $coverage['total_facilities'],
                'program_count' => $coverage['program_count'],
                'trend' => $this->calculateCountyTrend($county, $type, $year),
                'priority' => $this->calculatePriority($coverage['total_facilities'], $coverage['coverage_percentage'])
            ];
        });

        return response()->json([
                    'comparison' => $comparison,
                    'benchmark' => [
                        'average_coverage' => round($comparison->avg('coverage_percentage'), 1),
                        'total_participants' => $comparison->sum('participant_count'),
                        'total_programs' => $comparison->sum('program_count'),
                        'top_performer' => $comparison->sortByDesc('coverage_percentage')->first(),
                        'bottom_performer' => $comparison->sortBy('coverage_percentage')->first()
                    ],
                    'metadata' => [
                        'type' => $type,
                        'year' => $year,
                        'counties_compared' => count($countyIds)
                    ]
        ]);
    }

    /**
     * Export county data to CSV/Excel
     */
    public function exportCountyData(Request $request, $countyId) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', 'all');
        $format = $request->get('format', 'csv'); // csv, excel

        $county = County::findOrFail($countyId);
        $coverage = $this->calculateCountyCoverage($county, $type, $year);
        $facilities = $this->getFacilityDistribution($county, $type, $year);
        $facilityTypes = $this->getFacilityTypeBreakdown($county, $type, $year);

        $exportData = [
            'county_info' => [
                'name' => $county->name,
                'export_date' => now()->toDateString(),
                'training_type' => $type,
                'year' => $year
            ],
            'summary' => $coverage,
            'facilities' => $facilities,
            'facility_types' => $facilityTypes
        ];

        // In a real implementation, you would generate actual CSV/Excel files
        // For now, return download information
        return response()->json([
                    'message' => 'Export prepared successfully',
                    'download_info' => [
                        'filename' => "county_{$county->name}_{$type}_{$year}.{$format}",
                        'records' => count($facilities),
                        'format' => $format
                    ],
                    'data_preview' => $exportData
        ]);
    }

    /**
     * Get real-time alerts and notifications
     */
    public function getActiveAlerts(Request $request) {
        $type = $request->get('type', 'global_training');
        $level = $request->get('level', 'all'); // national, county, facility, all
        $severity = $request->get('severity', 'all'); // high, medium, low, all

        $alerts = [];

        // Check for counties with zero coverage
        if ($level === 'all' || $level === 'county') {
            $countiesWithZeroCoverage = County::get()->filter(function ($county) use ($type) {
                        $coverage = $this->calculateCountyCoverage($county, $type, 'all');
                        return $coverage['coverage_percentage'] === 0;
                    })->count();

            if ($countiesWithZeroCoverage > 0) {
                $alerts[] = [
                    'id' => 'zero_coverage_counties',
                    'type' => 'coverage_gap',
                    'severity' => 'high',
                    'title' => 'Counties with Zero Coverage',
                    'message' => "{$countiesWithZeroCoverage} counties have no training programs",
                    'action_required' => true,
                    'created_at' => now()
                ];
            }
        }

        // Check for facilities with low completion rates
        if ($level === 'all' || $level === 'facility') {
            $lowCompletionFacilities = Facility::with('trainings.participants')
                    ->get()
                    ->filter(function ($facility) use ($type) {
                        $coverage = $this->calculateFacilityCoverage($facility, $type, 'all');
                        return $coverage['is_covered'] && $coverage['completion_rate'] < 50;
                    })
                    ->count();

            if ($lowCompletionFacilities > 0) {
                $alerts[] = [
                    'id' => 'low_completion_facilities',
                    'type' => 'performance',
                    'severity' => 'medium',
                    'title' => 'Facilities with Low Completion Rates',
                    'message' => "{$lowCompletionFacilities} facilities have completion rates below 50%",
                    'action_required' => true,
                    'created_at' => now()
                ];
            }
        }

        // Filter by severity if specified
        if ($severity !== 'all') {
            $alerts = collect($alerts)->where('severity', $severity)->values();
        }

        return response()->json([
                    'alerts' => $alerts,
                    'total_alerts' => count($alerts),
                    'filters' => [
                        'type' => $type,
                        'level' => $level,
                        'severity' => $severity
                    ],
                    'generated_at' => now()
        ]);
    }

    /**
     * Get API health status
     */
    public function getApiStatus() {
        $checks = [
            'database' => $this->checkDatabaseConnection(),
            'cache' => $this->checkCacheConnection(),
            'models' => $this->checkModelIntegrity()
        ];

        $overallStatus = collect($checks)->every(function ($check) {
                    return $check['status'] === 'healthy';
                }) ? 'healthy' : 'degraded';

        return response()->json([
                    'overall_status' => $overallStatus,
                    'timestamp' => now(),
                    'version' => '1.0.0',
                    'checks' => $checks,
                    'environment' => app()->environment()
        ]);
    }

    private function checkDatabaseConnection() {
        try {
            DB::connection()->getPdo();
            $queryTime = microtime(true);
            DB::select('SELECT 1');
            $queryTime = (microtime(true) - $queryTime) * 1000;

            return [
                'status' => 'healthy',
                'message' => 'Database connection active',
                'response_time' => round($queryTime, 2) . 'ms'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ];
        }
    } 

    private function checkCacheConnection() {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_' . uniqid();

            Cache::put($testKey, $testValue, 60);
            $retrievedValue = Cache::get($testKey);
            Cache::forget($testKey);

            return $retrievedValue === $testValue ? ['status' => 'healthy', 'message' => 'Cache is working properly'] : ['status' => 'unhealthy', 'message' => 'Cache read/write failed'];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache connection failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkModelIntegrity() {
        try {
            $countyCount = County::count();
            $facilityCount = Facility::count();
            $trainingCount = Training::count();
            $participantCount = TrainingParticipant::count();

            return [
                'status' => 'healthy',
                'message' => "Models accessible: {$countyCount} counties, {$facilityCount} facilities, {$trainingCount} trainings, {$participantCount} participants",
                'counts' => [
                    'counties' => $countyCount,
                    'facilities' => $facilityCount,
                    'trainings' => $trainingCount,
                    'participants' => $participantCount
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Model access failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clear cached data for recalculation
     */
    public function clearCache(Request $request) {
        $pattern = $request->get('pattern', 'progressive_*');
        $entityId = $request->get('entity_id');
        $entityType = $request->get('entity_type'); // national, county, facility

        $clearedKeys = [];

        if ($entityType === 'county' && $entityId) {
            $keys = [
                "progressive_county_{$entityId}_*",
                "progressive_facility_type_{$entityId}_*"
            ];
            foreach ($keys as $keyPattern) {
                // In production, use pattern-based cache clearing
                Cache::flush(); // Simple flush for now
                $clearedKeys[] = $keyPattern;
            }
        } elseif ($entityType === 'facility' && $entityId) {
            $key = "progressive_facility_{$entityId}_*";
            Cache::flush();
            $clearedKeys[] = $key;
        } else {
            // Clear all progressive dashboard cache
            Cache::flush();
            $clearedKeys[] = 'progressive_*';
        }

        return response()->json([
                    'message' => 'Cache cleared successfully',
                    'cleared_patterns' => $clearedKeys,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'timestamp' => now()
        ]);
    }

    /**
     * Get dashboard statistics summary
     */
    public function getDashboardStats(Request $request) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', 'all');

        $counties = County::count();
        $facilities = Facility::count();
        $trainings = Training::where('type', $type)
                ->when($year !== 'all', function ($query) use ($year) {
                    $query->whereYear('start_date', $year);
                })
                ->count();

        $participants = TrainingParticipant::whereHas('training', function ($query) use ($type, $year) {
                    $query->where('type', $type);
                    if ($year !== 'all') {
                        $query->whereYear('start_date', $year);
                    }
                })->count();

        $completedParticipants = TrainingParticipant::whereHas('training', function ($query) use ($type, $year) {
                    $query->where('type', $type);
                    if ($year !== 'all') {
                        $query->whereYear('start_date', $year);
                    }
                })->where('completion_status', 'completed')->count();

        $completionRate = $participants > 0 ? round(($completedParticipants / $participants) * 100, 1) : 0;

        return response()->json([
                    'summary' => [
                        'total_counties' => $counties,
                        'total_facilities' => $facilities,
                        'total_trainings' => $trainings,
                        'total_participants' => $participants,
                        'completed_participants' => $completedParticipants,
                        'completion_rate' => $completionRate
                    ],
                    'metadata' => [
                        'type' => $type,
                        'year' => $year,
                        'year_display' => $this->getYearDisplayText($year),
                        'is_all_years' => $year === 'all',
                        'generated_at' => now()
                    ]
        ]);
    }

    /**
     * Get facility types for dropdown filters
     */
    public function getFacilityTypes() {
        $facilityTypes = FacilityType::select('id', 'name')
                ->orderBy('name')
                ->get();

        return response()->json($facilityTypes);
    }

    /**
     * Get departments for dropdown filters
     */
    public function getDepartments() {
        $departments = Department::select('id', 'name')
                ->orderBy('name')
                ->get();

        return response()->json($departments);
    }

    /**
     * Get cadres for dropdown filters  
     */
    public function getCadres() {
        $cadres = Cadre::select('id', 'name')
                ->orderBy('name')
                ->get();

        return response()->json($cadres);
    }

    /**
     * Calculate training intensity based on coverage data
     */
    private function calculateIntensity($coverage): string {
        $participants = $coverage['participant_count'] ?? 0;
        $facilities = $coverage['total_facilities'] ?? 1;
        $programs = $coverage['program_count'] ?? 0;
        
        // Calculate intensity score based on participants per facility and program diversity
        $participantRatio = $facilities > 0 ? $participants / $facilities : 0;
        $programRatio = $facilities > 0 ? $programs / $facilities : 0;
        
        // Combined intensity score
        $intensityScore = ($participantRatio * 0.7) + ($programRatio * 0.3);
        
        if ($intensityScore >= 10) return 'High';
        if ($intensityScore >= 5) return 'Medium';
        if ($intensityScore >= 1) return 'Low';
        return 'Minimal';
    }

    /**
     * Calculate county trend based on historical data
     */
    private function calculateCountyTrend($county, $type, $year): string {
        try {
            // Get current and previous year data for trend analysis
            $currentYear = $year === 'all' ? date('Y') : $year;
            $previousYear = $currentYear - 1;
            
            $currentCoverage = $this->calculateCountyCoverage($county, $type, $currentYear);
            $previousCoverage = $this->calculateCountyCoverage($county, $type, $previousYear);
            
            $currentPercentage = $currentCoverage['coverage_percentage'];
            $previousPercentage = $previousCoverage['coverage_percentage'];
            
            $difference = $currentPercentage - $previousPercentage;
            
            if ($difference > 10) return 'Improving';
            if ($difference < -10) return 'Declining';
            if ($difference > 0) return 'Stable Growth';
            if ($difference < 0) return 'Stable Decline';
            return 'Stable';
            
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Calculate priority level based on facilities and coverage
     */
    private function calculatePriority($totalFacilities, $coveragePercentage): string {
        // High priority: Many facilities with low coverage
        if ($totalFacilities >= 20 && $coveragePercentage < 30) return 'High';
        if ($totalFacilities >= 10 && $coveragePercentage < 50) return 'High';
        
        // Medium priority: Moderate facilities with moderate coverage
        if ($totalFacilities >= 5 && $coveragePercentage < 70) return 'Medium';
        
        // Low priority: Few facilities or high coverage
        return 'Low';
    }

    /**
     * Get county coordinates (placeholder - integrate with actual geo data)
     */
    private function getCountyCoordinates($county): ?array {
        // In a real implementation, this would fetch actual coordinates
        // For now, return null or default coordinates
        return [
            'latitude' => -1.2921, // Default Kenya coordinates
            'longitude' => 36.8219
        ];
    }

    /**
     * Get facility coordinates
     */
    private function getFacilityCoordinates($facility): ?array {
        if ($facility->lat && $facility->long) {
            return [
                'latitude' => (float) $facility->lat,
                'longitude' => (float) $facility->long
            ];
        }
        return null;
    }

    /**
     * Generate national insights based on counties data
     */
    private function generateNationalInsights($counties, $type): array {
        $insights = [];
        $totalCounties = $counties->count();
        $coveredCounties = $counties->where('coverage_percentage', '>', 0)->count();
        $highPerformers = $counties->where('coverage_percentage', '>=', 80)->count();
        $zeroCoverage = $counties->where('coverage_percentage', 0)->count();
        
        // Coverage insights
        if ($zeroCoverage > 0) {
            $insights[] = [
                'type' => 'alert',
                'title' => 'Counties with Zero Coverage',
                'message' => "{$zeroCoverage} counties have no {$type} programs. Immediate intervention required.",
                'action' => 'Launch targeted programs in uncovered counties'
            ];
        }
        
        if ($highPerformers > 0) {
            $insights[] = [
                'type' => 'success',
                'title' => 'High-Performing Counties',
                'message' => "{$highPerformers} counties have achieved 80%+ coverage. Consider them as mentorship hubs.",
                'action' => 'Leverage high performers to support struggling counties'
            ];
        }
        
        $coverageRate = $totalCounties > 0 ? round(($coveredCounties / $totalCounties) * 100) : 0;
        if ($coverageRate < 60) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'National Coverage Gap',
                'message' => "Only {$coverageRate}% of counties have active programs. National scale-up needed.",
                'action' => 'Develop national expansion strategy'
            ];
        }
        
        return $insights;
    }

    /**
     * Generate county-specific insights
     */
    private function generateCountyInsights($county, $facilityTypes, $departments, $type): array {
        $insights = [];
        
        // Facility type analysis
        $uncoveredTypes = $facilityTypes->where('coverage_percentage', 0);
        if ($uncoveredTypes->count() > 0) {
            $typeNames = $uncoveredTypes->pluck('name')->join(', ');
            $insights[] = [
                'type' => 'alert',
                'title' => 'Uncovered Facility Types',
                'message' => "No coverage for: {$typeNames}",
                'action' => 'Target specific facility types for program expansion'
            ];
        }
        
        // Department analysis
        $lowCoverageDepts = $departments->where('coverage_percentage', '<', 30);
        if ($lowCoverageDepts->count() > 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Departments Need Attention',
                'message' => "{$lowCoverageDepts->count()} departments have less than 30% coverage",
                'action' => 'Focus recruitment on underrepresented departments'
            ];
        }
        
        // Performance insights
        $bestType = $facilityTypes->sortByDesc('coverage_percentage')->first();
        if ($bestType && $bestType['coverage_percentage'] > 70) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Best Performing Type',
                'message' => "{$bestType['name']} facilities show {$bestType['coverage_percentage']}% coverage",
                'action' => 'Replicate success model in other facility types'
            ];
        }
        
        return $insights;
    }

    /**
     * Generate recommended actions for county
     */
    private function getRecommendedActions($county, $facilityTypes, $departments, $type): array {
        $actions = [];
        
        // Prioritize uncovered facility types
        $uncoveredTypes = $facilityTypes->where('coverage_percentage', 0);
        foreach ($uncoveredTypes->take(3) as $facilityType) {
            $actions[] = [
                'priority' => 'High',
                'title' => "Target {$facilityType['name']} Facilities",
                'description' => "Launch {$type} programs in {$facilityType['total']} uncovered facilities",
                'estimated_participants' => $facilityType['total'] * 5, // Estimate 5 participants per facility
                'timeline' => '3-6 months'
            ];
        }
        
        // Department-specific actions
        $lowCoverageDepts = $departments->where('coverage_percentage', '<', 50)->take(2);
        foreach ($lowCoverageDepts as $dept) {
            $actions[] = [
                'priority' => 'Medium',
                'title' => "Boost {$dept['name']} Participation",
                'description' => "Increase coverage from {$dept['coverage_percentage']}% to 70%",
                'estimated_participants' => $dept['untrained'],
                'timeline' => '6-12 months'
            ];
        }
        
        // Follow-up actions for covered facilities
        $coveredTypes = $facilityTypes->where('coverage_percentage', '>', 0)->where('coverage_percentage', '<', 80);
        foreach ($coveredTypes->take(2) as $facilityType) {
            $actions[] = [
                'priority' => 'Low',
                'title' => "Complete {$facilityType['name']} Coverage",
                'description' => "Reach remaining {$facilityType['uncovered']} facilities",
                'estimated_participants' => $facilityType['uncovered'] * 3,
                'timeline' => '6-9 months'
            ];
        }
        
        return $actions;
    }

    /**
     * Generate facility type insights
     */
    private function generateFacilityTypeInsights($facilities, $facilityTypeName, $type): array {
        $insights = [];
        $total = $facilities->count();
        $covered = $facilities->where('is_covered', true)->count();
        $highPerformers = $facilities->where('coverage_score', '>=', 80)->count();
        
        if ($covered === 0) {
            $insights[] = [
                'type' => 'alert',
                'title' => 'No Coverage Achieved',
                'message' => "None of the {$total} {$facilityTypeName} facilities have {$type} programs",
                'action' => 'Immediate program launch required'
            ];
        }
        
        if ($highPerformers > 0) {
            $insights[] = [
                'type' => 'success',
                'title' => 'High-Performing Facilities',
                'message' => "{$highPerformers} facilities show excellent performance",
                'action' => 'Use as mentorship hubs for other facilities'
            ];
        }
        
        $coverageRate = $total > 0 ? round(($covered / $total) * 100) : 0;
        if ($coverageRate > 0 && $coverageRate < 50) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Partial Coverage',
                'message' => "Only {$coverageRate}% of {$facilityTypeName} facilities are covered",
                'action' => 'Scale up programs to reach remaining facilities'
            ];
        }
        
        return $insights;
    }

    /**
     * Calculate coverage score for a facility
     */
    private function calculateCoverageScore($coverage): int {
        $participantWeight = 40;
        $programWeight = 30;
        $completionWeight = 30;
        
        $participantScore = min(($coverage['participant_count'] / 10) * 100, 100); // Max 10 participants = 100%
        $programScore = min(($coverage['program_count'] / 3) * 100, 100); // Max 3 programs = 100%
        $completionScore = $coverage['completion_rate'];
        
        $totalScore = (
            ($participantScore * $participantWeight) +
            ($programScore * $programWeight) +
            ($completionScore * $completionWeight)
        ) / 100;
        
        return round($totalScore);
    }

    /**
     * Get facility department breakdown
     */
    private function getFacilityDepartmentBreakdown($facility, $type, $year): array {
        if ($type === 'global_training') {
            return $facility->users()
                ->whereHas('trainingParticipations.training', function ($query) use ($type, $year) {
                    $query->where('type', $type);
                    if ($year !== 'all') {
                        $query->whereYear('start_date', $year);
                    }
                })
                ->with('department')
                ->get()
                ->groupBy('department.name')
                ->map(function ($users, $deptName) {
                    return [
                        'name' => $deptName ?: 'Unknown',
                        'count' => $users->count()
                    ];
                })
                ->values()
                ->toArray();
        } else {
            return TrainingParticipant::whereHas('training', function ($query) use ($facility, $type, $year) {
                    $query->where('type', $type)
                          ->where('facility_id', $facility->id);
                    if ($year !== 'all') {
                        $query->whereYear('start_date', $year);
                    }
                })
                ->with('user.department')
                ->get()
                ->groupBy('user.department.name')
                ->map(function ($participants, $deptName) {
                    return [
                        'name' => $deptName ?: 'Unknown',
                        'count' => $participants->count()
                    ];
                })
                ->values()
                ->toArray();
        }
    }

    /**
     * Get training history for a facility
     */
    private function getTrainingHistory($facility, $type, $year): array {
        $query = $facility->trainings()->where('type', $type);
        
        if ($year !== 'all') {
            $query->whereYear('start_date', $year);
        }
        
        return $query->with('participants')
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($training) {
                return [
                    'id' => $training->id,
                    'title' => $training->title,
                    'identifier' => $training->identifier,
                    'start_date' => $training->start_date->format('Y-m-d'),
                    'end_date' => $training->end_date ? $training->end_date->format('Y-m-d') : 'Ongoing',
                    'status' => $training->status,
                    'participants_count' => $training->participants()->count(),
                    'completion_rate' => $training->completion_rate
                ];
            })
            ->toArray();
    }

    /**
     * Get facility performance metrics
     */
    private function getFacilityPerformanceMetrics($facility, $type, $year): array {
        $participantsQuery = $type === 'global_training' 
            ? $facility->users()->whereHas('trainingParticipations.training', function ($q) use ($type, $year) {
                $q->where('type', $type);
                if ($year !== 'all') {
                    $q->whereYear('start_date', $year);
                }
              })
            : TrainingParticipant::whereHas('training', function ($q) use ($facility, $type, $year) {
                $q->where('facility_id', $facility->id)->where('type', $type);
                if ($year !== 'all') {
                    $q->whereYear('start_date', $year);
                }
              });
        
        $totalParticipants = $participantsQuery->count();
        
        $completedQuery = clone $participantsQuery;
        if ($type === 'global_training') {
            $completedCount = $completedQuery->whereHas('trainingParticipations', function ($q) use ($type, $year) {
                $q->where('completion_status', 'completed')
                  ->whereHas('training', function ($tq) use ($type, $year) {
                      $tq->where('type', $type);
                      if ($year !== 'all') {
                          $tq->whereYear('start_date', $year);
                      }
                  });
            })->count();
        } else {
            $completedCount = $completedQuery->where('completion_status', 'completed')->count();
        }
        
        $programsCount = $facility->trainings()->where('type', $type)
            ->when($year !== 'all', function ($q) use ($year) {
                $q->whereYear('start_date', $year);
            })->count();
        
        $departmentsCount = $type === 'global_training'
            ? $facility->users()->whereHas('trainingParticipations.training', function ($q) use ($type, $year) {
                $q->where('type', $type);
                if ($year !== 'all') {
                    $q->whereYear('start_date', $year);
                }
              })->distinct('department_id')->count()
            : TrainingParticipant::whereHas('training', function ($q) use ($facility, $type, $year) {
                $q->where('facility_id', $facility->id)->where('type', $type);
                if ($year !== 'all') {
                    $q->whereYear('start_date', $year);
                }
              })->join('users', 'training_participants.user_id', '=', 'users.id')
                ->distinct('users.department_id')->count();
        
        return [
            'total_participants' => $totalParticipants,
            'completed_participants' => $completedCount,
            'completion_rate' => $totalParticipants > 0 ? round(($completedCount / $totalParticipants) * 100, 1) : 0,
            'program_count' => $programsCount,
            'departments_represented' => $departmentsCount
        ];
    }

    /**
     * Get department breakdown for facility participants
     */
    private function getDepartmentBreakdown($participants): array {
        return $participants->groupBy('department')
            ->map(function ($group, $department) {
                return [
                    'name' => $department,
                    'count' => $group->count()
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get cadre breakdown for facility participants
     */
    private function getCadreBreakdown($participants): array {
        return $participants->groupBy('cadre')
            ->map(function ($group, $cadre) {
                return [
                    'name' => $cadre,
                    'count' => $group->count()
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Generate facility-specific insights
     */
    private function generateFacilityInsights($facility, $participants, $performance, $type): array {
        $insights = [];
        
        if ($performance['total_participants'] === 0) {
            $insights[] = [
                'type' => 'alert',
                'title' => 'No Participants',
                'message' => "This facility has no {$type} participants yet",
                'action' => 'Launch recruitment and enrollment campaign'
            ];
            return $insights;
        }
        
        if ($performance['completion_rate'] < 50) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Low Completion Rate',
                'message' => "Only {$performance['completion_rate']}% of participants complete programs",
                'action' => 'Investigate barriers and provide additional support'
            ];
        }
        
        if ($performance['completion_rate'] >= 80) {
            $insights[] = [
                'type' => 'success',
                'title' => 'High Performance',
                'message' => "Excellent {$performance['completion_rate']}% completion rate",
                'action' => 'Share best practices with other facilities'
            ];
        }
        
        if ($performance['departments_represented'] >= 5) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Multi-Department Engagement',
                'message' => "{$performance['departments_represented']} departments actively participating",
                'action' => 'Consider expanding to remaining departments'
            ];
        }
        
        return $insights;
    }

    /**
     * Get participant training history
     */
    private function getParticipantTrainingHistory($user): array {
        return $user->trainingParticipations()
            ->with(['training', 'assessmentResults'])
            ->orderBy('registration_date', 'desc')
            ->get()
            ->map(function ($participation) {
                $avgScore = $participation->assessmentResults->avg('score') ?? 0;
                
                return [
                    'training_title' => $participation->training->title,
                    'training_type' => $participation->training->type,
                    'registration_date' => $participation->registration_date->format('Y-m-d'),
                    'completion_status' => $participation->completion_status,
                    'completion_date' => $participation->completion_date?->format('Y-m-d'),
                    'assessment_score' => round($avgScore, 1)
                ];
            })
            ->toArray();
    }

    /**
     * Get assessment summary for participant
     */
    private function getAssessmentSummary($participant): array {
        $results = $participant->assessmentResults;
        
        if ($results->isEmpty()) {
            return [
                'total_assessments' => 0,
                'passed_assessments' => 0,
                'average_score' => 0,
                'status' => 'Not Assessed'
            ];
        }
        
        $totalAssessments = $results->count();
        $passedAssessments = $results->where('result', 'pass')->count();
        $averageScore = $results->avg('score') ?? 0;
        
        return [
            'total_assessments' => $totalAssessments,
            'passed_assessments' => $passedAssessments,
            'average_score' => round($averageScore, 1),
            'status' => $passedAssessments === $totalAssessments ? 'All Passed' : 'Partial'
        ];
    }

    /**
     * Get participant performance metrics
     */
    private function getParticipantPerformanceMetrics($participant): array {
        $user = $participant->user;
        $allParticipations = $user->trainingParticipations;
        
        $totalTrainings = $allParticipations->count();
        $completedTrainings = $allParticipations->where('completion_status', 'completed')->count();
        $averageScore = $allParticipations->flatMap->assessmentResults->avg('score') ?? 0;
        
        return [
            'total_trainings' => $totalTrainings,
            'completed_trainings' => $completedTrainings,
            'completion_rate' => $totalTrainings > 0 ? round(($completedTrainings / $totalTrainings) * 100, 1) : 0,
            'average_score' => round($averageScore, 1),
            'latest_activity' => $allParticipations->sortByDesc('registration_date')->first()?->registration_date?->diffForHumans()
        ];
    }

    /**
     * Get year display text
     */
    private function getYearDisplayText($year): string {
        return $year === 'all' ? 'All Years' : "Year {$year}";
    }
}

