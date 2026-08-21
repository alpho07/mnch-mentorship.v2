<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipStallReminder extends Model
{
    public const BUCKET_NO_CLASS = 'no_class';

    public const BUCKET_NO_MENTEE = 'no_mentee';

    public const BUCKET_NO_MODULES = 'no_modules';

    protected $fillable = [
        'training_id',
        'bucket',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
