<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentChecklist extends Model
{
    protected $fillable = ['assessment_type_id', 'title', 'description'];

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentChecklistItem::class)->orderBy('order');
    }
}
