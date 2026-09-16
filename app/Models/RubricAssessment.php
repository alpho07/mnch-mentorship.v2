<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_rubric_id',
        'class_module_id',
        'mentee_id',
        'mentor_id',
        'score',
        'passed',
        'notes',
        'assessed_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'passed' => 'boolean',
        'assessed_at' => 'datetime',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(ModuleRubric::class, 'module_rubric_id');
    }

    public function classModule(): BelongsTo
    {
        return $this->belongsTo(ClassModule::class);
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function itemResponses(): HasMany
    {
        return $this->hasMany(RubricItemResponse::class);
    }

    public function scorePercentage(): float
    {
        $total = $this->rubric?->total_marks ?? 0;

        return $total > 0 ? round(($this->score / $total) * 100, 1) : 0;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->passed ? 'Pass' : 'Fail';
    }
}
