<?php

namespace App\Jobs;

use App\Models\RagDocument;
use App\Models\RagDocumentOutline;
use App\Services\Rag\InAppRagEngine;
use App\Services\Rag\RagClient;
use App\Services\Rag\Settings\RagSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProcessRagDocument implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 3600;

    public function __construct(public int $documentId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    public function handle(RagClient $client, InAppRagEngine $engine, RagSettings $settings): void
    {
        $document = RagDocument::findOrFail($this->documentId);

        if (! config('rag.enabled')) {
            $this->markFailed($document, 'RAG is disabled.');

            return;
        }

        if ($document->status === RagDocument::STATUS_READY) {
            return;
        }

        $duplicate = RagDocument::query()
            ->whereKeyNot($document->getKey())
            ->where('sha256', $document->sha256)
            ->whereIn('status', [RagDocument::STATUS_PROCESSING, RagDocument::STATUS_READY])
            ->first();

        if ($duplicate) {
            $this->markFailed($document, 'Duplicate document checksum already exists; indexing skipped.');

            return;
        }

        $document->forceFill([
            'status' => RagDocument::STATUS_PROCESSING,
            'processing_started_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        try {
            if (! $document->fileExists()) {
                throw new RuntimeException('Stored document file is missing.');
            }

            $absolutePath = Storage::disk($document->disk)->path($document->path);
            $response = $this->shouldUseInAppIngestion($document)
                ? $engine->ingest($document, $absolutePath)
                : $client->ingest($absolutePath, $document->title);

            DB::transaction(function () use ($document, $response): void {
                $document->forceFill([
                    'status' => RagDocument::STATUS_READY,
                    'external_document_id' => $response['document_id'] ?? $response['id'] ?? $response['external_document_id'] ?? $document->external_document_id,
                    'page_or_slide_count' => $response['page_count'] ?? $response['slide_count'] ?? $response['page_or_slide_count'] ?? $response['units'] ?? $document->page_or_slide_count,
                    'chunk_count' => $response['chunk_count'] ?? $response['chunks'] ?? $document->chunk_count,
                    'metadata' => array_merge($document->metadata ?? [], [
                        'ingest_response' => $this->metadataSummary($response),
                    ]),
                    'processed_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();

                $this->storeOutline($document, $response['outline'] ?? []);
            });

            $settings->bumpCorpusVersion('RAG document processed: '.$document->id);
            BuildRagLexicon::dispatch();
        } catch (\Throwable $e) {
            $message = $client->sanitizeError($e->getMessage());
            $this->markFailed($document, $message);

            Log::warning('RAG document ingestion failed', [
                'document_id' => $document->id,
                'status' => $document->status,
                'error' => $message,
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $document = RagDocument::find($this->documentId);

        if ($document) {
            $this->markFailed($document, app(RagClient::class)->sanitizeError($exception->getMessage()));
        }
    }

    private function markFailed(RagDocument $document, string $message): void
    {
        $document->forceFill([
            'status' => RagDocument::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => $message,
        ])->save();

        app(RagSettings::class)->bumpCorpusVersion('RAG document failed: '.$document->id);
    }

    private function shouldUseInAppIngestion(RagDocument $document): bool
    {
        if (config('rag.engine') === 'external') {
            return true;
        }

        return ! in_array(strtolower((string) $document->extension), ['pdf', 'pptx'], true);
    }

    private function metadataSummary(array $response): array
    {
        return collect($response)
            ->except(['content', 'text', 'chunks', 'outline'])
            ->take(20)
            ->all();
    }

    private function storeOutline(RagDocument $document, mixed $outline): void
    {
        $document->outlines()->delete();

        if (! is_array($outline)) {
            return;
        }

        foreach (array_values($outline) as $index => $entry) {
            if (! is_array($entry) || blank($entry['title'] ?? null)) {
                continue;
            }

            RagDocumentOutline::create([
                'rag_document_id' => $document->id,
                'sort_order' => $index,
                'level' => max(1, min(6, (int) ($entry['level'] ?? 1))),
                'type' => Str::limit((string) ($entry['type'] ?? 'heading'), 32, ''),
                'title' => Str::limit(strip_tags((string) $entry['title']), 500, ''),
                'locator_type' => filled($entry['locator_type'] ?? null) ? Str::limit((string) $entry['locator_type'], 32, '') : null,
                'locator' => filled($entry['locator'] ?? null) ? Str::limit((string) $entry['locator'], 64, '') : null,
                'content' => filled($entry['content'] ?? null) ? Str::limit(strip_tags((string) $entry['content']), 2000) : null,
                'metadata' => is_array($entry['metadata'] ?? null) ? $entry['metadata'] : null,
            ]);
        }
    }
}
