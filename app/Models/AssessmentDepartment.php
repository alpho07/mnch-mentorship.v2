<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AssessmentDepartment extends Model {

    protected $fillable = [
        'assessment_type_id',
        'name',
        'slug',
        'color',
        'icon',
        'order',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    protected static function boot() {
        parent::boot();

        // Auto-generate slug from name
        static::creating(function ($department) {
            if (empty($department->slug)) {
                $department->slug = Str::slug($department->name);
            }
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function assessmentType(): BelongsTo {
        return $this->belongsTo(AssessmentType::class);
    }

    /**
     * Commodities applicable to this department
     */
    public function applicableCommodities(): BelongsToMany {
        return $this->belongsToMany(
                        Commodity::class,
                        'commodity_applicability',
                        'assessment_department_id',
                        'commodity_id'
                )->withTimestamps();
    }

    public function commodityResponses(): HasMany {
        return $this->hasMany(AssessmentCommodityResponse::class);
    }

    public function departmentScores(): HasMany {
        return $this->hasMany(AssessmentDepartmentScore::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query) {
        return $query->orderBy('order');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Check if commodity is applicable to this department
     */
    public function isCommodityApplicable(int $commodityId): bool {
        return $this->applicableCommodities()->where('commodity_id', $commodityId)->exists();
    }

    /**
     * Get applicable commodities for a specific category
     */
    public function getApplicableCommoditiesByCategory(int $categoryId) {
        return $this->applicableCommodities()
                        ->where('commodity_category_id', $categoryId)
                        ->where('is_active', true)
                        ->orderBy('order')
                        ->get();
    }
}
