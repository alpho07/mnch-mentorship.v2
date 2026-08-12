<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestionResponse extends Model
{
    protected $fillable = [
        'survey_response_id', 'survey_question_id', 'response_value',
        'explanation', 'metadata', 'score',
    ];

    protected $casts = [
        'metadata' => 'array',
        'score' => 'float',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class);
    }
}
