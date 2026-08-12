<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveySectionScore extends Model
{
    protected $fillable = [
        'survey_response_id', 'survey_section_id', 'total_score', 'max_score',
        'percentage', 'grade', 'total_questions', 'answered_questions', 'skipped_questions',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'percentage' => 'decimal:2',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SurveySection::class, 'survey_section_id');
    }
}
