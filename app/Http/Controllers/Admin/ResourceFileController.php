<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResourceFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceFileController extends Controller {

    public function download(ResourceFile $file): StreamedResponse {
        if (!$file->exists()) {
            abort(404, 'File not found');
        }

        if (!$file->resource->canUserAccess(auth()->user())) {
            abort(403, 'Access denied');
        }

        // Log download
        $file->resource->incrementDownloads(auth()->user());

        // Log activity
        activity()
                ->performedOn($file->resource)
                ->causedBy(auth()->user())
                ->withProperties(['file_name' => $file->original_name])
                ->log("Downloaded file: {$file->original_name}");

        return Storage::disk('resources')->download(
                        $file->file_path,
                        $file->original_name
                );
    }

    public function preview(ResourceFile $file): Response {
        if (!$file->exists()) {
            abort(404, 'File not found');
        }

        if (!$file->isPreviewable()) {
            abort(400, 'File cannot be previewed');
        }

        if (!$file->resource->canUserAccess(auth()->user())) {
            abort(403, 'Access denied');
        }

        // Log view
        $file->resource->incrementViews(auth()->user());

        $content = Storage::disk('resources')->get($file->file_path);

        return response($content)
                        ->header('Content-Type', $file->file_type)
                        ->header('Content-Disposition', 'inline; filename="' . $file->original_name . '"');
    }

    // Signed public endpoint — no auth, used by Google Docs / Office Online viewers
    public function tempView(ResourceFile $file): Response
    {
        if (!$file->exists()) {
            abort(404, 'File not found');
        }

        $content = Storage::disk('resources')->get($file->file_path);

        return response($content)
            ->header('Content-Type', $file->file_type)
            ->header('Content-Disposition', 'inline; filename="' . $file->original_name . '"')
            ->header('Cache-Control', 'no-store');
    }

    public static function signedTempUrl(ResourceFile $file): string
    {
        return URL::temporarySignedRoute(
            'resource-files.temp-view',
            now()->addMinutes(5),
            ['file' => $file->id]
        );
    }
}
