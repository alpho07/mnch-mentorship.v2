<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\FacilityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {

    public function index() {
        return view('dashboard.index');
    }

    public function getOverviewStats(Request $request) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', date('Y'));

        $cacheKey = "dashboard_overview_{$type}_{$year}";

        return Cache::remember($cacheKey, 3600, function () use ($type, $year) {
                    $totalCounties = County::count();
                    $totalFacilities = Facility::count();

                    // Counties with coverage in the selected year
                    $countiesWithCoverage = County::whereHas('facilities.users.trainingParticipations.training', function ($query) use ($type, $year) {
                                $query->where('type', $type)->whereYear('start_date', $year);
                            })->count();

                    // Facilities with coverage in the selected year
                    if ($type === 'global_training') {
                        $facilitiesWithCoverage = Facility::whereHas('users.trainingParticipations.training', function ($query) use ($type, $year) {
                                    $query->where('type', $type)->whereYear('start_date', $year);
                                })->count();
                    } else {
                        $facilitiesWithCoverage = Facility::whereHas('trainings', function ($query) use ($type, $year) {
                                    $query->where('type', $type)->whereYear('start_date', $year);
                                })->count();
                    }

                    // Total unique participants/mentees in the selected year
                    $totalParticipants = TrainingParticipant::whereHas('training', function ($query) use ($type, $year) {
                                $query->where('type', $type)->whereYear('start_date', $year);
                            })->distinct('user_id')->count();

                    // UNIQUE training programs/mentorships conducted in the selected year
                    $uniquePrograms = Training::where('type', $type)
                            ->whereYear('start_date', $year)
                            ->count();

                    // Total trainings/mentorships conducted across all time (for context)
                    $allTimePrograms = Training::where('type', $type)->count();

                    // Total participants across all time (for context)
                    $allTimeParticipants = TrainingParticipant::whereHas('training', function ($query) use ($type) {
                                $query->where('type', $type);
                            })->distinct('user_id')->count();

                    return [
                        'counties' => [
                            'total' => $totalCounties,
                            'covered' => $countiesWithCoverage,
                            'coverage_percentage' => $totalCounties > 0 ? round(($countiesWithCoverage / $totalCounties) * 100, 1) : 0
                        ],
                        'facilities' => [
                            'total' => $totalFacilities,
                            'covered' => $facilitiesWithCoverage,
                            'coverage_percentage' => $totalFacilities > 0 ? round(($facilitiesWithCoverage / $totalFacilities) * 100, 1) : 0
                        ],
                        'year_data' => [
                            'participants' => $totalParticipants,
                            'programs' => $uniquePrograms,
                            'year' => $year
                        ],
                        'all_time_data' => [
                            'participants' => $allTimeParticipants,
                            'programs' => $allTimePrograms
                        ],
                        'type' => $type === 'global_training' ? 'Global Training' : 'Facility Mentorship'
                    ];
                });
    }

    public function getCountiesHeatmapData(Request $request) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', date('Y'));

        $cacheKey = "heatmap_counties_{$type}_{$year}";

        return Cache::remember($cacheKey, 1800, function () use ($type, $year) {
                    $counties = County::with(['subcounties.facilities'])
                            ->get()
                            ->map(function ($county) use ($type, $year) {

                                if ($type === 'global_training') {
                                    // For global trainings, count participants from this county
                                    $participantCount = TrainingParticipant::whereHas('user.facility.subcounty', function ($query) use ($county) {
                                                $query->where('county_id', $county->id);
                                            })->whereHas('training', function ($query) use ($type, $year) {
                                                $query->where('type', $type)
                                                        ->whereYear('start_date', $year);
                                            })->distinct('user_id')->count();

                                    $facilitiesCovered = Facility::whereHas('subcounty', function ($query) use ($county) {
                                                $query->where('county_id', $county->id);
                                            })->whereHas('users.trainingParticipations.training', function ($query) use ($type, $year) {
                                                $query->where('type', $type)
                                                        ->whereYear('start_date', $year);
                                            })->count();
                                } else {
                                    // For mentorships, count mentees in facilities within this county
                                    $participantCount = TrainingParticipant::whereHas('training', function ($query) use ($county, $type, $year) {
                                                $query->where('type', $type)
                                                        ->whereYear('start_date', $year)
                                                        ->whereHas('facility.subcounty', function ($q) use ($county) {
                                                            $q->where('county_id', $county->id);
                                                        });
                                            })->distinct('user_id')->count();

                                    $facilitiesCovered = Facility::whereHas('subcounty', function ($query) use ($county) {
                                                $query->where('county_id', $county->id);
                                            })->whereHas('trainings', function ($query) use ($type, $year) {
                                                $query->where('type', $type)
                                                        ->whereYear('start_date', $year);
                                            })->count();
                                }

                                $totalFacilities = $county->facilities()->count();

                                return [
                                    'id' => $county->id,
                                    'name' => $county->name,
                                    'participant_count' => $participantCount,
                                    'facilities_covered' => $facilitiesCovered,
                                    'total_facilities' => $totalFacilities,
                                    'coverage_percentage' => $totalFacilities > 0 ? round(($facilitiesCovered / $totalFacilities) * 100, 1) : 0,
                                    'intensity' => $this->calculateIntensity($participantCount, $facilitiesCovered)
                                ];
                            });

                    return $counties;
                });
    }

    public function getCoverageByFacilityType(Request $request) {
        $type = $request->get('type', 'global_training');
        $countyId = $request->get('county_id');
        $year = $request->get('year', date('Y'));

        $cacheKey = "facility_type_coverage_{$type}_{$countyId}_{$year}";

        return Cache::remember($cacheKey, 1800, function () use ($type, $countyId, $year) {
                    $query = FacilityType::withCount([
                        'facilities as total_facilities',
                        'facilities as covered_facilities' => function ($query) use ($type, $countyId, $year) {
                            if ($countyId) {
                                $query->whereHas('subcounty', function ($q) use ($countyId) {
                                    $q->where('county_id', $countyId);
                                });
                            }

                            if ($type === 'global_training') {
                                $query->whereHas('users.trainingParticipations.training', function ($q) use ($type, $year) {
                                    $q->where('type', $type)->whereYear('start_date', $year);
                                });
                            } else {
                                $query->whereHas('trainings', function ($q) use ($type, $year) {
                                    $q->where('type', $type)->whereYear('start_date', $year);
                                });
                            }
                        }
                    ]);

                    if ($countyId) {
                        $query->whereHas('facilities.subcounty', function ($q) use ($countyId) {
                            $q->where('county_id', $countyId);
                        });
                    }

                    return $query->get()->map(function ($facilityType) {
                                $coveragePercentage = $facilityType->total_facilities > 0 ? round(($facilityType->covered_facilities / $facilityType->total_facilities) * 100, 1) : 0;

                                return [
                                    'name' => $facilityType->name,
                                    'total' => $facilityType->total_facilities,
                                    'covered' => $facilityType->covered_facilities,
                                    'uncovered' => $facilityType->total_facilities - $facilityType->covered_facilities,
                                    'coverage_percentage' => $coveragePercentage
                                ];
                            });
                });
    }

    public function getCoverageByDepartment(Request $request) {
        $type = $request->get('type', 'global_training');
        $countyId = $request->get('county_id');
        $facilityId = $request->get('facility_id');
        $year = $request->get('year', date('Y'));

        $cacheKey = "department_coverage_{$type}_{$countyId}_{$facilityId}_{$year}";

        return Cache::remember($cacheKey, 1800, function () use ($type, $countyId, $facilityId, $year) {
                    $departments = Department::withCount([
                                'users as total_staff' => function ($query) use ($countyId, $facilityId) {
                                    if ($facilityId) {
                                        $query->where('facility_id', $facilityId);
                                    } elseif ($countyId) {
                                        $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                                            $q->where('county_id', $countyId);
                                        });
                                    }
                                },
                                'users as trained_staff' => function ($query) use ($type, $countyId, $facilityId, $year) {
                                    if ($facilityId) {
                                        $query->where('facility_id', $facilityId);
                                    } elseif ($countyId) {
                                        $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                                            $q->where('county_id', $countyId);
                                        });
                                    }

                                    $query->whereHas('trainingParticipations.training', function ($q) use ($type, $year) {
                                        $q->where('type', $type)->whereYear('start_date', $year);
                                    });
                                }
                            ])->get();

                    return $departments->filter(function ($dept) {
                                return $dept->total_staff > 0;
                            })->map(function ($department) {
                                $coveragePercentage = $department->total_staff > 0 ? round(($department->trained_staff / $department->total_staff) * 100, 1) : 0;

                                return [
                                    'name' => $department->name,
                                    'total' => $department->total_staff,
                                    'trained' => $department->trained_staff,
                                    'untrained' => $department->total_staff - $department->trained_staff,
                                    'coverage_percentage' => $coveragePercentage
                                ];
                            })->sortByDesc('coverage_percentage')->values();
                });
    }

    public function getCoverageByCadre(Request $request) {
        $type = $request->get('type', 'global_training');
        $countyId = $request->get('county_id');
        $facilityId = $request->get('facility_id');
        $year = $request->get('year', date('Y'));

        $cacheKey = "cadre_coverage_{$type}_{$countyId}_{$facilityId}_{$year}";

        return Cache::remember($cacheKey, 1800, function () use ($type, $countyId, $facilityId, $year) {
                    $cadres = Cadre::withCount([
                                'users as total_staff' => function ($query) use ($countyId, $facilityId) {
                                    if ($facilityId) {
                                        $query->where('facility_id', $facilityId);
                                    } elseif ($countyId) {
                                        $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                                            $q->where('county_id', $countyId);
                                        });
                                    }
                                },
                                'users as trained_staff' => function ($query) use ($type, $countyId, $facilityId, $year) {
                                    if ($facilityId) {
                                        $query->where('facility_id', $facilityId);
                                    } elseif ($countyId) {
                                        $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                                            $q->where('county_id', $countyId);
                                        });
                                    }

                                    $query->whereHas('trainingParticipations.training', function ($q) use ($type, $year) {
                                        $q->where('type', $type)->whereYear('start_date', $year);
                                    });
                                }
                            ])->get();

                    return $cadres->filter(function ($cadre) {
                                return $cadre->total_staff > 0;
                            })->map(function ($cadre) {
                                $coveragePercentage = $cadre->total_staff > 0 ? round(($cadre->trained_staff / $cadre->total_staff) * 100, 1) : 0;

                                return [
                                    'name' => $cadre->name,
                                    'total' => $cadre->total_staff,
                                    'trained' => $cadre->trained_staff,
                                    'untrained' => $cadre->total_staff - $cadre->trained_staff,
                                    'coverage_percentage' => $coveragePercentage
                                ];
                            })->sortByDesc('coverage_percentage')->values();
                });
    }

    public function getCountyDetails(Request $request, $countyId) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', date('Y'));

        $county = County::with(['subcounties.facilities'])->findOrFail($countyId);

        $cacheKey = "county_details_{$countyId}_{$type}_{$year}";

        $details = Cache::remember($cacheKey, 1800, function () use ($county, $type, $year) {
            $facilities = $county->facilities();

            if ($type === 'global_training') {
                $coveredFacilities = $facilities->whereHas('users.trainingParticipations.training', function ($query) use ($type, $year) {
                            $query->where('type', $type)->whereYear('start_date', $year);
                        })->get();

                $participantCount = TrainingParticipant::whereHas('user.facility.subcounty', function ($query) use ($county) {
                            $query->where('county_id', $county->id);
                        })->whereHas('training', function ($query) use ($type, $year) {
                            $query->where('type', $type)->whereYear('start_date', $year);
                        })->distinct('user_id')->count();
            } else {
                $coveredFacilities = $facilities->whereHas('trainings', function ($query) use ($type, $year) {
                            $query->where('type', $type)->whereYear('start_date', $year);
                        })->get();

                $participantCount = TrainingParticipant::whereHas('training', function ($query) use ($county, $type, $year) {
                            $query->where('type', $type)
                                    ->whereYear('start_date', $year)
                                    ->whereHas('facility.subcounty', function ($q) use ($county) {
                                        $q->where('county_id', $county->id);
                                    });
                        })->distinct('user_id')->count();
            }

            $totalFacilities = $facilities->count();

            // FIX: Specify the table name for the ambiguous 'id' column
            $uncoveredFacilities = $facilities->whereNotIn('facilities.id', $coveredFacilities->pluck('id'))->get();

            return [
                'county' => [
                    'id' => $county->id,
                    'name' => $county->name,
                    'subcounties_count' => $county->subcounties->count(),
                ],
                'coverage' => [
                    'total_facilities' => $totalFacilities,
                    'covered_facilities' => $coveredFacilities->count(),
                    'uncovered_facilities' => $uncoveredFacilities->count(),
                    'coverage_percentage' => $totalFacilities > 0 ? round(($coveredFacilities->count() / $totalFacilities) * 100, 1) : 0,
                    'participant_count' => $participantCount
                ],
                'covered_facilities' => $coveredFacilities->map(function ($facility) {
                    return [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'type' => $facility->facilityType->name ?? 'Unknown',
                        'subcounty' => $facility->subcounty->name ?? 'Unknown',
                        'mfl_code' => $facility->mfl_code
                    ];
                }),
                'uncovered_facilities' => $uncoveredFacilities->map(function ($facility) {
                    return [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'type' => $facility->facilityType->name ?? 'Unknown',
                        'subcounty' => $facility->subcounty->name ?? 'Unknown',
                        'mfl_code' => $facility->mfl_code
                    ];
                })
            ];
        });

        return response()->json($details);
    }

    public function getEnhancedInsights(Request $request) {
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', date('Y'));

        $cacheKey = "enhanced_insights_{$type}_{$year}";

        return Cache::remember($cacheKey, 1800, function () use ($type, $year) {
                    // Get facility type coverage nationally
                    $facilityTypeCoverage = $this->getCoverageByFacilityType(new Request(['type' => $type, 'year' => $year]));
                    $departmentCoverage = $this->getCoverageByDepartment(new Request(['type' => $type, 'year' => $year]));
                    $cadreCoverage = $this->getCoverageByCadre(new Request(['type' => $type, 'year' => $year]));

                    $insights = [];
                    $typeName = $type === 'global_training' ? 'training' : 'mentorship';

                    // Most covered facility type insight
                    $mostCoveredFacilityType = $facilityTypeCoverage->sortByDesc('coverage_percentage')->first();
                    if ($mostCoveredFacilityType && $mostCoveredFacilityType['coverage_percentage'] > 0) {
                        $insights[] = [
                            'type' => 'success',
                            'title' => "Best Covered: {$mostCoveredFacilityType['name']}",
                            'description' => "{$mostCoveredFacilityType['coverage_percentage']}% of {$mostCoveredFacilityType['name']} facilities have {$typeName} programs ({$mostCoveredFacilityType['covered']}/{$mostCoveredFacilityType['total']})"
                        ];
                    }

                    // Least covered facility type insight
                    $leastCoveredFacilityType = $facilityTypeCoverage->sortBy('coverage_percentage')->first();
                    if ($leastCoveredFacilityType && $leastCoveredFacilityType['uncovered'] > 0) {
                        $insights[] = [
                            'type' => 'warning',
                            'title' => "Biggest Gap: {$leastCoveredFacilityType['name']}",
                            'description' => "Only {$leastCoveredFacilityType['coverage_percentage']}% coverage. {$leastCoveredFacilityType['uncovered']} {$leastCoveredFacilityType['name']} facilities need {$typeName}"
                        ];
                    }

                    // Most covered department insight
                    $mostCoveredDepartment = $departmentCoverage->sortByDesc('coverage_percentage')->first();
                    if ($mostCoveredDepartment && $mostCoveredDepartment['coverage_percentage'] > 0) {
                        $insights[] = [
                            'type' => 'info',
                            'title' => "Top Department: {$mostCoveredDepartment['name']}",
                            'description' => "{$mostCoveredDepartment['coverage_percentage']}% of {$mostCoveredDepartment['name']} staff have received {$typeName} ({$mostCoveredDepartment['trained']}/{$mostCoveredDepartment['total']})"
                        ];
                    }

                    // Least covered cadre insight
                    $leastCoveredCadre = $cadreCoverage->sortBy('coverage_percentage')->first();
                    if ($leastCoveredCadre && $leastCoveredCadre['untrained'] > 0) {
                        $insights[] = [
                            'type' => 'alert',
                            'title' => "Priority Cadre: {$leastCoveredCadre['name']}",
                            'description' => "Critical gap: Only {$leastCoveredCadre['coverage_percentage']}% coverage. {$leastCoveredCadre['untrained']} {$leastCoveredCadre['name']} professionals need {$typeName}"
                        ];
                    }

                    // Coverage balance insight
                    $facilityTypeRange = $facilityTypeCoverage->max('coverage_percentage') - $facilityTypeCoverage->min('coverage_percentage');
                    if ($facilityTypeRange > 50) {
                        $insights[] = [
                            'type' => 'warning',
                            'title' => 'Uneven Facility Coverage',
                            'description' => "{$facilityTypeRange}% gap between most and least covered facility types indicates need for targeted approach"
                        ];
                    }

                    return $insights;
                });
    }

    private function calculateIntensity($participantCount, $facilitiesCovered) {
        // Base calculation: participant density + facility coverage factor
        $participantScore = min(($participantCount / 100) * 5, 5); // Max 5 points for participants
        $facilityScore = min($facilitiesCovered * 0.5, 5); // Max 5 points for facilities
        // Combined intensity score (0-10)
        $intensity = $participantScore + $facilityScore;

        // Round to nearest integer
        return (int) round($intensity);
    }

    public function getAvailableYears(Request $request) {
        $type = $request->get('type', 'global_training');

        $years = Training::where('type', $type)
                ->selectRaw('DISTINCT YEAR(start_date) as year')
                ->whereNotNull('start_date')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        return response()->json($years);
    }
}
