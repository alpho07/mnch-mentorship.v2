<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class RagDocument extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'title',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'extension',
        'mime_type',
        'size_bytes',
        'sha256',
        'status',
        'external_document_id',
        'page_or_slide_count',
        'chunk_count',
        'processing_started_at',
        'processed_at',
        'failed_at',
        'error_message',
        'metadata',
        'uploaded_by',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'page_or_slide_count' => 'integer',
            'chunk_count' => 'integer',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(RagChunk::class);
    }

    public function outlines(): HasMany
    {
        return $this->hasMany(RagDocumentOutline::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function formattedSize(): string
    {
        $bytes = max(0, (int) $this->size_bytes);
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return round($bytes, 2).' '.$units[$unit];
    }

    public function fileExists(): bool
    {
        return filled($this->disk) && filled($this->path) && Storage::disk($this->disk)->exists($this->path);
    }
}
