<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CommodityCategory extends Model {

    protected $fillable = [
        'name',
        'slug',
        'order',
        'icon',
        'description',
    ];
    protected $casts = [
        'order' => 'integer',
    ];

    protected static function boot() {
        parent::boot();

        // Auto-generate slug from name
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function commodities(): HasMany {
        return $this->hasMany(Commodity::class);
    }

    public function departmentScores(): HasMany {
        return $this->hasMany(AssessmentDepartmentScore::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeOrdered($query) {
        return $query->orderBy('order');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get active commodities count
     */
    public function getActiveCommoditiesCount(): int {
        return $this->commodities()->where('is_active', true)->count();
    }
}
