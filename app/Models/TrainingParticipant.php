<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainingParticipant extends Model {

    use HasFactory;

    protected $fillable = [
        'training_id',
        'user_id',
        'registration_date',
        'attendance_status',
        'completion_status',
        'outcome_id',
        'completion_date',
        'certificate_issued',
        'notes'
    ];
    protected $casts = [
        'registration_date' => 'datetime',
        'completion_date' => 'datetime',
        'certificate_issued' => 'boolean',
        'score' => 'decimal'
    ];

    // Relationships
    public function training(): BelongsTo {
        return $this->belongsTo(Training::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function outcome(): BelongsTo {
        return $this->belongsTo(Grade::class, 'outcome_id');
    }

    // Scopes
    public function scopeCompleted($query) {
        return $query->where('completion_status', 'completed');
    }

    public function scopeActive($query) {
        return $query->whereIn('attendance_status', ['registered', 'attending']);
    }

    public function scopeInProgress($query) {
        return $query->where('completion_status', 'in_progress');
    }

    public function objectiveResults(): HasMany {
        return $this->hasMany(ParticipantObjectiveResult::class, 'participant_id');
    }

    // Computed Attributes
    public function getIsCompletedAttribute(): bool {
        return $this->completion_status === 'completed';
    }

    public function getFullNameAttribute(): string {
        return $this->user?->full_name ?? 'Unknown User';
    }

    public function getFacilityNameAttribute(): string {
        return $this->user?->facility?->name ?? 'Unknown Facility';
    }

    public function getOverallScoreAttribute(): ?float {
        // Load only the score column to keep memory light
        $results = $this->objectiveResults()->select('score')->get();
        if ($results->isEmpty()) {
            return null;
        }

        // Use raw value to bypass Eloquent decimal cast; coerce only numerics
        $avg = $results->avg(function ($row) {
            $raw = $row->getRawOriginal('score');
            if ($raw === '' || $raw === null) {
                return null;
            }
            return is_numeric($raw) ? (float) $raw : null;
        });

        return $avg !== null ? round($avg, 2) : null;
    }

    public function getOverallGradeAttribute(): string {
        $averageScore = $this->getOverallScoreAttribute();

        if ($averageScore === null) {
            return 'Not Assessed';
        }

        if ($averageScore >= 90)
            return 'Excellent';
        if ($averageScore >= 80)
            return 'Very Good';
        if ($averageScore >= 70)
            return 'Good';
        if ($averageScore >= 60)
            return 'Fair';
        return 'Needs Improvement';
    }

    public function assessmentResults(): HasMany {
        return $this->hasMany(MenteeAssessmentResult::class, 'participant_id');
    }

    // ========================================
    // ASSESSMENT COMPUTED ATTRIBUTES
    // ========================================
    // Get overall assessment status for this participant
    public function getOverallAssessmentStatusAttribute(): string {
        $calculation = $this->training->calculateOverallScore($this);
        return $calculation['status'];
    }

    // Get overall assessment score for this participant
    public function getOverallAssessmentScoreAttribute(): float {
        $calculation = $this->training->calculateOverallScore($this);
        return $calculation['score'];
    }

    // Get assessment progress (how many categories assessed)
    public function getAssessmentProgressAttribute(): array {
        $totalCategories = $this->training->assessmentCategories()->count();
        $assessedCategories = $this->assessmentResults()->count();

        return [
            'assessed' => $assessedCategories,
            'total' => $totalCategories,
            'percentage' => $totalCategories > 0 ? round(($assessedCategories / $totalCategories) * 100, 1) : 0,
        ];
    }

    // Check if participant has been assessed for a specific category
    public function hasAssessmentFor(int $categoryId): bool {
        return $this->assessmentResults()
                        ->where('assessment_category_id', $categoryId)
                        ->exists();
    }

    // Get assessment result for a specific category
    public function getAssessmentResult(int $categoryId): ?MenteeAssessmentResult {
        return $this->assessmentResults()
                        ->where('assessment_category_id', $categoryId)
                        ->first();
    }

    // Get the result (pass/fail) for a specific category
    public function getCategoryResult(int $categoryId): ?string {
        return $this->getAssessmentResult($categoryId)?->result;
    }

    // Check if participant passed a specific category
    public function passedCategory(int $categoryId): bool {
        return $this->getCategoryResult($categoryId) === 'pass';
    }

    // ========================================
    // ASSESSMENT METHODS
    // ========================================
    // Update assessment result for a category
    public function updateAssessmentResult(
            int $categoryId,
            string $result,
            ?float $score = null,
            ?string $feedback = null,
            ?string $mentorNotes = null,
            ?int $assessedBy = null
    ): MenteeAssessmentResult {
        // Get category weight from training
        $categoryWeight = $this->training->getCategoryWeight($categoryId) ?? 25.00;

        // Convert pass/fail to score if not provided
        if ($score === null) {
            $score = $result === 'pass' ? 100.00 : 0.00;
        }

        return $this->assessmentResults()->updateOrCreate(
                        ['assessment_category_id' => $categoryId],
                        [
                            'result' => $result,
                            'score' => $score,
                            'category_weight' => $categoryWeight,
                            'feedback' => $feedback,
                            'mentor_notes' => $mentorNotes,
                            'assessed_by' => $assessedBy ?? auth()->id(),
                            'assessment_date' => now(),
                            'attempts' => 1,
                        ]
                );
    }

    // Bulk update assessment results
    public function updateMultipleAssessments(array $assessments): array {
        $results = [];

        foreach ($assessments as $categoryId => $data) {
            $results[$categoryId] = $this->updateAssessmentResult(
                    $categoryId,
                    $data['result'],
                    $data['score'] ?? null,
                    $data['feedback'] ?? null,
                    $data['mentor_notes'] ?? null,
                    $data['assessed_by'] ?? null
            );
        }

        // Update participant completion status if all assessments done
        $this->updateCompletionStatus();

        return $results;
    }

    // Update participant completion status based on assessments
    public function updateCompletionStatus(): void {
        $calculation = $this->training->calculateOverallScore($this);

        if ($calculation['all_assessed']) {
            $this->update([
                'completion_status' => 'completed',
                'completion_date' => now(),
                'outcome_id' => $calculation['status'] === 'PASSED' ? 1 : 2, // Adjust based on your grades table
            ]);
        }
    }

    public function statusLogs(): HasMany {
        return $this->hasMany(ParticipantStatusLog::class, 'training_participant_id');
    }

    public function mentorshipStatusLogs(): HasMany {
        return $this->hasMany(ParticipantStatusLog::class, 'mentorship_participant_id');
    }
}
