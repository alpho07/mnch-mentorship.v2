<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function counties(): HasMany
    {
        return $this->hasMany(County::class);
    }

    // Query Scopes
    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    // Computed Attributes
    public function getCountyCountAttribute(): int
    {
        return $this->counties()->count();
    }

    public function getFacilityCountAttribute(): int
    {
        return Facility::whereHas('subcounty.county', function ($query) {
            $query->where('division_id', $this->id);
        })->count();
    }
}