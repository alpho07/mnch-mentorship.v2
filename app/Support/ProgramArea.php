<?php

namespace App\Support;

use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Program-area scoping (newborn & child vs EmONC) for area admins.
 * Area, role and universal-role definitions live in config/program_areas.php.
 */
class ProgramArea
{
    public const NEWBORN_CHILD = 'newborn_child';

    public const EMONC = 'emonc';

    public static function options(): array
    {
        return collect(config('program_areas.areas'))->map(fn ($a) => $a['label'])->all();
    }

    /** Area admin roles → area key. */
    public static function roleMap(): array
    {
        return collect(config('program_areas.areas'))->mapWithKeys(fn ($a, $k) => [$a['role'] => $k])->all();
    }

    /** The area this user is confined to, or null when unrestricted. */
    public static function forUser(?User $user): ?string
    {
        if (! $user || $user->hasRole(['super_admin', 'admin'])) {
            return null;
        }

        foreach (self::roleMap() as $role => $area) {
            if ($user->hasRole($role)) {
                return $area;
            }
        }

        return null;
    }

    public static function programIds(string $area): array
    {
        return Program::where('program_area', $area)->pluck('id')->all();
    }

    /**
     * Restrict a query on a table carrying a `program_area` column.
     *
     * @param  bool  $includeShared  rows with NULL area stay visible (generic content)
     */
    public static function scopeByColumn(Builder $query, ?User $user, string $column = 'program_area', bool $includeShared = false): Builder
    {
        $area = self::forUser($user);
        if ($area === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($area, $column, $includeShared) {
            $q->where($column, $area);
            if ($includeShared) {
                $q->orWhereNull($column);
            }
        });
    }

    /** Restrict a query by a program_id column (or a relation path ending in program_id). */
    public static function scopeByProgramId(Builder $query, ?User $user, string $column = 'program_id'): Builder
    {
        $area = self::forUser($user);

        return $area === null ? $query : $query->whereIn($column, self::programIds($area));
    }

    /** Restrict a query through a relation whose model has program_id. */
    public static function scopeByRelationProgram(Builder $query, ?User $user, string $relation): Builder
    {
        $area = self::forUser($user);

        return $area === null ? $query : $query->whereHas($relation, fn (Builder $q) => $q->whereIn('program_id', self::programIds($area)));
    }

    /** Users list: mentors & mentees are universal; everyone else must belong to the area. */
    public static function scopeUsers(Builder $query, ?User $user): Builder
    {
        $area = self::forUser($user);
        if ($area === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($area) {
            $q->where('program_area', $area)
                ->orWhereHas('roles', fn (Builder $r) => $r->whereIn('name', config('program_areas.universal_roles')));
        });
    }

    /** True when the given role names include anything other than mentor/mentee roles. */
    public static function requiresArea(array $roleNames): bool
    {
        return count(array_diff($roleNames, config('program_areas.universal_roles'))) > 0;
    }
}
