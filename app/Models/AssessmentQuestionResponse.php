<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentQuestionResponse extends Model {

    protected $fillable = [
        'assessment_id',
        'assessment_question_id',
        'response_value',
        'explanation',
        'metadata',
        'score',
    ];
    protected $casts = [
        'metadata' => 'array',
        'score' => 'float',
    ];

    /**
     * Assessment this response belongs to
     */
    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    /**
     * Question this response is for
     */
    public function question(): BelongsTo {
        return $this->belongsTo(AssessmentQuestion::class, 'assessment_question_id');
    }
}
