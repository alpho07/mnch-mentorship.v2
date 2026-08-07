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
     * Every module in the class has mentee progress that is completed/exempted, with a
     * passed video review — the gate for mentor approval and, transitively, certification.
     */
    public function hasCompletedAllModules(): bool
    {
        $class = $this->relationLoaded('mentorshipClass') ? $this->mentorshipClass : $this->mentorshipClass()->first();

        if (! $class) {
            return false;
        }

        $moduleIds = $class->classModules()->pluck('id');

        if ($moduleIds->isEmpty()) {
            return false;
        }

        $progressRecords = $this->moduleProgress()->whereIn('class_module_id', $moduleIds)->get();

        if ($progressRecords->count() !== $moduleIds->count()) {
            return false;
        }

        foreach ($progressRecords as $progress) {
            if (! in_array($progress->status, ['completed', 'exempted'])) {
                return false;
            }

            if (! $progress->isVideoPassed()) {
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

    public function isCertified(): bool
    {
        return $this->isMentorApproved() && $this->isHeadDrmhApproved();
    }

    public function hasAttendedSession(int $sessionId): bool
    {
        return $this->sessionAttendance()
            ->where('session_id', $sessionId)
            ->where('status', 'present')
            ->exists();
    }
}
