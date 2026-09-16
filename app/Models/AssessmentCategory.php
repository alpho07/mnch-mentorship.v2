<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category_type',
        'default_weight_percentage',
        'assessment_method',
        'order_sequence',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'default_weight_percentage' => 'decimal:1',
        'order_sequence' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class, 'training_assessment_categories')
            ->withPivot(['weight_percentage', 'pass_threshold', 'is_required', 'order_sequence', 'is_active'])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(MenteeAssessmentResult::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    // Computed Attributes
    public function getUsageCountAttribute(): int
    {
        return $this->trainings()->count();
    }

    public function getTotalAssessmentsAttribute(): int
    {
        return $this->assessmentResults()->count();
    }

    // Static methods
    public static function getAssessmentMethodOptions(): array
    {
        return [
            'Written Test' => 'Written Test',
            'Practical Demonstration' => 'Practical Demonstration',
            'Oral Examination' => 'Oral Examination',
            'Case Study' => 'Case Study',
            'Observation' => 'Clinical Observation',
            'Simulation' => 'Simulation Exercise',
        ];
    }

    public static function getCategoryTypeOptions(): array
    {
        return [
            'general' => 'General Assessment',
            'pre_test' => 'Pre-Test',
            'post_test' => 'Post-Test',
            'practical' => 'Practical Skills',
            'theory' => 'Clinical Theory',
            'communication' => 'Communication Skills',
            'attitude' => 'Professional Attitude',
        ];
    }
}