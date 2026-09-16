<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks which modules have been taught in a mentorship.
 * 
 * DOMAIN INVARIANT: UNIQUE(mentorship_id, module_id)
 * A module can only be taught ONCE per mentorship across all classes.
 */
class MentorshipModuleUsage extends Model
{
    protected $table = 'mentorship_module_usages';

    protected $fillable = [
        'mentorship_id',
        'module_id',
        'first_class_id',
    ];

    public function mentorship(): BelongsTo
    {
        return $this->belongsTo(Training::class, 'mentorship_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ProgramModule::class, 'module_id');
    }

    public function firstClass(): BelongsTo
    {
        return $this->belongsTo(MentorshipClass::class, 'first_class_id');
    }

    // Scopes
    public function scopeForMentorship($query, int $mentorshipId)
    {
        return $query->where('mentorship_id', $mentorshipId);
    }
}
