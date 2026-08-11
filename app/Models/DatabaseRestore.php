<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseRestore extends Model
{
    protected $fillable = [
        'database_backup_id',
        'safety_backup_id',
        'status',
        'error_message',
        'restored_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(DatabaseBackup::class, 'database_backup_id');
    }

    public function safetyBackup(): BelongsTo
    {
        return $this->belongsTo(DatabaseBackup::class, 'safety_backup_id');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }
}
