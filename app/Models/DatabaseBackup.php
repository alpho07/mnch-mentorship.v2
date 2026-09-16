<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'filename',
        'disk',
        'size_bytes',
        'type',
        'status',
        'triggered_by',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function restores(): HasMany
    {
        return $this->hasMany(DatabaseRestore::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'completed' && Storage::disk($this->disk)->exists($this->filename);
    }
}
