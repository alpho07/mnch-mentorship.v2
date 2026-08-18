<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentorship_class_id',
        'user_id',
        'status',
        'enrolled_at',
        'invitation_sent_at',
        'completed_at',
        'dropped_at',
        'drop_reason',
        'mentor_approved_at',
        'mentor_approved_by',
        'head_drmh_approved_at',
        'head_drmh_approved_by',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'dropped_at' => 'datetime',
        'invitation_sent_at' => 'datetime',
    ];

    // Relationships
    public function mentorshipClass(): BelongsTo
    {
        return $this->belongsTo(MentorshipClass::class, 'mentorship_class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityEnrollments(): HasMany
    {
        return $this->hasMany(ClassModuleActivityParticipant::class, 'class_participant_id');
    }

    public function sessionAttendance(): HasMany
    {
        return $this->hasMany(SessionAttendance::class, 'class_participant_id');
    }

    public function moduleProgress(): HasMany
    {
        return $this->hasMany(MenteeModuleProgress::class, 'class_participant_id');
    }

    public function mentorApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_approved_by');
    }

    public function headDrmhApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_drmh_approved_by');
    }

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(ModuleAssessmentResult::class, 'class_participant_id');
    }

    // Computed Attributes
    public function getAttendanceRateAttribute(): float
    {
        $class = $this->relationLoaded('mentorshipClass') ? $this->mentorshipClass : $this->mentorshipClass()->first();

        if (! $class) {
            return 0;
        }

        $totalSessions = $class->classModules()
            ->withCount('sessions')
            ->get()
            ->sum('sessions_count');

        if ($totalSessions === 0) {
            return 0;
        }

        $attendedSessions = $this->sessionAttendance()
            ->where('status', 'present')
            ->count();

        return round(($attendedSessions / $totalSessions) * 100, 1);
    }

    public function getSessionsAttendedAttribute(): int
    {
        return $this->sessionAttendance()->where('status', 'present')->count();
    }

    public function getTotalSessionsAttribute(): int
    {
        $class = $this->relationLoaded('mentorshipClass') ? $this->mentorshipClass : $this->mentorshipClass()->first();

        if (! $class) {
            return 0;
        }

        return $class->classModules()
            ->withCount('sessions')
            ->get()
            ->sum('sessions_count');
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['enrolled', 'active']);
    }

    // Query Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['enrolled', 'active']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeDropped($query)
    {
        return $query->where('status', 'dropped');
    }

    public function scopeByClass($query, int $classId)
    {
        return $query->where('mentorship_class_id', $classId);
    }

    // Helper Methods
    public function markActive(): bool
    {
        return $this->update(['status' => 'active']);
    }

    public function markCompleted(): bool
    {
        return $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Sets status to 'completed' the moment a mentee genuinely finishes
     * every module (all progress completed/exempted, every video review
     * passed) — see docs/PHASE1-DISCOVERY-BASELINE.md for why this needed
     * adding: markCompleted() previously had zero callers anywhere, so
     * mentor_approve/head_drmh_certify's status==='completed' visibility
     * gate on ManageClassMentees.php could never become true.
     */
    public function syncCompletionStatus(): bool
    {
        if ($this->status === 'completed') {
            return false;
        }

        if (! $this->hasCompletedAllModules()) {
            return false;
        }

        return $this->markCompleted();
    }

    public function drop(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'dropped',
            'dropped_at' => now(),
            'drop_reason' => $reason,
        ]);
    }

    /**
     * @throws \DomainException if the mentee hasn't completed every module in the class,
     *                          with a passed video review on each — enforced here so approval can't be granted
     *                          via any code path that skips the UI-level pre-check.
     */
    public function markMentorApproved(int $mentorUserId): bool
    {
        if (! $this->hasCompletedAllModules()) {
            throw new \DomainException('Cannot mentor-approve: not all modules are complete, or a hands-on video review is still pending/failed.');
        }

        return $this->update([
            'mentor_approved_at' => now(),
            'mentor_approved_by' => $mentorUserId,
        ]);
    }

    /**
     * Every module in the class has mentee progress that is completed/exempted.
     * Modules with an active rubric additionally require a passed video review;
     * modules with no rubric (non-EmONC programs never have one) are judged on
     * progress status alone. The gate for mentor approval and, transitively,
     * certification.
     */
    public function hasCompletedAllModules(): bool
    {
        $class = $this->relationLoaded('mentorshipClass') ? $this->mentorshipClass : $this->mentorshipClass()->first();

        if (! $class) {
            return false;
        }

        $classModules = $class->classModules()->get();

        if ($classModules->isEmpty()) {
            return false;
        }

        $progressRecords = $this->moduleProgress()
            ->whereIn('class_module_id', $classModules->pluck('id'))
            ->get()
            ->keyBy('class_module_id');

        if ($progressRecords->count() !== $classModules->count()) {
            return false;
        }

        foreach ($classModules as $classModule) {
            $progress = $progressRecords->get($classModule->id);

            if (! in_array($progress->status, ['completed', 'exempted'])) {
                return false;
            }

            $hasRubric = ModuleRubric::where('program_module_id', $classModule->program_module_id)
                ->where('is_active', true)
                ->exists();

            if ($hasRubric && ! $progress->isVideoPassed()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Program-wide completion gate for Head DRMH certification. Unlike
     * hasCompletedAllModules() (scoped to this one class), this checks every
     * module belonging to the mentee's program — including EmONC's tracks —
     * aggregated across ALL of the mentee's enrollments in that program,
     * regardless of which class, facility, or mentor administered them.
     * A program module counts as done if any one of the mentee's progress
     * records for it is completed/exempted (and video-passed, where an
     * active rubric applies).
     */
    public function hasCompletedAllProgramModules(): bool
    {
        $training = $this->relationLoaded('mentorshipClass')
            ? $this->mentorshipClass?->training
            : $this->mentorshipClass()->first()?->training;

        $program = $training ? Program::find($training->program_id) : null;

        if (! $program) {
            return false;
        }

        $programModuleIds = $program->completableModules()->pluck('id');

        if ($programModuleIds->isEmpty()) {
            return false;
        }

        $participantIds = self::where('user_id', $this->user_id)
            ->whereHas('mentorshipClass.training', fn ($q) => $q->where('program_id', $program->id))
            ->pluck('id');

        $progressByModule = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
            ->with('classModule')
            ->get()
            ->groupBy(fn (MenteeModuleProgress $p) => $p->classModule?->program_module_id);

        foreach ($programModuleIds as $programModuleId) {
            $hasRubric = ModuleRubric::where('program_module_id', $programModuleId)
                ->where('is_active', true)
                ->exists();

            $satisfied = $progressByModule->get($programModuleId, collect())->contains(
                fn (MenteeModuleProgress $p) => in_array($p->status, ['completed', 'exempted'])
                    && (! $hasRubric || $p->isVideoPassed())
            );

            if (! $satisfied) {
                return false;
            }
        }

        return true;
    }

    public function markHeadDrmhApproved(int $headDrmhUserId): bool
    {
        return $this->update([
            'head_drmh_approved_at' => now(),
            'head_drmh_approved_by' => $headDrmhUserId,
        ]);
    }

    public function isMentorApproved(): bool
    {
        return $this->mentor_approved_at !== null;
    }

    public function isHeadDrmhApproved(): bool
    {
        return $this->head_drmh_approved_at !== null;
    }

    /**
     * Head DRMH approval is the final gate — by the time it's granted,
     * isReadyForHeadDrmhCertification() already enforced mentor approval for
     * EmONC (and required nothing extra for non-EmONC, which never goes
     * through mentor_approve). Checking isMentorApproved() again here used
     * to make this permanently false for non-EmONC participants, breaking
     * their certificate download even after being certified.
     */
    public function isCertified(): bool
    {
        return $this->isHeadDrmhApproved();
    }

    /**
     * The shared readiness gate for Head DRMH certification, used by both
     * the class roster page's Certify button and the Head DRMH Dashboard.
     * Only relevant to programs configured with certificate_scope =
     * 'per_program' (see Program::usesPerProgramCertification()) — a mentee
     * must have completed every module belonging to their program, aggregated
     * across ALL of their enrollments in that program regardless of class,
     * facility, or mentor — see hasCompletedAllProgramModules(). Programs
     * using per-program certification additionally require mentor approval
     * first; mentees in 'per_class' programs (which never go through
     * mentor_approve — see ManageClassMentees.php) are ready the moment
     * every program module is done.
     */
    public function isReadyForHeadDrmhCertification(): bool
    {
        if (! $this->hasCompletedAllProgramModules()) {
            return false;
        }

        $training = $this->relationLoaded('mentorshipClass')
            ? $this->mentorshipClass?->training
            : $this->mentorshipClass()->first()?->training;

        $program = $training ? Program::find($training->program_id) : null;

        if ($program?->usesPerProgramCertification()) {
            return $this->isMentorApproved();
        }

        return true;
    }

    public function hasAttendedSession(int $sessionId): bool
    {
        return $this->sessionAttendance()
            ->where('session_id', $sessionId)
            ->where('status', 'present')
            ->exists();
    }
}
