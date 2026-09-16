<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ResourceFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_id',
        'original_name',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'is_primary',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'file_size' => 'integer',
    ];

    // Boot method to handle defaults
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Ensure original_name is set
            if (empty($model->original_name) && ! empty($model->file_name)) {
                $model->original_name = $model->file_name;
            }
        });
    }

    // Relationships
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    // File methods
    public function getFileUrlAttribute(): string
    {
        return Storage::disk('resources')->url($this->file_path);
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('admin.resource-files.download', $this);
    }

    public function getPreviewUrlAttribute(): string
    {
        return route('admin.resource-files.preview', $this);
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }

    public function getFileExtension(): ?string
    {
        return pathinfo($this->file_path, PATHINFO_EXTENSION);
    }

    public function isPreviewable(): bool
    {
        $previewableTypes = [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'text/plain', 'text/csv',
            'video/mp4', 'video/webm',
            'audio/mpeg', 'audio/wav',
        ];

        return in_array($this->file_type, $previewableTypes);
    }

    public function getFileIcon(): string
    {
        return match (true) {
            str_starts_with($this->file_type, 'image/') => 'heroicon-o-photo',
            str_starts_with($this->file_type, 'video/') => 'heroicon-o-video-camera',
            str_starts_with($this->file_type, 'audio/') => 'heroicon-o-musical-note',
            $this->file_type === 'application/pdf' => 'heroicon-o-document-text',
            str_contains($this->file_type, 'word') => 'heroicon-o-document',
            str_contains($this->file_type, 'excel') || str_contains($this->file_type, 'sheet') => 'heroicon-o-table-cells',
            str_contains($this->file_type, 'powerpoint') || str_contains($this->file_type, 'presentation') => 'heroicon-o-presentation-chart-bar',
            default => 'heroicon-o-document',
        };
    }

    public function exists(): bool
    {
        if (blank($this->file_path)) {
            return false;
        }

        return Storage::disk('resources')->exists($this->file_path);
    }

    public function deleteFile(): void
    {
        if ($this->exists()) {
            Storage::disk('resources')->delete($this->file_path);
        }
    }
}
