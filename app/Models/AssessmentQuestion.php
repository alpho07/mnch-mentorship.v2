<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentQuestion extends Model
{
    protected $fillable = [
        'assessment_section_id',
        'question_code',
        'question_text',
        'help_text',
        'question_type',
        'options',
        'is_required',
        'validation_rules',
        'display_conditions',
        'requires_explanation_on',
        'explanation_label',
        'skip_logic',
        'scoring_map',
        'is_scored',
        'order',
        'group',
        'indent_level',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'display_conditions' => 'array',
        'requires_explanation_on' => 'array',
        'skip_logic' => 'array',
        'scoring_map' => 'array',
        'is_required' => 'boolean',
        'is_scored' => 'boolean',
        'indent_level' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Section this question belongs to
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(AssessmentSection::class, 'assessment_section_id');
    }

    /**
     * Responses for this question
     */
    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentQuestionResponse::class, 'assessment_question_id');
    }

    /**
     * Scope to get only active questions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only scored questions
     */
    public function scopeScored($query)
    {
        return $query->where('is_scored', true);
    }

    /**
     * Scope to order by order column
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
