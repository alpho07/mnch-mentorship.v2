<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentEmailJob extends Model {

    protected $fillable = [
        'assessment_id',
        'user_id',
        'emails',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'emails'  => 'array',
        'sent_at' => 'datetime',
    ];

    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
