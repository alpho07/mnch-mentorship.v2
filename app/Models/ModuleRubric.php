<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleRubric extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_module_id',
        'title',
        'description',
        'case_scenario',
        'total_marks',
        'pass_marks',
        'pass_percentage',
        'equipment_supplies',
        'debrief_questions',
        'order_sequence',
        'is_active',
    ];

    protected $casts = [
        'total_marks' => 'integer',
        'pass_marks' => 'integer',
        'pass_percentage' => 'decimal:2',
        'equipment_supplies' => 'array',
        'debrief_questions' => 'array',
        'order_sequence' => 'integer',
        'is_active' => 'boolean',
    ];

    public function programModule(): BelongsTo
    {
        return $this->belongsTo(ProgramModule::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RubricItem::class)->orderBy('order_sequence');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(RubricAssessment::class);
    }

    public function isPassed(int $score): bool
    {
        return $score >= $this->pass_marks;
    }

    public function scorePercentage(int $score): float
    {
        if ($this->total_marks === 0) {
            return 0;
        }

        return round(($score / $this->total_marks) * 100, 1);
    }
}
