<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveySection extends Model
{
    protected $fillable = [
        'survey_id', 'code', 'name', 'description', 'is_scored', 'order', 'is_active',
    ];

    protected $casts = [
        'is_scored' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class);
    }

    public function sectionScores(): HasMany
    {
        return $this->hasMany(SurveySectionScore::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(SurveyEvent::class, 'survey_event_sections');
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
