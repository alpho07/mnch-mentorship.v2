<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyEvent extends Model
{
    protected $fillable = [
        'survey_id', 'code', 'name', 'order', 'repeatable',
    ];

    protected $casts = [
        'repeatable' => 'boolean',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(SurveySection::class, 'survey_event_sections');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Scoped to (this event, subject_type, subject_id) — including the null/null
     * "no subject" bucket, which every subject-less response to this event
     * shares. Never user-entered; called once, at response-creation time.
     */
    public function nextInstanceNumberFor(?string $subjectType, ?int $subjectId): int
    {
        $max = $this->responses()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->max('event_instance_number');

        return ($max ?? 0) + 1;
    }
}
