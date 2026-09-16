<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'program_id',
    ];

    protected $with = ['program'];

    // Relationships
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    // Query Scopes
    public function scopeByProgram($query, int $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopeWithSessions($query)
    {
        return $query->has('trainingSessions');
    }

    public function scopeWithTopics($query)
    {
        return $query->has('topics');
    }

    public function scopeActive($query)
    {
        return $query->whereHas('trainingSessions.training', function ($q) {
            $q->where('end_date', '>=', now());
        });
    }

    // Computed Attributes
    public function getSessionCountAttribute(): int
    {
        return $this->trainingSessions()->count();
    }

    public function getTopicCountAttribute(): int
    {
        return $this->topics()->count();
    }

    public function getTrainingCountAttribute(): int
    {
        return $this->trainingSessions()
            ->distinct('training_id')
            ->count('training_id');
    }

    public function getTotalObjectivesAttribute(): int
    {
        return Objective::whereHas('session', function ($query) {
            $query->where('module_id', $this->id);
        })->count();
    }

    public function getSkillObjectivesCountAttribute(): int
    {
        return Objective::whereHas('session', function ($query) {
            $query->where('module_id', $this->id);
        })->where('type', 'skill')->count();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->program->name} - {$this->name}";
    }
}