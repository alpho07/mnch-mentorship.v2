<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSectionScore extends Model {

    protected $fillable = [
        'assessment_id',
        'assessment_section_id',
        'total_score',
        'max_score',
        'percentage',
        'grade',
        'total_questions',
        'answered_questions',
        'skipped_questions',
    ];
    protected $casts = [
        'total_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'total_questions' => 'integer',
        'answered_questions' => 'integer',
        'skipped_questions' => 'integer',
    ];

    protected static function boot() {
        parent::boot();

        // Auto-calculate grade before saving
        static::saving(function ($score) {
            if ($score->percentage !== null) {
                $score->grade = self::calculateGrade($score->percentage);
            }
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class);
    }

    public function section(): BelongsTo {
        return $this->belongsTo(AssessmentSection::class, 'assessment_section_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForAssessment($query, int $assessmentId) {
        return $query->where('assessment_id', $assessmentId);
    }

    public function scopeForSection($query, int $sectionId) {
        return $query->where('assessment_section_id', $sectionId);
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
     * Calculate grade from percentage
     */
    public static function calculateGrade(float $percentage): string {
        return match (true) {
            $percentage >= 80 => 'green',
            $percentage >= 50 => 'yellow',
            default => 'red',
        };
    }

    /**
     * Get grade color attribute
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
     * Get grade label attribute
     */
    public function getGradeLabelAttribute(): string {
        return match ($this->grade) {
            'green' => 'Good (80-100%)',
            'yellow' => 'Fair (50-80%)',
            'red' => 'Poor (<50%)',
            default => 'Not Graded',
        };
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentageAttribute(): float {
        if ($this->total_questions === 0) {
            return 0;
        }

        return round(($this->answered_questions / $this->total_questions) * 100, 2);
    }
}
