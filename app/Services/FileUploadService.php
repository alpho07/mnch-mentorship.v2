<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class FileUploadService
{
    protected array $allowedMimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'zip' => 'application/zip',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];

    protected int $maxFileSize = 50 * 1024 * 1024; // 50MB

    public function uploadResourceFile(UploadedFile $file, string $resourceType = 'document'): array
    {
        $this->validateFile($file);

        $filename = $this->generateSecureFilename($file);
        $directory = $this->getResourceDirectory($resourceType);
        $fullPath = $directory . '/' . $filename;

        // Store file in private storage
        $stored = Storage::disk('resources')->putFileAs(
            $directory,
            $file,
            $filename
        );

        if (!$stored) {
            throw new Exception('Failed to upload file');
        }

        return [
            'path' => $fullPath,
            'original_name' => $file->getClientOriginalName(),
            'filename' => $filename,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
        ];
    }

    public function uploadThumbnail(UploadedFile $file): array
    {
        $this->validateImage($file);

        $filename = $this->generateSecureFilename($file);
        $directory = 'resources';
        
        // Resize and optimize image
        $image = \Intervention\Image\Facades\Image::make($file);
        $image->fit(800, 600, function ($constraint) {
            $constraint->upsize();
        })->encode('jpg', 85);

        // Store in public thumbnails disk
        $path = $directory . '/' . $filename;
        Storage::disk('thumbnails')->put($path, $image->stream());

        return [
            'path' => $path,
            'url' => Storage::disk('thumbnails')->url($path),
            'filename' => $filename,
            'size' => strlen($image->stream()),
        ];
    }

    protected function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new Exception('File size exceeds maximum allowed size of ' . ($this->maxFileSize / 1024 / 1024) . 'MB');
        }

        // Validate mime type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', array_keys($this->allowedMimeTypes)));
        }

        // Additional security checks
        $this->performSecurityChecks($file);
    }

    protected function validateImage(UploadedFile $file): void
    {
        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
        
        if (!in_array($file->getMimeType(), $allowedImageTypes)) {
            throw new Exception('Invalid image type');
        }

        if ($file->getSize() > 5 * 1024 * 1024) { // 5MB for images
            throw new Exception('Image size too large');
        }
    }

    protected function performSecurityChecks(UploadedFile $file): void
    {
        // Check if file is actually what it claims to be
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMimeType = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);

        if ($detectedMimeType !== $file->getMimeType()) {
            throw new Exception('File content does not match declared type');
        }

        // Check for malicious content in file name
        $filename = $file->getClientOriginalName();
        if (preg_match('/[<>:"|?*]/', $filename) || str_contains($filename, '..')) {
            throw new Exception('Invalid filename');
        }
    }

    protected function generateSecureFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = hash('sha256', $file->getClientOriginalName() . time() . random_bytes(8));
        return date('Y/m/') . substr($hash, 0, 40) . '.' . $extension;
    }

    protected function getResourceDirectory(string $type): string
    {
        return match($type) {
            'video' => 'videos',
            'audio' => 'audio',
            'image' => 'images',
            'archive' => 'archives',
            default => 'documents'
        };
    }

    public function deleteFile(string $path): bool
    {
        if (Storage::disk('resources')->exists($path)) {
            return Storage::disk('resources')->delete($path);
        }
        return false;
    }

    public function deleteThumbnail(string $path): bool
    {
        if (Storage::disk('thumbnails')->exists($path)) {
            return Storage::disk('thumbnails')->delete($path);
        }
        return false;
    }
}