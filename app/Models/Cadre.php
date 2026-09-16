<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cadre extends Model
{
    use HasFactory;
    protected $table='assessment_cadres';

    protected $fillable = ['name', 'code', 'description', 'order', 'is_active'];

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

    // Computed Attributes
    public function getUserCountAttribute(): int
    {
        return $this->users()->count();
    }

    public function getTrainingParticipationCountAttribute(): int
    {
        return $this->trainingParticipants()->count();
    }
}
