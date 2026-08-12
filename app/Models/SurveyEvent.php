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
}
