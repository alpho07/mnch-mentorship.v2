<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AssessmentTeamMember extends Pivot {

    protected $table = 'assessment_team';
    public $incrementing = true;
    protected $fillable = [
        'assessment_id',
        'user_id',
        'role',
        'added_by',
        'added_at',
    ];
    protected $casts = [
        'added_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'added_by');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function isTeamLead(): bool {
        return $this->role === 'team_lead'; 
    }

    public function isMember(): bool {
        return $this->role === 'member'; 
    }
}
