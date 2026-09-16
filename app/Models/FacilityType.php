<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    // Query Scopes
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

    public function getHubFacilityCountAttribute(): int
    {
        return $this->facilities()->where('is_hub', true)->count();
    }
}