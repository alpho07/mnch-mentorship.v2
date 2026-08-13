<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'facility_id',
        'assessment_type_id',
        'assessment_type',
        'assessment_date',
        'assessor_id',
        'assessor_name',
        'assessor_contact',
        'status',
        'overall_score',
        'overall_percentage',
        'overall_grade',
        'section_progress',
        'completed_at',
        'completed_by',
        'created_by',
        'updated_by',
        'feedback_given',
        'feedback_given_by',
        'feedback_given_at',
        'feedback_notes',
        'trained_before_mentorship',
        'trained_marked_by',
        'trained_marked_at',
        'excluded_cadre_ids',
        'tots_count',
        'is_locked',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'section_progress' => 'array',
        'completed_at' => 'datetime',
        'overall_score' => 'decimal:2',
        'overall_percentage' => 'decimal:2',
        'feedback_given' => 'boolean',
        'feedback_given_at' => 'datetime',
        'trained_before_mentorship' => 'boolean',
        'trained_marked_at' => 'datetime',
        'excluded_cadre_ids' => 'array',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    protected $with = ['facility.subcounty.county'];

    protected static function boot()
    {
        parent::boot();

        // Auto-populate from logged-in user
        static::creating(function ($assessment) {
            if (auth()->check()) {
                $user = auth()->user();
                $assessment->assessor_id = $user->id;
                $assessment->assessor_name = $user->name;
                $assessment->assessor_contact = $user->email ?? $user->phone;
                $assessment->created_by = $user->id;
            }

            if (empty($assessment->assessment_type)) {
                $assessment->assessment_type = 'baseline';
            }

            if (empty($assessment->assessment_date)) {
                $assessment->assessment_date = now();
            }
        });

        static::created(function (self $assessment) {
            if ($assessment->assessor_id && ! $assessment->teamMembers()->whereKey($assessment->assessor_id)->exists()) {
                $assessment->teamMembers()->attach($assessment->assessor_id, [
                    'role' => 'team_lead',
                    'added_by' => $assessment->assessor_id,
                    'added_at' => now(),
                ]);
            }
        });

        static::updating(function ($assessment) {
            if (auth()->check()) {
                $assessment->updated_by = auth()->id();
            }
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function feedbackGivenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feedback_given_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assessment_team')
            ->using(AssessmentTeamMember::class)
            ->withPivot(['role', 'added_by', 'added_at'])
            ->withTimestamps();
    }

    public function teamLeads(): BelongsToMany
    {
        return $this->teamMembers()->wherePivot('role', 'team_lead');
    }

    public function teamMembersOnly(): BelongsToMany
    {
        return $this->teamMembers()->wherePivot('role', 'member');
    }

    // Dynamic Question Responses (Infrastructure, Skills Lab, Info Systems, Quality)
    public function questionResponses(): HasMany
    {
        return $this->hasMany(AssessmentQuestionResponse::class);
    }

    // Section Scores
    public function sectionScores(): HasMany
    {
        return $this->hasMany(AssessmentSectionScore::class);
    }

    // Human Resources
    public function humanResourceResponses(): HasMany
    {
        return $this->hasMany(HumanResourceResponse::class);
    }

    // Health Products
    public function commodityResponses(): HasMany
    {
        return $this->hasMany(AssessmentCommodityResponse::class);
    }

    public function departmentScores(): HasMany
    {
        return $this->hasMany(AssessmentDepartmentScore::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeByFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByAssessor($query, $userId)
    {
        return $query->where('assessor_id', $userId);
    }

    public function scopeBaseline($query)
    {
        return $query->where('assessment_type', 'baseline');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Check if section is complete
     */
    public function isSectionComplete(string $sectionCode): bool
    {
        $progress = $this->section_progress ?? [];

        return isset($progress[$sectionCode]) && $progress[$sectionCode] === true;
    }

    /**
     * Mark section as complete
     */
    public function markSectionComplete(string $sectionCode): void
    {
        $progress = $this->section_progress ?? [];
        $progress[$sectionCode] = true;
        $this->section_progress = $progress;
        $this->save();

        // Auto-update status to in_progress
        if ($this->status === 'draft') {
            $this->update(['status' => 'in_progress']);
        }
    }

    /**
     * Get overall completion percentage
     */
    public function getCompletionPercentageAttribute(): float
    {
        $sections = $this->assessmentType
            ? $this->assessmentType->sections()->active()->pluck('code')->toArray()
            : AssessmentSection::active()->pluck('code')->toArray();
        $progress = $this->section_progress ?? [];
        $completed = count(array_filter($sections, fn ($s) => isset($progress[$s]) && $progress[$s]));

        return round(($completed / max(count($sections), 1)) * 100, 2);
    }

    /**
     * Check if assessment is fully complete
     */
    public function isFullyComplete(): bool
    {
        return $this->completion_percentage === 100.0;
    }

    /**
     * Complete the assessment
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ]);
    }

    /**
     * Get grade color
     */
    public function getGradeColorAttribute(): string
    {
        return match ($this->overall_grade) {
            'green' => 'success',
            'yellow' => 'warning',
            'red' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get grade label
     */
    public function getGradeLabelAttribute(): string
    {
        return match ($this->overall_grade) {
            'green' => 'Good (80-100%)',
            'yellow' => 'Fair (50-80%)',
            'red' => 'Poor (<50%)',
            default => 'Not Graded',
        };
    }

    public function isTeamMember(int $userId): bool
    {
        return $this->teamMembers()->whereKey($userId)->exists();
    }

    public function isTeamLead(int $userId): bool
    {
        return $this->teamLeads()->whereKey($userId)->exists();
    }

    public function canManageTeam(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        $user = User::find($userId);

        if ($user && $user->hasRole(['super_admin', 'admin', 'division'])) {
            return true;
        }

        if ($this->isTeamLead($userId)) {
            return true;
        }

        // Assessments created before team management was introduced have no
        // pivot row yet. Their original assessor may initialise the team once.
        return $this->teamLeads()->doesntExist()
            && ($this->assessor_id === $userId || $this->created_by === $userId);
    }

    public function canToggleLock(?int $userId): bool
    {
        return $this->canManageTeam($userId);
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    public function lock(int $userId): void
    {
        $this->update(['is_locked' => true, 'locked_at' => now(), 'locked_by' => $userId]);
    }

    public function unlock(): void
    {
        $this->update(['is_locked' => false, 'locked_at' => null, 'locked_by' => null]);
    }
}
