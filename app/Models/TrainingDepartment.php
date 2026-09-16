<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ============ TRAINING DEPARTMENT PIVOT MODEL ============
class TrainingDepartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'department_id',
    ];

    public $timestamps = true;

    // Relationships
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // Query Scopes
    public function scopeByTraining($query, int $trainingId)
    {
        return $query->where('training_id', $trainingId);
    }

    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    // Computed Attributes
    public function getTrainingTitleAttribute(): string
    {
        return $this->training?->title ?? 'Unknown Training';
    }

    public function getDepartmentNameAttribute(): string
    {
        return $this->department?->name ?? 'Unknown Department';
    }
}