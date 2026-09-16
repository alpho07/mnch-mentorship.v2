<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class County extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'uid',
        'division_id',
    ];

    // Relationships
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function subcounties(): HasMany
    {
        return $this->hasMany(Subcounty::class);
    }

    public function facilities(): HasManyThrough
    {
        return $this->hasManyThrough(Facility::class, Subcounty::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'county_user');
    }

    // Query Scopes
    public function scopeByDivision($query, int $divisionId)
    {
        return $query->where('division_id', $divisionId);
    }

    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    // Computed Attributes
    public function getSubcountyCountAttribute(): int
    {
        return $this->subcounties()->count();
    }

    public function getFacilityCountAttribute(): int
    {
        return $this->facilities()->count();
    }
}