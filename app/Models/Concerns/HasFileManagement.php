<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasFileManagement {

    // File-related computed attributes
    public function getFileUrlAttribute(): ?string {
        return $this->file_path ? Storage::disk('resources')->url($this->file_path) : null;
    }

    public function getThumbnailUrlAttribute(): ?string {
        if (!$this->featured_image) {
            return null;
        }

        return Storage::disk('thumbnails')->url($this->featured_image);
    }

    public function getFormattedFileSizeAttribute(): string {
        if (!$this->file_size) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    // File validation and helpers
    public function hasFile(): bool {
        return !empty($this->file_path) && Storage::disk('resources')->exists($this->file_path);
    }

    public function hasValidFile(): bool {
        if (!$this->hasFile()) {
            return false;
        }

        $actualSize = Storage::disk('resources')->size($this->file_path);
        return $actualSize === $this->file_size;
    }

    public function getFileExtension(): ?string {
        if (!$this->file_path) {
            return null;
        }

        return pathinfo($this->file_path, PATHINFO_EXTENSION);
    }

    public function isPreviewable(): bool {
        if (!$this->hasFile()) {
            return false;
        }

        $previewableTypes = [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif',
            'text/plain', 'text/html',
            'video/mp4', 'audio/mpeg',
        ];

        return in_array($this->file_type, $previewableTypes);
    }

    public function isImageFile(): bool {
        return $this->file_type && str_starts_with($this->file_type, 'image/');
    }

    public function isVideoFile(): bool {
        return $this->file_type && str_starts_with($this->file_type, 'video/');
    }

    public function isAudioFile(): bool {
        return $this->file_type && str_starts_with($this->file_type, 'audio/');
    }

    public function getFileTypeCategory(): string {
        if ($this->isImageFile())
            return 'image';
        if ($this->isVideoFile())
            return 'video';
        if ($this->isAudioFile())
            return 'audio';

        return match ($this->file_type) {
            'application/pdf' => 'pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'spreadsheet',
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed' => 'archive',
            'text/plain', 'text/csv' => 'text',
            default => 'document'
        };
    }

    public function getFileIcon(): string {
        return match ($this->getFileTypeCategory()) {
            'pdf' => 'heroicon-o-document-text',
            'document' => 'heroicon-o-document',
            'presentation' => 'heroicon-o-presentation-chart-bar',
            'spreadsheet' => 'heroicon-o-table-cells',
            'image' => 'heroicon-o-photo',
            'video' => 'heroicon-o-video-camera',
            'audio' => 'heroicon-o-musical-note',
            'archive' => 'heroicon-o-archive-box',
            'text' => 'heroicon-o-document-text',
            default => 'heroicon-o-document'
        };
    }

    public function getDownloadFilename(): string {
        $extension = $this->getFileExtension();
        $filename = \Str::slug($this->title);

        return $extension ? "{$filename}.{$extension}" : $filename;
    }

    public function canBeDownloaded(): bool {
        return $this->is_downloadable &&
                $this->status === 'published' &&
                $this->hasFile();
    }

    // File management methods
    public function deleteFiles(): void {
        if ($this->file_path && Storage::disk('resources')->exists($this->file_path)) {
            Storage::disk('resources')->delete($this->file_path);
        }

        if ($this->featured_image && Storage::disk('thumbnails')->exists($this->featured_image)) {
            Storage::disk('thumbnails')->delete($this->featured_image);
        }
    }

    public function copyFileTo(string $newPath): bool {
        if (!$this->hasFile()) {
            return false;
        }

        return Storage::disk('resources')->copy($this->file_path, $newPath);
    }
}
