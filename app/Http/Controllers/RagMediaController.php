<?php

namespace App\Http\Controllers;

use App\Models\RagDocument;
use App\Support\RagAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RagMediaController extends Controller
{
    public function __invoke(Request $request, string $externalDocumentId, string $filename): Response
    {
        abort_unless(RagAccess::canUseChat($request->user()), 403);
        abort_if(! Str::isUuid($externalDocumentId), 404);
        abort_if($filename !== basename($filename) || ! preg_match('/^[A-Za-z0-9._-]+$/', $filename), 404);

        $document = RagDocument::query()
            ->where('external_document_id', $externalDocumentId)
            ->where('status', RagDocument::STATUS_READY)
            ->first();

        abort_unless($document, 404);

        $baseUrl = rtrim((string) config('rag.base_url', 'http://127.0.0.1:8001'), '/');
        $response = Http::baseUrl($baseUrl)
            ->timeout((int) config('rag.request_timeout', 30))
            ->get('/media/'.$externalDocumentId.'/'.$filename);

        abort_unless($response->successful(), 404);

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type', 'application/octet-stream'),
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
