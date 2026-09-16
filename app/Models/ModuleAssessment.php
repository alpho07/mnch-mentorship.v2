<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleAssessment extends Model {

    protected $fillable = [
        'class_module_id',
        'title',
        'description',
        'assessment_type',
        'pass_threshold',
        'max_score',
        'weight_percentage',
        'is_active',
        'questions_data',
        'order_sequence',
    ];
    protected $casts = [
        'pass_threshold' => 'decimal:2',
        'max_score' => 'decimal:2',
        'weight_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'questions_data' => 'array',
        'order_sequence' => 'integer',
    ];

    public function classModule(): BelongsTo {
        return $this->belongsTo(ClassModule::class, 'class_module_id');
    }

    public function results(): HasMany {
        return $this->hasMany(ModuleAssessmentResult::class, 'module_assessment_id');
    }
}
