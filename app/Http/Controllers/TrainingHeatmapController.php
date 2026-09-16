<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Facility;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Filament\Widgets\KenyaTrainingHeatmapWidget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TrainingHeatmapController extends Controller
{
    public function mohHeatmap()
    {
        $widget = new KenyaTrainingHeatmapWidget();
        // Set filter for global training only
        $widget->updateFilters(['training_type' => ['global_training']]);
        
        return view('training.heatmap', [
            'widget' => $widget,
            'widgetId' => 'moh-heatmap-' . uniqid(),
            'type' => 'moh',
            'title' => 'MOH Training Coverage',
            'description' => 'Interactive dashboard showing MOH training participation across all 47 counties'
        ]);
    }

    public function mentorshipHeatmap()
    {
        $widget = new KenyaTrainingHeatmapWidget();
        // Set filter for facility mentorship only
        $widget->updateFilters(['training_type' => ['facility_mentorship']]);
        
        return view('training.heatmap', [
            'widget' => $widget,
            'widgetId' => 'mentorship-heatmap-' . uniqid(),
            'type' => 'mentorship',
            'title' => 'Facility Mentorship Coverage',
            'description' => 'Comprehensive view of facility-based mentorship programs and participant outcomes'
        ]);
    }

    public function facilityParticipants(Request $request, $facilityId)
    {
        // Use caching to avoid repeated heavy queries
        $cacheKey = "facility_participants_{$facilityId}_" . md5($request->getQueryString());
        
        $data = Cache::remember($cacheKey, 15, function () use ($request, $facilityId) {
            return $this->buildFacilityParticipantsData($request, $facilityId);
        });

        return response()->json($data);
    }

    private function buildFacilityParticipantsData(Request $request, $facilityId)
    {
        $facility = Facility::select('id', 'name', 'mfl_code', 'subcounty_id')
            ->with(['subcounty:id,name,county_id', 'subcounty.county:id,name', 'facilityType:id,name'])
            ->findOrFail($facilityId);
        
        $query = TrainingParticipant::select([
                'training_participants.id',
                'training_participants.training_id', 
                'training_participants.user_id',
                'training_participants.completion_status',
                'training_participants.completion_date'
            ])
            ->whereHas('user', fn($q) => $q->where('facility_id', $facilityId))
            ->with([
                'user:id,first_name,last_name,cadre_id,department_id',
                'user.cadre:id,name',
                'user.department:id,name',
                'training:id,title,type,start_date,end_date',
                'training.programs:id,name'
            ]);

        // Apply filters efficiently
        if ($request->has('training_id')) {
            $query->where('training_id', $request->training_id);
        }

        if ($request->has('training_type')) {
            $query->whereHas('training', fn($q) => $q->where('type', $request->training_type));
        }

        $participants = $query->get();

        // Calculate summary efficiently
        $summary = [
            'total_participants' => $participants->count(),
            'unique_users' => $participants->pluck('user_id')->unique()->count(),
            'completed_trainings' => $participants->where('completion_status', 'completed')->count(),
            'in_progress' => $participants->where('completion_status', 'in_progress')->count(),
            'average_score' => 0, // Simplified for performance
            'pass_rate' => 0,     // Simplified for performance
            'cadre_breakdown' => $participants->groupBy('user.cadre.name')->map->count()->take(5),
            'department_breakdown' => $participants->groupBy('user.department.name')->map->count()->take(5)
        ];

        // Group participants by training efficiently
        $participantsByTraining = $participants->groupBy('training.title')->map(function($trainingParticipants) {
            return [
                'training' => $trainingParticipants->first()->training,
                'participants' => $trainingParticipants->map(function($participant) {
                    return [
                        'id' => $participant->user->id,
                        'name' => $participant->user->first_name . ' ' . $participant->user->last_name,
                        'cadre' => $participant->user->cadre?->name,
                        'department' => $participant->user->department?->name,
                        'completion_status' => $participant->completion_status,
                        'completion_date' => $participant->completion_date?->format('M j, Y'),
                        'assessment_score' => null // Simplified for performance
                    ];
                })
            ];
        });

        return [
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'mfl_code' => $facility->mfl_code,
                'subcounty' => $facility->subcounty->name,
                'county' => $facility->subcounty->county->name,
                'type' => $facility->facilityType->name ?? 'Unknown'
            ],
            'summary' => $summary,
            'participants_by_training' => $participantsByTraining,
            'insights' => $this->generateSimpleFacilityInsights($summary)
        ];
    }

    public function participantProfile($userId)
    {
        // Use caching for participant profiles
        $cacheKey = "participant_profile_{$userId}";
        
        $data = Cache::remember($cacheKey, 30, function () use ($userId) {
            return $this->buildParticipantProfileData($userId);
        });

        return response()->json($data);
    }

    private function buildParticipantProfileData($userId)
    {
        $user = User::select([
                'id', 'first_name', 'last_name', 'email', 'phone', 
                'cadre_id', 'department_id', 'facility_id'
            ])
            ->with([
                'facility:id,name,mfl_code,subcounty_id',
                'facility.subcounty:id,name,county_id',
                'facility.subcounty.county:id,name',
                'cadre:id,name',
                'department:id,name'
            ])
            ->findOrFail($userId);

        // Get training history efficiently
        $trainingHistory = TrainingParticipant::select([
                'training_participants.id',
                'training_participants.training_id',
                'training_participants.registration_date',
                'training_participants.completion_status',
                'training_participants.completion_date'
            ])
            ->where('user_id', $userId)
            ->with([
                'training:id,title,type,start_date,end_date',
                'training.programs:id,name'
            ])
            ->orderBy('registration_date', 'desc')
            ->limit(20) // Limit for performance
            ->get()
            ->map(function($participation) {
                return [
                    'training_id' => $participation->training->id,
                    'training_title' => $participation->training->title,
                    'training_type' => $participation->training->type,
                    'programs' => $participation->training->programs->pluck('name')->implode(', '),
                    'registration_date' => $participation->registration_date?->format('M j, Y'),
                    'start_date' => $participation->training->start_date?->format('M j, Y'),
                    'end_date' => $participation->training->end_date?->format('M j, Y'),
                    'completion_status' => $participation->completion_status,
                    'completion_date' => $participation->completion_date?->format('M j, Y'),
                    'assessment_score' => null, // Simplified
                    'assessment_status' => null, // Simplified
                    'assessment_details' => []   // Simplified
                ];
            });

        // Calculate simplified metrics
        $totalTrainings = $trainingHistory->count();
        $completedTrainings = $trainingHistory->where('completion_status', 'completed')->count();
        $completionRate = $totalTrainings > 0 ? round(($completedTrainings / $totalTrainings) * 100, 1) : 0;

        $trainingSummary = [
            'total_trainings' => $totalTrainings,
            'completed' => $completedTrainings,
            'completion_rate' => $completionRate,
            'average_score' => null, // Simplified
            'latest_training' => $trainingHistory->first()
        ];

        $performanceMetrics = [
            'overall_score' => null,
            'completion_rate' => $completionRate,
            'assessment_completion' => 0,
            'trend' => 'Stable',
            'strengths' => [],
            'improvement_areas' => []
        ];

        return [
            'user' => [
                'id' => $user->id,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'phone' => $user->phone,
                'cadre' => $user->cadre?->name,
                'department' => $user->department?->name,
                'facility' => [
                    'name' => $user->facility?->name,
                    'mfl_code' => $user->facility?->mfl_code,
                    'subcounty' => $user->facility?->subcounty?->name,
                    'county' => $user->facility?->subcounty?->county?->name
                ],
                'current_status' => 'Active'
            ],
            'training_summary' => $trainingSummary,
            'training_history' => $trainingHistory,
            'performance_metrics' => $performanceMetrics,
            'recommendations' => [],
            'insights' => []
        ];
    }

    private function generateSimpleFacilityInsights($summary)
    {
        $insights = [];

        if ($summary['pass_rate'] >= 80) {
            $insights[] = "🌟 Excellent performance! High engagement in training programs.";
        } elseif ($summary['total_participants'] > 20) {
            $insights[] = "📈 High participation with {$summary['total_participants']} trainees.";
        }

        if ($summary['completed_trainings'] > 0) {
            $insights[] = "✅ {$summary['completed_trainings']} successful training completions.";
        }

        return $insights;
    }
}