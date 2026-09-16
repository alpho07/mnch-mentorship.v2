<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleAssessmentResult extends Model {

    protected $fillable = [
        'module_assessment_id',
        'class_participant_id',
        'mentee_progress_id',
        'score',
        'status',
        'feedback',
        'assessed_by',
        'assessed_at',
        'answers_data',
    ];
    protected $casts = [
        'score' => 'decimal:2',
        'assessed_at' => 'datetime',
        'answers_data' => 'array',
    ];

    public function moduleAssessment(): BelongsTo {
        return $this->belongsTo(ModuleAssessment::class, 'module_assessment_id');
    }

    public function classParticipant(): BelongsTo {
        return $this->belongsTo(ClassParticipant::class, 'class_participant_id');
    }

    public function menteeProgress(): BelongsTo {
        return $this->belongsTo(MenteeModuleProgress::class, 'mentee_progress_id');
    }

    public function assessor(): BelongsTo {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
