<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function participantObjectiveResults(): HasMany
    {
        return $this->hasMany(ParticipantObjectiveResult::class);
    }

    public function trainingParticipantsAsOutcome(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class, 'outcome_id');
    }

    // Query Scopes
    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopePassingGrades($query)
    {
        return $query->whereIn('name', ['Pass', 'Passed', 'Competent', 'Satisfactory']);
    }

    public function scopeFailingGrades($query)
    {
        return $query->whereIn('name', ['Fail', 'Failed', 'Not Competent', 'Unsatisfactory']);
    }

    // Computed Attributes
    public function getUsageCountAttribute(): int
    {
        return $this->participantObjectiveResults()->count() + 
               $this->trainingParticipantsAsOutcome()->count();
    }

    public function getIsPassingGradeAttribute(): bool
    {
        $passingGrades = ['Pass', 'Passed', 'Competent', 'Satisfactory'];
        return in_array($this->name, $passingGrades);
    }
}