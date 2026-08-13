<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentChecklistItem extends Model
{
    protected $fillable = ['assessment_checklist_id', 'group_label', 'label', 'qty', 'order'];

    protected $casts = [
        'qty' => 'integer',
        'order' => 'integer',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(AssessmentChecklist::class, 'assessment_checklist_id');
    }
}
