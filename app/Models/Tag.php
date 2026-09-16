<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model {

    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    // Relationships
    public function resources(): BelongsToMany {
        return $this->belongsToMany(Resource::class, 'resource_tags');
    }

    // Query Scopes
    public function scopeByName($query, string $name) {
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopePopular($query, int $minCount = 1) {
        return $query->withCount('resources')
                        ->having('resources_count', '>=', $minCount);
    }

    // Computed Attributes
    public function getResourceCountAttribute(): int {
        return $this->resources()->count();
    }

    // Route Key
    public function getRouteKeyName() {
        return 'slug';
    }
}
