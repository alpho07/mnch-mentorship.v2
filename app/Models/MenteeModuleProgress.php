<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenteeModuleProgress extends Model
{
    protected $table = 'mentee_module_progress';

    protected $fillable = [
        'class_participant_id',
        'class_module_id',
        'status',
        'started_at',
        'completed_at',
        'exempted_at',
        'completed_in_previous_class',
        'attendance_percentage',
        'assessment_score',
        'assessment_status',
        'notes',
        'pre_test_attempt_id',
        'post_test_attempt_id',
        'hands_on_video_url',
        'hands_on_video_path',
        'video_review_status',
        'video_reviewed_at',
        'video_reviewed_by',
        'video_review_notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'exempted_at' => 'datetime',
        'completed_in_previous_class' => 'boolean',
        'attendance_percentage' => 'float',
        'assessment_score' => 'float',
    ];

    // Relationships
    public function classParticipant(): BelongsTo
    {
        return $this->belongsTo(ClassParticipant::class, 'class_participant_id');
    }

    public function classModule(): BelongsTo
    {
        return $this->belongsTo(ClassModule::class, 'class_module_id');
    }

    public function preTestAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'pre_test_attempt_id');
    }

    public function postTestAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'post_test_attempt_id');
    }

    public function videoReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'video_reviewed_by');
    }

    // Assessment results for this progress (through module_assessment_results table)
    public function assessmentResults(): HasMany
    {
        return $this->hasMany(ModuleAssessmentResult::class, 'mentee_progress_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ModuleAssessment::class, 'mentee_progress_id');
    }

    // Computed Attributes
    public function getIsExemptedAttribute(): bool
    {
        return $this->status === 'exempted' || $this->completed_in_previous_class;
    }

    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->status, ['completed', 'exempted']);
    }

    public function getRequiresAssessmentAttribute(): bool
    {
        return $this->status === 'in_progress' &&
                $this->classModule->requires_assessment;
    }

    public function getHasPassedAssessmentAttribute(): bool
    {
        return $this->assessment_status === 'passed';
    }

    // Status Methods
    public function markStarted(): bool
    {
        if ($this->is_exempted) {
            return false;
        }

        return $this->update([
            'status' => 'in_progress',
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markCompleted(
        ?float $attendancePercentage = null,
        ?float $assessmentScore = null,
        ?string $assessmentStatus = null
    ): bool {
        if ($this->is_exempted) {
            return false;
        }

        $attributes = ['status' => 'completed', 'completed_at' => now()];

        // Only write these when a value is actually available (explicit
        // param or already-loaded attribute) — a row created and completed
        // within the same request never picked up the DB's own default for
        // assessment_status, and blindly writing null over a NOT NULL
        // column fails the update outright.
        if (($attendancePercentage ?? $this->attendance_percentage) !== null) {
            $attributes['attendance_percentage'] = $attendancePercentage ?? $this->attendance_percentage;
        }
        if (($assessmentScore ?? $this->assessment_score) !== null) {
            $attributes['assessment_score'] = $assessmentScore ?? $this->assessment_score;
        }
        if (($assessmentStatus ?? $this->assessment_status) !== null) {
            $attributes['assessment_status'] = $assessmentStatus ?? $this->assessment_status;
        }

        return $this->update($attributes);
    }

    public function recordAssessment(float $score, string $status): bool
    {
        return $this->update([
            'assessment_score' => $score,
            'assessment_status' => $status,
        ]);
    }

    public function recordVideoReview(string $status, ?string $notes = null, ?int $reviewerId = null): bool
    {
        return $this->update([
            'video_review_status' => $status,
            'video_review_notes' => $notes,
            'video_reviewed_at' => now(),
            'video_reviewed_by' => $reviewerId,
        ]);
    }

    public function hasSubmittedVideo(): bool
    {
        return filled($this->hands_on_video_url) || filled($this->hands_on_video_path);
    }

    public function isVideoPassed(): bool
    {
        return $this->video_review_status === 'passed';
    }

    public function isVideoFailed(): bool
    {
        return $this->video_review_status === 'failed';
    }

    public function youtubeEmbedUrl(): ?string
    {
        $url = $this->hands_on_video_url;

        if (empty($url)) {
            return null;
        }

        $patterns = [
            '/(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return "https://www.youtube.com/embed/{$matches[1]}";
            }
        }

        return null;
    }

    public function isDirectVideoUrl(): bool
    {
        $url = $this->hands_on_video_url ?? '';

        return (bool) preg_match('/\.(mp4|mov|avi|mkv|webm|m4v|3gp|ogg)(\?.*)?$/i', $url);
    }

    /**
     * Whether the mentee has finished their own self-service steps for an
     * EmONC module — pre-test (if the module has one), hands-on video
     * passed, and post-test (if the module has one) — independent of the
     * mentor's separate Activity Completion Matrix. Scoped to EmONC only,
     * matching every other quiz/video gate in the mentee module flow.
     *
     * Deliberately does NOT look at `status` or `is_exempted` — those are
     * shared with the Activity Completion Matrix's own, unrelated
     * completion cascade (marking `status = 'completed'` from live-session
     * activities alone, independent of whether the mentee has even started
     * their pre-test). Callers that need "is everything, including
     * activities, genuinely done" should combine this with
     * areAllActivitiesCompleted() — see readyForMenteeAutoCompletion().
     */
    private function menteeStepsAreDone(): bool
    {
        $classModule = $this->classModule;
        $program = $classModule?->mentorshipClass?->training?->program;
        $isEmonc = $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');

        if (! $isEmonc || ! $classModule) {
            return false;
        }

        if (! $this->isVideoPassed()) {
            return false;
        }

        $quizzes = $classModule->programModule?->quizzes ?? collect();

        if ($quizzes->contains(fn ($q) => $q->isPreTest()) && ! $this->pre_test_attempt_id) {
            return false;
        }

        if ($quizzes->contains(fn ($q) => $q->isPostTest()) && ! $this->post_test_attempt_id) {
            return false;
        }

        return true;
    }

    /**
     * Whether it's safe to flip `status` to 'completed' from the mentee's
     * own steps finishing — requires the mentor's activities to ALSO
     * already be done, so this can never race ahead of (or contradict) the
     * Activity Completion Matrix's own completion signal, which several
     * other things key off of (class-module auto-completion, certification).
     */
    public function readyForMenteeAutoCompletion(): bool
    {
        if ($this->is_exempted || $this->status === 'completed') {
            return false;
        }

        return $this->menteeStepsAreDone() && $this->areAllActivitiesCompleted();
    }

    /**
     * Auto-completes and locks the module for the mentee the moment their
     * own steps are all done, so they can't keep retaking a finished
     * post-test or resubmitting an already-passed video.
     */
    public function maybeAutoComplete(): bool
    {
        if (! $this->readyForMenteeAutoCompletion()) {
            return false;
        }

        return $this->markCompleted();
    }

    /**
     * Whether this module is locked for the mentee — no more retaking the
     * post-test, resubmitting the video, or restarting the pre-test.
     */
    /**
     * Whether this module is locked for the mentee — no more retaking the
     * post-test, resubmitting the video, restarting the pre-test, or (on
     * the mentor side) re-reviewing the video / re-editing the Activity
     * Completion Matrix for them.
     *
     * Deliberately keyed off the mentee's OWN step completion rather than
     * `status` alone — `status` can already be 'completed' purely via the
     * Activity Completion Matrix (live-session activities), independent of
     * whether the mentee has touched their pre-test/video/post-test at
     * all, and locking them out in that state would strand them.
     */
    public function isLockedForMentee(): bool
    {
        if ($this->status === 'exempted') {
            return true;
        }

        return $this->menteeStepsAreDone();
    }

    /**
     * Whether all enrolled activities for this module/track are marked done for this mentee.
     */
    public function areAllActivitiesCompleted(): bool
    {
        $classModule = $this->classModule;

        if (! $classModule) {
            return true;
        }

        $activityIds = $classModule->programModule?->activities?->pluck('id') ?? collect();

        if ($activityIds->isEmpty()) {
            return true;
        }

        $completedCount = ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
            ->where('class_participant_id', $this->class_participant_id)
            ->whereIn('activity_id', $activityIds)
            ->where('status', 'completed')
            ->count();

        return $completedCount === $activityIds->count();
    }

    // Query Scopes
    public function scopeExempted($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'exempted')
                ->orWhere('completed_in_previous_class', true);
        });
    }

    public function scopeNotExempted($query)
    {
        return $query->where('status', '!=', 'exempted')
            ->where('completed_in_previous_class', false);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'exempted']);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'not_started')
            ->where('completed_in_previous_class', false);
    }
}
