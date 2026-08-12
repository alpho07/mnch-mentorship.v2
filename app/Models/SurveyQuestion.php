<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    protected $fillable = [
        'survey_section_id', 'question_code', 'question_text', 'help_text',
        'question_type', 'options', 'is_required', 'validation_rules',
        'display_conditions', 'requires_explanation_on', 'explanation_label',
        'scoring_map', 'is_scored', 'order', 'group', 'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'display_conditions' => 'array',
        'requires_explanation_on' => 'array',
        'scoring_map' => 'array',
        'is_required' => 'boolean',
        'is_scored' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(SurveySection::class, 'survey_section_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyQuestionResponse::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeScored($query)
    {
        return $query->where('is_scored', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
