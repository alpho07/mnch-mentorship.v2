<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\County;
use App\Models\Subcounty;
use App\Models\ClassParticipant;
use App\Models\ClassAttendance;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\Facility;
use App\Models\TrainingParticipant;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\FacilityType;
use App\Services\AssessmentAnalyticsService;
use App\Services\EmoncDashboardService;
use App\Services\MentorAnalyticsDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AnalyticsDashboardController extends Controller {

    public function index(Request $request) {
        $currentYear  = Carbon::now()->year;
        $selectedYear = (string) ($request->get('year') ?? '');
        $mode         = $request->get('mode', 'mentorship');

        // ── Role-based geographic scoping ────────────────────────────────
        // Pre-fill county/subcounty/facility filters for non-above-site users
        // when those params aren't explicitly set (e.g. via the Filament embed).
        $user = auth()->user();
        if ($user && ! $user->isAboveSite()) {
            if ($user->hasRole('county_mentor_lead') && ! $request->filled('county_id')) {
                $request->merge(['county_id' => $user->counties()->value('counties.id')]);
            } elseif ($user->hasRole('subcounty_mentor_lead') && ! $request->filled('subcounty_id')) {
                $sub = $user->subcounties()->first();
                if ($sub) {
                    $request->merge([
                        'county_id'    => $sub->county_id,
                        'subcounty_id' => $sub->id,
                    ]);
                }
            } elseif (! $request->filled('facility_id')) {
                $facilityId = $user->facility_id ?? $user->facilities()->value('facilities.id');
                if ($facilityId) {
                    $facility = Facility::with('subcounty')->find($facilityId);
                    if ($facility) {
                        $request->merge([
                            'county_id'    => $facility->subcounty?->county_id,
                            'subcounty_id' => $facility->subcounty_id,
                            'facility_id'  => $facilityId,
                        ]);
                    }
                }
            }
        }
        // ─────────────────────────────────────────────────────────────────

        // Get available years
        $availableYears = Training::selectRaw('YEAR(start_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        if ($mode === 'mentor') {
            $mentorFilters = [
                'program_id'    => $request->get('program_id'),
                'county_id'     => $request->get('county_id'),
                'subcounty_id'  => $request->get('subcounty_id'),
                'facility_id'   => $request->get('facility_id'),
                'mentor_id'     => $request->get('mentor_id'),
                'cadre_id'      => $request->get('cadre_id'),
                'department_id' => $request->get('department_id'),
            ];

            $data            = app(MentorAnalyticsDashboardService::class)->build(auth()->user(), $mentorFilters);
            $mentorKpis      = $data['kpis'];
            $mentorMatrix    = $data['matrix'];
            $mentorCharts    = $data['chartData'];
            $mentorInsights  = $data['insights'];
            $mentorExceptions = $data['exceptions'];

            // Filter option lists
            $mentorPrograms    = \App\Models\Program::orderBy('name')->get(['id', 'name']);
            $mentorCounties    = \App\Models\County::orderBy('name')->get(['id', 'name']);
            $mentorSubcounties = $request->filled('county_id')
                ? \App\Models\Subcounty::where('county_id', $request->county_id)->orderBy('name')->get(['id', 'name'])
                : collect();
            $mentorFacilities  = $request->filled('subcounty_id')
                ? \App\Models\Facility::where('subcounty_id', $request->subcounty_id)->orderBy('name')->get(['id', 'name'])
                : collect();
            $mentorCadres      = \App\Models\Cadre::orderBy('name')->get(['id', 'name']);
            $mentorDepartments = \App\Models\Department::orderBy('name')->get(['id', 'name']);

            // All lead mentors for mentor dropdown (unfiltered — needed to build the select)
            $mentorUsers = \App\Models\Training::where('type', 'facility_mentorship')->dashboardVisible()
                ->whereNotNull('mentor_id')
                ->with('mentor:id,name')
                ->get()
                ->pluck('mentor')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values();

            return view('analytics.dashboard.index', compact(
                'mode', 'selectedYear', 'availableYears',
                'mentorKpis', 'mentorMatrix', 'mentorCharts', 'mentorInsights', 'mentorExceptions',
                'mentorFilters', 'mentorPrograms', 'mentorCounties', 'mentorSubcounties',
                'mentorFacilities', 'mentorCadres', 'mentorDepartments', 'mentorUsers'
            ));
        }

        if ($mode === 'emonc') {
            $emoncData           = app(EmoncDashboardService::class)->build(auth()->user());
            $emoncKpis           = $emoncData['kpis'];
            $emoncMatrix         = $emoncData['completionMatrix'];
            $emoncChartData      = $emoncData['chartData'];
            $emoncPendingActions = $emoncData['pendingActions'];
            $emoncExtendedStats  = $emoncData['extendedStats'];
            $emoncInsights       = $emoncData['insights'];

            return view('analytics.dashboard.index', compact(
                'mode', 'selectedYear', 'availableYears',
                'emoncKpis', 'emoncMatrix', 'emoncChartData',
                'emoncPendingActions', 'emoncExtendedStats', 'emoncInsights'
            ));
        }

        if ($mode === 'assessment') {
            $selectedSubcounty = $request->get('subcounty_id');
            $selectedFacility  = $request->get('facility_id');

            $filters = [
                'year'            => $selectedYear,
                'county_id'       => $request->get('county_id'),
                'subcounty_id'    => $selectedSubcounty,
                'facility_id'     => $selectedFacility,
                'assessment_type' => $request->get('assessment_type'),
            ];

            $assessmentService      = app(AssessmentAnalyticsService::class);
            $summaryStats           = $assessmentService->getSummaryStats($filters);
            $chartData              = $assessmentService->getChartData($filters);
            $facilitiesReadiness    = $assessmentService->getFacilitiesReadiness($filters);
            $insights               = $assessmentService->generateInsights($summaryStats);
            $skillsMentorshipStatus = $assessmentService->summarizeSkillsLabMentorshipStatus($facilitiesReadiness);
            $selectedCounty         = $filters['county_id'];
            $selectedAssessmentType = $filters['assessment_type'];

            // Scope county list for non-above-site users
            if ($user && ! $user->isAboveSite()) {
                $allowedCountyIds = $user->scopedCountyIds()->toArray();
                $counties = County::whereIn('id', $allowedCountyIds)->orderBy('name')->get(['id', 'name']);
            } else {
                $counties = County::orderBy('name')->get(['id', 'name']);
            }

            $subcounties = $selectedCounty
                ? Subcounty::where('county_id', $selectedCounty)->orderBy('name')->get(['id', 'name'])
                : collect();

            $facilities = $selectedSubcounty
                ? Facility::whereNull('deleted_at')->where('subcounty_id', $selectedSubcounty)->orderBy('name')->get(['id', 'name'])
                : collect();

            $availableYears = Assessment::selectRaw('YEAR(assessment_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

            return view('analytics.dashboard.index', compact(
                'mode', 'selectedYear', 'availableYears',
                'summaryStats', 'chartData', 'facilitiesReadiness', 'insights',
                'skillsMentorshipStatus',
                'counties', 'selectedCounty', 'selectedAssessmentType',
                'subcounties', 'selectedSubcounty', 'facilities', 'selectedFacility'
            ));
        }

        // ── County / Subcounty / Facility filters ──────────────────────────
        $selectedCounty    = $request->get('county_id');
        $selectedSubcounty = $request->get('subcounty_id');
        $selectedFacility  = $request->get('facility_id');

        $counties      = $this->getCountiesData($selectedYear, $mode, null, $selectedCounty, $selectedSubcounty, $selectedFacility);
        $trainingsList = $this->getTrainingsList($selectedYear, $mode, $selectedCounty, $selectedSubcounty, $selectedFacility);
        $summaryStats  = $this->getSummaryStats($selectedYear, $mode, null, $selectedCounty, $selectedSubcounty, $selectedFacility);
        $chartData     = $this->getChartData($selectedYear, $mode, null, $selectedCounty, $selectedSubcounty, $selectedFacility);
        $extendedStats = $this->getExtendedStats($selectedYear, $mode, $selectedCounty, $selectedSubcounty, $selectedFacility);
        $insights      = $this->generateInsights($summaryStats, $extendedStats, $mode);

        // Scope counties list to the user's visible geography
        if ($user && ! $user->isAboveSite()) {
            $allowedCountyIds = $user->scopedCountyIds()->toArray();
            $counties = collect($counties)->filter(fn ($c) => in_array($c->id, $allowedCountyIds))->values();
        }

        // Filter dropdown option lists — only counties/subcounties/facilities
        // where a qualifying training has actually taken place (mentorship
        // mode: non-pilot, active/live mentorships only — pilot/live status
        // has no meaning for global trainings, so that mode just requires
        // at least one training). Cascades by parent, same as the
        // assessment/mentor filters.
        $qualifyingFacilityIds = $this->facilityIdsWithQualifyingTrainings($mode);

        $geoCountiesQuery = County::whereHas('facilities', fn ($q) => $q->whereIn('facilities.id', $qualifyingFacilityIds));
        if ($user && ! $user->isAboveSite()) {
            $geoCountiesQuery->whereIn('counties.id', $user->scopedCountyIds());
        }
        $geoCounties = $geoCountiesQuery->orderBy('name')->get(['id', 'name']);

        $geoSubcounties = $selectedCounty
            ? Subcounty::where('county_id', $selectedCounty)
                ->whereHas('facilities', fn ($q) => $q->whereIn('facilities.id', $qualifyingFacilityIds))
                ->orderBy('name')->get(['id', 'name'])
            : collect();
        $geoFacilities = $selectedSubcounty
            ? Facility::whereNull('deleted_at')->where('subcounty_id', $selectedSubcounty)
                ->whereIn('id', $qualifyingFacilityIds)
                ->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('analytics.dashboard.index', compact(
                        'counties', 'trainingsList', 'summaryStats', 'chartData',
                        'availableYears', 'selectedYear', 'mode',
                        'extendedStats', 'insights',
                        'geoCounties', 'geoSubcounties', 'geoFacilities',
                        'selectedCounty', 'selectedSubcounty', 'selectedFacility'
                ));
    }

    public function geojson(Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        $trainingId = $request->get('training_id', '');
        $selectedCounty = $request->get('county_id');
        $selectedSubcounty = $request->get('subcounty_id');
        $selectedFacility = $request->get('facility_id');

        // Load the actual GeoJSON file
        $path = public_path('kenyan-counties.geojson');
        if (!File::exists($path)) {
            return response()->json([
                        'type' => 'FeatureCollection',
                        'features' => [],
                        'error' => 'GeoJSON file not found'
                            ], 404);
        }

        // Get the GeoJSON data
        $geojsonContent = File::get($path);
        $geojsonData = json_decode($geojsonContent, true);

        if (!$geojsonData || !isset($geojsonData['features'])) {
            return response()->json([
                        'type' => 'FeatureCollection',
                        'features' => [],
                        'error' => 'Invalid GeoJSON format'
                            ], 400);
        }

        // Get counties data with statistics
        $counties = $this->getCountiesData($selectedYear, $mode, $trainingId, $selectedCounty, $selectedSubcounty, $selectedFacility);

        // Create a mapping of county statistics by normalized name
        $countyStatsMap = [];
        foreach ($counties as $county) {
            // Calculate total programs for this county properly
            $totalPrograms = $this->getCountyPrograms($county->id, $selectedYear, $mode, $trainingId, $selectedSubcounty, $selectedFacility);

            // Get total facilities with programs for this county
            if ($mode === 'training') {
                $facilitiesWithPrograms = Facility::whereHas('users.trainingParticipations.training', function ($query) use ($selectedYear, $trainingId) {
                    $query->where('type', 'global_training');
                    if (!empty($selectedYear)) {
                        $query->whereYear('start_date', $selectedYear);
                    }
                    if ($trainingId) {
                        $query->where('id', $trainingId);
                    }
                })->whereHas('subcounty', function ($query) use ($county) {
                    $query->where('county_id', $county->id);
                })
                ->when($selectedFacility, fn ($q) => $q->where('id', $selectedFacility))
                ->when(!$selectedFacility && $selectedSubcounty, fn ($q) => $q->where('subcounty_id', $selectedSubcounty))
                ->count();
            } else {
                // For mentorship, count facilities that host mentorship programs
                $facilitiesWithPrograms = Facility::whereHas('trainings', function ($query) use ($selectedYear, $trainingId) {
                    $query->where('type', 'facility_mentorship')->where('is_pilot', false);
                    if (!empty($selectedYear)) {
                        $query->whereYear('start_date', $selectedYear);
                    }
                    if ($trainingId) {
                        $query->where('id', $trainingId);
                    }
                })->whereHas('subcounty', function ($query) use ($county) {
                    $query->where('county_id', $county->id);
                })
                ->when($selectedFacility, fn ($q) => $q->where('id', $selectedFacility))
                ->when(!$selectedFacility && $selectedSubcounty, fn ($q) => $q->where('subcounty_id', $selectedSubcounty))
                ->count();
            }

            // Create multiple normalized variations for better matching
            $variations = [
                strtoupper(trim($county->name)),
                strtoupper(trim(str_replace(' ', '', $county->name))),
                strtoupper(trim(str_replace([' ', '-', "'", '.'], '', $county->name))),
                trim($county->name),
                trim(str_replace(' ', '', $county->name))
            ];

            $stats = [
                'county_id' => $county->id,
                'county_name' => $county->name,
                'coverage_percentage' => $county->coverage_percentage ?? 0,
                'total_programs' => $totalPrograms,
                'total_participants' => $county->total_participants ?? 0,
                'total_facilities' => $county->total_facilities ?? 0,
                'facilities_with_programs' => $facilitiesWithPrograms
            ];

            // Map all variations to the same stats
            foreach ($variations as $variation) {
                $countyStatsMap[$variation] = $stats;
            }
        }

        // Process each feature and merge with statistics
        $processedFeatures = [];
        foreach ($geojsonData['features'] as $feature) {
            // Get county name from the COUNTY property
            $geoCountyName = $feature['properties']['COUNTY'] ?? null;

            if ($geoCountyName) {
                // Try to match with our database county statistics
                $matched = false;

                // Try exact match first
                if (isset($countyStatsMap[$geoCountyName])) {
                    $feature['properties'] = array_merge(
                            $feature['properties'],
                            $countyStatsMap[$geoCountyName]
                    );
                    $matched = true;
                } else {
                    // Try various normalized matching approaches
                    $matchingVariations = [
                        strtoupper($geoCountyName),
                        strtolower($geoCountyName),
                        ucfirst(strtolower($geoCountyName)),
                        strtoupper(str_replace(' ', '', $geoCountyName)),
                        strtoupper(str_replace([' ', '-', "'", '.'], '', $geoCountyName))
                    ];

                    foreach ($matchingVariations as $variation) {
                        if (isset($countyStatsMap[$variation])) {
                            $feature['properties'] = array_merge(
                                    $feature['properties'],
                                    $countyStatsMap[$variation]
                            );
                            $matched = true;
                            break;
                        }
                    }
                }

                // If still no match, set default values
                if (!$matched) {
                    $feature['properties'] = array_merge($feature['properties'], [
                        'county_id' => null,
                        'county_name' => $geoCountyName,
                        'coverage_percentage' => 0,
                        'total_programs' => 0,
                        'total_participants' => 0,
                        'total_facilities' => 0,
                        'facilities_with_programs' => 0
                    ]);
                }
            } else {
                // No COUNTY property found
                $feature['properties'] = array_merge($feature['properties'], [
                    'county_id' => null,
                    'county_name' => 'Unknown County',
                    'coverage_percentage' => 0,
                    'total_programs' => 0,
                    'total_participants' => 0,
                    'total_facilities' => 0,
                    'facilities_with_programs' => 0
                ]);
            }

            $processedFeatures[] = $feature;
        }

        return response()->json([
                    'type' => 'FeatureCollection',
                    'features' => $processedFeatures
        ]);
    }

    public function county($countyId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        $selectedTraining = $request->get('training_id', ''); // This is key for training flow
        $view = $request->get('view', 'programs'); // NEW: 'programs' or 'facilities'

        if ($mode === 'training') {
            // For training mode, show facilities filtered by selected program (if any)
            $county = County::with(['subcounties', 'facilities.facilityType'])->findOrFail($countyId);
            
            if ($selectedTraining) {
                // EXISTING PATH: Program is selected - show facilities for that specific program
                $facilities = $this->getFacilitiesForSpecificTraining($countyId, $selectedTraining);
                $programs = Training::where('id', $selectedTraining)->get(); // The selected program
                $breadcrumbTitle = $programs->first()->title ?? 'Training Program';
                $view = 'facilities'; // Force facilities view when training is selected
            } elseif ($view === 'facilities') {
                // NEW PATH: No program selected, but user wants to see all training facilities in county
                return $this->countyFacilities($countyId, $request);
            } else {
                // EXISTING PATH: No program selected - show programs list
                $facilities = null; // Don't show facilities, show programs instead
                $programs = $this->getTrainingsForCounty($countyId, $selectedYear);
                $breadcrumbTitle = 'All Training Programs';
            }

            $coverageData = $this->getCoverageData($countyId, $selectedYear, $mode);

            $availableYears = Training::selectRaw('YEAR(start_date) as year')
                    ->distinct()
                    ->orderBy('year', 'desc')
                    ->pluck('year')
                    ->filter();

            $breadcrumbs = [
                ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
                ['name' => $county->name . ' County - ' . $breadcrumbTitle, 'url' => null]
            ];

            return view('analytics.dashboard.county', compact(
                            'county', 'programs', 'facilities', 'coverageData', 'availableYears',
                            'selectedYear', 'selectedTraining', 'mode', 'breadcrumbs', 'view'
                    ));
            
        } else {
            // For mentorship mode, return county mentorships JSON for AJAX
            return $this->countyMentorships($countyId, $request);
        }
    }

    /**
     * NEW METHOD: Handle county facilities view when no specific training is selected
     * Path: County -> All Training Facilities -> Individual Facility Programs -> Participants
     */
    public function countyFacilities($countyId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        
        $county = County::with(['subcounties'])->findOrFail($countyId);
        
        // Get all facilities in this county that have training participants
        $facilities = Facility::whereHas('users.trainingParticipations.training', function ($query) use ($selectedYear) {
            $query->where('type', 'global_training');
            if (!empty($selectedYear)) {
                $query->whereYear('start_date', $selectedYear);
            }
        })
        ->whereHas('subcounty', function ($query) use ($countyId) {
            $query->where('county_id', $countyId);
        })
        ->with(['subcounty', 'facilityType'])
        ->withCount([
            'users as total_participants' => function ($query) use ($selectedYear) {
                $query->whereHas('trainingParticipations.training', function ($q) use ($selectedYear) {
                    $q->where('type', 'global_training');
                    if (!empty($selectedYear)) {
                        $q->whereYear('start_date', $selectedYear);
                    }
                });
            }
        ])
        ->addSelect([
            'unique_training_programs' => function ($query) use ($selectedYear) {
                $query->selectRaw('COUNT(DISTINCT trainings.id)')
                        ->from('training_participants')
                        ->join('users', 'training_participants.user_id', '=', 'users.id')
                        ->join('trainings', 'training_participants.training_id', '=', 'trainings.id')
                        ->whereColumn('users.facility_id', 'facilities.id')
                        ->where('trainings.type', 'global_training');
                if (!empty($selectedYear)) {
                    $query->whereYear('trainings.start_date', $selectedYear);
                }
            }
        ])
        ->orderBy('total_participants', 'desc')
        ->get();

        $coverageData = $this->getCoverageData($countyId, $selectedYear, $mode);

        $availableYears = Training::selectRaw('YEAR(start_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index', ['mode' => 'training'])],
            ['name' => $county->name . ' County Programs', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'mode' => 'training', 'year' => $selectedYear])],
            ['name' => 'Training Facilities', 'url' => null]
        ];

        return view('analytics.dashboard.county-facilities', compact(
                        'county', 'facilities', 'coverageData', 'availableYears',
                        'selectedYear', 'mode', 'breadcrumbs'
                ));
    }

    /**
     * NEW METHOD: Handle individual facility's training programs 
     * Path: County -> Facilities -> Facility Programs -> Participants
     */
    public function facilityPrograms($countyId, $facilityId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');

        $county = County::findOrFail($countyId);
        $facility = Facility::with(['facilityType', 'subcounty'])->findOrFail($facilityId);

        // Get all training programs this facility has participated in
        $programs = Training::where('type', 'global_training')
                ->whereHas('participants.user', function ($query) use ($facilityId) {
                    $query->where('facility_id', $facilityId);
                })
                ->when(!empty($selectedYear), function ($query) use ($selectedYear) {
                    $query->whereYear('start_date', $selectedYear);
                })
                ->withCount([
                    'participants as facility_participants' => function ($query) use ($facilityId) {
                        $query->whereHas('user', function ($q) use ($facilityId) {
                            $q->where('facility_id', $facilityId);
                        });
                    }
                ])
                ->orderBy('start_date', 'desc')
                ->get();

        $availableYears = Training::selectRaw('YEAR(start_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index', ['mode' => 'training'])],
            ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'mode' => 'training', 'view' => 'facilities', 'year' => $selectedYear])],
            ['name' => $facility->name . ' Programs', 'url' => null]
        ];

        return view('analytics.dashboard.facility-programs', compact(
                        'county', 'facility', 'programs', 'availableYears',
                        'selectedYear', 'mode', 'breadcrumbs'
                ));
    }

    // County mentorships API for sidebar (mentorship mode)
    public function countyMentorships($countyId, Request $request) {
        $selectedYear = $request->get('year', '');
        
        $county = County::findOrFail($countyId);

        // Get facilities with mentorship programs
        $facilities = Facility::whereHas('trainings', function ($query) use ($selectedYear) {
            $query->where('type', 'facility_mentorship')->where('is_pilot', false);
            if (!empty($selectedYear)) {
                $query->whereYear('start_date', $selectedYear);
            }
        })
        ->whereHas('subcounty', function ($query) use ($countyId) {
            $query->where('county_id', $countyId);
        })
        ->with(['subcounty', 'facilityType'])
        ->withCount([
            'trainings as mentorship_count' => function ($query) use ($selectedYear) {
                $query->where('type', 'facility_mentorship')->where('is_pilot', false);
                if (!empty($selectedYear)) {
                    $query->whereYear('start_date', $selectedYear);
                }
            }
        ])
        ->addSelect([
            'total_mentees' => function ($query) use ($selectedYear) {
                $query->selectRaw('COUNT(DISTINCT class_participants.user_id)')
                        ->from('trainings')
                        ->join('mentorship_classes', 'trainings.id', '=', 'mentorship_classes.training_id')
                        ->join('class_participants', 'mentorship_classes.id', '=', 'class_participants.mentorship_class_id')
                        ->whereColumn('trainings.facility_id', 'facilities.id')
                        ->where('trainings.type', 'facility_mentorship')->where('trainings.is_pilot', false);
                if (!empty($selectedYear)) {
                    $query->whereYear('trainings.start_date', $selectedYear);
                }
            }
        ])
        ->get();

        // Calculate summary stats
        $totalMentorships = $facilities->sum('mentorship_count');
        $totalMentees = $facilities->sum('total_mentees');
        $totalFacilities = $facilities->count();

        return response()->json([
            'county' => [
                'id' => $county->id,
                'name' => $county->name
            ],
            'summary' => [
                'total_facilities' => $totalFacilities,
                'total_mentorships' => $totalMentorships,
                'total_mentees' => $totalMentees
            ],
            'facilities' => $facilities->map(function ($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'subcounty' => $facility->subcounty->name ?? 'N/A',
                    'facility_type' => $facility->facilityType->name ?? 'N/A',
                    'mfl_code' => $facility->mfl_code,
                    'mentorship_count' => $facility->mentorship_count ?? 0,
                    'total_mentees' => $facility->total_mentees ?? 0
                ];
            })
        ]);
    }

    // Facility mentorships view (mentorship mode)
    public function facilityMentorships($countyId, $facilityId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'mentorship');

        $county = County::findOrFail($countyId);
        $facility = Facility::with(['facilityType', 'subcounty'])->findOrFail($facilityId);

        // Get mentorship programs for this facility
        $mentorships = Training::where('type', 'facility_mentorship')->where('is_pilot', false)
                ->where('facility_id', $facilityId)
                ->when(!empty($selectedYear), function ($query) use ($selectedYear) {
                    $query->whereYear('start_date', $selectedYear);
                })
                ->with(['mentor'])
                ->orderBy('start_date', 'desc')
                ->get();

        $menteeCounts = ClassParticipant::query()
                ->join('mentorship_classes', 'class_participants.mentorship_class_id', '=', 'mentorship_classes.id')
                ->whereIn('mentorship_classes.training_id', $mentorships->pluck('id'))
                ->groupBy('mentorship_classes.training_id')
                ->selectRaw('mentorship_classes.training_id, COUNT(DISTINCT class_participants.user_id) as mentees_count')
                ->pluck('mentees_count', 'training_id');

        $mentorships->each(function ($mentorship) use ($menteeCounts) {
            $mentorship->mentees_count = (int) ($menteeCounts[$mentorship->id] ?? 0);
        });

        $availableYears = Training::selectRaw('YEAR(start_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
            ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.index', ['mode' => 'mentorship'])],
            ['name' => $facility->name . ' Mentorships', 'url' => null]
        ];

        return view('analytics.dashboard.facility-mentorships', compact(
                        'county', 'facility', 'mentorships', 'availableYears',
                        'selectedYear', 'mode', 'breadcrumbs'
                ));
    }

    public function program($countyId, $programId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');

        $county = County::findOrFail($countyId);
        $program = Training::findOrFail($programId);

        if ($mode === 'training') {
            // For training: show facilities that participated in this specific program
            $facilities = $this->getFacilitiesForProgram($countyId, $programId, $mode);
            $programStats = $this->getProgramStats($countyId, $programId, $mode);

            $availableYears = Training::selectRaw('YEAR(start_date) as year')
                    ->distinct()
                    ->orderBy('year', 'desc')
                    ->pluck('year')
                    ->filter();

            $breadcrumbs = [
                ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
                ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'year' => $selectedYear, 'mode' => $mode, 'training_id' => $programId])],
                ['name' => $program->title, 'url' => null]
            ];

            return view('analytics.dashboard.county-program', compact(
                            'county', 'program', 'facilities', 'programStats', 'availableYears',
                            'selectedYear', 'mode', 'breadcrumbs'
                    ));
        } else {
            // For mentorships, go directly to participants
            $participants = $this->getParticipantsForProgram($programId);
            
            $availableYears = Training::selectRaw('YEAR(start_date) as year')
                    ->distinct()
                    ->orderBy('year', 'desc')
                    ->pluck('year')
                    ->filter();

            $breadcrumbs = [
                ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
                ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.index', ['mode' => 'mentorship'])],
                ['name' => $program->title . ' Participants', 'url' => null]
            ];

            return view('analytics.dashboard.mentorship-participants', compact(
                            'county', 'program', 'participants', 'availableYears',
                            'selectedYear', 'mode', 'breadcrumbs'
                    ));
        }
    }

    public function facility($countyId, $programId, $facilityId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');

        $county = County::findOrFail($countyId);
        $program = Training::findOrFail($programId);
        $facility = Facility::with(['facilityType', 'subcounty'])->findOrFail($facilityId);

        $participants = $this->getParticipantsForFacility($programId, $facilityId);
        $facilityStats = $this->getFacilityStats($programId, $facilityId);

        $availableYears = Training::selectRaw('YEAR(start_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
            ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'year' => $selectedYear, 'mode' => $mode, 'training_id' => $programId])],
            ['name' => $program->title, 'url' => route('analytics.dashboard.program', ['county' => $countyId, 'program' => $programId, 'year' => $selectedYear, 'mode' => $mode])],
            ['name' => $facility->name, 'url' => null]
        ];

        return view('analytics.dashboard.facility', compact(
                        'county', 'program', 'facility', 'participants', 'facilityStats',
                        'availableYears', 'selectedYear', 'mode', 'breadcrumbs'
                ));
    }
    
    public function mentorshipParticipant($countyId, $programId, $participantId, Request $request) {
    $selectedYear = $request->get('year', '');
    $mode = $request->get('mode', 'mentorship');

    $county = County::findOrFail($countyId);
    $program = Training::findOrFail($programId);
    
    $participant = ClassParticipant::with([
                'user.facility', 'user.department', 'user.cadre',
                'assessmentResults.moduleAssessment'
            ])->findOrFail($participantId);

    $participant->completion_status = match ($participant->status) {
        'completed' => 'completed',
        'dropped' => 'dropped',
        default => 'in_progress',
    };

    // Get the facility from the participant's user
    $facility = $participant->user->facility;

    // Get training history
    $trainingHistory = TrainingParticipant::where('user_id', $participant->user_id)
            ->where('id', '!=', $participantId)
            ->with(['training', 'assessmentResults'])
            ->latest('registration_date')
            ->get();

    $availableYears = Training::selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter();

    $breadcrumbs = [
        ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
        ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.index', ['mode' => 'mentorship'])],
        ['name' => $program->title . ' Participants', 'url' => route('analytics.dashboard.program', ['county' => $countyId, 'program' => $programId, 'year' => $selectedYear, 'mode' => $mode])],
        ['name' => $participant->user->full_name, 'url' => null]
    ];

    return view('analytics.dashboard.participant', compact(
                    'county', 'program', 'facility', 'participant', 'trainingHistory',
                    'availableYears', 'selectedYear', 'mode', 'breadcrumbs'
            ));
}

   public function participant($countyId, $programId, $facilityId, $participantId, Request $request) {
    $selectedYear = $request->get('year', '');
    $mode = $request->get('mode', 'training');

    $county = County::findOrFail($countyId);
    $program = Training::findOrFail($programId);
    $facility = Facility::findOrFail($facilityId);
    $participant = TrainingParticipant::with([
                'user.facility', 'user.department', 'user.cadre',
                'assessmentResults.assessmentCategory'
            ])->findOrFail($participantId);

    // Get training history
    $trainingHistory = TrainingParticipant::where('user_id', $participant->user_id)
            ->where('id', '!=', $participantId)
            ->with(['training', 'assessmentResults'])
            ->latest('registration_date')
            ->get();

    $availableYears = Training::selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter();

    // Only for training mode (mentorship uses mentorshipParticipant method)
    $breadcrumbs = [
        ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
        ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'year' => $selectedYear, 'mode' => $mode, 'training_id' => $programId])],
        ['name' => $program->title, 'url' => route('analytics.dashboard.program', ['county' => $countyId, 'program' => $programId, 'year' => $selectedYear, 'mode' => $mode])],
        ['name' => $facility->name, 'url' => route('analytics.dashboard.facility', ['county' => $countyId, 'program' => $programId, 'facility' => $facilityId, 'year' => $selectedYear, 'mode' => $mode])],
        ['name' => $participant->user->full_name, 'url' => null]
    ];

    return view('analytics.dashboard.participant', compact(
                    'county', 'program', 'facility', 'participant', 'trainingHistory',
                    'availableYears', 'selectedYear', 'mode', 'breadcrumbs'
            ));
}

    // AJAX endpoints (existing functionality preserved)
    public function getCountyData(Request $request) {
        // Existing functionality preserved
        return response()->json(['message' => 'Method not implemented yet']);
    }

    public function getCoverageCharts(Request $request) {
        // Existing functionality preserved
        return response()->json(['message' => 'Method not implemented yet']);
    }

    public function exportData(Request $request) {
        // Existing functionality preserved
        return response()->json(['message' => 'Method not implemented yet']);
    }

    public function getTrainingData(Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        $trainingId = $request->get('training_id', '');
        $countyId = $request->get('county_id');
        $subcountyId = $request->get('subcounty_id');
        $facilityId = $request->get('facility_id');

        try {
            // Get chart data
            $chartData = $this->getChartDataSimple($selectedYear, $mode, $trainingId, $countyId, $subcountyId, $facilityId);

            // Get summary stats
            $summaryStats = $this->getSummaryStatsSimple($selectedYear, $mode, $trainingId, $countyId, $subcountyId, $facilityId);

            return response()->json([
                        'success' => true,
                        'chartData' => $chartData,
                        'summaryStats' => $summaryStats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                        'success' => false,
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    // Helper Methods
    private function getFacilitiesForSpecificTraining($countyId, $trainingId) {
        return Facility::whereHas('users.trainingParticipations', function ($query) use ($trainingId) {
                    $query->where('training_id', $trainingId);
                })
                ->whereHas('subcounty', function ($query) use ($countyId) {
                    $query->where('county_id', $countyId);
                })
                ->with(['subcounty', 'facilityType'])
                ->withCount([
                    'users as participants_count' => function ($query) use ($trainingId) {
                        $query->whereHas('trainingParticipations', function ($q) use ($trainingId) {
                            $q->where('training_id', $trainingId);
                        });
                    }
                ])
                ->get();
    }

    // Updated counties data method
    /**
     * Narrow a Facility (or facility-relation) query builder to the most
     * specific of facility_id / subcounty_id / county_id that was selected —
     * mirrors the drill-down semantics already used for the assessment and
     * mentor analytics filters.
     */
    private function applyFacilityGeoScope($query, $countyId = null, $subcountyId = null, $facilityId = null): void {
        if ($facilityId) {
            $query->where('id', $facilityId);
        } elseif ($subcountyId) {
            $query->where('subcounty_id', $subcountyId);
        } elseif ($countyId) {
            $query->whereHas('subcounty', fn ($s) => $s->where('county_id', $countyId));
        }
    }

    /**
     * A mentorship training only counts as a real, live mentorship when it
     * is active, non-pilot, AND has at least one mentee actually enrolled —
     * a training with no mentees isn't a "full live mentorship" even if its
     * status/pilot flag say otherwise. Applies the constraint to a Training
     * query builder already scoped to type = facility_mentorship.
     */
    private function applyLiveMentorshipConstraint($query): void {
        $query->dashboardVisible()
            ->whereIn('status', ['active', 'completed'])
            ->whereHas('mentorshipClasses.participants', fn ($q) => $q->whereIn('status', ['enrolled', 'active', 'completed']));
    }

    /**
     * Facility IDs where a qualifying training has actually taken place, for
     * populating the geo filter dropdowns. Pilot/live status (and having an
     * actual mentee) is a facility_mentorship-only concept — global
     * trainings have no such distinction, so training mode just requires at
     * least one training.
     */
    private function facilityIdsWithQualifyingTrainings(string $mode): array {
        if ($mode === 'training') {
            return Facility::whereHas('users.trainingParticipations.training', fn ($q) => $q->where('type', 'global_training'))
                ->pluck('id')->all();
        }

        return Facility::whereHas('trainings', function ($q) {
                $q->where('type', 'facility_mentorship');
                $this->applyLiveMentorshipConstraint($q);
            })
            ->pluck('id')->all();
    }

    private function getCountiesData($year, $mode, $trainingId = null, $countyId = null, $subcountyId = null, $facilityId = null) {
        $geoScope = fn ($query) => $this->applyFacilityGeoScope($query, $countyId, $subcountyId, $facilityId);

        if ($mode === 'training') {
            // Existing training logic
            $query = County::withCount([
                'facilities as total_facilities' => $geoScope,
                'facilities as facilities_with_programs' => function ($query) use ($year, $trainingId, $geoScope) {
                    $query->whereHas('users.trainingParticipations.training', function ($q) use ($year, $trainingId) {
                        $q->where('type', 'global_training');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $q->where('id', $trainingId);
                        }
                    });
                    $geoScope($query);
                }
            ]);
        } else {
            // Mentorship logic - get counties based on facilities hosting mentorship programs
            $query = County::withCount([
                'facilities as total_facilities' => $geoScope,
                'facilities as facilities_with_programs' => function ($query) use ($year, $trainingId, $geoScope) {
                    // Facilities that have live mentorship programs
                    $query->whereHas('trainings', function ($q) use ($year, $trainingId) {
                        $q->where('type', 'facility_mentorship');
                        $this->applyLiveMentorshipConstraint($q);
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $q->where('id', $trainingId);
                        }
                    });
                    $geoScope($query);
                }
            ]);
        }

        return $query->get()->map(function ($county) use ($year, $mode, $trainingId, $subcountyId, $facilityId) {
            $county->coverage_percentage = $county->total_facilities > 0 ?
                round(($county->facilities_with_programs / $county->total_facilities) * 100, 1) : 0;
            $county->total_participants = $this->getCountyParticipants($county->id, $year, $mode, $trainingId, $subcountyId, $facilityId);
            return $county;
        });
    }

    private function getTrainingsList($year, $mode, $countyId = null, $subcountyId = null, $facilityId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        $query = Training::where('type', $trainingType);
        if ($mode !== 'training') {
            $this->applyLiveMentorshipConstraint($query);
        }

        if (!empty($year)) {
            $query->whereYear('start_date', $year);
        }

        if ($countyId || $subcountyId || $facilityId) {
            $geoScope = fn ($q) => $this->applyFacilityGeoScope($q, $countyId, $subcountyId, $facilityId);
            if ($mode === 'training') {
                $query->whereHas('participants.user.facility', $geoScope);
            } else {
                $query->whereHas('facility', $geoScope);
            }
        }

        $trainings = $query->withCount([
                            'participants as total_participants'
                        ])
                        ->addSelect([
                            'facilities_count' => function ($query) use ($mode) {
                                if ($mode === 'training') {
                                    // For training: count distinct facilities with participants
                                    $query->selectRaw('COUNT(DISTINCT users.facility_id)')
                                            ->from('training_participants')
                                            ->join('users', 'training_participants.user_id', '=', 'users.id')
                                            ->whereColumn('training_participants.training_id', 'trainings.id');
                                } else {
                                    // For mentorship: count is always 1 (the facility hosting the mentorship)
                                    $query->selectRaw('1')
                                            ->whereColumn('trainings.facility_id', 'trainings.facility_id');
                                }
                            }
                        ])
                        ->with(['facility', 'county', 'partner'])
                        ->orderBy('start_date', 'desc')
                        ->get();

        if ($mode === 'mentorship' && $trainings->isNotEmpty()) {
            $menteeCounts = ClassParticipant::query()
                ->join('mentorship_classes', 'class_participants.mentorship_class_id', '=', 'mentorship_classes.id')
                ->whereIn('mentorship_classes.training_id', $trainings->pluck('id'))
                ->groupBy('mentorship_classes.training_id')
                ->selectRaw('mentorship_classes.training_id, COUNT(DISTINCT class_participants.user_id) as total_participants')
                ->pluck('total_participants', 'training_id');

            $trainings->each(function ($training) use ($menteeCounts) {
                $training->total_participants = (int) ($menteeCounts[$training->id] ?? 0);
            });
        }

        return $trainings->map(function ($training) {
                            // Calculate coverage percentage
                            $totalTargetFacilities = $this->getTotalTargetFacilities($training);
                            $training->coverage_percentage = $totalTargetFacilities > 0 ? 
                                round(($training->facilities_count / $totalTargetFacilities) * 100, 1) : 0;

                            // Get involved counties
                            $training->involved_counties = $this->getTrainingCounties($training->id);

                            return $training;
                        });
    }

    // Updated summary stats method
    private function getSummaryStats($year, $mode, $trainingId = null, $countyId = null, $subcountyId = null, $facilityId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        $geoScope = fn ($q) => $this->applyFacilityGeoScope($q, $countyId, $subcountyId, $facilityId);
        $hasGeoFilter = $countyId || $subcountyId || $facilityId;

        $totalProgramsQuery = Training::where('type', $trainingType);
        if ($mode !== 'training') {
            $this->applyLiveMentorshipConstraint($totalProgramsQuery);
        }
        if (!empty($year)) {
            $totalProgramsQuery->whereYear('start_date', $year);
        }
        if ($trainingId) {
            $totalProgramsQuery->where('id', $trainingId);
        }
        if ($hasGeoFilter) {
            if ($mode === 'training') {
                $totalProgramsQuery->whereHas('participants.user.facility', $geoScope);
            } else {
                $totalProgramsQuery->whereHas('facility', $geoScope);
            }
        }
        $totalPrograms = $totalProgramsQuery->count();

        if ($mode === 'training') {
            $totalParticipantsQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
                $query->where('type', $trainingType);
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
            if ($hasGeoFilter) {
                $totalParticipantsQuery->whereHas('user.facility', $geoScope);
            }
            $totalParticipants = $totalParticipantsQuery->distinct('user_id')->count();
        } else {
            $totalParticipantsQuery = ClassParticipant::whereHas('mentorshipClass.training', function ($query) use ($year, $trainingId, $hasGeoFilter, $geoScope) {
                $query->where('type', 'facility_mentorship')->where('is_pilot', false)->whereIn('status', ['active', 'completed']);
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
                if ($hasGeoFilter) {
                    $query->whereHas('facility', $geoScope);
                }
            });
            $totalParticipants = $totalParticipantsQuery->distinct('user_id')->count('user_id');
        }

        // Total facilities calculation for mentorship
        if ($mode === 'training') {
            // For training: facilities with users participating in training programs
            $totalFacilitiesQuery = Facility::whereHas('users.trainingParticipations.training', function ($query) use ($year, $trainingId) {
                $query->where('type', 'global_training');
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
        } else {
            // For mentorship: facilities that host mentorship programs
            $totalFacilitiesQuery = Facility::whereHas('trainings', function ($query) use ($year, $trainingId) {
                $query->where('type', 'facility_mentorship');
                $this->applyLiveMentorshipConstraint($query);
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
        }
        if ($hasGeoFilter) {
            $geoScope($totalFacilitiesQuery);
        }
        $totalFacilities = $totalFacilitiesQuery->count();

        // Calculate facility coverage — denominator narrows to the selected
        // geography too, so the percentage stays meaningful when filtered.
        $allFacilitiesQuery = Facility::query();
        if ($hasGeoFilter) {
            $geoScope($allFacilitiesQuery);
        }
        $allFacilities = $allFacilitiesQuery->count();
        $facilityCoverage = $allFacilities > 0 ? round(($totalFacilities / $allFacilities) * 100, 1) : 0;

        return compact('totalPrograms', 'totalParticipants', 'totalFacilities', 'facilityCoverage');
    }

    // Updated chart data method
    private function getChartData($year, $mode, $trainingId = null, $countyId = null, $subcountyId = null, $facilityId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        $geoScope = fn ($q) => $this->applyFacilityGeoScope($q, $countyId, $subcountyId, $facilityId);
        $hasGeoFilter = $countyId || $subcountyId || $facilityId;

        // Narrow a query-builder (not Eloquent) joined against `users` down to
        // the selected facility/subcounty/county, joining `facilities` (and
        // `subcounties` for a county-only filter) as needed.
        $userFacilityGeoJoin = function ($q) use ($countyId, $subcountyId, $facilityId) {
            $q->join('facilities', 'facilities.id', '=', 'users.facility_id');
            if ($facilityId) {
                $q->where('facilities.id', $facilityId);
            } elseif ($subcountyId) {
                $q->where('facilities.subcounty_id', $subcountyId);
            } elseif ($countyId) {
                $q->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                    ->where('subcounties.county_id', $countyId);
            }
        };

        // Same, but for a query-builder already joined against `trainings`
        // whose own facility_id anchors the mentorship program.
        $trainingFacilityGeoJoin = function ($q) use ($countyId, $subcountyId, $facilityId) {
            if ($facilityId) {
                $q->where('trainings.facility_id', $facilityId);
            } elseif ($subcountyId) {
                $q->join('facilities', 'facilities.id', '=', 'trainings.facility_id')
                    ->where('facilities.subcounty_id', $subcountyId);
            } elseif ($countyId) {
                $q->join('facilities', 'facilities.id', '=', 'trainings.facility_id')
                    ->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                    ->where('subcounties.county_id', $countyId);
            }
        };

        if ($mode === 'training') {
            // Department data via TrainingParticipant
            $departmentData = DB::table('training_participants')
                ->join('trainings', 'trainings.id', '=', 'training_participants.training_id')
                ->join('users', 'users.id', '=', 'training_participants.user_id')
                ->join('departments', 'departments.id', '=', 'users.department_id')
                ->where('trainings.type', 'global_training')
                ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                ->when($trainingId, fn($q) => $q->where('trainings.id', $trainingId))
                ->when($hasGeoFilter, $userFacilityGeoJoin)
                ->select('departments.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                ->groupBy('departments.id', 'departments.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            $cadreData = DB::table('training_participants')
                ->join('trainings', 'trainings.id', '=', 'training_participants.training_id')
                ->join('users', 'users.id', '=', 'training_participants.user_id')
                ->join('assessment_cadres', 'assessment_cadres.id', '=', 'users.cadre_id')
                ->where('trainings.type', 'global_training')
                ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                ->when($trainingId, fn($q) => $q->where('trainings.id', $trainingId))
                ->when($hasGeoFilter, $userFacilityGeoJoin)
                ->select('assessment_cadres.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                ->groupBy('assessment_cadres.id', 'assessment_cadres.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get();
        } else {
            // Department data via ClassParticipant for mentorship
            $departmentData = DB::table('class_participants')
                ->join('mentorship_classes', 'mentorship_classes.id', '=', 'class_participants.mentorship_class_id')
                ->join('trainings', 'trainings.id', '=', 'mentorship_classes.training_id')
                ->join('users', 'users.id', '=', 'class_participants.user_id')
                ->join('departments', 'departments.id', '=', 'users.department_id')
                ->where('trainings.type', 'facility_mentorship')->where('trainings.is_pilot', false)->whereIn('trainings.status', ['active', 'completed'])
                ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                ->when($trainingId, fn($q) => $q->where('trainings.id', $trainingId))
                ->when($hasGeoFilter, $trainingFacilityGeoJoin)
                ->select('departments.name', DB::raw('COUNT(DISTINCT class_participants.user_id) as count'))
                ->groupBy('departments.id', 'departments.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            $cadreData = DB::table('class_participants')
                ->join('mentorship_classes', 'mentorship_classes.id', '=', 'class_participants.mentorship_class_id')
                ->join('trainings', 'trainings.id', '=', 'mentorship_classes.training_id')
                ->join('users', 'users.id', '=', 'class_participants.user_id')
                ->join('assessment_cadres', 'assessment_cadres.id', '=', 'users.cadre_id')
                ->where('trainings.type', 'facility_mentorship')->where('trainings.is_pilot', false)->whereIn('trainings.status', ['active', 'completed'])
                ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                ->when($trainingId, fn($q) => $q->where('trainings.id', $trainingId))
                ->when($hasGeoFilter, $trainingFacilityGeoJoin)
                ->select('assessment_cadres.name', DB::raw('COUNT(DISTINCT class_participants.user_id) as count'))
                ->groupBy('assessment_cadres.id', 'assessment_cadres.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get();
        }

        // Facility type coverage - adjusted for mentorship
        if ($mode === 'training') {
            $facilityTypeData = FacilityType::withCount([
                'facilities as total_facilities' => $geoScope,
                'facilities as facilities_with_training' => function ($query) use ($year, $trainingId, $geoScope) {
                    $query->whereHas('users.trainingParticipations.training', function ($q) use ($year, $trainingId) {
                        $q->where('type', 'global_training');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $q->where('id', $trainingId);
                        }
                    });
                    $geoScope($query);
                }
            ])->get()->map(function ($type) {
                $type->coverage_percentage = $type->total_facilities > 0 ?
                    round(($type->facilities_with_training / $type->total_facilities) * 100, 1) : 0;
                return $type;
            });
        } else {
            // For mentorship - facilities hosting live mentorship programs
            $facilityTypeData = FacilityType::withCount([
                'facilities as total_facilities' => $geoScope,
                'facilities as facilities_with_training' => function ($query) use ($year, $trainingId, $geoScope) {
                    $query->whereHas('trainings', function ($q) use ($year, $trainingId) {
                        $q->where('type', 'facility_mentorship');
                        $this->applyLiveMentorshipConstraint($q);
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $q->where('id', $trainingId);
                        }
                    });
                    $geoScope($query);
                }
            ])->get()->map(function ($type) {
                $type->coverage_percentage = $type->total_facilities > 0 ?
                    round(($type->facilities_with_training / $type->total_facilities) * 100, 1) : 0;
                return $type;
            });
        }

        // Monthly trends (last 6 months)
        $monthlyData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $count = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
                        $query->where('type', $trainingType);
                        if (!empty($year)) {
                            $query->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $query->where('id', $trainingId);
                        }
                    })
                    ->when($hasGeoFilter, fn ($q) => $q->whereHas('user.facility', $geoScope))
                    ->whereBetween('registration_date', [$monthStart, $monthEnd])
                    ->count();

            $monthlyData->push([
                'month' => $date->format('M'),
                'count' => $count
            ]);
        }

        return [
            'departments' => $departmentData,
            'cadres' => $cadreData,
            'facilityTypes' => $facilityTypeData,
            'monthly' => $monthlyData
        ];
    }

    private function getChartDataSimple($year, $mode, $trainingId = null, $countyId = null, $subcountyId = null, $facilityId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        $geoScope = fn ($q) => $this->applyFacilityGeoScope($q, $countyId, $subcountyId, $facilityId);
        $hasGeoFilter = $countyId || $subcountyId || $facilityId;
        $userFacilityGeoJoin = function ($q) use ($countyId, $subcountyId, $facilityId) {
            $q->join('facilities', 'facilities.id', '=', 'users.facility_id');
            if ($facilityId) {
                $q->where('facilities.id', $facilityId);
            } elseif ($subcountyId) {
                $q->where('facilities.subcounty_id', $subcountyId);
            } elseif ($countyId) {
                $q->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                    ->where('subcounties.county_id', $countyId);
            }
        };
        $trainingFacilityGeoJoin = function ($q) use ($countyId, $subcountyId, $facilityId) {
            if ($facilityId) {
                $q->where('trainings.facility_id', $facilityId);
            } elseif ($subcountyId) {
                $q->join('facilities', 'facilities.id', '=', 'trainings.facility_id')
                    ->where('facilities.subcounty_id', $subcountyId);
            } elseif ($countyId) {
                $q->join('facilities', 'facilities.id', '=', 'trainings.facility_id')
                    ->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                    ->where('subcounties.county_id', $countyId);
            }
        };

        // Base query for participants
        $baseQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId, $mode) {
            $query->where('type', $trainingType);
            if ($mode !== 'training') {
                $query->dashboardVisible();
            }
            if (!empty($year)) {
                $query->whereYear('start_date', $year);
            }
            if ($trainingId) {
                $query->where('id', $trainingId);
            }
        });

        // Department data
        $departmentData = collect();
        try {
            $departmentData = (clone $baseQuery)
                    ->join('users', 'training_participants.user_id', '=', 'users.id')
                    ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                    ->when($hasGeoFilter, $userFacilityGeoJoin)
                    ->select('departments.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                    ->whereNotNull('departments.name')
                    ->groupBy('departments.id', 'departments.name')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get();
        } catch (\Exception $e) {
            $departmentData = collect([
                (object) ['name' => 'No Data', 'count' => 0]
            ]);
        }

        // Cadre data
        $cadreData = collect();
        try {
            $cadreData = (clone $baseQuery)
                    ->join('users', 'training_participants.user_id', '=', 'users.id')
                    ->leftJoin('cadres', 'users.cadre_id', '=', 'cadres.id')
                    ->when($hasGeoFilter, $userFacilityGeoJoin)
                    ->select('cadres.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                    ->whereNotNull('cadres.name')
                    ->groupBy('cadres.id', 'cadres.name')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get();
        } catch (\Exception $e) {
            $cadreData = collect([
                (object) ['name' => 'No Data', 'count' => 0]
            ]);
        }

        // Facility type data for mentorship
        $facilityTypeData = collect();
        try {
            if ($mode === 'training') {
                $facilityTypeData = FacilityType::withCount([
                    'facilities as total_facilities' => $geoScope,
                    'facilities as facilities_with_training' => function ($query) use ($year, $trainingId, $geoScope) {
                        $query->whereHas('users.trainingParticipations.training', function ($q) use ($year, $trainingId) {
                            $q->where('type', 'global_training');
                            if (!empty($year)) {
                                $q->whereYear('start_date', $year);
                            }
                            if ($trainingId) {
                                $q->where('id', $trainingId);
                            }
                        });
                        $geoScope($query);
                    }
                ])->get()->map(function ($type) {
                    $type->coverage_percentage = $type->total_facilities > 0 ?
                        round(($type->facilities_with_training / $type->total_facilities) * 100, 1) : 0;
                    return $type;
                });
            } else {
                // For mentorship
                $facilityTypeData = FacilityType::withCount([
                    'facilities as total_facilities' => $geoScope,
                    'facilities as facilities_with_training' => function ($query) use ($year, $trainingId, $geoScope) {
                        $query->whereHas('trainings', function ($q) use ($year, $trainingId) {
                            $q->where('type', 'facility_mentorship');
                            $this->applyLiveMentorshipConstraint($q);
                            if (!empty($year)) {
                                $q->whereYear('start_date', $year);
                            }
                            if ($trainingId) {
                                $q->where('id', $trainingId);
                            }
                        });
                        $geoScope($query);
                    }
                ])->get()->map(function ($type) {
                    $type->coverage_percentage = $type->total_facilities > 0 ?
                        round(($type->facilities_with_training / $type->total_facilities) * 100, 1) : 0;
                    return $type;
                });
            }
        } catch (\Exception $e) {
            $facilityTypeData = collect();
        }

        // Monthly trends
        $monthlyData = collect();
        try {
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $monthStart = $date->copy()->startOfMonth();
                $monthEnd = $date->copy()->endOfMonth();

                $count = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
                            $query->where('type', $trainingType);
                            if (!empty($year)) {
                                $query->whereYear('start_date', $year);
                            }
                            if ($trainingId) {
                                $query->where('id', $trainingId);
                            }
                        })
                        ->when($hasGeoFilter, fn ($q) => $q->whereHas('user.facility', $geoScope))
                        ->whereBetween('registration_date', [$monthStart, $monthEnd])
                        ->count();

                $monthlyData->push([
                    'month' => $date->format('M'),
                    'count' => $count
                ]);
            }
        } catch (\Exception $e) {
            // Fallback data
            $monthlyData = collect([
                ['month' => 'Jan', 'count' => 0],
                ['month' => 'Feb', 'count' => 0],
                ['month' => 'Mar', 'count' => 0],
                ['month' => 'Apr', 'count' => 0],
                ['month' => 'May', 'count' => 0],
                ['month' => 'Jun', 'count' => 0]
            ]);
        }

        // Mentorship-specific chart data
        $completionDistribution = [];
        $classStatusBreakdown = [];

        if ($mode === 'mentorship') {
            $progressRows = DB::table('class_participants')
                ->join('mentorship_classes', 'mentorship_classes.id', '=', 'class_participants.mentorship_class_id')
                ->join('trainings', 'trainings.id', '=', 'mentorship_classes.training_id')
                ->leftJoin('class_modules', 'class_modules.mentorship_class_id', '=', 'mentorship_classes.id')
                ->leftJoin('mentee_module_progress', function ($join) {
                    $join->on('mentee_module_progress.class_participant_id', '=', 'class_participants.id')
                         ->on('mentee_module_progress.class_module_id', '=', 'class_modules.id');
                })
                ->where('trainings.type', 'facility_mentorship')->where('trainings.is_pilot', false)->whereIn('trainings.status', ['active', 'completed'])
                ->whereIn('class_participants.status', ['enrolled', 'active', 'completed'])
                ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                ->when($trainingId, fn($q) => $q->where('trainings.id', $trainingId))
                ->when($hasGeoFilter, $trainingFacilityGeoJoin)
                ->groupBy('class_participants.id')
                ->selectRaw('
                    class_participants.id,
                    COUNT(DISTINCT class_modules.id) as total_modules,
                    SUM(CASE WHEN mentee_module_progress.status IN ("completed", "exempted") THEN 1 ELSE 0 END) as completed_modules
                ')
                ->get();

            $buckets = ['0-25%' => 0, '26-50%' => 0, '51-75%' => 0, '76-99%' => 0, '100%' => 0];
            foreach ($progressRows as $row) {
                $total = (int) $row->total_modules;
                $completed = (int) $row->completed_modules;
                if ($total === 0) {
                    continue;
                }
                $pct = ($completed / $total) * 100;
                if ($pct >= 100) {
                    $buckets['100%']++;
                } elseif ($pct >= 76) {
                    $buckets['76-99%']++;
                } elseif ($pct >= 51) {
                    $buckets['51-75%']++;
                } elseif ($pct >= 26) {
                    $buckets['26-50%']++;
                } else {
                    $buckets['0-25%']++;
                }
            }
            $completionDistribution = $buckets;

            $classStatusBreakdown = MentorshipClass::whereHas('training', function ($q) use ($year, $trainingId, $hasGeoFilter, $geoScope) {
                    $q->where('type', 'facility_mentorship')->where('is_pilot', false);
                    if (!empty($year)) {
                        $q->whereYear('start_date', $year);
                    }
                    if ($trainingId) {
                        $q->where('id', $trainingId);
                    }
                    if ($hasGeoFilter) {
                        $q->whereHas('facility', $geoScope);
                    }
                })
                ->selectRaw("COALESCE(status, 'draft') as status, COUNT(*) as cnt")
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray();
        }

        return [
            'departments' => $departmentData,
            'cadres' => $cadreData,
            'facilityTypes' => $facilityTypeData,
            'monthly' => $monthlyData,
            'completionDistribution' => $completionDistribution,
            'classStatusBreakdown' => $classStatusBreakdown,
        ];
    }

    private function getSummaryStatsSimple($year, $mode, $trainingId = null, $countyId = null, $subcountyId = null, $facilityId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        $geoScope = fn ($q) => $this->applyFacilityGeoScope($q, $countyId, $subcountyId, $facilityId);
        $hasGeoFilter = $countyId || $subcountyId || $facilityId;

        try {
            $totalProgramsQuery = Training::where('type', $trainingType);
            if ($mode !== 'training') {
                $this->applyLiveMentorshipConstraint($totalProgramsQuery);
            }
            if (!empty($year)) {
                $totalProgramsQuery->whereYear('start_date', $year);
            }
            if ($trainingId) {
                $totalProgramsQuery->where('id', $trainingId);
            }
            if ($hasGeoFilter) {
                if ($mode === 'training') {
                    $totalProgramsQuery->whereHas('participants.user.facility', $geoScope);
                } else {
                    $totalProgramsQuery->whereHas('facility', $geoScope);
                }
            }
            $totalPrograms = $totalProgramsQuery->count();

            $totalParticipantsQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
                $query->where('type', $trainingType);
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
            if ($hasGeoFilter) {
                $totalParticipantsQuery->whereHas('user.facility', $geoScope);
            }
            $totalParticipants = $totalParticipantsQuery->distinct('user_id')->count();

            // Total facilities for mentorship
            if ($mode === 'training') {
                $totalFacilitiesQuery = Facility::whereHas('users.trainingParticipations.training', function ($query) use ($year, $trainingId) {
                    $query->where('type', 'global_training');
                    if (!empty($year)) {
                        $query->whereYear('start_date', $year);
                    }
                    if ($trainingId) {
                        $query->where('id', $trainingId);
                    }
                });
            } else {
                $totalFacilitiesQuery = Facility::whereHas('trainings', function ($query) use ($year, $trainingId) {
                    $query->where('type', 'facility_mentorship');
                    $this->applyLiveMentorshipConstraint($query);
                    if (!empty($year)) {
                        $query->whereYear('start_date', $year);
                    }
                    if ($trainingId) {
                        $query->where('id', $trainingId);
                    }
                });
            }
            if ($hasGeoFilter) {
                $geoScope($totalFacilitiesQuery);
            }
            $totalFacilities = $totalFacilitiesQuery->count();

            // Calculate facility coverage
            $allFacilitiesQuery = Facility::query();
            if ($hasGeoFilter) {
                $geoScope($allFacilitiesQuery);
            }
            $allFacilities = $allFacilitiesQuery->count();
            $facilityCoverage = $allFacilities > 0 ? round(($totalFacilities / $allFacilities) * 100, 1) : 0;

            // Mentorship attendance rate
            $attendanceRate = 0;
            if ($mode === 'mentorship') {
                $attendanceStats = DB::table('mentee_module_progress')
                    ->join('class_modules', 'class_modules.id', '=', 'mentee_module_progress.class_module_id')
                    ->join('mentorship_classes', 'mentorship_classes.id', '=', 'class_modules.mentorship_class_id')
                    ->join('trainings', 'trainings.id', '=', 'mentorship_classes.training_id')
                    ->where('trainings.type', 'facility_mentorship')->where('trainings.is_pilot', false)->whereIn('trainings.status', ['active', 'completed'])
                    ->whereNotIn('mentee_module_progress.status', ['exempted'])
                    ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                    ->when($trainingId, fn($q) => $q->where('trainings.id', $trainingId))
                    ->when($hasGeoFilter, function ($q) use ($facilityId, $subcountyId, $countyId) {
                        if ($facilityId) {
                            $q->where('trainings.facility_id', $facilityId);
                        } elseif ($subcountyId) {
                            $q->join('facilities', 'facilities.id', '=', 'trainings.facility_id')
                                ->where('facilities.subcounty_id', $subcountyId);
                        } elseif ($countyId) {
                            $q->join('facilities', 'facilities.id', '=', 'trainings.facility_id')
                                ->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                                ->where('subcounties.county_id', $countyId);
                        }
                    })
                    ->selectRaw('COUNT(*) as total_slots, SUM(CASE WHEN mentee_module_progress.status IN ("in_progress", "completed") THEN 1 ELSE 0 END) as present_slots')
                    ->first();

                if ($attendanceStats && $attendanceStats->total_slots > 0) {
                    $attendanceRate = round(($attendanceStats->present_slots / $attendanceStats->total_slots) * 100, 1);
                }
            }

            return [
                'totalPrograms' => $totalPrograms,
                'totalParticipants' => $totalParticipants,
                'totalFacilities' => $totalFacilities,
                'facilityCoverage' => $facilityCoverage,
                'attendanceRate' => $attendanceRate,
            ];
        } catch (\Exception $e) {
            return [
                'totalPrograms' => 0,
                'totalParticipants' => 0,
                'totalFacilities' => 0,
                'facilityCoverage' => 0,
                'attendanceRate' => 0,
            ];
        }
    }

    // Updated county participants method
    private function getCountyParticipants($countyId, $year, $mode, $trainingId = null, $subcountyId = null, $facilityId = null) {
        if ($mode === 'training') {
            // For training: participants from facilities in this county
            $query = TrainingParticipant::whereHas('user.facility', function ($query) use ($countyId, $subcountyId, $facilityId) {
                        $query->whereHas('subcounty', fn ($s) => $s->where('county_id', $countyId));
                        if ($subcountyId) {
                            $query->where('subcounty_id', $subcountyId);
                        }
                        if ($facilityId) {
                            $query->where('id', $facilityId);
                        }
                    })->whereHas('training', function ($query) use ($year, $trainingId) {
                $query->where('type', 'global_training');
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
        } else {
            // For mentorship: participants in live mentorship programs hosted by facilities in this county
            $query = ClassParticipant::whereHas('mentorshipClass.training', function ($query) use ($countyId, $subcountyId, $facilityId, $year, $trainingId) {
                $query->where('type', 'facility_mentorship');
                $this->applyLiveMentorshipConstraint($query);
                $query->whereHas('facility', function ($q) use ($countyId, $subcountyId, $facilityId) {
                        $q->whereHas('subcounty', fn ($s) => $s->where('county_id', $countyId));
                        if ($subcountyId) {
                            $q->where('subcounty_id', $subcountyId);
                        }
                        if ($facilityId) {
                            $q->where('id', $facilityId);
                        }
                    });
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
        }

        return $query->distinct('user_id')->count('user_id');
    }

    // Updated county programs method
    private function getCountyPrograms($countyId, $year, $mode, $trainingId = null, $subcountyId = null, $facilityId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        $query = Training::where('type', $trainingType);
        if ($mode !== 'training') {
            $this->applyLiveMentorshipConstraint($query);
        }

        if (!empty($year)) {
            $query->whereYear('start_date', $year);
        }

        if ($trainingId) {
            $query->where('id', $trainingId);
        }

        $facilityConstraint = function ($q) use ($countyId, $subcountyId, $facilityId) {
            $q->whereHas('subcounty', fn ($s) => $s->where('county_id', $countyId));
            if ($subcountyId) {
                $q->where('subcounty_id', $subcountyId);
            }
            if ($facilityId) {
                $q->where('id', $facilityId);
            }
        };

        if ($mode === 'training') {
            // For training: programs that have participants from facilities in this county
            $query->whereHas('participants.user.facility', $facilityConstraint);
        } else {
            // For mentorship: programs that are based in facilities in this county
            $query->whereHas('facility', $facilityConstraint);
        }

        return $query->count();
    }

    private function getTotalTargetFacilities($training) {
        if ($training->type === 'facility_mentorship') {
            return 1; // Only one facility for mentorship
        }

        // For global training, count facilities in participating counties
        $counties = $this->getTrainingCounties($training->id);
        return Facility::whereHas('subcounty', function ($query) use ($counties) {
                    $query->whereIn('county_id', $counties->pluck('id'));
                })->count();
    }

    private function getTrainingCounties($trainingId) {
        $training = Training::find($trainingId);
        
        if ($training && $training->type === 'facility_mentorship') {
            // For mentorship, get the county of the facility hosting the mentorship
            return County::whereHas('facilities.trainings', function ($query) use ($trainingId) {
                $query->where('id', $trainingId);
            })->get();
        } else {
            // For training, get counties with participants
            return County::whereHas('facilities.users.trainingParticipations', function ($query) use ($trainingId) {
                        $query->where('training_id', $trainingId);
                    })->get();
        }
    }

    private function getTrainingsForCounty($countyId, $year) {
        $query = Training::where('type', 'global_training')
                ->whereHas('participants.user.facility.subcounty', function ($query) use ($countyId) {
                    $query->where('county_id', $countyId);
                });

        if (!empty($year)) {
            $query->whereYear('start_date', $year);
        }

        return $query->withCount([
                            'participants as county_participants' => function ($query) use ($countyId) {
                                $query->whereHas('user.facility.subcounty', function ($q) use ($countyId) {
                                    $q->where('county_id', $countyId);
                                });
                            }
                        ])
                        ->get();
    }

    private function getMentorshipsForCounty($countyId, $year) {
        $query = Training::where('type', 'facility_mentorship')->where('is_pilot', false)
                ->whereHas('facility.subcounty', function ($query) use ($countyId) {
                    $query->where('county_id', $countyId);
                });

        if (!empty($year)) {
            $query->whereYear('start_date', $year);
        }

        $mentorships = $query->with('facility')->get();

        $menteeCounts = ClassParticipant::query()
                ->join('mentorship_classes', 'class_participants.mentorship_class_id', '=', 'mentorship_classes.id')
                ->whereIn('mentorship_classes.training_id', $mentorships->pluck('id'))
                ->groupBy('mentorship_classes.training_id')
                ->selectRaw('mentorship_classes.training_id, COUNT(DISTINCT class_participants.user_id) as mentees_count')
                ->pluck('mentees_count', 'training_id');

        $mentorships->each(function ($mentorship) use ($menteeCounts) {
            $mentorship->mentees_count = (int) ($menteeCounts[$mentorship->id] ?? 0);
        });

        return $mentorships;
    }

    private function getCoverageData($countyId, $year, $mode) {
        // Coverage by Department
        $departmentCoverage = Department::withCount([
                    'users as county_users' => function ($query) use ($countyId) {
                        $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                            $q->where('county_id', $countyId);
                        });
                    },
                    'users as trained_users' => function ($query) use ($countyId, $year, $mode) {
                        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
                        $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                            $q->where('county_id', $countyId);
                        })->whereHas('trainingParticipations.training', function ($q) use ($trainingType, $year) {
                            $q->where('type', $trainingType);
                            if (!empty($year)) {
                                $q->whereYear('start_date', $year);
                            }
                        });
                    }
                ])->get()->map(function ($dept) {
            $dept->coverage_percentage = $dept->county_users > 0 ? round(($dept->trained_users / $dept->county_users) * 100, 1) : 0;
            return $dept;
        });

        // Coverage by Cadre
        $cadreCoverage = Cadre::withCount([
                    'users as county_users' => function ($query) use ($countyId) {
                        $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                            $q->where('county_id', $countyId);
                        });
                    },
                    'users as trained_users' => function ($query) use ($countyId, $year, $mode) {
                        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
                        $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                            $q->where('county_id', $countyId);
                        })->whereHas('trainingParticipations.training', function ($q) use ($trainingType, $year) {
                            $q->where('type', $trainingType);
                            if (!empty($year)) {
                                $q->whereYear('start_date', $year);
                            }
                        });
                    }
                ])->get()->map(function ($cadre) {
            $cadre->coverage_percentage = $cadre->county_users > 0 ? round(($cadre->trained_users / $cadre->county_users) * 100, 1) : 0;
            return $cadre;
        });

        // Coverage by Facility Type for mentorship
        if ($mode === 'training') {
            $facilityTypeCoverage = FacilityType::withCount([
                        'facilities as county_facilities' => function ($query) use ($countyId) {
                            $query->whereHas('subcounty', function ($q) use ($countyId) {
                                $q->where('county_id', $countyId);
                            });
                        },
                        'facilities as facilities_with_training' => function ($query) use ($countyId, $year) {
                            $query->whereHas('subcounty', function ($q) use ($countyId) {
                                $q->where('county_id', $countyId);
                            })->whereHas('users.trainingParticipations.training', function ($q) use ($year) {
                                $q->where('type', 'global_training');
                                if (!empty($year)) {
                                    $q->whereYear('start_date', $year);
                                }
                            });
                        }
                    ])->get()->map(function ($type) {
                $type->coverage_percentage = $type->county_facilities > 0 ? 
                    round(($type->facilities_with_training / $type->county_facilities) * 100, 1) : 0;
                return $type;
            });
        } else {
            // For mentorship mode
            $facilityTypeCoverage = FacilityType::withCount([
                        'facilities as county_facilities' => function ($query) use ($countyId) {
                            $query->whereHas('subcounty', function ($q) use ($countyId) {
                                $q->where('county_id', $countyId);
                            });
                        },
                        'facilities as facilities_with_training' => function ($query) use ($countyId, $year) {
                           $query->whereHas('subcounty', function ($q) use ($countyId) {
                                $q->where('county_id', $countyId);
                            })->whereHas('trainings', function ($q) use ($year) {
                                $q->where('type', 'facility_mentorship')->where('is_pilot', false);
                                if (!empty($year)) {
                                    $q->whereYear('start_date', $year);
                                }
                            });
                        }
                    ])->get()->map(function ($type) {
                $type->coverage_percentage = $type->county_facilities > 0 ? 
                    round(($type->facilities_with_training / $type->county_facilities) * 100, 1) : 0;
                return $type;
            });
        }

        return compact('departmentCoverage', 'cadreCoverage', 'facilityTypeCoverage');
    }

    private function getFacilitiesForProgram($countyId, $programId, $mode) {
        if ($mode === 'training') {
            return Facility::whereHas('users.trainingParticipations', function ($query) use ($programId) {
                                $query->where('training_id', $programId);
                            })
                            ->whereHas('subcounty', function ($query) use ($countyId) {
                                $query->where('county_id', $countyId);
                            })
                            ->with(['subcounty', 'facilityType'])
                            ->withCount([
                                'users as participants_count' => function ($query) use ($programId) {
                                    $query->whereHas('trainingParticipations', function ($q) use ($programId) {
                                        $q->where('training_id', $programId);
                                    });
                                }
                            ])
                            ->get();
        } else {
            // For mentorship, there's typically one facility per program
            $training = Training::with('facility.facilityType', 'facility.subcounty')->findOrFail($programId);
            return collect([$training->facility]);
        }
    }

    private function getProgramStats($countyId, $programId, $mode) {
        if ($mode === 'training') {
            $totalParticipants = TrainingParticipant::where('training_id', $programId)
                            ->whereHas('user.facility.subcounty', function ($query) use ($countyId) {
                                $query->where('county_id', $countyId);
                            })->count();
        } else {
            // For mentorship, all participants are typically in the same facility/county
            $totalParticipants = ClassParticipant::whereHas('mentorshipClass.training', function ($query) use ($programId, $countyId) {
                $query->where('id', $programId)
                        ->whereHas('facility.subcounty', function ($q) use ($countyId) {
                            $q->where('county_id', $countyId);
                        });
            })->distinct('user_id')->count('user_id');
        }

        return compact('totalParticipants');
    }

    private function getParticipantsForFacility($programId, $facilityId) {
        return TrainingParticipant::where('training_id', $programId)
                        ->whereHas('user', function ($query) use ($facilityId) {
                            $query->where('facility_id', $facilityId);
                        })
                        ->with(['user.department', 'user.cadre', 'assessmentResults.assessmentCategory'])
                        ->get();
    }

    private function getParticipantsForProgram($programId) {
        return ClassParticipant::whereHas('mentorshipClass.training', function ($query) use ($programId) {
                            $query->where('id', $programId);
                        })
                        ->with(['user.facility', 'user.department', 'user.cadre', 'assessmentResults.moduleAssessment'])
                        ->get()
                        ->each(function ($participant) {
                            $participant->completion_status = match ($participant->status) {
                                'completed' => 'completed',
                                'dropped' => 'dropped',
                                default => 'in_progress',
                            };
                        });
    }

    private function getFacilityStats($programId, $facilityId) {
        $participants = $this->getParticipantsForFacility($programId, $facilityId);

        $departmentStats = $participants->groupBy('user.department.name')->map(function ($group, $dept) {
            return [
                'department' => $dept,
                'count' => $group->count()
            ];
        });

        $cadreStats = $participants->groupBy('user.cadre.name')->map(function ($group, $cadre) {
            return [
                'cadre' => $cadre,
                'count' => $group->count()
            ];
        });

        return compact('departmentStats', 'cadreStats');
    }

    private function getExtendedStats(string $year, string $mode, $countyId = null, $subcountyId = null, $facilityId = null): array {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        $geoScope = fn ($q) => $this->applyFacilityGeoScope($q, $countyId, $subcountyId, $facilityId);
        $hasGeoFilter = $countyId || $subcountyId || $facilityId;

        // Narrows a query-builder already joined against `trainings` (whose
        // own facility_id anchors a mentorship program) to the selected geo.
        $trainingFacilityGeoJoin = function ($q) use ($countyId, $subcountyId, $facilityId) {
            if ($facilityId) {
                $q->where('trainings.facility_id', $facilityId);
            } elseif ($subcountyId) {
                $q->join('facilities', 'facilities.id', '=', 'trainings.facility_id')
                    ->where('facilities.subcounty_id', $subcountyId);
            } elseif ($countyId) {
                $q->join('facilities', 'facilities.id', '=', 'trainings.facility_id')
                    ->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                    ->where('subcounties.county_id', $countyId);
            }
        };

        try {
            // 1. Status breakdown — deliberately does NOT restrict to
            // status=active (that's the whole point of this chart), but
            // still excludes pilots and mentee-less trainings.
            $statusQuery = Training::where('type', $trainingType);
            if ($mode !== 'training') {
                $statusQuery->dashboardVisible()
                    ->whereHas('mentorshipClasses.participants', fn ($q) => $q->whereIn('status', ['enrolled', 'active', 'completed']));
            }
            if (!empty($year)) $statusQuery->whereYear('start_date', $year);
            if ($hasGeoFilter) {
                if ($mode === 'training') {
                    $statusQuery->whereHas('participants.user.facility', $geoScope);
                } else {
                    $statusQuery->whereHas('facility', $geoScope);
                }
            }
            $statusBreakdown = $statusQuery
                ->selectRaw("COALESCE(status, 'unknown') as status, COUNT(*) as cnt")
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray();

            // 2. 12-month trend
            $trend12 = [];
            for ($i = 11; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $ms = $date->copy()->startOfMonth();
                $me = $date->copy()->endOfMonth();

                if ($mode === 'training') {
                    $cnt = TrainingParticipant::whereHas('training', function ($q) use ($trainingType, $year) {
                        $q->where('type', $trainingType);
                        if (!empty($year)) $q->whereYear('start_date', $year);
                    })
                    ->when($hasGeoFilter, fn ($q) => $q->whereHas('user.facility', $geoScope))
                    ->whereBetween('registration_date', [$ms, $me])->count();
                } else {
                    $cnt = Training::where('type', 'facility_mentorship')->dashboardVisible()
                        ->when($hasGeoFilter, fn ($q) => $q->whereHas('facility', $geoScope))
                        ->whereBetween('start_date', [$ms, $me])
                        ->count();
                }
                $trend12[] = ['month' => $date->format('M y'), 'short' => $date->format('M'), 'count' => $cnt];
            }

            // 3. Year-over-year comparison
            $curYear = Carbon::now()->year;
            $prevYear = $curYear - 1;
            if ($mode === 'training') {
                $curCount = TrainingParticipant::whereHas('training', fn($q) =>
                    $q->where('type', $trainingType)->whereYear('start_date', $curYear))
                    ->when($hasGeoFilter, fn ($q) => $q->whereHas('user.facility', $geoScope))
                    ->count();
                $prevCount = TrainingParticipant::whereHas('training', fn($q) =>
                    $q->where('type', $trainingType)->whereYear('start_date', $prevYear))
                    ->when($hasGeoFilter, fn ($q) => $q->whereHas('user.facility', $geoScope))
                    ->count();
            } else {
                $pilotFilter = fn ($q) => $q->dashboardVisible();
                $curCount  = Training::where('type', $trainingType)->when(true, $pilotFilter)->whereYear('start_date', $curYear)
                    ->when($hasGeoFilter, fn ($q) => $q->whereHas('facility', $geoScope))
                    ->count();
                $prevCount = Training::where('type', $trainingType)->when(true, $pilotFilter)->whereYear('start_date', $prevYear)
                    ->when($hasGeoFilter, fn ($q) => $q->whereHas('facility', $geoScope))
                    ->count();
            }
            $yoyChange = $prevCount > 0 ? round((($curCount - $prevCount) / $prevCount) * 100, 1) : 0;
            $yearComparison = ['current' => $curCount, 'previous' => $prevCount, 'change' => $yoyChange, 'year' => $curYear];

            // 4. Top 10 counties by participants
            if ($mode === 'training') {
                $topCounties = DB::table('counties')
                    ->join('subcounties', 'subcounties.county_id', '=', 'counties.id')
                    ->join('facilities', 'facilities.subcounty_id', '=', 'subcounties.id')
                    ->join('users', 'users.facility_id', '=', 'facilities.id')
                    ->join('training_participants', 'training_participants.user_id', '=', 'users.id')
                    ->join('trainings', 'trainings.id', '=', 'training_participants.training_id')
                    ->where('trainings.type', 'global_training')
                    ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                    ->when($facilityId, fn($q) => $q->where('facilities.id', $facilityId))
                    ->when(!$facilityId && $subcountyId, fn($q) => $q->where('facilities.subcounty_id', $subcountyId))
                    ->when(!$facilityId && !$subcountyId && $countyId, fn($q) => $q->where('counties.id', $countyId))
                    ->select('counties.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as participant_count'))
                    ->groupBy('counties.id', 'counties.name')
                    ->orderByDesc('participant_count')
                    ->limit(10)
                    ->get()
                    ->map(fn($r) => ['name' => $r->name, 'count' => (int) $r->participant_count])
                    ->toArray();
            } else {
                $topCounties = DB::table('counties')
                    ->join('subcounties', 'subcounties.county_id', '=', 'counties.id')
                    ->join('facilities', 'facilities.subcounty_id', '=', 'subcounties.id')
                    ->join('trainings', 'trainings.facility_id', '=', 'facilities.id')
                    ->join('mentorship_classes', 'mentorship_classes.training_id', '=', 'trainings.id')
                    ->join('class_participants', 'class_participants.mentorship_class_id', '=', 'mentorship_classes.id')
                    ->where('trainings.type', 'facility_mentorship')->where('trainings.is_pilot', false)
                    ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                    ->when($facilityId, fn($q) => $q->where('facilities.id', $facilityId))
                    ->when(!$facilityId && $subcountyId, fn($q) => $q->where('facilities.subcounty_id', $subcountyId))
                    ->when(!$facilityId && !$subcountyId && $countyId, fn($q) => $q->where('counties.id', $countyId))
                    ->select('counties.name', DB::raw('COUNT(DISTINCT class_participants.user_id) as participant_count'))
                    ->groupBy('counties.id', 'counties.name')
                    ->orderByDesc('participant_count')
                    ->limit(10)
                    ->get()
                    ->map(fn($r) => ['name' => $r->name, 'count' => (int) $r->participant_count])
                    ->toArray();
            }

            // 5. Average participants per program
            $totalPrograms = array_sum($statusBreakdown) ?: 1;
            $totalParticipants = $mode === 'training'
                ? TrainingParticipant::whereHas('training', function ($q) use ($trainingType, $year) {
                    $q->where('type', $trainingType);
                    if (!empty($year)) $q->whereYear('start_date', $year);
                })
                ->when($hasGeoFilter, fn ($q) => $q->whereHas('user.facility', $geoScope))
                ->distinct('user_id')->count()
                : ClassParticipant::whereHas('mentorshipClass.training', function ($q) use ($year, $hasGeoFilter, $geoScope) {
                    $q->where('type', 'facility_mentorship')->where('is_pilot', false);
                    if (!empty($year)) $q->whereYear('start_date', $year);
                    if ($hasGeoFilter) $q->whereHas('facility', $geoScope);
                })->distinct('user_id')->count('user_id');
            $avgParticipants = round($totalParticipants / $totalPrograms, 1);

            // 6. Mentee status breakdown (mentorship mode)
            $menteeStatus = [];
            if ($mode === 'mentorship') {
                $menteeStatus = ClassParticipant::whereHas('mentorshipClass.training', function ($q) use ($year, $hasGeoFilter, $geoScope) {
                    $q->where('type', 'facility_mentorship')->where('is_pilot', false);
                    if (!empty($year)) $q->whereYear('start_date', $year);
                    if ($hasGeoFilter) $q->whereHas('facility', $geoScope);
                })
                ->selectRaw("COALESCE(status, 'enrolled') as status, COUNT(DISTINCT user_id) as cnt")
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray();
            }

            // 7. Top 5 programs by participants
            $topPrograms = $mode === 'training'
                ? Training::where('type', 'global_training')
                    ->when(!empty($year), fn($q) => $q->whereYear('start_date', $year))
                    ->when($hasGeoFilter, fn ($q) => $q->whereHas('participants.user.facility', $geoScope))
                    ->withCount('participants as p_count')
                    ->orderByDesc('p_count')
                    ->limit(5)
                    ->get(['id', 'title', 'status', 'start_date'])
                    ->map(fn($t) => ['title' => $t->title, 'count' => $t->p_count, 'status' => $t->status])
                    ->toArray()
                : Training::where('type', 'facility_mentorship')->dashboardVisible()
                    ->when(!empty($year), fn($q) => $q->whereYear('start_date', $year))
                    ->when($hasGeoFilter, fn ($q) => $q->whereHas('facility', $geoScope))
                    ->get(['id', 'title', 'status', 'start_date'])
                    ->map(function ($t) {
                        $cnt = ClassParticipant::whereHas('mentorshipClass', fn($q) => $q->where('training_id', $t->id))
                            ->distinct('user_id')->count();
                        return ['title' => $t->title, 'count' => $cnt, 'status' => $t->status];
                    })
                    ->sortByDesc('count')
                    ->take(5)
                    ->values()
                    ->toArray();

            // 8. Mentorship-specific metrics
            $attendanceRate = 0;
            $completionDistribution = [];
            $classStatusBreakdown = [];

            if ($mode === 'mentorship') {
                // Overall module attendance rate (confirmed / enrolled slots)
                $attendanceStats = DB::table('mentee_module_progress')
                    ->join('class_modules', 'class_modules.id', '=', 'mentee_module_progress.class_module_id')
                    ->join('mentorship_classes', 'mentorship_classes.id', '=', 'class_modules.mentorship_class_id')
                    ->join('trainings', 'trainings.id', '=', 'mentorship_classes.training_id')
                    ->where('trainings.type', 'facility_mentorship')->where('trainings.is_pilot', false)->whereIn('trainings.status', ['active', 'completed'])
                    ->whereNotIn('mentee_module_progress.status', ['exempted'])
                    ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                    ->when($hasGeoFilter, $trainingFacilityGeoJoin)
                    ->selectRaw('COUNT(*) as total_slots, SUM(CASE WHEN mentee_module_progress.status IN ("in_progress", "completed") THEN 1 ELSE 0 END) as present_slots')
                    ->first();

                if ($attendanceStats && $attendanceStats->total_slots > 0) {
                    $attendanceRate = round(($attendanceStats->present_slots / $attendanceStats->total_slots) * 100, 1);
                }

                // Mentee module completion distribution
                $progressRows = DB::table('class_participants')
                    ->join('mentorship_classes', 'mentorship_classes.id', '=', 'class_participants.mentorship_class_id')
                    ->join('trainings', 'trainings.id', '=', 'mentorship_classes.training_id')
                    ->leftJoin('class_modules', 'class_modules.mentorship_class_id', '=', 'mentorship_classes.id')
                    ->leftJoin('mentee_module_progress', function ($join) {
                        $join->on('mentee_module_progress.class_participant_id', '=', 'class_participants.id')
                             ->on('mentee_module_progress.class_module_id', '=', 'class_modules.id');
                    })
                    ->where('trainings.type', 'facility_mentorship')->where('trainings.is_pilot', false)->whereIn('trainings.status', ['active', 'completed'])
                    ->whereIn('class_participants.status', ['enrolled', 'active', 'completed'])
                    ->when(!empty($year), fn($q) => $q->whereYear('trainings.start_date', $year))
                    ->when($hasGeoFilter, $trainingFacilityGeoJoin)
                    ->groupBy('class_participants.id')
                    ->selectRaw('
                        class_participants.id,
                        COUNT(DISTINCT class_modules.id) as total_modules,
                        SUM(CASE WHEN mentee_module_progress.status IN ("completed", "exempted") THEN 1 ELSE 0 END) as completed_modules
                    ')
                    ->get();

                $buckets = ['0-25%' => 0, '26-50%' => 0, '51-75%' => 0, '76-99%' => 0, '100%' => 0];
                foreach ($progressRows as $row) {
                    $total = (int) $row->total_modules;
                    $completed = (int) $row->completed_modules;
                    if ($total === 0) {
                        continue;
                    }
                    $pct = ($completed / $total) * 100;
                    if ($pct >= 100) {
                        $buckets['100%']++;
                    } elseif ($pct >= 76) {
                        $buckets['76-99%']++;
                    } elseif ($pct >= 51) {
                        $buckets['51-75%']++;
                    } elseif ($pct >= 26) {
                        $buckets['26-50%']++;
                    } else {
                        $buckets['0-25%']++;
                    }
                }
                $completionDistribution = $buckets;

                // Class lifecycle status breakdown
                $classStatusBreakdown = MentorshipClass::whereHas('training', function ($q) use ($year, $hasGeoFilter, $geoScope) {
                        $q->where('type', 'facility_mentorship')->where('is_pilot', false);
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                        if ($hasGeoFilter) {
                            $q->whereHas('facility', $geoScope);
                        }
                    })
                    ->selectRaw("COALESCE(status, 'draft') as status, COUNT(*) as cnt")
                    ->groupBy('status')
                    ->pluck('cnt', 'status')
                    ->toArray();
            }

            return compact('statusBreakdown', 'trend12', 'yearComparison', 'topCounties',
                           'avgParticipants', 'menteeStatus', 'topPrograms',
                           'attendanceRate', 'completionDistribution', 'classStatusBreakdown');
        } catch (\Exception $e) {
            return [
                'statusBreakdown' => [],
                'trend12' => [],
                'yearComparison' => ['current' => 0, 'previous' => 0, 'change' => 0, 'year' => date('Y')],
                'topCounties' => [],
                'avgParticipants' => 0,
                'menteeStatus' => [],
                'topPrograms' => [],
                'attendanceRate' => 0,
                'completionDistribution' => [],
                'classStatusBreakdown' => [],
            ];
        }
    }

    private function generateInsights(array $summaryStats, array $extendedStats, string $mode): array {
        $insights = [];
        $label = $mode === 'training' ? 'training' : 'mentorship';
        $pLabel = $mode === 'training' ? 'participants' : 'mentees';

        $coverage = $summaryStats['facilityCoverage'] ?? 0;
        $programs = $summaryStats['totalPrograms'] ?? 0;
        $participants = $summaryStats['totalParticipants'] ?? 0;
        $avg = $extendedStats['avgParticipants'] ?? 0;
        $yoy = $extendedStats['yearComparison'] ?? [];
        $topCounties = $extendedStats['topCounties'] ?? [];
        $statusMap = $extendedStats['statusBreakdown'] ?? [];

        // Coverage insight
        if ($coverage >= 70) {
            $insights[] = ['type' => 'success', 'icon' => 'check-circle', 'text' =>
                "Strong facility coverage at {$coverage}% — {$label} programs are reaching a broad base of healthcare facilities."];
        } elseif ($coverage >= 35) {
            $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'text' =>
                "Moderate facility coverage at {$coverage}% — " . number_format($summaryStats['totalFacilities'] ?? 0) . " facilities active; consider expanding to uncovered areas."];
        } elseif ($programs > 0) {
            $insights[] = ['type' => 'danger', 'icon' => 'exclamation-circle', 'text' =>
                "Low facility coverage at {$coverage}% — significant expansion of {$label} programs needed to reach more healthcare facilities."];
        }

        // Participant throughput
        if ($programs > 0 && $avg > 0) {
            $insights[] = ['type' => 'info', 'icon' => 'users', 'text' =>
                "Average of {$avg} {$pLabel} per {$label} across {$programs} programs — total reach: " . number_format($participants) . " unique {$pLabel}."];
        }

        // Year-on-year
        if (!empty($yoy) && ($yoy['current'] > 0 || $yoy['previous'] > 0)) {
            $change = $yoy['change'];
            $year = $yoy['year'];
            if ($change > 5) {
                $insights[] = ['type' => 'success', 'icon' => 'arrow-up', 'text' =>
                    "{$change}% year-on-year increase in {$year} — {$label} program activity is growing strongly."];
            } elseif ($change < -5) {
                $abs = abs($change);
                $insights[] = ['type' => 'warning', 'icon' => 'arrow-down', 'text' =>
                    "{$abs}% decline in {$year} versus prior year — review {$label} program enrollment and engagement strategies."];
            } else {
                $insights[] = ['type' => 'info', 'icon' => 'minus-circle', 'text' =>
                    "Stable year-on-year activity ({$change}% change) — {$year} {$label} performance is consistent with prior year."];
            }
        }

        // Top county
        if (!empty($topCounties)) {
            $top = $topCounties[0];
            $insights[] = ['type' => 'primary', 'icon' => 'map-marker-alt', 'text' =>
                "{$top['name']} county leads with " . number_format($top['count']) . " {$pLabel} — a high-impact county for {$label} scale-up."];
        }

        // Status insight (active vs completed)
        $active = $statusMap['active'] ?? 0;
        $completed = $statusMap['completed'] ?? 0;
        $draft = $statusMap['draft'] ?? 0;
        $total = array_sum($statusMap);
        if ($total > 0 && ($active > 0 || $completed > 0)) {
            $activeRate = round(($active / $total) * 100);
            $completedRate = round(($completed / $total) * 100);
            $insights[] = ['type' => 'info', 'icon' => 'chart-pie', 'text' =>
                "{$activeRate}% of {$label} programs are active; {$completedRate}% completed" .
                ($draft > 0 ? "; {$draft} in draft" : "") . "."];
        }

        // Mentorship-specific insights
        if ($mode === 'mentorship') {
            $attendanceRate = $extendedStats['attendanceRate'] ?? 0;
            if ($attendanceRate > 0) {
                if ($attendanceRate >= 80) {
                    $insights[] = ['type' => 'success', 'icon' => 'clipboard-check', 'text' =>
                        "Strong module attendance at {$attendanceRate}% — mentees are consistently showing up for sessions."];
                } elseif ($attendanceRate >= 60) {
                    $insights[] = ['type' => 'warning', 'icon' => 'clipboard-check', 'text' =>
                        "Module attendance is {$attendanceRate}% — consider follow-up with mentors to improve session attendance."];
                } else {
                    $insights[] = ['type' => 'danger', 'icon' => 'clipboard-check', 'text' =>
                        "Low module attendance at {$attendanceRate}% — review scheduling, mentor engagement, and mentee barriers."];
                }
            }

            $completionDistribution = $extendedStats['completionDistribution'] ?? [];
            $fullyCompleted = $completionDistribution['100%'] ?? 0;
            $totalMenteesWithProgress = array_sum($completionDistribution);
            if ($totalMenteesWithProgress > 0) {
                $completionRate = round(($fullyCompleted / $totalMenteesWithProgress) * 100);
                if ($completionRate >= 70) {
                    $insights[] = ['type' => 'success', 'icon' => 'graduation-cap', 'text' =>
                        "{$completionRate}% of mentees have completed all assigned modules — mentorship completion is on track."];
                } elseif ($completionRate >= 40) {
                    $insights[] = ['type' => 'warning', 'icon' => 'graduation-cap', 'text' =>
                        "{$completionRate}% of mentees have completed all modules — monitor those in lower completion buckets."];
                } else {
                    $insights[] = ['type' => 'danger', 'icon' => 'graduation-cap', 'text' =>
                        "Only {$completionRate}% of mentees have completed all modules — significant support intervention may be needed."];
                }
            }

            $classStatusBreakdown = $extendedStats['classStatusBreakdown'] ?? [];
            $activeClasses = $classStatusBreakdown['active'] ?? 0;
            $draftClasses = $classStatusBreakdown['draft'] ?? 0;
            if ($activeClasses > 0 || $draftClasses > 0) {
                $insights[] = ['type' => 'primary', 'icon' => 'chalkboard-teacher', 'text' =>
                    "Class pipeline: {$activeClasses} active classes and {$draftClasses} draft classes across mentorship programs."];
            }
        }

        return array_slice($insights, 0, 5);
    }

    public function exportReadiness(Request $request)
    {
        $filters = [
            'year'            => $request->get('year'),
            'county_id'       => $request->get('county_id'),
            'subcounty_id'    => $request->get('subcounty_id'),
            'facility_id'     => $request->get('facility_id'),
            'assessment_type' => $request->get('assessment_type'),
        ];

        $assessmentService   = app(AssessmentAnalyticsService::class);
        $facilitiesReadiness = $assessmentService->getFacilitiesReadiness($filters);
        $summaryStats        = $assessmentService->getSummaryStats($filters);

        $selectedYear           = $filters['year'];
        $selectedCounty         = $filters['county_id']
            ? County::find($filters['county_id'])?->name
            : null;
        $selectedAssessmentType = $filters['assessment_type'];
        $generatedAt            = Carbon::now()->format('d M Y, H:i');

        $pdf = Pdf::loadView('analytics.dashboard.assessment-readiness-export', compact(
            'facilitiesReadiness', 'summaryStats',
            'selectedYear', 'selectedCounty', 'selectedAssessmentType', 'generatedAt'
        ))->setPaper('a4', 'landscape');

        $filename = 'facilities-readiness-' . ($selectedYear ?: 'all') . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    public function facilityMentorshipBreakdown(Facility $facility, Request $request)
    {
        $selectedYear = $request->get('year', '');

        $mentorships = Training::where('type', 'facility_mentorship')->where('is_pilot', false)
            ->where('status', '!=', 'draft')
            ->where('facility_id', $facility->id)
            ->when(!empty($selectedYear), fn($q) => $q->whereYear('start_date', $selectedYear))
            ->with([
                'mentor',
                'program',
                'mentorshipClasses' => fn($q) => $q->with([
                    'classModules.programModule',
                    'classModules.sessions',
                    'participants' => fn($q) => $q->with(['user.cadre', 'user.department']),
                ]),
            ])
            ->orderBy('start_date', 'desc')
            ->get();

        $mentorships->each(function ($training) {
            $training->mentorshipClasses->each(function ($class) {
                $moduleIds     = $class->classModules->pluck('id');
                $totalSlots    = $class->classModules->count() * $class->participants->count();
                $attendedSlots = ClassAttendance::whereIn('class_module_id', $moduleIds)->count();

                $class->attendance_total   = $totalSlots;
                $class->attendance_present = $attendedSlots;

                $class->participants->each(function ($participant) use ($class, $moduleIds) {
                    $attended = ClassAttendance::whereIn('class_module_id', $moduleIds)
                        ->where('user_id', $participant->user_id)
                        ->count();
                    $total = $class->classModules->count();

                    $participant->modules_attended = $attended;
                    $participant->modules_total    = $total;
                    $participant->attendance_pct   = $total > 0 ? round(($attended / $total) * 100) : 0;
                    $participant->attendance_label = match (true) {
                        $participant->attendance_pct >= 80 => 'Present',
                        $participant->attendance_pct >= 50 => 'Partial',
                        default                            => 'Absent',
                    };
                });
            });
        });

        $availableYears = Training::selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index', ['mode' => 'assessment'])],
            ['name' => $facility->name . ' — Mentorships', 'url' => null],
        ];

        return view('analytics.dashboard.facility-mentorship-breakdown', compact(
            'facility', 'mentorships', 'availableYears', 'selectedYear', 'breadcrumbs'
        ));
    }
}
