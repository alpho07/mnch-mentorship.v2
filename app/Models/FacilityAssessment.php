<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacilityAssessment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_id',
        'assessor_id',
        'assessment_date',
        'infrastructure_score',
        'equipment_score',
        'staff_capacity_score',
        'training_environment_score',
        'overall_score',
        'status',
        'recommendations',
        'next_assessment_due',
        'assessment_notes',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'next_assessment_due' => 'date',
        'infrastructure_score' => 'decimal:1',
        'equipment_score' => 'decimal:1',
        'staff_capacity_score' => 'decimal:1',
        'training_environment_score' => 'decimal:1',
        'overall_score' => 'decimal:1',
        'recommendations' => 'array',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeValid($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
                    ->where('next_assessment_due', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('next_assessment_due', '<=', now());
    }

    // Computed Attributes
    public function getIsValidAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED && 
               $this->next_assessment_due > now();
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_EXPIRED => 'warning',
            default => 'gray',
        };
    }

    public function getReadinessLevelAttribute(): string
    {
        if ($this->overall_score >= 90) return 'Excellent';
        if ($this->overall_score >= 80) return 'Very Good';
        if ($this->overall_score >= 70) return 'Good';
        if ($this->overall_score >= 60) return 'Fair';
        return 'Needs Improvement';
    }

    // Methods
    public function calculateOverallScore(): void
    {
        $scores = [
            $this->infrastructure_score,
            $this->equipment_score,
            $this->staff_capacity_score,
            $this->training_environment_score,
        ];

        $validScores = array_filter($scores, fn($score) => $score !== null);
        
        if (count($validScores) > 0) {
            $this->overall_score = array_sum($validScores) / count($validScores);
            
            // Auto-approve if score is above threshold
            if ($this->overall_score >= 70) {
                $this->status = self::STATUS_APPROVED;
                $this->next_assessment_due = now()->addYear();
            } else {
                $this->status = self::STATUS_REJECTED;
            }
        }
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected', 
            self::STATUS_EXPIRED => 'Expired',
        ];
    }
}