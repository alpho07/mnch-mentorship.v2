<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentDepartmentScore extends Model {

    protected $fillable = [
        'assessment_id',
        'assessment_department_id',
        'commodity_category_id',
        'available_count',
        'total_applicable',
        'percentage',
        'grade',
    ];
    protected $casts = [
        'available_count' => 'integer',
        'total_applicable' => 'integer',
        'percentage' => 'decimal:2',
    ];

    protected static function boot() {
        parent::boot();

        // Auto-calculate grade before saving
        static::saving(function ($score) {
            if ($score->percentage !== null) {
                $score->grade = match (true) {
                    $score->percentage >= 80 => 'green',
                    $score->percentage >= 50 => 'yellow',
                    default => 'red',
                };
            }
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class);
    }

    public function department(): BelongsTo {
        return $this->belongsTo(AssessmentDepartment::class, 'assessment_department_id');
    }

    public function category(): BelongsTo {
        return $this->belongsTo(CommodityCategory::class, 'commodity_category_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForAssessment($query, int $assessmentId) {
        return $query->where('assessment_id', $assessmentId);
    }

    public function scopeForDepartment($query, int $departmentId) {
        return $query->where('assessment_department_id', $departmentId);
    }

    public function scopeForCategory($query, int $categoryId) {
        return $query->where('commodity_category_id', $categoryId);
    }

    public function scopeGreen($query) {
        return $query->where('grade', 'green');
    }

    public function scopeYellow($query) {
        return $query->where('grade', 'yellow');
    }

    public function scopeRed($query) {
        return $query->where('grade', 'red');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get grade color
     */
    public function getGradeColorAttribute(): string {
        return match ($this->grade) {
            'green' => 'success',
            'yellow' => 'warning',
            'red' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get grade label
     */
    public function getGradeLabelAttribute(): string {
        return match ($this->grade) {
            'green' => 'Good (80-100%)',
            'yellow' => 'Fair (50-80%)',
            'red' => 'Poor (<50%)',
            default => 'Not Graded',
        };
    }
}
