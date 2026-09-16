<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scope extends Model
{
    protected $fillable = [
        'slug', 'label', 'icon', 'color', 'gradient', 'tabs', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'gradient'   => 'array',
        'tabs'       => 'array',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function roleAccess(): HasMany
    {
        return $this->hasMany(ScopeRoleAccess::class);
    }

    /** Returns the role names that may access this scope. */
    public function roleNames(): array
    {
        return $this->roleAccess()->pluck('role_name')->toArray();
    }

    /**
     * Returns active scopes allowed for the given user.
     * super_admin always receives all active scopes (lockout prevention).
     */
    public static function forUser(User $user): Collection
    {
        $roles = $user->getRoleNames()->toArray();

        if (in_array('super_admin', $roles)) {
            return static::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        return static::where('is_active', true)
            ->whereHas('roleAccess', fn ($q) => $q->whereIn('role_name', $roles))
            ->orderBy('sort_order')
            ->get();
    }
}
