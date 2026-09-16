<?php

namespace App\Services;

use App\Models\User;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\Facility;
use App\Models\Program;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SmartParticipantSuggestionService
{
    /**
     * Suggest participants for a training based on intelligent criteria
     */
    public function suggestParticipants(Training $training, int $limit = 20): Collection
    {
        $suggestions = collect();

        // Get users who haven't participated in this training
        $excludeUserIds = $training->participants()->pluck('user_id')->toArray();

        $query = User::whereNotIn('id', $excludeUserIds)
            ->where('status', 'active')
            ->with(['facility.subcounty.county', 'department', 'cadre', 'trainingParticipations']);

        // Apply training-type specific filters
        $query = $this->applyTrainingTypeFilters($query, $training);

        // Apply department filters if specified
        $query = $this->applyDepartmentFilters($query, $training);

        $candidateUsers = $query->get();

        // Score and rank candidates
        foreach ($candidateUsers as $user) {
            $score = $this->calculateUserScore($user, $training);

            if ($score > 0) {
                $suggestions->push([
                    'user' => $user,
                    'score' => $score,
                    'reasons' => $this->getSelectionReasons($user, $training),
                    'priority' => $this->calculatePriority($score),
                    'recommendation_strength' => $this->getRecommendationStrength($score),
                ]);
            }
        }

        return $suggestions->sortByDesc('score')->take($limit);
    }

    /**
     * Apply training type specific filters
     */
    private function applyTrainingTypeFilters($query, Training $training)
    {
        // If it's facility mentorship, only suggest users from that facility
        if ($training->isFacilityMentorship() && $training->facility_id) {
            $query->where('facility_id', $training->facility_id);
        }

        // For global trainings, prioritize users from nearby facilities
        if ($training->isGlobalTraining() && $training->facility_id) {
            $nearbyFacilityIds = $this->getNearbyFacilities($training->facility_id);
            // Don't filter strictly, but we'll use this for scoring
        }

        return $query;
    }

    /**
     * Apply department filters
     */
    private function applyDepartmentFilters($query, Training $training)
    {
        // Filter by target departments if specified
        if ($training->departments()->exists()) {
            $departmentIds = $training->departments()->pluck('department_id')->toArray();
            $query->whereIn('department_id', $departmentIds);
        }

        return $query;
    }

    /**
     * Calculate user score based on multiple criteria
     */
    private function calculateUserScore(User $user, Training $training): float
    {
        $score = 0;

        // Base score for active users
        $score += 10;

        // Department relevance
        $score += $this->scoreDepartmentRelevance($user, $training);

        // Training history analysis
        $score += $this->scoreTrainingHistory($user, $training);

        // Performance in previous trainings
        $score += $this->scorePreviousPerformance($user);

        // Cadre relevance to program
        $score += $this->scoreCadreRelevance($user, $training);

        // Geographic proximity (for global trainings)
        $score += $this->scoreGeographicProximity($user, $training);

        // Workload balance (avoid overloading individuals)
        $score += $this->scoreWorkloadBalance($user);

        // Career development opportunities
        $score += $this->scoreCareerDevelopment($user, $training);

        return max(0, $score);
    }

    /**
     * Score department relevance
     */
    private function scoreDepartmentRelevance(User $user, Training $training): float
    {
        $score = 0;

        // Direct department match
        if ($training->departments()->where('department_id', $user->department_id)->exists()) {
            $score += 25;
        }

        // Related department bonus
        $relatedDepartments = $this->getRelatedDepartments($user->department);
        $trainingDepartments = $training->departments()->pluck('name')->toArray();

        foreach ($relatedDepartments as $related) {
            if (in_array($related, $trainingDepartments)) {
                $score += 10;
                break;
            }
        }

        return $score;
    }

    /**
     * Score training history
     */
    private function scoreTrainingHistory(User $user, Training $training): float
    {
        $score = 0;
        $trainingCount = $user->trainingParticipations()->count();

        // Encourage new participants
        if ($trainingCount === 0) {
            $score += 20; // New to training
        } elseif ($trainingCount < 3) {
            $score += 15; // Limited experience - good for development
        } elseif ($trainingCount < 6) {
            $score += 10; // Moderate experience
        } else {
            $score += 5; // Experienced - lower priority for basic training
        }

        // Check for similar program participation
        $similarProgramParticipation = $user->trainingParticipations()
            ->whereHas('training', function ($query) use ($training) {
                $query->where('program_id', $training->program_id);
            })
            ->count();

        if ($similarProgramParticipation === 0) {
            $score += 15; // New to this program type
        } elseif ($similarProgramParticipation === 1) {
            $score += 5; // Some exposure, refresher might be good
        } else {
            $score -= 10; // Already well-trained in this program
        }

        return $score;
    }

    /**
     * Score previous performance
     */
    private function scorePreviousPerformance(User $user): float
    {
        $score = 0;

        $completedTrainings = $user->trainingParticipations()
            ->where('completion_status', 'completed')
            ->count();

        $totalTrainings = $user->trainingParticipations()->count();

        if ($totalTrainings > 0) {
            $completionRate = ($completedTrainings / $totalTrainings) * 100;

            if ($completionRate >= 90) {
                $score += 20; // Excellent track record
            } elseif ($completionRate >= 75) {
                $score += 15; // Good track record
            } elseif ($completionRate >= 60) {
                $score += 10; // Average track record
            } else {
                $score += 5; // Poor track record, but give a chance
            }

            // Bonus for consistently high performance
            $avgScore = $user->trainingParticipations()
                ->where('final_score', '>', 0)
                ->avg('final_score');

            if ($avgScore >= 85) {
                $score += 10;
            } elseif ($avgScore >= 75) {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * Score cadre relevance to program
     */
    private function scoreCadreRelevance(User $user, Training $training): float
    {
        if (!$user->cadre || !$training->program) {
            return 0;
        }

        $relevanceScore = $this->calculateCadreProgramRelevance($user->cadre, $training->program);
        return $relevanceScore * 15; // Max 15 points for high relevance
    }

    /**
     * Score geographic proximity
     */
    private function scoreGeographicProximity(User $user, Training $training): float
    {
        $score = 0;

        if (!$training->isGlobalTraining() || !$training->facility_id || !$user->facility_id) {
            return $score;
        }

        if ($user->facility_id === $training->facility_id) {
            $score += 15; // Same facility
        } elseif ($this->areNearbyFacilities($user->facility, $training->facility)) {
            $score += 10; // Nearby facilities
        } elseif ($this->areSameCounty($user->facility, $training->facility)) {
            $score += 5; // Same county
        }

        return $score;
    }

    /**
     * Score workload balance
     */
    private function scoreWorkloadBalance(User $user): float
    {
        $score = 0;

        // Check recent training participation
        $recentParticipations = $user->trainingParticipations()
            ->where('registration_date', '>=', now()->subMonth())
            ->count();

        if ($recentParticipations === 0) {
            $score += 10; // No recent training - good to engage
        } elseif ($recentParticipations === 1) {
            $score += 5; // Moderate recent activity
        } elseif ($recentParticipations === 2) {
            $score += 0; // Neutral
        } else {
            $score -= 15; // Overloaded - avoid for now
        }

        // Check ongoing trainings
        $ongoingTrainings = $user->trainingParticipations()
            ->whereHas('training', function ($query) {
                $query->where('status', 'ongoing');
            })
            ->count();

        if ($ongoingTrainings >= 2) {
            $score -= 20; // Too many ongoing commitments
        } elseif ($ongoingTrainings === 1) {
            $score -= 5; // One ongoing commitment
        }

        return $score;
    }

    /**
     * Score career development opportunities
     */
    private function scoreCareerDevelopment(User $user, Training $training): float
    {
        $score = 0;

        // Check if this training would fill skill gaps
        $userSkillAreas = $this->getUserSkillAreas($user);
        $trainingSkillAreas = $this->getTrainingSkillAreas($training);

        $skillGaps = array_diff($trainingSkillAreas, $userSkillAreas);
        $score += count($skillGaps) * 3; // 3 points per skill gap addressed

        // Check career progression potential
        if ($this->isCareerProgressionTraining($user, $training)) {
            $score += 15;
        }

        return min($score, 20); // Cap at 20 points
    }

    /**
     * Get selection reasons for a user
     */
    private function getSelectionReasons(User $user, Training $training): array
    {
        $reasons = [];

        // Department match
        if ($training->departments()->where('department_id', $user->department_id)->exists()) {
            $reasons[] = 'Target department match';
        }

        // Training history
        $trainingCount = $user->trainingParticipations()->count();
        if ($trainingCount === 0) {
            $reasons[] = 'New to training programs';
        } elseif ($trainingCount < 3) {
            $reasons[] = 'Limited training experience - good for development';
        }

        // Performance history
        $completedCount = $user->trainingParticipations()
            ->where('completion_status', 'completed')
            ->count();

        if ($completedCount > 0) {
            $completionRate = ($completedCount / $trainingCount) * 100;
            if ($completionRate >= 80) {
                $reasons[] = "Strong completion rate ({$completionRate}%)";
            }
        }

        // Cadre relevance
        if ($this->calculateCadreProgramRelevance($user->cadre, $training->program) > 0.7) {
            $reasons[] = 'Highly relevant professional background';
        }

        // Geographic proximity
        if ($training->isGlobalTraining() && $this->areNearbyFacilities($user->facility, $training->facility)) {
            $reasons[] = 'Convenient location';
        }

        // Career development
        if ($this->isCareerProgressionTraining($user, $training)) {
            $reasons[] = 'Good for career advancement';
        }

        if (empty($reasons)) {
            $reasons[] = 'Active staff member eligible for training';
        }

        return $reasons;
    }

    /**
     * Calculate priority level
     */
    private function calculatePriority(float $score): string
    {
        if ($score >= 80) {
            return 'high';
        } elseif ($score >= 60) {
            return 'medium';
        } elseif ($score >= 40) {
            return 'low';
        }

        return 'very_low';
    }

    /**
     * Get recommendation strength
     */
    private function getRecommendationStrength(float $score): string
    {
        if ($score >= 85) {
            return 'strongly_recommended';
        } elseif ($score >= 70) {
            return 'recommended';
        } elseif ($score >= 50) {
            return 'consider';
        }

        return 'low_priority';
    }

    /**
     * Helper methods
     */
    private function getRelatedDepartments(Department $department): array
    {
        $relationshipMap = [
            'clinical' => ['nursing', 'medical', 'surgery'],
            'laboratory' => ['pathology', 'diagnostics', 'research'],
            'pharmacy' => ['clinical pharmacy', 'dispensing', 'procurement'],
            'administration' => ['management', 'hr', 'finance'],
        ];

        $deptName = strtolower($department->name);

        foreach ($relationshipMap as $category => $related) {
            if (Str::contains($deptName, $category) || in_array($deptName, $related)) {
                return $related;
            }
        }

        return [];
    }

    private function calculateCadreProgramRelevance(Cadre $cadre, Program $program): float
    {
        $relevanceMap = [
            'clinical' => ['nurse', 'clinical officer', 'doctor', 'midwife', 'medical'],
            'laboratory' => ['lab technician', 'lab technologist', 'pathologist', 'scientist'],
            'pharmacy' => ['pharmacist', 'pharmaceutical technician', 'chemist'],
            'management' => ['manager', 'supervisor', 'administrator', 'coordinator'],
            'support' => ['clerk', 'assistant', 'attendant', 'support'],
        ];

        $programName = strtolower($program->name);
        $cadreName = strtolower($cadre->name);

        $maxRelevance = 0;

        foreach ($relevanceMap as $programType => $relevantCadres) {
            if (Str::contains($programName, $programType)) {
                foreach ($relevantCadres as $relevantCadre) {
                    if (Str::contains($cadreName, $relevantCadre)) {
                        $maxRelevance = max($maxRelevance, 1.0);
                    }
                }
            }
        }

        return $maxRelevance;
    }

    private function areNearbyFacilities(?Facility $facility1, ?Facility $facility2): bool
    {
        if (!$facility1 || !$facility2) {
            return false;
        }

        // Same subcounty = nearby
        return $facility1->subcounty_id === $facility2->subcounty_id;
    }

    private function areSameCounty(?Facility $facility1, ?Facility $facility2): bool
    {
        if (!$facility1 || !$facility2) {
            return false;
        }

        return $facility1->subcounty->county_id === $facility2->subcounty->county_id;
    }

    private function getNearbyFacilities(int $facilityId): array
    {
        $facility = Facility::find($facilityId);
        if (!$facility) {
            return [];
        }

        return Facility::where('subcounty_id', $facility->subcounty_id)
            ->pluck('id')
            ->toArray();
    }

    private function getUserSkillAreas(User $user): array
    {
        // Get skill areas from completed trainings
        return $user->trainingParticipations()
            ->whereHas('training.program')
            ->with('training.program')
            ->where('completion_status', 'completed')
            ->get()
            ->pluck('training.program.name')
            ->map(fn($name) => strtolower($name))
            ->unique()
            ->toArray();
    }

    private function getTrainingSkillAreas(Training $training): array
    {
        $skillAreas = [];

        if ($training->program) {
            $skillAreas[] = strtolower($training->program->name);
        }

        // Add modules as skill areas
        $moduleSkills = $training->modules()
            ->pluck('name')
            ->map(fn($name) => strtolower($name))
            ->toArray();

        return array_merge($skillAreas, $moduleSkills);
    }

    private function isCareerProgressionTraining(User $user, Training $training): bool
    {
        // Check if training is advanced level for user's cadre
        $advancedKeywords = ['advanced', 'leadership', 'management', 'supervisor', 'senior'];
        $trainingTitle = strtolower($training->title);
        $programName = strtolower($training->program?->name ?? '');

        foreach ($advancedKeywords as $keyword) {
            if (Str::contains($trainingTitle, $keyword) || Str::contains($programName, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get suggestions with filters applied
     */
    public function getSuggestionsWithFilters(Training $training, array $filters = []): Collection
    {
        $suggestions = $this->suggestParticipants($training, $filters['limit'] ?? 50);

        // Apply additional filters
        if (!empty($filters['department_ids'])) {
            $suggestions = $suggestions->filter(function ($suggestion) use ($filters) {
                return in_array($suggestion['user']->department_id, $filters['department_ids']);
            });
        }

        if (!empty($filters['cadre_ids'])) {
            $suggestions = $suggestions->filter(function ($suggestion) use ($filters) {
                return in_array($suggestion['user']->cadre_id, $filters['cadre_ids']);
            });
        }

        if (!empty($filters['priority'])) {
            $suggestions = $suggestions->filter(function ($suggestion) use ($filters) {
                return $suggestion['priority'] === $filters['priority'];
            });
        }

        if (!empty($filters['min_score'])) {
            $suggestions = $suggestions->filter(function ($suggestion) use ($filters) {
                return $suggestion['score'] >= $filters['min_score'];
            });
        }

        return $suggestions->take($filters['limit'] ?? 20);
    }
}
