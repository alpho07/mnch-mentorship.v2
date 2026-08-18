<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'visible_to_roles',
        'certificate_scope',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    /**
     * Active, individually-completable curriculum units: standalone modules
     * and EmONC tracks. Excludes both inactive rows AND parent "module" rows
     * that exist only to group tracks (e.g. PPH's parent module) — those
     * never get their own ClassModule/progress record (only their tracks
     * do, per ModuleUsageService::assignModulesToClass()), so including them
     * would permanently block completion checks and inflate module counts.
     */
    public function completableModules(): \Illuminate\Support\Collection
    {
        return $this->programModules()
            ->where('is_active', true)
            ->whereDoesntHave('children', fn ($q) => $q->where('is_active', true))
            ->get();
    }

    /**
     * Completable modules with no tracks of their own — i.e. not a track,
     * and not a parent grouping tracks.
     */
    public function standaloneModules(): \Illuminate\Support\Collection
    {
        return $this->completableModules()->whereNull('parent_id');
    }

    /**
     * Completable modules that ARE a track (child of a parent module).
     */
    public function trackModules(): \Illuminate\Support\Collection
    {
        return $this->completableModules()->whereNotNull('parent_id');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function isEmonc(): bool
    {
        return str_contains(strtolower($this->name), 'maternal')
            && str_contains(strtolower($this->name), 'emonc');
    }

    /**
     * Certificate issuance policy: 'per_class' means a mentee is certified
     * once they finish one class (today's default for every program except
     * EmONC). 'per_program' means every module across ALL of the mentee's
     * classes in this program must be done, and — per
     * ClassParticipant::isReadyForHeadDrmhCertification() — additionally
     * requires mentor approval before Head DRMH certification. Configurable
     * per program (Curriculum → Programs) rather than hardcoded to EmONC, in
     * case another program adopts the same policy later.
     */
    public function usesPerProgramCertification(): bool
    {
        return $this->certificate_scope === 'per_program';
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

    /**
     * Whether a deactivated program is still pickable by the given user.
     * Deliberately has no super_admin bypass — the Mentorship Settings
     * "Program Activation" toggle is meant to apply to everyone the same
     * way the "New Mentorship" / "Guided Setup" button toggles do, with no
     * exceptions. The only carve-out is the explicit per-role
     * visible_to_roles override editable on Curriculum -> Programs.
     */
    public function isSelectableBy(?User $user = null): bool
    {
        if ($this->is_active) {
            return true;
        }

        $user = $user ?? auth()->user();

        if (! $user) {
            return false;
        }

        $roles = $user->getRoleNames()->toArray();

        return collect($this->visible_to_roles ?? [])
            ->intersect($roles)
            ->isNotEmpty();
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
