<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceController extends Controller
{
    public function download(Resource $resource): StreamedResponse
    {
        // Try to get the primary file first, fallback to first file
        $file = $resource->primaryFile ?: $resource->files()->first();
        
        if (!$file || !$file->exists()) {
            abort(404, 'No downloadable file found for this resource');
        }

        if (!$resource->canUserAccess(auth()->user())) {
            abort(403, 'Access denied');
        }

        // Log download activity
        $resource->incrementDownloads(auth()->user());
        
        // Log activity if function exists
        if (function_exists('activity')) {
            activity()
                ->performedOn($resource)
                ->causedBy(auth()->user())
                ->withProperties(['file_name' => $file->original_name])
                ->log("Downloaded resource file: {$file->original_name}");
        }

        return Storage::disk('resources')->download(
            $file->file_path,
            $file->original_name
        );
    }

    public function preview(Resource $resource): Response
    {
        // Try to get the primary file first, fallback to first file
        $file = $resource->primaryFile ?: $resource->files()->first();
        
        if (!$file || !$file->exists()) {
            abort(404, 'No previewable file found for this resource');
        }

        if (!$file->isPreviewable()) {
            abort(400, 'This file type cannot be previewed');
        }

        if (!$resource->canUserAccess(auth()->user())) {
            abort(403, 'Access denied');
        }

        // Log view activity
        $resource->incrementViews(auth()->user());

        // Log activity if function exists
        if (function_exists('activity')) {
            activity()
                ->performedOn($resource)
                ->causedBy(auth()->user())
                ->withProperties(['file_name' => $file->original_name])
                ->log("Previewed resource file: {$file->original_name}");
        }

        $content = Storage::disk('resources')->get($file->file_path);
        
        return response($content)
            ->header('Content-Type', $file->file_type)
            ->header('Content-Disposition', 'inline; filename="' . $file->original_name . '"')
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public function downloadAll(Resource $resource)
    {
        if (!$resource->hasFiles()) {
            abort(404, 'No files found for this resource');
        }

        if (!$resource->canUserAccess(auth()->user())) {
            abort(403, 'Access denied');
        }

        // If only one file, download it directly
        if ($resource->files()->count() === 1) {
            return $this->download($resource);
        }

        // Create a ZIP file with all resource files
        $zip = new \ZipArchive();
        $zipFileName = storage_path('app/temp/' . $resource->slug . '_files.zip');
        
        // Ensure temp directory exists
        if (!file_exists(dirname($zipFileName))) {
            mkdir(dirname($zipFileName), 0755, true);
        }

        if ($zip->open($zipFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            abort(500, 'Unable to create ZIP file');
        }

        foreach ($resource->files as $file) {
            if ($file->exists()) {
                $filePath = Storage::disk('resources')->path($file->file_path);
                $zip->addFile($filePath, $file->original_name);
            }
        }

        $zip->close();

        // Log download activity
        $resource->incrementDownloads(auth()->user());
        
        if (function_exists('activity')) {
            activity()
                ->performedOn($resource)
                ->causedBy(auth()->user())
                ->withProperties([
                    'download_type' => 'zip_all_files',
                    'file_count' => $resource->files()->count()
                ])
                ->log("Downloaded all files as ZIP archive");
        }

        return response()->download($zipFileName, $resource->slug . '_files.zip')->deleteFileAfterSend();
    }
}