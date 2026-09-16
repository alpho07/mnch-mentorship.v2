<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantObjectiveResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_id',
        'objective_id',
        'score',
        'grade_id',
        'assessed_by',
        'assessment_date',
        'feedback',
    ];

    protected $casts = [
        'assessment_date' => 'datetime',
        'score' => 'decimal:2',
    ];

    // Relationships
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TrainingParticipant::class, 'participant_id');
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class, 'objective_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    // Computed Attributes
    public function getPassedAttribute(): bool
    {
        if (!$this->score || !$this->objective) {
            return false;
        }

        $passCriteria = $this->objective->pass_criteria ?? 70;
        return $this->score >= $passCriteria;
    }

    public function getGradeNameAttribute(): ?string
    {
        return $this->grade?->name;
    }

    public function getFormattedScoreAttribute(): string
    {
        return $this->score ? number_format($this->score, 1) . '%' : 'Not scored';
    }

    public function getFormattedAssessmentDateAttribute(): ?string
    {
        return $this->assessment_date?->format('M j, Y');
    }

    // Scopes
    public function scopeByParticipant($query, int $participantId)
    {
        return $query->where('participant_id', $participantId);
    }

    public function scopeByObjective($query, int $objectiveId)
    {
        return $query->where('objective_id', $objectiveId);
    }

    public function scopePassed($query)
    {
        return $query->whereHas('objective', function ($q) {
            $q->whereRaw('participant_objective_results.score >= COALESCE(objectives.pass_criteria, 70)');
        });
    }

    public function scopeFailed($query)
    {
        return $query->whereHas('objective', function ($q) {
            $q->whereRaw('participant_objective_results.score < COALESCE(objectives.pass_criteria, 70)');
        });
    }

    public function scopeAssessedBy($query, int $userId)
    {
        return $query->where('assessed_by', $userId);
    }

    public function scopeAssessedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('assessment_date', [$startDate, $endDate]);
    }
}
