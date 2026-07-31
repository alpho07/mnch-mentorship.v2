<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Training extends Model
{
    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'type', // 'global_training' or 'facility_mentorship'
        'lead_type', // 'national', 'county', 'partner'
        'status',
        'identifier',
        'program_id',
        'facility_id',
        'county_id', // Added for mentorships
        'lead_county_id', // for county-led trainings
        'lead_partner_id', // for partner-led trainings
        'lead_division_id', // for national-led trainings
        'approved_training_area_id', // for training areas
        'organizer_id',
        'mentor_id',
        'start_date',
        'end_date',
        'registration_deadline',
        'max_participants',
        'target_audience',
        'completion_criteria',
        'materials_needed',
        'learning_outcomes',
        'prerequisites',
        'training_approaches',
        'notes',
        'assess_participants',
        'provide_materials',
        'location_type', // 'hospital', 'hotel', 'online'
        'online_link', // for online trainings
        'deleted_by',
        'deletion_reason',
        'is_pilot',
        'guided_setup_completed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_deadline' => 'datetime',
        'materials_needed' => 'array',
        'learning_outcomes' => 'array',
        'prerequisites' => 'array',
        'training_approaches' => 'array',
        'assess_participants' => 'boolean',
        'provide_materials' => 'boolean',
        'is_pilot' => 'boolean',
        'guided_setup_completed_at' => 'datetime',
    ];

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_pilot', false);
    }

    public function scopePilot(Builder $query): Builder
    {
        return $query->where('is_pilot', true);
    }

    /**
     * Facility-mentorship trainings started via the guided wizard but never
     * finished (Send Invitations never submitted). Legacy /create and
     * pre-migration trainings are backfilled to "complete" and excluded.
     */
    public function scopePendingGuidedSetup(Builder $query): Builder
    {
        return $query->where('type', 'facility_mentorship')
            ->whereNull('guided_setup_completed_at');
    }

    // ============================================
    // BASIC RELATIONSHIPS
    // ============================================

    /**
     * Training belongs to an approved training area
     */
    public function approvedTrainingArea(): BelongsTo
    {
        return $this->belongsTo(ApprovedTrainingArea::class, 'approved_training_area_id');
    }

    /**
     * Training belongs to a program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Training can have multiple programs (many-to-many)
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'training_programs', 'training_id', 'program_id');
    }

    /**
     * Training belongs to a facility
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Training belongs to a county (for mentorships)
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * Lead county for county-led trainings
     */
    public function leadCounty(): BelongsTo
    {
        return $this->belongsTo(County::class, 'lead_county_id');
    }

    /**
     * Lead partner for partner-led trainings
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'lead_partner_id');
    }

    /**
     * Division for national-led trainings
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'lead_division_id');
    }

    /**
     * Training organizer
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Training mentor (lead mentor for mentorships)
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /**
     * User who soft-deleted this mentorship
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ============================================
    // MENTORSHIP CLASS SYSTEM RELATIONSHIPS
    // ============================================

    /**
     * Training has many mentorship classes (cohorts)
     */
    public function mentorshipClasses(): HasMany
    {
        return $this->hasMany(MentorshipClass::class, 'training_id');
    }

    /**
     * Training has many co-mentors
     */
    public function coMentors(): HasMany
    {
        return $this->hasMany(MentorshipCoMentor::class, 'training_id');
    }

    /**
     * [TDD] Get only accepted co-mentors (active access).
     */
    public function acceptedCoMentors(): HasMany
    {
        return $this->hasMany(MentorshipCoMentor::class, 'training_id')
            ->where('status', 'accepted');
    }

    /**
     * [TDD] Mentorship has many module usage records.
     * Tracks which modules have been taught across all classes.
     * Domain invariant: UNIQUE(mentorship_id, module_id).
     */
    public function moduleUsages(): HasMany
    {
        return $this->hasMany(MentorshipModuleUsage::class, 'mentorship_id');
    }

    /**
     * Get all class sessions across all classes for this training
     */
    public function allSessions()
    {
        return ClassSession::whereHas('classModule.mentorshipClass', function ($query) {
            $query->where('training_id', $this->id);
        });
    }

    /**
     * [TDD] Get all attendance records across all classes in this mentorship.
     * Uses the class_attendances table (authoritative attendance source).
     */
    public function classAttendances()
    {
        return ClassAttendance::whereIn(
            'class_id',
            $this->mentorshipClasses()->pluck('id')
        );
    }

    // ============================================
    // PARTICIPANTS & ATTENDANCE
    // ============================================

    /**
     * Training has many participants (mentees)
     */
    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    /**
     * Training has many sessions (old system, kept for compatibility)
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    // ============================================
    // MANY-TO-MANY RELATIONSHIPS
    // ============================================

    /**
     * Training targets multiple departments
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'training_departments');
    }

    /**
     * Training covers multiple modules
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'training_modules');
    }

    /**
     * Training uses multiple methodologies
     */
    public function methodologies(): BelongsToMany
    {
        return $this->belongsToMany(Methodology::class, 'training_methodologies');
    }

    /**
     * Training targets multiple facilities
     */
    public function targetFacilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'training_target_facilities', 'training_id', 'facility_id');
    }

    /**
     * Training locations
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'training_locations', 'training_id', 'location_id');
    }

    /**
     * Training associated counties (many-to-many)
     */
    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class, 'training_counties', 'training_id', 'county_id');
    }

    /**
     * Training associated partners (many-to-many)
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class, 'training_partners', 'training_id', 'partner_id');
    }

    /**
     * Training hospital locations (many-to-many)
     */
    public function hospitals(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'training_hospitals', 'training_id', 'facility_id');
    }

    /**
     * Training hotel locations (one-to-many)
     */
    public function hotels(): HasMany
    {
        return $this->hasMany(TrainingHotel::class, 'training_id');
    }

    // ============================================
    // MATERIALS & ASSESSMENTS
    // ============================================

    /**
     * Training has many materials
     */
    public function trainingMaterials(): HasMany
    {
        return $this->hasMany(TrainingMaterial::class);
    }

    /**
     * Training has many assessment categories
     */
    public function assessmentCategories(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentCategory::class, 'training_assessment_categories')
            ->withPivot([
                'weight_percentage',
                'pass_threshold',
                'is_required',
                'order_sequence',
                'is_active',
            ])
            ->withTimestamps()
            ->wherePivot('is_active', true)
            ->orderByPivot('order_sequence');
    }

    // ============================================
    // TYPE CHECKER METHODS
    // ============================================

    /**
     * Check if training is a global training
     */
    public function isGlobalTraining(): bool
    {
        return $this->type === 'global_training';
    }

    /**
     * Check if training is a facility mentorship
     */
    public function isFacilityMentorship(): bool
    {
        return $this->type === 'facility_mentorship';
    }

    /**
     * Check if training is nationally led
     */
    public function isNationalLed(): bool
    {
        return $this->lead_type === 'national';
    }

    /**
     * Check if training is county led
     */
    public function isCountyLed(): bool
    {
        return $this->lead_type === 'county';
    }

    /**
     * Check if training is partner led
     */
    public function isPartnerLed(): bool
    {
        return $this->lead_type === 'partner';
    }

    // ============================================
    // MENTORSHIP-SPECIFIC HELPER METHODS
    // ============================================

    /**
     * Check if user is a co-mentor for this training
     */
    public function isCoMentor(int $userId): bool
    {
        return $this->coMentors()
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * Check if user can facilitate sessions (lead mentor or accepted co-mentor)
     */
    public function canUserFacilitate(int $userId): bool
    {
        return $this->mentor_id === $userId || $this->isCoMentor($userId);
    }

    /**
     * Scope trainings where the user is the lead mentor or an accepted co-mentor.
     */
    public function scopeForMentorOrCoMentor(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('mentor_id', $userId)
                ->orWhereHas('acceptedCoMentors', fn (Builder $cm) => $cm->where('user_id', $userId));
        });
    }

    public function hasEnrolledMentees(): bool
    {
        return ClassParticipant::whereHas(
            'mentorshipClass', fn ($q) => $q->where('training_id', $this->id)
        )->exists();
    }

    public function hasActiveOrCompletedClasses(): bool
    {
        return $this->mentorshipClasses()
            ->whereIn('status', ['active', 'completed'])
            ->exists();
    }

    public function canBeDeleted(): bool
    {
        return ! $this->hasEnrolledMentees() && ! $this->hasActiveOrCompletedClasses();
    }

    /**
     * Get total number of classes for this training
     */
    public function getClassesCountAttribute(): int
    {
        return $this->mentorshipClasses()->count();
    }

    /**
     * Get total number of modules across all classes
     */
    public function getTotalModulesCountAttribute(): int
    {
        return ClassModule::whereHas('mentorshipClass', function ($query) {
            $query->where('training_id', $this->id);
        })->count();
    }

    /**
     * Get total number of completed modules
     */
    public function getCompletedModulesCountAttribute(): int
    {
        return ClassModule::whereHas('mentorshipClass', function ($query) {
            $query->where('training_id', $this->id);
        })->where('status', 'completed')->count();
    }

    /**
     * Get total session count across all classes
     */
    public function getTotalSessionsCountAttribute(): int
    {
        return $this->allSessions()->count();
    }

    /**
     * Get completed session count
     */
    public function getCompletedSessionsCountAttribute(): int
    {
        return $this->allSessions()->where('status', 'completed')->count();
    }

    /**
     * Get overall mentorship progress percentage
     */
    public function getMentorshipProgressAttribute(): float
    {
        $totalSessions = $this->total_sessions_count;

        if ($totalSessions === 0) {
            return 0;
        }

        $completedSessions = $this->completed_sessions_count;

        return round(($completedSessions / $totalSessions) * 100, 2);
    }

    /**
     * Get distinct module IDs assigned across classes in this mentorship.
     */
    public function getUsedModuleIdsAttribute(): array
    {
        return ClassModule::whereHas('mentorshipClass', fn ($query) => $query->where('training_id', $this->id))
            ->distinct()
            ->pluck('program_module_id')
            ->toArray();
    }

    /**
     * Get count of program modules not yet assigned to any class in this mentorship.
     */
    public function getAvailableModulesCountAttribute(): int
    {
        $totalProgramModules = ProgramModule::whereHas('program', function ($query) {
            $query->where('id', $this->program_id);
        })->where('is_active', true)->whereNull('parent_id')->count();

        $usedCount = ClassModule::whereHas('mentorshipClass', fn ($query) => $query->where('training_id', $this->id))
            ->distinct('program_module_id')
            ->count('program_module_id');

        return max(0, $totalProgramModules - $usedCount);
    }

    /**
     * [TDD] Get total attendance records from class_attendances (authoritative).
     */
    public function getAttendanceRecordsCountAttribute(): int
    {
        return $this->classAttendances()->count();
    }

    // ============================================
    // COMPUTED ATTRIBUTES
    // ============================================

    /**
     * Get lead organization name
     */
    public function getLeadOrganizationAttribute(): string
    {
        return match ($this->lead_type) {
            'national' => $this->division?->name ?? 'Ministry of Health',
            'county' => $this->counties->pluck('name')->implode(', ') ?: 'County not specified',
            'partner' => $this->partners->pluck('name')->implode(', ') ?: 'Partner not specified',
            default => 'Not specified',
        };
    }

    /**
     * Get training status based on dates
     */
    public function getTrainingStatusAttribute(): string
    {
        if ($this->status) {
            return $this->status;
        }

        $now = now();

        if (! $this->start_date || ! $this->end_date) {
            return 'draft';
        }

        if ($now->lt($this->start_date)) {
            return 'upcoming';
        }

        if ($now->between($this->start_date, $this->end_date)) {
            return 'ongoing';
        }

        if ($now->gt($this->end_date)) {
            return 'completed';
        }

        return 'draft';
    }

    /**
     * Get duration in days
     */
    public function getDurationDaysAttribute(): int
    {
        if (! $this->start_date || ! $this->end_date) {
            return 0;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Get human-readable duration label
     */
    public function getDurationLabelAttribute(): ?string
    {
        $days = $this->duration_days; // reuses existing accessor
        if ($days <= 0) {
            return null;
        }
        if ($days < 7) {
            return $days.($days === 1 ? ' day' : ' days');
        }
        if ($days < 14) {
            return '1 week';
        }
        if ($days < 28) {
            $weeks = (int) floor($days / 7);

            return $weeks.' weeks';
        }
        if ($days < 60) {
            return '1 month';
        }
        if ($days < 365) {
            $months = (int) floor($days / 30);

            return $months.($months === 1 ? ' month' : ' months');
        }
        $years = (int) floor($days / 365);

        return $years.($years === 1 ? ' year' : ' years');
    }

    /**
     * Get remaining capacity
     */
    public function getRemainingCapacityAttribute(): string
    {
        if (! $this->max_participants) {
            return 'Unlimited';
        }

        $enrolled = $this->participants()->count();
        $remaining = $this->max_participants - $enrolled;

        return $remaining > 0 ? (string) $remaining : '0';
    }

    /**
     * Get capacity utilization percentage
     */
    public function getCapacityUtilizationAttribute(): float
    {
        if (! $this->max_participants) {
            return 0;
        }

        $enrolled = $this->participants()->count();

        return round(($enrolled / $this->max_participants) * 100, 1);
    }

    /**
     * Get completion rate of participants
     */
    public function getCompletionRateAttribute(): float
    {
        $total = $this->participants()->count();

        if ($total === 0) {
            return 0;
        }

        $completed = $this->participants()
            ->where('completion_status', 'completed')
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get average score of participants
     */
    public function getAverageScoreAttribute(): float
    {
        return $this->participants()
            ->join('participant_objective_results', 'training_participants.id', '=', 'participant_objective_results.participant_id')
            ->avg('participant_objective_results.score') ?? 0;
    }

    /**
     * Get total planned material cost
     */
    public function getTotalMaterialCostAttribute(): float
    {
        return $this->trainingMaterials()->sum('total_cost') ?? 0;
    }

    /**
     * Get actual material cost
     */
    public function getActualMaterialCostAttribute(): float
    {
        return $this->trainingMaterials()
            ->selectRaw('SUM(quantity_used * unit_cost) as actual_cost')
            ->value('actual_cost') ?? 0;
    }

    /**
     * Get material utilization rate
     */
    public function getMaterialUtilizationRateAttribute(): float
    {
        $totalPlanned = $this->trainingMaterials()->sum('quantity_planned');
        $totalUsed = $this->trainingMaterials()->sum('quantity_used');

        return $totalPlanned > 0 ? round(($totalUsed / $totalPlanned) * 100, 1) : 0;
    }

    // ============================================
    // QUERY SCOPES
    // ============================================

    /**
     * Scope to filter global trainings
     */
    public function scopeGlobalTrainings($query)
    {
        return $query->where('type', 'global_training');
    }

    /**
     * Scope to filter facility mentorships
     */
    public function scopeFacilityMentorships($query)
    {
        return $query->where('type', 'facility_mentorship');
    }

    /**
     * Scope to filter nationally led trainings
     */
    public function scopeNationalLed($query)
    {
        return $query->where('lead_type', 'national');
    }

    /**
     * Scope to filter county led trainings
     */
    public function scopeCountyLed($query)
    {
        return $query->where('lead_type', 'county');
    }

    /**
     * Scope to filter partner led trainings
     */
    public function scopePartnerLed($query)
    {
        return $query->where('lead_type', 'partner');
    }

    /**
     * Scope for open registration
     */
    public function scopeRegistrationOpen($query)
    {
        return $query->where('status', 'registration_open')
            ->where('registration_deadline', '>=', now());
    }

    /**
     * Scope for ongoing trainings
     */
    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    /**
     * Scope for upcoming trainings
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    /**
     * Scope for completed trainings
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter trainings with assessments
     */
    public function scopeWithAssessments($query)
    {
        return $query->where('assess_participants', true)
            ->orWhereHas('assessmentCategories');
    }

    /**
     * Scope to filter trainings with materials
     */
    public function scopeWithMaterials($query)
    {
        return $query->where('provide_materials', true)
            ->orWhereHas('trainingMaterials');
    }

    // ============================================
    // ANALYTICS METHODS
    // ============================================

    /**
     * Get participants grouped by facility
     */
    public function getParticipantsByFacility(): Collection
    {
        return $this->participants()
            ->join('users', 'training_participants.user_id', '=', 'users.id')
            ->join('facilities', 'users.facility_id', '=', 'facilities.id')
            ->groupBy('facilities.name')
            ->selectRaw('facilities.name, COUNT(*) as count')
            ->get();
    }

    /**
     * Get participants grouped by department
     */
    public function getParticipantsByDepartment(): Collection
    {
        return $this->participants()
            ->join('users', 'training_participants.user_id', '=', 'users.id')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->groupBy('departments.name')
            ->selectRaw('departments.name, COUNT(*) as count')
            ->get();
    }

    /**
     * Get participants grouped by cadre
     */
    public function getParticipantsByCadre(): Collection
    {
        return $this->participants()
            ->join('users', 'training_participants.user_id', '=', 'users.id')
            ->join('cadres', 'users.cadre_id', '=', 'cadres.id')
            ->groupBy('cadres.name')
            ->selectRaw('cadres.name, COUNT(*) as count')
            ->get();
    }

    /**
     * Get material wastage report
     */
    public function getMaterialWastage(): Collection
    {
        return $this->trainingMaterials()
            ->with('inventoryItem')
            ->get()
            ->filter(fn ($material) => $material->wastage_quantity > 0)
            ->map(fn ($material) => [
                'material' => $material->inventoryItem->name,
                'wastage_quantity' => $material->wastage_quantity,
                'wastage_cost' => $material->wastage_quantity * $material->unit_cost,
            ]);
    }

    // ============================================
    // ASSESSMENT METHODS
    // ============================================

    /**
     * Attach assessment categories to training
     */
    public function attachAssessmentCategories(array $categories): void
    {
        $attachData = [];

        foreach ($categories as $categoryId => $settings) {
            $category = AssessmentCategory::find($categoryId);

            if (! $category) {
                continue;
            }

            $attachData[$categoryId] = [
                'weight_percentage' => $settings['weight_percentage'] ?? $category->default_weight_percentage,
                'pass_threshold' => $settings['pass_threshold'] ?? 70.00,
                'is_required' => $settings['is_required'] ?? $category->is_required,
                'order_sequence' => $settings['order_sequence'] ?? $category->order_sequence,
                'is_active' => $settings['is_active'] ?? true,
            ];
        }

        $this->assessmentCategories()->sync($attachData);
    }

    /**
     * Update assessment category settings
     */
    public function updateCategorySettings(int $categoryId, array $settings): bool
    {
        return $this->assessmentCategories()->updateExistingPivot($categoryId, $settings);
    }

    /**
     * Get category weight
     */
    public function getCategoryWeight(int $categoryId): ?float
    {
        $category = $this->assessmentCategories()->find($categoryId);

        return $category?->pivot->weight_percentage;
    }

    /**
     * Validate assessment category weights total to 100%
     */
    public function validateCategoryWeights(): array
    {
        $totalWeight = $this->assessmentCategories()->sum('training_assessment_categories.weight_percentage');

        return [
            'total_weight' => $totalWeight,
            'is_valid' => abs($totalWeight - 100.0) < 0.1,
            'difference' => $totalWeight - 100.0,
        ];
    }

    /**
     * Calculate overall score for a participant
     */
    public function calculateOverallScore(TrainingParticipant $participant): array
    {
        $categories = $this->assessmentCategories;
        $results = $participant->assessmentResults->keyBy('assessment_category_id');

        $totalWeight = 0;
        $achievedWeight = 0;
        $allAssessed = true;
        $requiredPassed = true;

        foreach ($categories as $category) {
            $result = $results->get($category->id);
            $categoryWeight = $category->pivot->weight_percentage;

            if (! $result) {
                $allAssessed = false;
                if ($category->pivot->is_required) {
                    $requiredPassed = false;
                }

                continue;
            }

            $totalWeight += $categoryWeight;

            if ($result->result === 'pass') {
                $achievedWeight += $categoryWeight;
            } elseif ($category->pivot->is_required) {
                $requiredPassed = false;
            }
        }

        $overallScore = $totalWeight > 0 ? round(($achievedWeight / $totalWeight) * 100, 1) : 0;

        if (! $allAssessed) {
            $status = 'INCOMPLETE';
        } elseif (! $requiredPassed) {
            $status = 'FAILED';
        } elseif ($overallScore >= 70) {
            $status = 'PASSED';
        } else {
            $status = 'FAILED';
        }

        return [
            'score' => $overallScore,
            'status' => $status,
            'assessed_categories' => $results->count(),
            'total_categories' => $categories->count(),
            'all_assessed' => $allAssessed,
            'required_passed' => $requiredPassed,
            'total_weight' => $totalWeight,
            'achieved_weight' => $achievedWeight,
        ];
    }

    /**
     * Get assessment summary for all participants
     */
    public function getAssessmentSummary(): array
    {
        $participants = $this->participants()->with('assessmentResults')->get();
        $totalCategories = $this->assessmentCategories()->count();

        $passed = 0;
        $failed = 0;
        $incomplete = 0;
        $totalScore = 0;
        $assessedParticipants = 0;
        $completedAssessments = 0;

        foreach ($participants as $participant) {
            $calculation = $this->calculateOverallScore($participant);
            $completedAssessments += $calculation['assessed_categories'];

            if ($calculation['all_assessed']) {
                $assessedParticipants++;
                $totalScore += $calculation['score'];

                if ($calculation['status'] === 'PASSED') {
                    $passed++;
                } else {
                    $failed++;
                }
            } else {
                $incomplete++;
            }
        }

        $totalPossible = $participants->count() * $totalCategories;

        return [
            'total_mentees' => $participants->count(),
            'total_categories' => $totalCategories,
            'passed_mentees' => $passed,
            'failed_mentees' => $failed,
            'incomplete_mentees' => $incomplete,
            'completion_rate' => $totalPossible > 0 ? round(($completedAssessments / $totalPossible) * 100, 1) : 0,
            'pass_rate' => $assessedParticipants > 0 ? round(($passed / $assessedParticipants) * 100, 1) : 0,
            'average_score' => $assessedParticipants > 0 ? round($totalScore / $assessedParticipants, 1) : 0,
        ];
    }

    // ============================================
    // FEATURE CHECKS
    // ============================================

    /**
     * Check if training has assessments
     */
    public function hasAssessments(): bool
    {
        return $this->assess_participants === true || $this->assessmentCategories()->exists();
    }

    /**
     * Check if training has materials planning
     */
    public function hasMaterials(): bool
    {
        return $this->provide_materials === true || $this->trainingMaterials()->exists();
    }

    /**
     * Get assessment status
     */
    public function getAssessmentStatusAttribute(): string
    {
        if (! $this->hasAssessments()) {
            return 'Not Configured';
        }

        if ($this->assessmentCategories()->count() === 0) {
            return 'No Categories';
        }

        $totalWeight = $this->assessmentCategories->sum('pivot.weight_percentage');

        if (abs($totalWeight - 100) >= 0.1) {
            return 'Invalid Weights';
        }

        return 'Configured';
    }

    /**
     * Get materials status
     */
    public function getMaterialsStatusAttribute(): string
    {
        if (! $this->hasMaterials()) {
            return 'Not Planned';
        }

        if ($this->trainingMaterials()->count() === 0) {
            return 'No Materials';
        }

        return 'Materials Planned';
    }

    /**
     * Get training features summary
     */
    public function getFeaturesSummaryAttribute(): array
    {
        return [
            'has_programs' => $this->programs()->exists(),
            'has_modules' => $this->modules()->exists(),
            'has_methodologies' => $this->methodologies()->exists(),
            'has_assessments' => $this->hasAssessments(),
            'has_materials' => $this->hasMaterials(),
            'has_participants' => $this->participants()->exists(),
            'has_classes' => $this->mentorshipClasses()->exists(),
            'has_co_mentors' => $this->coMentors()->where('status', 'accepted')->exists(),
            'has_module_usages' => $this->moduleUsages()->exists(),
            'assessment_status' => $this->assessment_status,
            'materials_status' => $this->materials_status,
        ];
    }
}
