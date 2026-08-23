<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user notification opt-outs, stored as a sparse map:
 * { "<event_key>": { "mail": bool, "database": bool, "broadcast": bool } }
 * A missing row or missing event/channel key means the channel is ON.
 */
class UserNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'channels',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
