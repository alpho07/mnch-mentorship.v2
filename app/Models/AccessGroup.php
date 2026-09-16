<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccessGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // Relationships
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'access_group_users');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_access_groups');
    }

    // Query Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    // Helper Methods
    public function addUser(User $user): void
    {
        if (!$this->users()->where('user_id', $user->id)->exists()) {
            $this->users()->attach($user);
        }
    }

    public function removeUser(User $user): void
    {
        $this->users()->detach($user);
    }

    public function hasUser(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    // Computed Attributes
    public function getUserCountAttribute(): int
    {
        return $this->users()->count();
    }

    public function getResourceCountAttribute(): int
    {
        return $this->resources()->count();
    }
}
