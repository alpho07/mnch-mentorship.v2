<?php

namespace App\Forms\Components;

use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class DebuggableFileUpload extends FileUpload {

    protected function setUp(): void {
        parent::setUp();

        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file, $component) {
            Log::info('=== FILE UPLOAD STARTED ===', [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'tmp_path' => $file->getRealPath(),
                'tmp_exists' => file_exists($file->getRealPath()),
                'disk' => $component->getDiskName(),
                'directory' => $component->getDirectory(),
            ]);

            try {
                // Check if temp file is readable
                if (!is_readable($file->getRealPath())) {
                    throw new \Exception('Temporary file is not readable');
                }

                // Get disk instance
                $disk = \Storage::disk($component->getDiskName());
                Log::info('Disk config', [
                    'disk_name' => $component->getDiskName(),
                    'disk_root' => $disk->path(''),
                    'root_exists' => is_dir($disk->path('')),
                    'root_writable' => is_writable($disk->path('')),
                ]);

                // Build full path
                $directory = $component->getDirectory();
                $fullPath = $disk->path($directory);

                Log::info('Target directory check', [
                    'directory' => $directory,
                    'full_path' => $fullPath,
                    'exists' => is_dir($fullPath),
                    'writable' => is_writable($fullPath),
                ]);

                // Create directory if needed
                if (!is_dir($fullPath)) {
                    Log::info('Creating directory', ['path' => $fullPath]);
                    mkdir($fullPath, 0775, true);
                }

                // Generate filename
                $filename = $component->getUploadedFileNameForStorage($file);
                $targetPath = $directory . '/' . $filename;

                Log::info('Attempting to store file', [
                    'filename' => $filename,
                    'target_path' => $targetPath,
                    'full_target' => $disk->path($targetPath),
                ]);

                // Attempt the upload
                $result = $disk->putFileAs($directory, $file, $filename);

                if (!$result) {
                    throw new \Exception('Storage returned false - file was not saved');
                }

                Log::info('File stored successfully', [
                    'result' => $result,
                    'exists_after' => $disk->exists($result),
                    'size_after' => $disk->size($result),
                ]);

                return $result;
            } catch (\Exception $e) {
                Log::error('=== FILE UPLOAD FAILED ===', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                Notification::make()
                        ->title('Upload Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                throw $e;
            }
        });
    }
}
