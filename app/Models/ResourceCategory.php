<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ResourceCategory::class, 'parent_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'category_id');
    }

    // Query Scopes
    public function scopeParent($query)
    {
        return $query->whereNull('parent_id');
    }

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

    // Helper Methods
    public function getAllChildren()
    {
        return $this->children()->with('children')->get();
    }

    // Computed Attributes
    public function getFullPathAttribute(): string
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' > ');
    }

    public function getResourceCountAttribute(): int
    {
        return $this->resources()->count();
    }

    public function getChildrenCountAttribute(): int
    {
        return $this->children()->count();
    }


}
