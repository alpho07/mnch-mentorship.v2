<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Objective extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'training_session_id', // Keep for session-specific objectives
        'objective_text',
        'type',
        'objective_order',
        'pass_criteria',
        'assessment_method',
    ];

    protected $casts = [
        'objective_order' => 'integer',
        'pass_criteria' => 'decimal:2',
    ];

    // Relationships
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ParticipantObjectiveResult::class);
    }

    // Query Scopes
    public function scopeByTraining($query, int $trainingId)
    {
        return $query->where('training_id', $trainingId);
    }

    public function scopeBySession($query, int $sessionId)
    {
        return $query->where('training_session_id', $sessionId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSkillBased($query)
    {
        return $query->where('type', 'skill');
    }

    public function scopeKnowledgeBased($query)
    {
        return $query->where('type', 'knowledge');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('objective_order');
    }

    public function scopeWithResults($query)
    {
        return $query->has('results');
    }

    // Computed Attributes
    public function getResultsCountAttribute(): int
    {
        return $this->results()->count();
    }

    public function getAverageScoreAttribute(): ?float
    {
        return $this->results()->avg('score');
    }

    public function getPassRateAttribute(): float
    {
        $totalResults = $this->results()->count();
        if ($totalResults === 0) return 0;

        $passedResults = $this->results()
            ->where('score', '>=', $this->pass_criteria ?? 70)
            ->count();

        return round(($passedResults / $totalResults) * 100, 2);
    }

    public function getCompletionRateAttribute(): float
    {
        // This would need the total number of participants for the training
        $training = $this->training;
        if (!$training) return 0;

        $totalParticipants = $training->participants()->count();
        if ($totalParticipants === 0) return 0;

        $assessedParticipants = $this->results()->distinct('participant_id')->count();
        return round(($assessedParticipants / $totalParticipants) * 100, 2);
    }

    public function getIsSkillBasedAttribute(): bool
    {
        return $this->type === 'skill';
    }

    public function getIsKnowledgeBasedAttribute(): bool
    {
        return $this->type === 'knowledge';
    }

    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'knowledge' => 'blue',
            'skill' => 'green',
            'attitude' => 'purple',
            'competency' => 'indigo',
            default => 'gray',
        };
    }
}
