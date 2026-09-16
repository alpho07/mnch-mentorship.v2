<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenteeAssessmentResult extends Model 
{
    use HasFactory;

    protected $fillable = [
        'participant_id',
        'assessment_category_id',
        'result', // 'pass' or 'fail'
        'assessed_by',
        'assessment_date',
        'category_weight',
        'feedback',
    ];

    protected $casts = [
        'assessment_date' => 'datetime',
        'category_weight' => 'decimal:1',
    ];

    // Relationships
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TrainingParticipant::class, 'participant_id');
    }

    public function assessmentCategory(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    // Simple computed attributes
    public function getPassedAttribute(): bool
    {
        return $this->result === 'pass';
    }

    public function getDisplayResultAttribute(): string
    {
        return strtoupper($this->result ?? 'NOT ASSESSED');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->result) {
            'pass' => 'success',
            'fail' => 'danger',
            default => 'gray',
        };
    }

    // Scopes
    public function scopePassed($query)
    {
        return $query->where('result', 'pass');
    }

    public function scopeFailed($query)
    {
        return $query->where('result', 'fail');
    }

    public function scopeByTraining($query, int $trainingId)
    {
        return $query->whereHas('participant', function ($q) use ($trainingId) {
            $q->where('training_id', $trainingId);
        });
    }
}