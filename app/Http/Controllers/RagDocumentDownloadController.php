<?php

namespace App\Http\Controllers;

use App\Models\RagDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RagDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, RagDocument $document): StreamedResponse
    {
        abort_unless(config('rag.enabled'), 404);
        abort_unless($request->user()?->can('download', $document), 403);

        abort_if(blank($document->disk) || blank($document->path), 404);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        $name = $document->original_name ?: $document->stored_name ?: basename($document->path);

        return $disk->download($document->path, $name);
    }
}
