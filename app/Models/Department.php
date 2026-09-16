<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(
            Training::class, 
            'training_departments', 
            'department_id', 
            'training_id'
        );
    }

    public function trainingParticipants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Query Scopes
    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopeWithUsers($query)
    {
        return $query->has('users');
    }

    public function scopeWithTrainings($query)
    {
        return $query->has('trainings');
    }

    // Computed Attributes
    public function getUserCountAttribute(): int
    {
        return $this->users()->count();
    }

    public function getTrainingCountAttribute(): int
    {
        return $this->trainings()->count();
    }

    public function getParticipantCountAttribute(): int
    {
        return $this->trainingParticipants()->count();
    }
}