<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commodity extends Model {

    protected $fillable = [
        'commodity_category_id',
        'name',
        'description',
        'order',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function category(): BelongsTo {
        return $this->belongsTo(CommodityCategory::class, 'commodity_category_id');
    }

    /**
     * Departments where this commodity is applicable
     */
    public function applicableDepartments(): BelongsToMany {
        return $this->belongsToMany(
                        AssessmentDepartment::class,
                        'commodity_applicability',
                        'commodity_id',
                        'assessment_department_id'
                )->withTimestamps();
    }

    public function responses(): HasMany {
        return $this->hasMany(AssessmentCommodityResponse::class);
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

    public function scopeByCategory($query, int $categoryId) {
        return $query->where('commodity_category_id', $categoryId);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Check if commodity is applicable to department
     */
    public function isApplicableToDepartment(int $departmentId): bool {
        return $this->applicableDepartments()
                        ->where('assessment_department_id', $departmentId)
                        ->exists();
    }

    /**
     * Get response for specific assessment and department
     */
    public function getResponseForAssessment(int $assessmentId, int $departmentId): ?AssessmentCommodityResponse {
        return $this->responses()
                        ->where('assessment_id', $assessmentId)
                        ->where('assessment_department_id', $departmentId)
                        ->first();
    }
}
