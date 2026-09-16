<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Methodology extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    // Query Scopes
    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopeWithSessions($query)
    {
        return $query->has('trainingSessions');
    }

    // Computed Attributes
    public function getSessionCountAttribute(): int
    {
        return $this->trainingSessions()->count();
    }

    public function getTrainingCountAttribute(): int
    {
        return $this->trainingSessions()
            ->distinct('training_id')
            ->count('training_id');
    }
}
