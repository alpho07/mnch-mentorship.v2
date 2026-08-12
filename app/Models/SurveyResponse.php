<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SurveyResponse extends Model
{
    protected $fillable = [
        'survey_id', 'survey_event_id', 'event_instance_number', 'subject_type', 'subject_id', 'respondent_name',
        'respondent_email', 'respondent_contact', 'status', 'submitted_at',
        'overall_score', 'overall_percentage', 'created_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'overall_score' => 'decimal:2',
        'overall_percentage' => 'decimal:2',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SurveyEvent::class, 'survey_event_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questionResponses(): HasMany
    {
        return $this->hasMany(SurveyQuestionResponse::class);
    }

    public function sectionScores(): HasMany
    {
        return $this->hasMany(SurveySectionScore::class);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function markSubmitted(): void
    {
        $this->update(['status' => 'submitted', 'submitted_at' => now()]);
    }
}
