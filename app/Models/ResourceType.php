<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // Relationships
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
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

    public function scopeWithResources($query)
    {
        return $query->has('resources');
    }

    // Computed Attributes
    public function getResourceCountAttribute(): int
    {
        return $this->resources()->count();
    }

    public function getPublishedResourceCountAttribute(): int
    {
        return $this->resources()->published()->count();
    }

  
}
