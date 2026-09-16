<?php

namespace App\Filament\Resources\RagDocumentResource\Pages;

use App\Filament\Resources\RagDocumentResource;
use App\Jobs\ProcessRagDocument;
use App\Models\RagDocument;
use Illuminate\Validation\ValidationException;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateRagDocument extends CreateRecord
{
    protected static string $resource = RagDocumentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $disk = (string) config('rag.uploads.disk', 'local');
        $path = (string) ($data['path'] ?? '');
        $originalName = is_array($data['original_name'] ?? null)
            ? reset($data['original_name'])
            : ($data['original_name'] ?? basename($path));
        $extension = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));
        $allowedExtensions = config('rag.uploads.allowed_extensions', ['pdf', 'pptx']);

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'path' => 'This document type is not supported.',
            ]);
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $mimeType = Storage::disk($disk)->mimeType($path);
        $allowedMimeTypes = config('rag.uploads.allowed_mime_types', []);

        if ($mimeType && $allowedMimeTypes && ! in_array($mimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'path' => 'The uploaded document type is not supported.',
            ]);
        }

        $record = RagDocument::create([
            'title' => $data['title'],
            'original_name' => $originalName ?: basename($path),
            'stored_name' => basename($path),
            'disk' => $disk,
            'path' => $path,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size_bytes' => Storage::disk($disk)->size($path),
            'sha256' => hash_file('sha256', $absolutePath),
            'status' => RagDocument::STATUS_PENDING,
            'metadata' => [
                'uploaded_at' => now()->toIso8601String(),
            ],
            'uploaded_by' => auth()->id(),
        ]);

        ProcessRagDocument::dispatch($record->id);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
