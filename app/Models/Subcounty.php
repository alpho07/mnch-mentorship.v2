<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcounty extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'uid',
        'county_id',
    ];

    protected $with = ['county'];

    // Relationships
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subcounty_user');
    }

    // Query Scopes
    public function scopeByCounty($query, int $countyId)
    {
        return $query->where('county_id', $countyId);
    }

    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopeWithFacilities($query)
    {
        return $query->has('facilities');
    }

    // Computed Attributes
    public function getFacilityCountAttribute(): int
    {
        return $this->facilities()->count();
    }

    public function getHubFacilitiesCountAttribute(): int
    {
        return $this->facilities()->where('is_hub', true)->count();
    }

    public function getFullLocationAttribute(): string
    {
        return "{$this->name}, {$this->county->name}";
    }
}