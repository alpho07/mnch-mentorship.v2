<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cadre extends Model
{
    use HasFactory;
    protected $table='assessment_cadres';

    protected $fillable = ['name', 'code', 'category', 'description', 'order', 'is_active'];

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

    /**
     * Cadres seeded for a specific assessment template's own bucket set
     * (e.g. 'emonc') — kept separate from the unscoped general-purpose
     * cadre list used elsewhere (Users, Training Participants), which has
     * category = null and is unaffected by this scope.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
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
