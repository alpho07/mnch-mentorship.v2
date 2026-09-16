<?php

namespace App\Services\Rag;

use App\Models\RagChunk;
use App\Models\RagDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InAppRagEngine
{
    public function __construct(
        private readonly DocumentTextExtractor $extractor,
        private readonly ExternalAiProvider $provider,
    ) {}

    public function ingest(RagDocument $document, string $absolutePath): array
    {
        $sections = $this->extractor->extract($absolutePath, $document->extension);
        $chunks = $this->extractor->chunk($sections);

        if ($chunks === []) {
            throw new RuntimeException('No readable text could be extracted from the document.');
        }

        $embeddingModel = $this->provider->embeddingModel();
        $embeddingReady = $this->provider->embeddingReady();

        DB::transaction(function () use ($document, $chunks, $embeddingModel, $embeddingReady): void {
            $document->chunks()->delete();

            $chunkIndex = 0;

            foreach (array_chunk($chunks, max(1, (int) config('rag.embeddings.batch_size', 32)), true) as $batch) {
                $embeddings = $embeddingReady ? $this->provider->embed(array_column($batch, 'content')) : [];

                foreach (array_values($batch) as $offset => $chunk) {
                    RagChunk::create([
                        'rag_document_id' => $document->id,
                        'chunk_index' => $chunkIndex++,
                        'locator_type' => $chunk['locator_type'] ?? null,
                        'locator' => $chunk['locator'] ?? null,
                        'content' => $chunk['content'],
                        'content_sha256' => hash('sha256', $chunk['content']),
                        'embedding' => $embeddings[$offset] ?? null,
                        'embedding_model' => $embeddingReady ? $embeddingModel : null,
                    ]);
                }
            }
        });

        return [
            'document_id' => 'rag-document-'.$document->id,
            'chunk_count' => count($chunks),
            'page_or_slide_count' => count($sections),
            'engine' => 'external',
            'embedding_model' => $embeddingReady ? $embeddingModel : null,
            'keyword_only' => ! $embeddingReady,
        ];
    }

    public function ask(string $question, int $topK): array
    {
        if (! $this->provider->embeddingReady()) {
            $sources = $this->keywordSources($question, $topK);

            if ($sources === []) {
                throw new RuntimeException('No ready indexed documents are available for search.');
            }

            $answer = $this->provider->answer($question, $sources);

            return [
                'answer' => $answer['answer'],
                'sources' => $sources,
                'model' => $answer['model'],
                'token_usage' => $answer['token_usage'] ?? null,
            ];
        }

        $queryEmbeddings = collect($this->provider->embed($this->relatedSearchQueries($question)))
            ->filter(fn ($embedding): bool => is_array($embedding) && $embedding !== [])
            ->values()
            ->all();

        if ($queryEmbeddings === []) {
            throw new RuntimeException('Embedding provider returned an empty query vector.');
        }

        $sources = RagChunk::query()
            ->with('document')
            ->whereNotNull('embedding')
            ->whereHas('document', fn ($query) => $query->where('status', RagDocument::STATUS_READY))
            ->latest()
            ->limit((int) config('rag.search_pool_limit', 1000))
            ->get()
            ->map(function (RagChunk $chunk) use ($queryEmbeddings): array {
                return [
                    'score' => collect($queryEmbeddings)
                        ->map(fn (array $embedding): float => $this->cosine($embedding, $chunk->embedding ?? []))
                        ->max() ?? 0.0,
                    'rag_chunk_id' => $chunk->id,
                    'rag_document_id' => $chunk->rag_document_id,
                    'chunk_index' => $chunk->chunk_index,
                    'document' => $chunk->document?->title ?? 'Document',
                    'page' => $chunk->locator_type === 'page' && is_numeric($chunk->locator) ? (int) $chunk->locator : null,
                    'slide' => $chunk->locator_type === 'slide' && is_numeric($chunk->locator) ? (int) $chunk->locator : null,
                    'locator_type' => $chunk->locator_type,
                    'locator' => $chunk->locator,
                    'content' => $chunk->content,
                ];
            })
            ->sortByDesc('score')
            ->unique(fn (array $source): string => implode('|', [
                $source['document'] ?? '',
                $source['locator_type'] ?? '',
                $source['locator'] ?? '',
                hash('sha256', (string) ($source['content'] ?? '')),
            ]))
            ->take($topK)
            ->values()
            ->all();

        $sources = $this->mergeNeighboringChunkSources($sources, $topK);

        if ($sources === []) {
            throw new RuntimeException('No ready indexed documents are available for search.');
        }

        $answer = $this->provider->answer($question, $sources);

        return [
            'answer' => $answer['answer'],
            'sources' => $sources,
            'model' => $answer['model'],
            'token_usage' => $answer['token_usage'] ?? null,
        ];
    }

    public function health(): array
    {
        return $this->provider->health();
    }

    private function keywordSources(string $question, int $topK): array
    {
        $terms = $this->searchTerms($question);
        if ($terms === []) {
            return [];
        }

        $sources = RagChunk::query()
            ->with('document')
            ->whereHas('document', fn ($query) => $query->where('status', RagDocument::STATUS_READY))
            ->latest()
            ->limit((int) config('rag.search_pool_limit', 1000))
            ->get()
            ->map(function (RagChunk $chunk) use ($terms): array {
                $content = Str::lower($chunk->content);
                $title = Str::lower((string) $chunk->document?->title);
                $score = collect($terms)->sum(
                    fn (string $term): int => (Str::contains($content, $term) ? 1 : 0) + (Str::contains($title, $term) ? 3 : 0)
                );

                return [
                    'score' => $score,
                    'rag_chunk_id' => $chunk->id,
                    'rag_document_id' => $chunk->rag_document_id,
                    'chunk_index' => $chunk->chunk_index,
                    'document' => $chunk->document?->title ?? 'Document',
                    'page' => $chunk->locator_type === 'page' && is_numeric($chunk->locator) ? (int) $chunk->locator : null,
                    'slide' => $chunk->locator_type === 'slide' && is_numeric($chunk->locator) ? (int) $chunk->locator : null,
                    'locator_type' => $chunk->locator_type,
                    'locator' => $chunk->locator,
                    'content' => $chunk->content,
                ];
            })
            ->filter(fn (array $source): bool => $source['score'] > 0)
            ->sortByDesc('score')
            ->take($topK)
            ->values()
            ->all();

        return $this->mergeNeighboringChunkSources($sources, $topK);
    }

    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    private function mergeNeighboringChunkSources(array $sources, int $topK): array
    {
        $neighbors = collect($sources)
            ->flatMap(function (array $source): array {
                if (! is_numeric($source['rag_document_id'] ?? null) || ! is_numeric($source['chunk_index'] ?? null)) {
                    return [];
                }

                $chunkIndex = (int) $source['chunk_index'];

                return RagChunk::query()
                    ->with('document')
                    ->where('rag_document_id', (int) $source['rag_document_id'])
                    ->whereBetween('chunk_index', [max(0, $chunkIndex - 1), $chunkIndex + 1])
                    ->orderBy('chunk_index')
                    ->get()
                    ->map(fn (RagChunk $chunk): array => [
                        'rag_chunk_id' => $chunk->id,
                        'rag_document_id' => $chunk->rag_document_id,
                        'chunk_index' => $chunk->chunk_index,
                        'document' => $chunk->document?->title ?? 'Document',
                        'page' => $chunk->locator_type === 'page' && is_numeric($chunk->locator) ? (int) $chunk->locator : null,
                        'slide' => $chunk->locator_type === 'slide' && is_numeric($chunk->locator) ? (int) $chunk->locator : null,
                        'locator_type' => $chunk->locator_type,
                        'locator' => $chunk->locator,
                        'content' => $chunk->content,
                    ])
                    ->all();
            });

        return collect($sources)
            ->merge($neighbors)
            ->unique(fn (array $source): string => implode('|', [
                $source['rag_document_id'] ?? '',
                $source['locator_type'] ?? '',
                $source['locator'] ?? '',
                hash('sha256', (string) ($source['content'] ?? '')),
            ]))
            ->take(max($topK, min(12, $topK * 2)))
            ->values()
            ->all();
    }

    private function relatedSearchQueries(string $question): array
    {
        $terms = $this->searchTerms($question);
        $queries = [trim($question)];

        $topic = collect($terms)->take(5)->implode(' ');
        if ($topic !== '' && ! Str::contains(Str::lower($question), Str::lower($topic))) {
            $queries[] = $topic;
        }

        foreach (collect($terms)->sliding(2) as $pair) {
            $concept = implode(' ', $pair->all());
            if ($concept !== '') {
                $queries[] = $concept;
            }
        }

        if (Str::contains(Str::lower($question), ['key message', 'key messages', 'main message', 'takeaway', 'takeaways'])) {
            $topic = collect($terms)->take(5)->implode(' ');
            if ($topic !== '') {
                array_push($queries, "{$topic} summary", "{$topic} key facts", "{$topic} take home");
            }
        }

        array_push($queries, ...collect($terms)->take(6)->all());
        array_push($queries, ...$this->inferredScopeQueries($terms));
        array_push($queries, ...$this->terminologyQueries($terms));

        return collect($queries)
            ->map(fn (string $query): string => Str::limit(trim($query), 180, ''))
            ->filter()
            ->unique(fn (string $query): string => Str::lower($query))
            ->take(24)
            ->values()
            ->all();
    }

    private function terminologyQueries(array $terms): array
    {
        $topic = collect($terms)->take(4)->implode(' ');
        if ($topic === '') {
            return [];
        }

        return collect([
            "{$topic} synonyms",
            "{$topic} abbreviations",
            "{$topic} related terminology",
            "{$topic} parent topic",
            "{$topic} child topic",
            "{$topic} module",
            "{$topic} topic",
            "{$topic} guideline",
            "{$topic} protocol",
            "{$topic} procedure",
        ])->values()->all();
    }

    private function inferredScopeQueries(array $terms): array
    {
        $topic = collect($terms)->take(4)->implode(' ');
        if ($topic === '') {
            return [];
        }

        return collect([
            'overview',
            'definition',
            'assessment',
            'management',
            'steps',
            'recommendations',
            'complications',
            'monitoring',
            'follow up',
        ])
            ->map(fn (string $scope): string => "{$topic} {$scope}")
            ->values()
            ->all();
    }

    private function searchTerms(string $question): array
    {
        $stopWords = [
            'about', 'all', 'and', 'are', 'can', 'does', 'for', 'from', 'give', 'how',
            'bring', 'describe', 'display', 'list', 'manual', 'me', 'open', 'of', 'pick',
            'please', 'pull', 'select', 'show', 'view',
            'more', 'summarize', 'summary', 'tell', 'the', 'this', 'to', 'what', 'which',
            'with', 'you',
        ];

        preg_match_all('/\b(?:[a-z][a-z0-9_-]{2,}|\d{1,3})\b/i', Str::lower($question), $matches);

        return collect($matches[0] ?? [])
            ->reject(fn (string $term): bool => in_array($term, $stopWords, true))
            ->map(fn (string $term): string => Str::endsWith($term, 's') && strlen($term) > 4 ? substr($term, 0, -1) : $term)
            ->unique()
            ->take(18)
            ->values()
            ->all();
    }
}
