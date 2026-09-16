<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Facility;
use App\Models\User;
use App\Models\Program;
use App\Models\Cadre;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HeatmapController extends Controller
{
    /**
     * Get training programs for a specific county
     * GET /api/heatmap/counties/{county}/trainings
     */
    public function getCountyTrainings(Request $request, string $countyName): JsonResponse
    {
        try {
            // Validate and find county
            $county = County::where('name', 'LIKE', '%' . $countyName . '%')->first();
            
            if (!$county) {
                return response()->json([
                    'error' => 'County not found',
                    'message' => "County '{$countyName}' does not exist"
                ], 404);
            }

            // Apply filters from request
            $filters = $this->extractFilters($request);
            
            // Cache key for performance
            $cacheKey = "county_trainings_{$county->id}_" . md5(serialize($filters));
            
            $trainings = Cache::remember($cacheKey, 300, function () use ($county, $filters) {
                return $this->buildTrainingsQuery($county, $filters)
                    ->with(['programs', 'participants.user.facility.subcounty'])
                    ->get()
                    ->map(function ($training) use ($county) {
                        return $this->formatTrainingData($training, $county);
                    });
            });

            return response()->json([
                'success' => true,
                'data' => $trainings,
                'county' => [
                    'id' => $county->id,
                    'name' => $county->name,
                ],
                'meta' => [
                    'total_trainings' => $trainings->count(),
                    'total_participants' => $trainings->sum('participants_count'),
                    'total_facilities' => $trainings->sum('facilities_count'),
                    'filters_applied' => array_filter($filters)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching county trainings', [
                'county' => $countyName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch training data',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while fetching training data'
            ], 500);
        }
    }

    /**
     * Get facilities participating in a specific training
     * GET /api/heatmap/trainings/{training}/facilities
     */
    public function getTrainingFacilities(Request $request, int $trainingId): JsonResponse
    {
        try {
            $training = Training::with(['participants.user.facility.subcounty.county', 'participants.user.cadre'])
                ->find($trainingId);

            if (!$training) {
                return response()->json([
                    'error' => 'Training not found',
                    'message' => "Training with ID {$trainingId} does not exist"
                ], 404);
            }

            // Apply filters
            $filters = $this->extractFilters($request);
            
            // Cache key
            $cacheKey = "training_facilities_{$trainingId}_" . md5(serialize($filters));
            
            $facilities = Cache::remember($cacheKey, 300, function () use ($training, $filters) {
                return $this->buildFacilitiesData($training, $filters);
            });

            return response()->json([
                'success' => true,
                'data' => $facilities,
                'training' => [
                    'id' => $training->id,
                    'title' => $training->title,
                    'type' => $training->type,
                    'status' => $training->status,
                    'identifier' => $training->identifier,
                ],
                'meta' => [
                    'total_facilities' => count($facilities),
                    'total_participants' => array_sum(array_column($facilities, 'participants_count')),
                    'avg_completion_rate' => count($facilities) > 0 ? round(array_sum(array_column($facilities, 'completion_rate')) / count($facilities), 1) : 0,
                    'filters_applied' => array_filter($filters)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching training facilities', [
                'training_id' => $trainingId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to fetch facility data',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while fetching facility data'
            ], 500);
        }
    }

    /**
     * Get participants from a specific facility (optionally filtered by training)
     * GET /api/heatmap/facilities/{facility}/participants
     */
    public function getFacilityParticipants(Request $request, int $facilityId): JsonResponse
    {
        try {
            $facility = Facility::with(['subcounty.county'])->find($facilityId);

            if (!$facility) {
                return response()->json([
                    'error' => 'Facility not found',
                    'message' => "Facility with ID {$facilityId} does not exist"
                ], 404);
            }

            // Apply filters
            $filters = $this->extractFilters($request);
            $trainingId = $request->query('training_id');
            
            // Cache key
            $cacheKey = "facility_participants_{$facilityId}_{$trainingId}_" . md5(serialize($filters));
            
            $participants = Cache::remember($cacheKey, 300, function () use ($facilityId, $trainingId, $filters) {
                return $this->buildParticipantsQuery($facilityId, $trainingId, $filters)
                    ->with([
                        'user.cadre', 
                        'training', 
                        'objectiveResults',
                        'assessmentResults.assessmentCategory'
                    ])
                    ->get()
                    ->map(function ($participant) {
                        return $this->formatParticipantData($participant);
                    });
            });

            return response()->json([
                'success' => true,
                'data' => $participants,
                'facility' => [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'subcounty' => $facility->subcounty?->name,
                    'county' => $facility->subcounty?->county?->name,
                    'mfl_code' => $facility->mfl_code,
                ],
                'meta' => [
                    'total_participants' => $participants->count(),
                    'completed' => $participants->where('completion_status', 'completed')->count(),
                    'in_progress' => $participants->where('completion_status', 'in_progress')->count(),
                    'avg_score' => $participants->where('overall_score', '>', 0)->avg('overall_score') ?: 0,
                    'filters_applied' => array_filter($filters)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching facility participants', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to fetch participant data',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while fetching participant data'
            ], 500);
        }
    }

    /**
     * Get detailed profile for a specific participant
     * GET /api/heatmap/participants/{participant}/profile
     */
    public function getParticipantProfile(Request $request, int $participantId): JsonResponse
    {
        try {
            $participant = TrainingParticipant::with([
                'user.cadre',
                'user.facility.subcounty.county',
                'user.trainingParticipations.training.programs',
                'user.trainingParticipations.objectiveResults',
                'user.statusLogs' => function($query) {
                    $query->latest('effective_date')->limit(5);
                },
                'assessmentResults.assessmentCategory',
                'assessmentResults.assessor',
                'training.programs',
                'objectiveResults'
            ])->find($participantId);

            if (!$participant || !$participant->user) {
                return response()->json([
                    'error' => 'Participant not found',
                    'message' => "Participant with ID {$participantId} does not exist"
                ], 404);
            }

            // Cache key
            $cacheKey = "participant_profile_{$participantId}";
            
            $profileData = Cache::remember($cacheKey, 600, function () use ($participant) {
                return $this->buildParticipantProfile($participant);
            });

            return response()->json([
                'success' => true,
                'data' => $profileData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching participant profile', [
                'participant_id' => $participantId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to fetch participant profile',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while fetching participant profile'
            ], 500);
        }
    }

    /**
     * Get comprehensive statistics for the heatmap
     * GET /api/heatmap/statistics
     */
    public function getHeatmapStatistics(Request $request): JsonResponse
    {
        try {
            $filters = $this->extractFilters($request);
            $cacheKey = "heatmap_statistics_" . md5(serialize($filters));
            
            $statistics = Cache::remember($cacheKey, 600, function () use ($filters) {
                return [
                    'overview' => $this->getOverviewStatistics($filters),
                    'county_breakdown' => $this->getCountyBreakdown($filters),
                    'performance_metrics' => $this->getPerformanceMetrics($filters),
                    'trends' => $this->getTrendAnalysis($filters),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $statistics,
                'meta' => [
                    'generated_at' => now()->toISOString(),
                    'filters_applied' => array_filter($filters)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching heatmap statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to fetch statistics',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while fetching statistics'
            ], 500);
        }
    }

    /**
     * Bulk export participants data
     * POST /api/heatmap/bulk/export/participants
     */
    public function exportParticipants(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = $this->extractFilters($request);
        
        $fileName = 'kenya_training_participants_' . date('Y-m-d') . '.csv';
        
        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            
            // Write CSV headers
            fputcsv($handle, [
                'ID',
                'Full Name',
                'Email',
                'Phone',
                'ID Number',
                'Cadre',
                'Department',
                'Facility',
                'Facility Type',
                'MFL Code',
                'Subcounty',
                'County',
                'Training Title',
                'Training Type',
                'Training Identifier',
                'Registration Date',
                'Completion Status',
                'Completion Date',
                'Overall Score',
                'Overall Grade',
                'Certificate Issued',
                'Current Status',
                'Performance Trend',
                'Attrition Risk',
                'Notes'
            ]);
            
            // Query participants with applied filters
            $query = TrainingParticipant::with([
                'user.cadre',
                'user.department', 
                'user.facility.facilityType',
                'user.facility.subcounty.county',
                'training.programs',
                'objectiveResults'
            ]);
            
            // Apply filters
            $this->applyParticipantFilters($query, $filters);
            
            // Stream data in chunks to handle large datasets
            $query->chunk(1000, function ($participants) use ($handle) {
                foreach ($participants as $participant) {
                    $user = $participant->user;
                    $facility = $user?->facility;
                    
                    fputcsv($handle, [
                        $participant->id,
                        $user?->full_name,
                        $user?->email,
                        $user?->phone,
                        $user?->id_number,
                        $user?->cadre?->name,
                        $user?->department?->name,
                        $facility?->name,
                        $facility?->facilityType?->name,
                        $facility?->mfl_code,
                        $facility?->subcounty?->name,
                        $facility?->subcounty?->county?->name,
                        $participant->training?->title,
                        $participant->training?->type,
                        $participant->training?->identifier,
                        $participant->registration_date?->format('Y-m-d'),
                        $participant->completion_status,
                        $participant->completion_date?->format('Y-m-d'),
                        $participant->overall_score,
                        $participant->overall_grade,
                        $participant->certificate_issued ? 'Yes' : 'No',
                        $user?->current_status ?: 'active',
                        $user?->performance_trend,
                        $user?->attrition_risk,
                        $participant->notes
                    ]);
                }
            });
            
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Bulk export facilities data
     * POST /api/heatmap/bulk/export/facilities
     */
    public function exportFacilities(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = $this->extractFilters($request);
        
        $fileName = 'kenya_training_facilities_' . date('Y-m-d') . '.csv';
        
        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            
            // Write CSV headers
            fputcsv($handle, [
                'Facility ID',
                'Facility Name',
                'Facility Type',
                'MFL Code',
                'Subcounty',
                'County',
                'Is Hub',
                'Hub Facility',
                'Total Trainings',
                'Total Participants',
                'Completed Participants',
                'Completion Rate (%)',
                'Average Score',
                'Top Performing Training',
                'Latest Training Date',
                'Coordinates (Lat, Long)',
                'Operating Hours',
                'Contact Information'
            ]);
            
            // Get facilities that have participated in trainings
            $facilitiesQuery = Facility::with([
                'subcounty.county',
                'facilityType',
                'hub',
                'users.trainingParticipations.training',
                'users.trainingParticipations.objectiveResults'
            ])->whereHas('users.trainingParticipations');
            
            // Apply geographic filters if provided
            if (!empty($filters['county_id'])) {
                $facilitiesQuery->whereHas('subcounty', function ($q) use ($filters) {
                    $q->whereIn('county_id', $filters['county_id']);
                });
            }
            
            $facilitiesQuery->chunk(100, function ($facilities) use ($handle, $filters) {
                foreach ($facilities as $facility) {
                    // Calculate facility statistics
                    $participations = collect();
                    foreach ($facility->users as $user) {
                        foreach ($user->trainingParticipations as $participation) {
                            // Apply training-level filters
                            if ($this->participationMatchesFilters($participation, $filters)) {
                                $participations->push($participation);
                            }
                        }
                    }
                    
                    if ($participations->isEmpty()) continue;
                    
                    $totalParticipants = $participations->count();
                    $completedParticipants = $participations->where('completion_status', 'completed')->count();
                    $completionRate = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;
                    $averageScore = $participations->where('overall_score', '>', 0)->avg('overall_score') ?: 0;
                    $totalTrainings = $participations->pluck('training_id')->unique()->count();
                    $latestTraining = $participations->sortByDesc('registration_date')->first();
                    $topPerformingTraining = $participations->sortByDesc('overall_score')->first();
                    
                    fputcsv($handle, [
                        $facility->id,
                        $facility->name,
                        $facility->facilityType?->name,
                        $facility->mfl_code,
                        $facility->subcounty?->name,
                        $facility->subcounty?->county?->name,
                        $facility->is_hub ? 'Yes' : 'No',
                        $facility->hub?->name,
                        $totalTrainings,
                        $totalParticipants,
                        $completedParticipants,
                        $completionRate,
                        round($averageScore, 1),
                        $topPerformingTraining?->training?->title,
                        $latestTraining?->registration_date?->format('Y-m-d'),
                        $facility->lat && $facility->long ? "{$facility->lat}, {$facility->long}" : '',
                        json_encode($facility->operating_hours),
                        '' // Contact information could be added if available
                    ]);
                }
            });
            
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Bulk export trainings data
     * POST /api/heatmap/bulk/export/trainings
     */
    public function exportTrainings(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = $this->extractFilters($request);
        
        $fileName = 'kenya_training_programs_' . date('Y-m-d') . '.csv';
        
        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            
            // Write CSV headers
            fputcsv($handle, [
                'Training ID',
                'Title',
                'Identifier',
                'Type',
                'Status',
                'Description',
                'Programs',
                'Organizer',
                'Mentor',
                'Location',
                'Start Date',
                'End Date',
                'Registration Deadline',
                'Max Participants',
                'Actual Participants',
                'Completion Rate (%)',
                'Average Score',
                'Pass Rate (%)',
                'Total Facilities',
                'Counties Covered',
                'Target Audience',
                'Learning Outcomes',
                'Prerequisites',
                'Training Approaches',
                'Materials Cost',
                'Actual Cost',
                'Material Utilization (%)',
                'Notes'
            ]);
            
            // Query trainings with applied filters
            $query = Training::with([
                'programs',
                'organizer',
                'mentor',
                'participants.user.facility.subcounty.county',
                'participants.objectiveResults',
                'trainingMaterials'
            ]);
            
            // Apply filters
            $this->applyTrainingFilters($query, $filters);
            
            $query->chunk(100, function ($trainings) use ($handle) {
                foreach ($trainings as $training) {
                    $participants = $training->participants;
                    $totalParticipants = $participants->count();
                    $completedParticipants = $participants->where('completion_status', 'completed')->count();
                    $completionRate = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;
                    
                    // Calculate pass rate
                    $assessedParticipants = $participants->filter(function ($p) {
                        return $p->objectiveResults->isNotEmpty();
                    });
                    $passedParticipants = $assessedParticipants->filter(function ($p) {
                        return $p->objectiveResults->avg('score') >= 70;
                    });
                    $passRate = $assessedParticipants->count() > 0 ? 
                        round(($passedParticipants->count() / $assessedParticipants->count()) * 100, 1) : 0;
                    
                    // Get unique facilities and counties
                    $facilities = $participants->pluck('user.facility_id')->filter()->unique();
                    $counties = $participants->pluck('user.facility.subcounty.county.name')->filter()->unique();
                    
                    // Calculate average score
                    $scores = $participants->pluck('overall_score')->filter();
                    $averageScore = $scores->isNotEmpty() ? round($scores->avg(), 1) : 0;
                    
                    fputcsv($handle, [
                        $training->id,
                        $training->title,
                        $training->identifier,
                        $training->type,
                        $training->status,
                        $training->description,
                        $training->programs->pluck('name')->implode(', '),
                        $training->organizer?->full_name,
                        $training->mentor?->full_name,
                        $training->location,
                        $training->start_date?->format('Y-m-d'),
                        $training->end_date?->format('Y-m-d'),
                        $training->registration_deadline?->format('Y-m-d H:i'),
                        $training->max_participants,
                        $totalParticipants,
                        $completionRate,
                        $averageScore,
                        $passRate,
                        $facilities->count(),
                        $counties->count(),
                        $training->target_audience,
                        is_array($training->learning_outcomes) ? implode('; ', $training->learning_outcomes) : $training->learning_outcomes,
                        is_array($training->prerequisites) ? implode('; ', $training->prerequisites) : $training->prerequisites,
                        is_array($training->training_approaches) ? implode('; ', $training->training_approaches) : $training->training_approaches,
                        $training->total_material_cost ?? 0,
                        $training->actual_material_cost ?? 0,
                        $training->material_utilization_rate ?? 0,
                        $training->notes
                    ]);
                }
            });
            
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Extract and validate filters from request
     */
    private function extractFilters(Request $request): array
    {
        return [
            'program_id' => $request->query('program_id') ? explode(',', $request->query('program_id')) : [],
            'training_type' => $request->query('training_type') ? explode(',', $request->query('training_type')) : [],
            'status' => $request->query('status') ? explode(',', $request->query('status')) : [],
            'date_range' => $request->query('date_range'),
            'cadre_id' => $request->query('cadre_id') ? explode(',', $request->query('cadre_id')) : [],
            'completion_status' => $request->query('completion_status'),
            'search' => $request->query('search'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'county_id' => $request->query('county_id') ? explode(',', $request->query('county_id')) : [],
        ];
    }

    /**
     * Build trainings query with filters
     */
    private function buildTrainingsQuery(County $county, array $filters): Builder
    {
        $query = Training::query()
            ->whereHas('participants.user.facility.subcounty', function ($q) use ($county) {
                $q->where('county_id', $county->id);
            });

        // Apply training type filter
        if (!empty($filters['training_type'])) {
            $query->whereIn('type', $filters['training_type']);
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        // Apply program filter
        if (!empty($filters['program_id'])) {
            $query->whereHas('programs', function ($q) use ($filters) {
                $q->whereIn('programs.id', $filters['program_id']);
            });
        }

        // Apply date range filter
        if (!empty($filters['date_range'])) {
            $this->applyDateRangeFilter($query, $filters['date_range']);
        }

        // Apply custom date range
        if (!empty($filters['start_date'])) {
            $query->where('start_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('end_date', '<=', $filters['end_date']);
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('identifier', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('start_date', 'desc');
    }

    /**
     * Build participants query with filters
     */
    private function buildParticipantsQuery(int $facilityId, ?int $trainingId, array $filters): Builder
    {
        $query = TrainingParticipant::query()
            ->whereHas('user', function ($q) use ($facilityId) {
                $q->where('facility_id', $facilityId);
            });

        if ($trainingId) {
            $query->where('training_id', $trainingId);
        }

        // Apply completion status filter
        if (!empty($filters['completion_status'])) {
            $query->where('completion_status', $filters['completion_status']);
        }

        // Apply cadre filter
        if (!empty($filters['cadre_id'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->whereIn('cadre_id', $filters['cadre_id']);
            });
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where(function ($subQ) use ($filters) {
                    $subQ->where('first_name', 'LIKE', '%' . $filters['search'] . '%')
                         ->orWhere('last_name', 'LIKE', '%' . $filters['search'] . '%')
                         ->orWhere('email', 'LIKE', '%' . $filters['search'] . '%');
                });
            });
        }

        return $query->orderBy('registration_date', 'desc');
    }

    /**
     * Format training data for API response
     */
    private function formatTrainingData(Training $training, County $county): array
    {
        $countyParticipants = $training->participants->filter(function ($participant) use ($county) {
            return $participant->user?->facility?->subcounty?->county_id === $county->id;
        });

        $facilitiesCount = $countyParticipants->pluck('user.facility_id')->unique()->count();
        $participantsCount = $countyParticipants->count();
        $completedCount = $countyParticipants->where('completion_status', 'completed')->count();

        return [
            'id' => $training->id,
            'title' => $training->title,
            'type' => $training->type,
            'identifier' => $training->identifier,
            'status' => $training->status,
            'description' => $training->description,
            'programs' => $training->programs->pluck('name')->implode(', '),
            'start_date' => $training->start_date?->format('M j, Y'),
            'end_date' => $training->end_date?->format('M j, Y'),
            'facilities_count' => $facilitiesCount,
            'participants_count' => $participantsCount,
            'completion_rate' => $participantsCount > 0 ? round(($completedCount / $participantsCount) * 100, 1) : 0,
            'average_score' => $this->calculateAverageScore($countyParticipants),
            'location' => $training->location,
            'organizer' => $training->organizer?->full_name,
        ];
    }

    /**
     * Build facilities data for training
     */
    private function buildFacilitiesData(Training $training, array $filters): array
    {
        $facilitiesData = $training->participants
            ->filter(function ($participant) use ($filters) {
                // Apply filters
                if (!empty($filters['completion_status']) && $participant->completion_status !== $filters['completion_status']) {
                    return false;
                }
                
                if (!empty($filters['cadre_id']) && !in_array($participant->user?->cadre_id, $filters['cadre_id'])) {
                    return false;
                }

                return $participant->user?->facility !== null;
            })
            ->groupBy('user.facility_id')
            ->map(function ($participants, $facilityId) {
                $facility = $participants->first()->user->facility;
                $participantCount = $participants->count();
                $completedCount = $participants->where('completion_status', 'completed')->count();
                $averageScore = $this->calculateAverageScore($participants);

                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'subcounty' => $facility->subcounty?->name,
                    'county' => $facility->subcounty?->county?->name,
                    'facility_type' => $facility->facilityType?->name,
                    'mfl_code' => $facility->mfl_code,
                    'participants_count' => $participantCount,
                    'completion_rate' => $participantCount > 0 ? round(($completedCount / $participantCount) * 100, 1) : 0,
                    'average_score' => $averageScore,
                    'coordinates' => $facility->coordinates,
                    'is_hub' => $facility->is_hub,
                ];
            })
            ->values()
            ->toArray();

        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $facilitiesData = array_filter($facilitiesData, function ($facility) use ($filters) {
                return stripos($facility['name'], $filters['search']) !== false ||
                       stripos($facility['subcounty'], $filters['search']) !== false;
            });
        }

        return array_values($facilitiesData);
    }

    /**
     * Format participant data for API response
     */
    private function formatParticipantData(TrainingParticipant $participant): array
    {
        return [
            'id' => $participant->id,
            'user_id' => $participant->user_id,
            'full_name' => $participant->user?->full_name,
            'email' => $participant->user?->email,
            'cadre' => $participant->user?->cadre?->name,
            'completion_status' => $participant->completion_status,
            'attendance_status' => $participant->attendance_status,
            'overall_score' => $participant->overall_score,
            'overall_grade' => $participant->overall_grade,
            'registration_date' => $participant->registration_date?->format('M j, Y'),
            'completion_date' => $participant->completion_date?->format('M j, Y'),
            'certificate_issued' => $participant->certificate_issued,
            'training_title' => $participant->training?->title,
            'training_type' => $participant->training?->type,
            'current_status' => $participant->user?->current_status ?: 'active',
            'assessment_progress' => $participant->assessment_progress ?? [],
            'notes' => $participant->notes,
        ];
    }

    /**
     * Build comprehensive participant profile
     */
    private function buildParticipantProfile(TrainingParticipant $participant): array
    {
        $user = $participant->user;
        
        // Calculate training summary
        $allParticipations = $user->trainingParticipations;
        $trainingSummary = [
            'total_trainings' => $allParticipations->count(),
            'completed' => $allParticipations->where('completion_status', 'completed')->count(),
            'in_progress' => $allParticipations->where('completion_status', 'in_progress')->count(),
            'completion_rate' => $user->training_completion_rate ?? 0,
            'average_score' => $user->overall_training_score ?: 0,
        ];

        // Get training history
        $trainingHistory = $allParticipations->map(function ($tp) {
            return [
                'id' => $tp->id,
                'title' => $tp->training?->title,
                'type' => $tp->training?->type,
                'programs' => $tp->training?->programs->pluck('name')->implode(', ') ?? '',
                'start_date' => $tp->training?->start_date?->format('M j, Y'),
                'end_date' => $tp->training?->end_date?->format('M j, Y'),
                'completion_status' => $tp->completion_status,
                'overall_score' => $tp->overall_score,
                'overall_grade' => $tp->overall_grade,
                'registration_date' => $tp->registration_date?->format('M j, Y'),
                'completion_date' => $tp->completion_date?->format('M j, Y'),
                'certificate_issued' => $tp->certificate_issued,
            ];
        })->sortByDesc('registration_date')->values()->toArray();

        // Get assessment results
        $assessmentResults = $participant->assessmentResults->map(function ($result) {
            return [
                'id' => $result->id,
                'category_name' => $result->assessmentCategory?->name,
                'result' => $result->result,
                'score' => $result->score,
                'feedback' => $result->feedback,
                'assessment_date' => $result->assessment_date?->format('M j, Y'),
                'assessor_name' => $result->assessor?->full_name,
                'category_weight' => $result->category_weight,
                'attempts' => $result->attempts ?? 1,
            ];
        })->toArray();

        // Get status history
        $statusHistory = $user->statusLogs->map(function ($log) {
            return [
                'previous_status' => $log->previous_status,
                'new_status' => $log->new_status,
                'effective_date' => $log->effective_date?->format('M j, Y'),
                'reason' => $log->reason,
                'notes' => $log->notes,
                'changed_by' => $log->changedBy?->full_name,
            ];
        })->toArray();

        return [
            'id' => $participant->id,
            'user_id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'id_number' => $user->id_number,
            'cadre' => $user->cadre?->name,
            'department' => $user->department?->name,
            'facility_name' => $user->facility?->name,
            'facility_type' => $user->facility?->facilityType?->name,
            'subcounty' => $user->facility?->subcounty?->name,
            'county' => $user->facility?->subcounty?->county?->name,
            'current_status' => $user->current_status ?? 'active',
            'training_summary' => $trainingSummary,
            'training_history' => $trainingHistory,
            'assessment_results' => $assessmentResults,
            'status_history' => $statusHistory,
            'performance_trend' => $user->performance_trend ?? 'Stable',
            'attrition_risk' => $user->attrition_risk ?? 'Low',
            'mentorship_performance' => $this->getMentorshipPerformance($user),
            'created_at' => $user->created_at?->format('M j, Y'),
            'updated_at' => $user->updated_at?->format('M j, Y'),
        ];
    }

    /**
     * Get mentorship performance for a user
     */
    private function getMentorshipPerformance(User $user): array
    {
        $participations = $user->trainingParticipations()
            ->whereHas('training', function ($query) {
                $query->where('type', 'facility_mentorship');
            })
            ->with(['training', 'assessmentResults'])
            ->get();

        $totalTrainings = $participations->count();
        $completedTrainings = 0;
        $passedTrainings = 0;
        $totalScore = 0;
        $assessedTrainings = 0;

        foreach ($participations as $participation) {
            if ($participation->completion_status === 'completed') {
                $completedTrainings++;
            }

            if ($participation->assessmentResults->isNotEmpty()) {
                $assessedTrainings++;
                $avgScore = $participation->assessmentResults->avg('score') ?? 0;
                $totalScore += $avgScore;

                if ($avgScore >= 70) {
                    $passedTrainings++;
                }
            }
        }

        return [
            'total_trainings' => $totalTrainings,
            'completed_trainings' => $completedTrainings,
            'passed_trainings' => $passedTrainings,
            'assessed_trainings' => $assessedTrainings,
            'completion_rate' => $totalTrainings > 0 ? round(($completedTrainings / $totalTrainings) * 100, 1) : 0,
            'pass_rate' => $assessedTrainings > 0 ? round(($passedTrainings / $assessedTrainings) * 100, 1) : 0,
            'average_score' => $assessedTrainings > 0 ? round($totalScore / $assessedTrainings, 1) : 0,
        ];
    }

    /**
     * Apply date range filter to query
     */
    private function applyDateRangeFilter(Builder $query, string $dateRange, string $dateField = 'start_date'): void
    {
        switch ($dateRange) {
            case 'last-6-months':
                $query->where($dateField, '>=', now()->subMonths(6));
                break;
            case 'last-year':
                $query->where($dateField, '>=', now()->subYear());
                break;
            case '2024':
                $query->whereYear($dateField, 2024);
                break;
            case '2023':
                $query->whereYear($dateField, 2023);
                break;
            case 'current-year':
                $query->whereYear($dateField, now()->year);
                break;
        }
    }

    /**
     * Calculate average score for a collection of participants
     */
    private function calculateAverageScore($participants): float
    {
        $scores = $participants->map(fn($p) => $p->overall_score)->filter();
        return $scores->isEmpty() ? 0 : round($scores->avg(), 1);
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStatistics(array $filters): array
    {
        $trainingsQuery = Training::query();
        $participantsQuery = TrainingParticipant::query();
        
        // Apply filters to both queries
        $this->applyFiltersToStatistics($trainingsQuery, $participantsQuery, $filters);
        
        return [
            'total_trainings' => $trainingsQuery->count(),
            'total_participants' => $participantsQuery->count(),
            'total_facilities' => $participantsQuery->join('users', 'training_participants.user_id', '=', 'users.id')
                ->distinct('users.facility_id')->count('users.facility_id'),
            'total_counties' => $participantsQuery->join('users', 'training_participants.user_id', '=', 'users.id')
                ->join('facilities', 'users.facility_id', '=', 'facilities.id')
                ->join('subcounties', 'facilities.subcounty_id', '=', 'subcounties.id')
                ->distinct('subcounties.county_id')->count('subcounties.county_id'),
            'completion_rate' => $this->calculateCompletionRate($participantsQuery),
            'pass_rate' => $this->calculatePassRate($participantsQuery),
        ];
    }

    /**
     * Get county breakdown statistics
     */
    private function getCountyBreakdown(array $filters): array
    {
        $query = TrainingParticipant::selectRaw('
                counties.id as county_id,
                counties.name as county_name,
                COUNT(DISTINCT training_participants.training_id) as total_trainings,
                COUNT(*) as total_participants,
                COUNT(CASE WHEN completion_status = "completed" THEN 1 END) as completed_participants,
                COUNT(DISTINCT users.facility_id) as total_facilities,
                AVG(CASE WHEN overall_score IS NOT NULL THEN overall_score ELSE 0 END) as avg_score
            ')
            ->join('users', 'training_participants.user_id', '=', 'users.id')
            ->join('facilities', 'users.facility_id', '=', 'facilities.id')
            ->join('subcounties', 'facilities.subcounty_id', '=', 'subcounties.id')
            ->join('counties', 'subcounties.county_id', '=', 'counties.id')
            ->groupBy('counties.id', 'counties.name')
            ->orderBy('counties.name');

        // Apply filters
        $this->applyParticipantFilters($query, $filters);

        return $query->get()
            ->map(function ($item) {
                $completionRate = $item->total_participants > 0 
                    ? round(($item->completed_participants / $item->total_participants) * 100, 1) 
                    : 0;

                return [
                    'county_id' => $item->county_id,
                    'county_name' => $item->county_name,
                    'total_trainings' => $item->total_trainings,
                    'total_participants' => $item->total_participants,
                    'completed_participants' => $item->completed_participants,
                    'completion_rate' => $completionRate,
                    'total_facilities' => $item->total_facilities,
                    'average_score' => round($item->avg_score, 1),
                    'intensity' => $this->calculateCountyIntensity($item->total_trainings, $item->total_participants, $item->total_facilities),
                ];
            })
            ->toArray();
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(array $filters): array
    {
        return [
            'average_score' => TrainingParticipant::whereHas('objectiveResults')
                ->join('participant_objective_results', 'training_participants.id', '=', 'participant_objective_results.participant_id')
                ->avg('participant_objective_results.score') ?: 0,
            'top_performing_counties' => $this->getTopPerformingCounties(5),
            'bottom_performing_counties' => $this->getBottomPerformingCounties(5),
            'training_effectiveness' => $this->calculateTrainingEffectiveness(),
        ];
    }

    /**
     * Get trend analysis
     */
    private function getTrendAnalysis(array $filters): array
    {
        return [
            'monthly_participation' => $this->getMonthlyParticipationTrend(),
            'completion_trends' => $this->getCompletionTrends(),
            'performance_trends' => $this->getPerformanceTrends(),
        ];
    }

    /**
     * Helper methods for statistics
     */
    private function applyFiltersToStatistics(Builder $trainingsQuery, Builder $participantsQuery, array $filters): void
    {
        // Apply common filters to both queries
        if (!empty($filters['training_type'])) {
            $trainingsQuery->whereIn('type', $filters['training_type']);
            $participantsQuery->whereHas('training', function ($q) use ($filters) {
                $q->whereIn('type', $filters['training_type']);
            });
        }
    }

    private function calculateCompletionRate(Builder $query): float
    {
        $total = $query->count();
        if ($total === 0) return 0;
        
        $completed = (clone $query)->where('completion_status', 'completed')->count();
        return round(($completed / $total) * 100, 1);
    }

    private function calculatePassRate(Builder $query): float
    {
        $totalAssessed = (clone $query)->whereHas('objectiveResults')->count();
        if ($totalAssessed === 0) return 0;
        
        $passed = (clone $query)->whereHas('objectiveResults', function($q) {
            $q->havingRaw('AVG(score) >= 70');
        })->count();
        
        return round(($passed / $totalAssessed) * 100, 1);
    }

    private function getTopPerformingCounties(int $limit): array
    {
        return TrainingParticipant::selectRaw('
                subcounties.county_id,
                counties.name as county_name,
                AVG(CASE WHEN overall_score IS NOT NULL THEN overall_score ELSE 0 END) as avg_score,
                COUNT(*) as total_participants,
                COUNT(CASE WHEN completion_status = "completed" THEN 1 END) as completed_participants
            ')
            ->join('users', 'training_participants.user_id', '=', 'users.id')
            ->join('facilities', 'users.facility_id', '=', 'facilities.id')
            ->join('subcounties', 'facilities.subcounty_id', '=', 'subcounties.id')
            ->join('counties', 'subcounties.county_id', '=', 'counties.id')
            ->groupBy('subcounties.county_id', 'counties.name')
            ->having('total_participants', '>=', 10)
            ->orderBy('avg_score', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'county_name' => $item->county_name,
                    'average_score' => round($item->avg_score, 1),
                    'total_participants' => $item->total_participants,
                    'completion_rate' => $item->total_participants > 0 
                        ? round(($item->completed_participants / $item->total_participants) * 100, 1) 
                        : 0,
                ];
            })
            ->toArray();
    }

    private function getBottomPerformingCounties(int $limit): array
    {
        return TrainingParticipant::selectRaw('
                subcounties.county_id,
                counties.name as county_name,
                AVG(CASE WHEN overall_score IS NOT NULL THEN overall_score ELSE 0 END) as avg_score,
                COUNT(*) as total_participants,
                COUNT(CASE WHEN completion_status = "completed" THEN 1 END) as completed_participants
            ')
            ->join('users', 'training_participants.user_id', '=', 'users.id')
            ->join('facilities', 'users.facility_id', '=', 'facilities.id')
            ->join('subcounties', 'facilities.subcounty_id', '=', 'subcounties.id')
            ->join('counties', 'subcounties.county_id', '=', 'counties.id')
            ->groupBy('subcounties.county_id', 'counties.name')
            ->having('total_participants', '>=', 10)
            ->orderBy('avg_score', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'county_name' => $item->county_name,
                    'average_score' => round($item->avg_score, 1),
                    'total_participants' => $item->total_participants,
                    'completion_rate' => $item->total_participants > 0 
                        ? round(($item->completed_participants / $item->total_participants) * 100, 1) 
                        : 0,
                ];
            })
            ->toArray();
    }

    private function calculateTrainingEffectiveness(): float
    {
        $totalParticipants = TrainingParticipant::count();
        if ($totalParticipants === 0) return 0;

        $effectiveParticipants = TrainingParticipant::where('completion_status', 'completed')
            ->whereHas('objectiveResults', function($q) {
                $q->havingRaw('AVG(score) >= 70');
            })
            ->count();

        return round(($effectiveParticipants / $totalParticipants) * 100, 1);
    }

    private function getMonthlyParticipationTrend(): array
    {
        return TrainingParticipant::selectRaw('
                YEAR(registration_date) as year,
                MONTH(registration_date) as month,
                COUNT(*) as total_participants,
                COUNT(CASE WHEN completion_status = "completed" THEN 1 END) as completed_participants
            ')
            ->whereNotNull('registration_date')
            ->where('registration_date', '>=', now()->subYear())
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => "{$item->year}-" . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'total_participants' => $item->total_participants,
                    'completed_participants' => $item->completed_participants,
                    'completion_rate' => $item->total_participants > 0 
                        ? round(($item->completed_participants / $item->total_participants) * 100, 1) 
                        : 0,
                ];
            })
            ->toArray();
    }

    private function getCompletionTrends(): array
    {
        return Training::selectRaw('
                YEAR(start_date) as year,
                MONTH(start_date) as month,
                type,
                COUNT(*) as total_trainings,
                AVG(
                    (SELECT COUNT(*) FROM training_participants tp 
                     WHERE tp.training_id = trainings.id AND tp.completion_status = "completed") * 100.0 /
                    NULLIF((SELECT COUNT(*) FROM training_participants tp2 WHERE tp2.training_id = trainings.id), 0)
                ) as avg_completion_rate
            ')
            ->whereNotNull('start_date')
            ->where('start_date', '>=', now()->subYear())
            ->groupBy('year', 'month', 'type')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->groupBy(function($item) {
                return "{$item->year}-" . str_pad($item->month, 2, '0', STR_PAD_LEFT);
            })
            ->map(function ($monthData, $period) {
                return [
                    'period' => $period,
                    'global_training' => $monthData->where('type', 'global_training')->first()?->avg_completion_rate ?: 0,
                    'facility_mentorship' => $monthData->where('type', 'facility_mentorship')->first()?->avg_completion_rate ?: 0,
                    'total_trainings' => $monthData->sum('total_trainings'),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getPerformanceTrends(): array
    {
        return TrainingParticipant::selectRaw('
                YEAR(registration_date) as year,
                MONTH(registration_date) as month,
                AVG(CASE WHEN overall_score IS NOT NULL THEN overall_score ELSE 0 END) as avg_score,
                COUNT(*) as total_participants
            ')
            ->whereNotNull('registration_date')
            ->where('registration_date', '>=', now()->subYear())
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => "{$item->year}-" . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'average_score' => round($item->avg_score, 1),
                    'total_participants' => $item->total_participants,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate intensity score for a county
     */
    private function calculateCountyIntensity(int $trainings, int $participants, int $facilities): float
    {
        if ($trainings === 0) return 0;

        $trainingScore = $trainings * 2;
        $participantScore = ($participants / max(1, $trainings)) * 1.5;
        $facilityScore = $facilities * 3;

        return round(($trainingScore + $participantScore + $facilityScore) / 6.5, 1);
    }

    /**
     * Apply filters to participant query
     */
    private function applyParticipantFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['completion_status'])) {
            $query->where('completion_status', $filters['completion_status']);
        }

        if (!empty($filters['cadre_id'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->whereIn('cadre_id', $filters['cadre_id']);
            });
        }

        if (!empty($filters['training_type'])) {
            $query->whereHas('training', function ($q) use ($filters) {
                $q->whereIn('type', $filters['training_type']);
            });
        }

        if (!empty($filters['program_id'])) {
            $query->whereHas('training.programs', function ($q) use ($filters) {
                $q->whereIn('programs.id', $filters['program_id']);
            });
        }

        if (!empty($filters['county_id'])) {
            $query->whereHas('user.facility.subcounty', function ($q) use ($filters) {
                $q->whereIn('county_id', $filters['county_id']);
            });
        }

        if (!empty($filters['search'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where(function ($subQ) use ($filters) {
                    $subQ->where('first_name', 'LIKE', '%' . $filters['search'] . '%')
                         ->orWhere('last_name', 'LIKE', '%' . $filters['search'] . '%')
                         ->orWhere('email', 'LIKE', '%' . $filters['search'] . '%');
                });
            });
        }

        if (!empty($filters['date_range'])) {
            $this->applyDateRangeFilter($query, $filters['date_range'], 'registration_date');
        }
    }

    /**
     * Apply filters to training query
     */
    private function applyTrainingFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['training_type'])) {
            $query->whereIn('type', $filters['training_type']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['program_id'])) {
            $query->whereHas('programs', function ($q) use ($filters) {
                $q->whereIn('programs.id', $filters['program_id']);
            });
        }

        if (!empty($filters['date_range'])) {
            $this->applyDateRangeFilter($query, $filters['date_range'], 'start_date');
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('identifier', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }
    }

    /**
     * Check if a participation matches the given filters
     */
    private function participationMatchesFilters(TrainingParticipant $participation, array $filters): bool
    {
        if (!empty($filters['training_type']) && !in_array($participation->training?->type, $filters['training_type'])) {
            return false;
        }

        if (!empty($filters['status']) && !in_array($participation->training?->status, $filters['status'])) {
            return false;
        }

        if (!empty($filters['completion_status']) && $participation->completion_status !== $filters['completion_status']) {
            return false;
        }

        if (!empty($filters['date_range'])) {
            $startDate = $participation->training?->start_date;
            if ($startDate && !$this->dateMatchesRange($startDate, $filters['date_range'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a date matches the given range
     */
    private function dateMatchesRange(Carbon $date, string $range): bool
    {
        switch ($range) {
            case 'last-6-months':
                return $date->gte(now()->subMonths(6));
            case 'last-year':
                return $date->gte(now()->subYear());
            case '2024':
                return $date->year === 2024;
            case '2023':
                return $date->year === 2023;
            case 'current-year':
                return $date->year === now()->year;
            default:
                return true;
        }
    }
}