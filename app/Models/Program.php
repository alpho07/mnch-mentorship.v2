<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ProgramModule;

class Program extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'visible_to_roles',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'visible_to_roles' => 'array',
    ];

    // Relationships
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function programModules(): HasMany
    {
        return $this->hasMany(ProgramModule::class);
    }

    /**
     * Top-level curriculum modules only — excludes track rows (e.g. PPH's 11
     * tracks), which are sub-procedures under their parent module, not
     * separate modules in their own right.
     */
    public function topLevelModules(): HasMany
    {
        return $this->programModules()->whereNull('parent_id');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    // Query Scopes
    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopeWithModules($query)
    {
        return $query->has('modules');
    }

    public function scopeWithTrainings($query)
    {
        return $query->has('trainings');
    }

    public function scopeActive($query)
    {
        return $query->whereHas('trainings', function ($q) {
            $q->where('end_date', '>=', now());
        });
    }

    /**
     * Filter to programs visible to the given user.
     * - super_admin sees all programs regardless of is_active.
     * - Active programs are visible to everyone.
     * - Inactive programs are only visible to roles listed in visible_to_roles.
     */
    public function scopeAvailableTo($query, ?User $user = null)
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return $query->where('is_active', true);
        }

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        $roles = $user->getRoleNames()->toArray();

        return $query->where(function ($q) use ($roles) {
            $q->where('is_active', true);
            foreach ($roles as $role) {
                $q->orWhereJsonContains('visible_to_roles', $role);
            }
        });
    }

    // Computed Attributes
    public function getTrainingCountAttribute(): int
    {
        return $this->trainings()->count();
    }

    public function getActiveTrainingCountAttribute(): int
    {
        return $this->trainings()
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->count();
    }

    public function getTotalParticipantsAttribute(): int
    {
        return TrainingParticipant::whereHas('training', function ($query) {
            $query->where('program_id', $this->id);
        })->count();
    }

    public function getCompletedTrainingCountAttribute(): int
    {
        return $this->trainings()
            ->where('end_date', '<', now())
            ->count();
    }

    public function getUpcomingTrainingCountAttribute(): int
    {
        return $this->trainings()
            ->where('start_date', '>', now())
            ->count();
    }
}