<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasFileManagement
{
    // Legacy single file support (for backward compatibility)
    public function getFileUrlAttribute(): ?string
    {
        $primaryFile = $this->primaryFile;
        return $primaryFile ? $primaryFile->file_url : null;
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $primaryFile = $this->primaryFile;
        return $primaryFile ? $primaryFile->formatted_file_size : '';
    }

    // Multiple files support
    public function hasFiles(): bool
    {
        return $this->files()->exists();
    }

    public function hasPrimaryFile(): bool
    {
        return $this->primaryFile()->exists();
    }

    public function getTotalFileSize(): int
    {
        return $this->files()->sum('file_size');
    }

    public function getFormattedTotalFileSizeAttribute(): string
    {
        $totalSize = $this->getTotalFileSize();
        
        if (!$totalSize) return '';

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $totalSize;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function deleteAllFiles(): void
    {
        foreach ($this->files as $file) {
            $file->deleteFile();
            $file->delete();
        }

        // Also delete featured image
        if ($this->featured_image && Storage::disk('thumbnails')->exists($this->featured_image)) {
            Storage::disk('thumbnails')->delete($this->featured_image);
        }
    }

    // File type helpers
    public function hasFileType(string $type): bool
    {
        return $this->files()->where('file_type', 'like', "{$type}/%")->exists();
    }

    public function getFilesByType(string $type)
    {
        return $this->files()->where('file_type', 'like', "{$type}/%")->get();
    }

    public function getPreviewableFiles()
    {
        return $this->files->filter(fn($file) => $file->isPreviewable());
    }
}


