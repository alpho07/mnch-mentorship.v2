<?php

namespace App\Services\Rag;

use App\Models\RagDocument;
use App\Models\RagDocumentOutline;
use App\Models\RagTermBridge;
use App\Support\RagSourceFormatter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RagClient
{
    public function __construct(
        private readonly InAppRagEngine $engine,
        private readonly ExternalAiProvider $provider,
        private readonly DocumentTextExtractor $extractor,
    ) {}

    public function ingest(string $absolutePath, string $title): array
    {
        if ($this->usesInAppEngine()) {
            throw new RuntimeException('Use InAppRagEngine::ingest for external RAG document ingestion.');
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException('Document file is not readable.');
        }

        $response = $this->request((int) config('rag.ingest_timeout'))
            ->attach('file', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post('/ingest', [
                'title' => $this->sanitizeTitle($title),
            ]);

        return $this->decodeResponse($response->throw()->json());
    }

    public function ask(string $question, int $topK): array
    {
        $started = hrtime(true);

        try {
            if ($this->usesHybridEngine() && $cached = $this->cachedAnswer($question, $started)) {
                return $cached;
            }

            if ($this->usesInAppEngine()) {
                $data = $this->engine->ask($question, $this->clampTopK($topK));
                $answer = $this->stripThink((string) ($data['answer'] ?? ''));

                if ($answer === '') {
                    throw new RuntimeException('RAG service returned an empty answer.');
                }

                return [
                    'answer' => $answer,
                    'citations' => $this->normalizeSources($data['sources'] ?? $data['citations'] ?? []),
                    'retrieved_sources' => $this->normalizeSources($data['sources'] ?? []),
                    'model' => isset($data['model']) ? (string) $data['model'] : null,
                    'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                    'token_usage' => $this->usageMetadata($data),
                ];
            }

            if ($this->usesHybridEngine()) {
                $response = $this->askHybrid($question, $topK, $started);
                $this->storeCachedAnswer($question, $response);

                return $response;
            }

            $payload = [
                'question' => $question,
                'top_k' => $this->clampTopK($topK),
            ];

            $response = $this->request((int) config('rag.request_timeout'))
                ->post('/ask', $payload);

            $data = $this->decodeResponse($response->throw()->json());
            $answer = $this->stripThink((string) ($data['answer'] ?? ''));

            if ($answer === '') {
                throw new RuntimeException('RAG service returned an empty answer.');
            }

            return [
                'answer' => $answer,
                'citations' => $this->normalizeSources($data['sources'] ?? $data['citations'] ?? []),
                'retrieved_sources' => $this->normalizeSources($data['sources'] ?? []),
                'model' => isset($data['model']) ? (string) $data['model'] : null,
                'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'token_usage' => $this->usageMetadata($data),
            ];
        } catch (ConnectionException|RequestException|RuntimeException $e) {
            Log::warning('RAG ask failed', [
                'error' => $this->sanitizeError($e->getMessage()),
                'top_k' => $this->clampTopK($topK),
            ]);

            throw new RuntimeException($this->sanitizeError($e->getMessage()), previous: $e);
        }
    }

    public function askStream(string $question, int $topK, callable $onDelta): array
    {
        $started = hrtime(true);

        if (! $this->usesHybridEngine()) {
            $response = $this->ask($question, $topK);
            $onDelta((string) ($response['answer'] ?? ''));

            return $response;
        }

        try {
            if ($cached = $this->cachedAnswer($question, $started)) {
                $onDelta((string) ($cached['answer'] ?? ''));

                return $cached;
            }

            $retrievalStarted = hrtime(true);
            $profile = $this->retrievalProfile($question, $topK);
            $clampedTopK = $profile['top_k'];
            $searches = [];
            $primaryQueries = $this->plannedSearchQueries($question, $profile['query_limit'], (bool) $profile['use_query_planner']);
            $sources = $this->runHybridSearches($primaryQueries, $clampedTopK, $searches);
            $visualMode = $this->visualRequestMode($question);

            if ($visualMode === 'present' && $this->hasMediaSource($sources)) {
                $sources = $this->prioritizeMediaSources($sources);
                $answer = $this->visualAnswer($sources);
                $onDelta($answer);

                return [
                    'answer' => $answer,
                    'citations' => $sources,
                    'retrieved_sources' => $sources,
                    'model' => 'local-media',
                    'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                    'token_usage' => $this->responseMetadata(null, $searches, $this->retrievalTrace($profile, $primaryQueries, [], $searches, $sources, $retrievalStarted)),
                ];
            }

            $fallbackQueries = [];
            if (! $this->hasUsefulHybridSources($sources)) {
                $fallbackQueries = $this->fallbackSearchQueries($question, $primaryQueries, $profile['fallback_limit']);
                $sources = collect($sources)
                    ->merge($this->runHybridSearches($fallbackQueries, $clampedTopK, $searches))
                    ->unique(fn (array $source): string => $this->sourceKey($source))
                    ->take(max($clampedTopK, min(12, $clampedTopK * 2)))
                    ->values()
                    ->all();
            }

            if (! $this->hasUsefulHybridSources($sources) || $this->shouldTryCurriculumLookup($question)) {
                $sources = $this->mergeCurriculumSources($this->curriculumLookupQuestion($question, $primaryQueries), $sources, $clampedTopK);
                $sources = $this->mergeOutlineSources($question, $sources, $clampedTopK);
            }

            if (! $this->hasUsefulHybridSources($sources) || $this->shouldTryStoredDocumentLookup($question)) {
                $sources = $this->mergeStoredDocumentSources($question, $sources, $clampedTopK);
            }

            $sources = $this->prioritizeContentSources($sources, $clampedTopK, $question, (bool) $visualMode);
            $trace = $this->retrievalTrace($profile, $primaryQueries, $fallbackQueries, $searches, $sources, $retrievalStarted);

            if ($sources === []) {
                throw new RuntimeException('No relevant indexed documents are available for search.');
            }

            if ($visualMode && $this->hasMediaSource($sources)) {
                $sources = $this->prioritizeMediaSources($sources);
            }

            if ($curriculumAnswer = $this->curriculumAnswer($question, $sources)) {
                $onDelta($curriculumAnswer);

                $response = [
                    'answer' => $curriculumAnswer,
                    'citations' => $sources,
                    'retrieved_sources' => $sources,
                    'model' => 'local-curriculum',
                    'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                    'token_usage' => $this->responseMetadata(null, $searches, $trace),
                ];
                $this->storeCachedAnswer($question, $response);

                return $response;
            }

            $answer = $this->provider->answerStream($question, $sources, $onDelta);

            $response = [
                'answer' => $this->stripThink($answer['answer']),
                'citations' => $sources,
                'retrieved_sources' => $sources,
                'model' => $answer['model'],
                'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'token_usage' => $this->responseMetadata($answer['token_usage'] ?? null, $searches, $trace),
            ];
            $this->storeCachedAnswer($question, $response);

            return $response;
        } catch (ConnectionException|RequestException|RuntimeException $e) {
            Log::warning('RAG streaming ask failed', [
                'error' => $this->sanitizeError($e->getMessage()),
                'top_k' => $this->clampTopK($topK),
            ]);

            throw new RuntimeException($this->sanitizeError($e->getMessage()), previous: $e);
        }
    }

    public function health(): array
    {
        if ($this->usesInAppEngine()) {
            return $this->engine->health();
        }

        if ($this->usesHybridEngine()) {
            $local = $this->localHealth();

            return [
                'ok' => (bool) ($local['ok'] ?? false) && $this->provider->chatReady(),
                'status' => $local['status'] ?? null,
                'body' => [
                    'engine' => 'hybrid',
                    'local' => $local['body'] ?? null,
                    'chat_provider' => config('rag.chat.provider'),
                    'chat_model' => $this->provider->chatModel(),
                    'local_embeddings' => true,
                ],
                'error' => $local['error'] ?? null,
            ];
        }

        return $this->localHealth();
    }

    public function delete(?string $externalDocumentId): bool
    {
        if ($this->usesInAppEngine()) {
            return true;
        }

        $endpoint = config('rag.delete_endpoint');

        if (blank($endpoint) || blank($externalDocumentId)) {
            return true;
        }

        try {
            $response = $this->request((int) config('rag.request_timeout'))
                ->post($endpoint, ['document_id' => $externalDocumentId]);

            return $response->successful();
        } catch (ConnectionException|RuntimeException $e) {
            Log::warning('RAG remote delete failed safely', [
                'document_id' => Str::limit((string) $externalDocumentId, 80, ''),
                'error' => $this->sanitizeError($e->getMessage()),
            ]);

            return false;
        }
    }

    public function stripThink(string $value): string
    {
        return trim((string) preg_replace('/<think\b[^>]*>.*?<\/think>/is', '', $value));
    }

    public function clampTopK(int $topK): int
    {
        $min = (int) config('rag.top_k.min', 1);
        $max = (int) config('rag.top_k.max', 10);

        return max($min, min($max, $topK));
    }

    public function normalizeSources(mixed $sources): array
    {
        if (! is_array($sources)) {
            return [];
        }

        return collect($sources)
            ->filter(fn ($source) => is_array($source))
            ->map(function (array $source): array {
                $locator = $source['locator'] ?? $source['page'] ?? $source['slide'] ?? null;
                $locatorType = isset($source['locator_type']) ? (string) $source['locator_type'] : null;
                $page = $source['page'] ?? ($locatorType === 'page' ? $locator : null);
                $slide = $source['slide'] ?? ($locatorType === 'slide' ? $locator : null);

                return [
                    'document' => Str::limit(strip_tags((string) ($source['document'] ?? $source['title'] ?? 'Document')), 255, ''),
                    'document_id' => filled($source['document_id'] ?? null) ? (string) $source['document_id'] : null,
                    'page' => is_numeric($page) ? (int) $page : null,
                    'slide' => is_numeric($slide) ? (int) $slide : null,
                    'locator_type' => $locatorType ?: ($source['slide'] ?? null ? 'slide' : 'page'),
                    'locator' => is_numeric($locator) ? (int) $locator : (filled($locator) ? (string) $locator : null),
                    'content' => filled($source['content'] ?? null) ? Str::limit(RagSourceFormatter::plain((string) $source['content']), 5000) : null,
                    'media' => $this->normalizeMedia($source['media'] ?? [], (string) ($source['document_id'] ?? '')),
                    'retrieval_rank' => is_numeric($source['source'] ?? null) ? (int) $source['source'] : null,
                    'distance' => is_numeric($source['distance'] ?? null) ? (float) $source['distance'] : null,
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeMedia(mixed $media, string $externalDocumentId): array
    {
        if (! is_array($media) || ! Str::isUuid($externalDocumentId)) {
            return [];
        }

        return collect($media)
            ->filter(fn ($item) => is_array($item) && filled($item['filename'] ?? null))
            ->map(function (array $item) use ($externalDocumentId): array {
                $filename = basename((string) $item['filename']);

                return [
                    'filename' => $filename,
                    'content_type' => (string) ($item['content_type'] ?? 'application/octet-stream'),
                    'alt' => Str::limit(strip_tags((string) ($item['alt'] ?? 'Slide image')), 255, ''),
                    'url' => route('rag.media.show', [
                        'externalDocumentId' => $externalDocumentId,
                        'filename' => $filename,
                    ]),
                ];
            })
            ->values()
            ->all();
    }

    private function mergeOutlineSources(string $question, array $sources, int $topK): array
    {
        try {
            $outlineSources = $this->outlineSources($question, min(3, max(1, $topK)));
        } catch (\Throwable $e) {
            Log::debug('RAG outline enrichment skipped', [
                'error' => $this->sanitizeError($e->getMessage()),
            ]);

            return $sources;
        }

        if ($outlineSources === []) {
            return $sources;
        }

        return collect($sources)
            ->merge($outlineSources)
            ->unique(fn (array $source): string => implode('|', [
                $source['document'] ?? '',
                $source['locator_type'] ?? '',
                $source['locator'] ?? '',
                $source['content'] ?? '',
            ]))
            ->sortByDesc(fn (array $source): int => $this->sourceCompletenessScore($source))
            ->take(max($topK, min(10, count($sources) + count($outlineSources))))
            ->values()
            ->all();
    }

    private function outlineSources(string $question, int $limit): array
    {
        if (! $this->isOutlineUsefulQuestion($question)) {
            return [];
        }

        $terms = $this->searchTerms($question);
        if ($terms === []) {
            return [];
        }

        return RagDocumentOutline::query()
            ->with('document:id,title,status')
            ->whereHas('document', fn ($query) => $query->where('status', RagDocument::STATUS_READY))
            ->get()
            ->map(function (RagDocumentOutline $outline) use ($terms, $question): array {
                $normalizedQuestion = Str::lower($question);
                $title = Str::lower((string) $outline->title);
                $haystack = Str::lower(implode(' ', [
                    $outline->document?->title,
                    $outline->title,
                    $outline->content,
                ]));

                $score = collect($terms)
                    ->sum(fn (string $term): int => (Str::contains($haystack, $term) ? 1 : 0) + (Str::contains($title, $term) ? 2 : 0));

                if (Str::contains($haystack, ['table of content', 'contents', 'module', 'modules', 'key topic'])) {
                    $score += 2;
                }

                if (Str::contains($normalizedQuestion, ['module', 'modules']) && in_array($outline->type, ['module', 'topic'], true)) {
                    $score += 4;
                }

                if (Str::contains($normalizedQuestion, ['module', 'modules']) && $outline->type === 'contents') {
                    $score -= 1;
                }

                return [
                    'score' => $score,
                    'document' => $outline->document?->title ?? 'Document',
                    'page' => $outline->locator_type === 'page' && is_numeric($outline->locator) ? (int) $outline->locator : null,
                    'slide' => $outline->locator_type === 'slide' && is_numeric($outline->locator) ? (int) $outline->locator : null,
                    'locator_type' => $outline->locator_type,
                    'locator' => $outline->locator,
                    'content' => trim(implode("\n", array_filter([
                        "Document outline: {$outline->title}",
                        $outline->content,
                    ]))),
                ];
            })
            ->filter(fn (array $source): bool => $source['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $source): array => collect($source)->except('score')->all())
            ->values()
            ->all();
    }

    private function mergeStoredDocumentSources(string $question, array $sources, int $topK): array
    {
        $terms = $this->searchTerms($question);
        if ($terms === []) {
            return $sources;
        }

        try {
            $documentHints = collect($sources)
                ->pluck('document')
                ->filter()
                ->map(fn (string $title): string => Str::lower($title))
                ->values();

            $matches = RagDocument::query()
                ->where('status', RagDocument::STATUS_READY)
                ->get()
                ->map(function (RagDocument $document) use ($terms, $documentHints, $question): array {
                    $title = Str::lower($document->title);
                    $titleScore = collect($terms)->sum(fn (string $term): int => Str::contains($title, $term) ? 3 : 0);
                    $titleScore += $this->documentTitleIntentBoost($question, $title);

                    if ($documentHints->contains(fn (string $hint): bool => $hint === $title || Str::contains($hint, $title) || Str::contains($title, $hint))) {
                        $titleScore += 8;
                    }

                    return ['document' => $document, 'score' => $titleScore];
                })
                ->filter(fn (array $match): bool => $match['score'] > 0)
                ->sortByDesc('score')
                ->take(3)
                ->values();
        } catch (\Throwable $e) {
            Log::debug('RAG stored document enrichment skipped', [
                'error' => $this->sanitizeError($e->getMessage()),
            ]);

            return $sources;
        }

        if ($matches->isEmpty()) {
            return $sources;
        }

        $storedSources = [];

        foreach ($matches as $match) {
            /** @var RagDocument $document */
            $document = $match['document'];

            if (! $document->fileExists()) {
                continue;
            }

            try {
                $sections = $this->extractor->chunk(
                    $this->extractor->extract(Storage::disk($document->disk)->path($document->path), $document->extension)
                );
            } catch (\Throwable $e) {
                Log::debug('RAG stored document fallback extraction failed', [
                    'document_id' => $document->id,
                    'error' => $this->sanitizeError($e->getMessage()),
                ]);

                continue;
            }

            $allowTitleMatchedSection = $match['score'] >= 12 || count($terms) <= 3;

            $sectionSources = collect($sections)
                ->map(function (array $section) use ($document, $terms, $match, $question): array {
                    $content = RagSourceFormatter::plain((string) ($section['content'] ?? ''));
                    $haystack = Str::lower(str_replace(['-', '‑'], '', $content));
                    $contentScore = collect($terms)->sum(fn (string $term): int => Str::contains($haystack, $term) ? 1 : 0);
                    $contentScore += $this->sectionIntentBoost($question, $haystack);
                    $content = trim("Document: {$document->title}\n\n{$content}");

                    return [
                        'score' => $match['score'] + $contentScore,
                        'content_score' => $contentScore,
                        'retrieval_score' => $match['score'] + $contentScore,
                        'source_origin' => 'stored',
                        'document' => $document->title,
                        'page' => ($section['locator_type'] ?? null) === 'page' && is_numeric($section['locator'] ?? null) ? (int) $section['locator'] : null,
                        'slide' => ($section['locator_type'] ?? null) === 'slide' && is_numeric($section['locator'] ?? null) ? (int) $section['locator'] : null,
                        'locator_type' => $section['locator_type'] ?? 'document',
                        'locator' => $section['locator'] ?? null,
                        'content' => $content,
                    ];
                })
                ->filter(fn (array $source): bool => $source['content_score'] > 0
                    || ($allowTitleMatchedSection && mb_strlen(RagSourceFormatter::plain((string) ($source['content'] ?? ''))) >= 80))
                ->sortByDesc('score')
                ->take(max(1, $topK))
                ->map(fn (array $source): array => collect($source)->except(['score', 'content_score'])->all())
                ->values()
                ->all();

            array_push($storedSources, ...$sectionSources);
        }

        if ($storedSources === []) {
            return $sources;
        }

        return collect($storedSources)
            ->merge($sources)
            ->unique(fn (array $source): string => implode('|', [
                $source['document'] ?? '',
                $source['locator_type'] ?? '',
                $source['locator'] ?? '',
                $source['content'] ?? '',
            ]))
            ->sortByDesc(fn (array $source): int => $this->sourceCompletenessScore($source))
            ->take(max($topK, min(10, count($sources) + count($storedSources))))
            ->map(fn (array $source): array => collect($source)->except(['source_origin', 'retrieval_score'])->all())
            ->values()
            ->all();
    }

    private function mergeCurriculumSources(string $question, array $sources, int $topK): array
    {
        $terms = $this->isCurriculumScheduleQuestion($question)
            ? $this->scheduleTopicTerms($question)
            : $this->searchTerms($question);
        if ($terms === []) {
            return $sources;
        }

        $path = database_path('seeders/data/mentorship_curriculum_2025_10_13.php');
        if (! is_file($path)) {
            return $sources;
        }

        $curriculum = require $path;
        if (! is_array($curriculum)) {
            return $sources;
        }

        $curriculumSources = collect($curriculum)
            ->flatMap(fn (array $modules, string $program): array => collect($modules)
                ->map(function (array $module) use ($program, $terms): ?array {
                    $title = (string) ($module['module'] ?? '');
                    $sessions = collect($module['sessions'] ?? [])
                        ->filter(fn ($session): bool => is_array($session))
                        ->values();

                    $haystack = Str::lower(implode(' ', array_filter([
                        $program,
                        $title,
                        $sessions->pluck('session')->implode(' '),
                        $sessions->pluck('methodology')->implode(' '),
                    ])));

                    $score = collect($terms)->sum(fn (string $term): int => Str::contains($haystack, $term) ? 1 : 0);
                    if ($score <= 0) {
                        return null;
                    }

                    $sessionLines = $sessions
                        ->map(fn (array $session): string => '- '.$session['session'].' ('.$session['methodology'].', '.$session['time_minutes'].' minutes)')
                        ->implode("\n");

                    return [
                        'score' => $score,
                        'source_origin' => 'curriculum',
                        'document' => Str::headline($program).' Mentorship curriculum',
                        'page' => null,
                        'slide' => null,
                        'locator_type' => 'module',
                        'locator' => $title,
                        'content' => trim("{$title}\n\nThis curriculum module covers:\n{$sessionLines}"),
                    ];
                })
                ->filter()
                ->all())
            ->sortByDesc('score')
            ->take(max(1, $topK))
            ->map(fn (array $source): array => collect($source)->except('score')->all())
            ->values()
            ->all();

        if ($curriculumSources === []) {
            return $sources;
        }

        return collect($curriculumSources)
            ->merge($sources)
            ->unique(fn (array $source): string => $this->sourceKey($source))
            ->sortByDesc(fn (array $source): int => $this->sourceCompletenessScore($source))
            ->take(max($topK, min(10, count($sources) + count($curriculumSources))))
            ->values()
            ->all();
    }

    private function documentTitleIntentBoost(string $question, string $title): int
    {
        $normalizedQuestion = Str::lower(str_replace(['-', '‑'], ' ', $question));
        $normalizedTitle = Str::lower(str_replace(['-', '‑'], ' ', $title));
        $score = 0;

        $questionTerms = collect($this->searchTerms($question));
        $contentTerms = $questionTerms
            ->reject(fn (string $term): bool => is_numeric($term))
            ->values();

        if ($contentTerms->isNotEmpty() && $contentTerms->every(fn (string $term): bool => Str::contains($normalizedTitle, $term))) {
            $score += 12;
        }

        foreach ($questionTerms->filter(fn (string $term): bool => is_numeric($term)) as $number) {
            if (Str::contains($normalizedTitle, ["module {$number}", "module{$number}", " {$number}.", " {$number}:"])) {
                $score += 12;
            }
        }

        if (Str::contains($normalizedQuestion, 'preterms') && Str::contains($normalizedTitle, 'preterms')) {
            $score += 6;
        }

        return $score;
    }

    private function sourceCompletenessScore(array $source, array $terms = [], bool $preferMedia = false): int
    {
        $content = RagSourceFormatter::plain((string) ($source['content'] ?? ''));
        $score = min(50, intdiv(mb_strlen($content), 80));
        $haystack = Str::lower(implode(' ', [
            $source['document'] ?? '',
            $source['locator_type'] ?? '',
            $source['locator'] ?? '',
            $content,
        ]));

        if ($terms !== []) {
            $score += collect($terms)->sum(function (string $term) use ($haystack, $source): int {
                $title = Str::lower((string) ($source['document'] ?? ''));

                return (Str::contains($haystack, $term) ? 10 : 0)
                    + (Str::contains($title, $term) ? 8 : 0);
            });
        }

        if (($source['source_origin'] ?? null) === 'stored') {
            $score += 30;
        }

        if (Str::startsWith($content, 'Document:')) {
            $score += 45;
        }

        if (($source['source_origin'] ?? null) === 'curriculum') {
            $score += 35;
        }

        if (is_numeric($source['retrieval_score'] ?? null)) {
            $score += min(60, (int) $source['retrieval_score'] * 2);
        }

        if (filled($source['locator'] ?? null)) {
            $score += 5;
        }

        if ($preferMedia && ! empty($source['media'] ?? [])) {
            $score += 80 + (count($source['media']) * 10);
        }

        if (is_numeric($source['retrieval_rank'] ?? null)) {
            $score += max(0, 12 - (int) $source['retrieval_rank']);
        }

        if (Str::startsWith($content, 'Document outline:')) {
            $score -= 20;
        }

        if ($this->isModuleTitleOnlySource($source)) {
            $score -= 40;
        }

        return $score;
    }

    private function prioritizeContentSources(array $sources, int $topK, string $question = '', bool $preferMedia = false): array
    {
        $terms = $question !== '' ? $this->searchTerms($question) : [];

        return collect($sources)
            ->unique(fn (array $source): string => $this->sourceKey($source))
            ->sortByDesc(fn (array $source): int => $this->sourceCompletenessScore($source, $terms, $preferMedia))
            ->take(max($topK, min(10, count($sources))))
            ->values()
            ->all();
    }

    private function curriculumAnswer(string $question, array $sources): ?string
    {
        $normalizedQuestion = Str::lower($question);

        if ($scheduleAnswer = $this->curriculumScheduleAnswer($question, $sources)) {
            return $scheduleAnswer;
        }

        $isOxygenTherapyQuestion = Str::contains($normalizedQuestion, ['oxygen therapy'])
            || (Str::contains($normalizedQuestion, 'oxygen') && Str::contains($normalizedQuestion, 'therapy'));
        $isHypothermiaQuestion = Str::contains($normalizedQuestion, 'hypothermia');

        if (! $isOxygenTherapyQuestion && ! $isHypothermiaQuestion) {
            return null;
        }

        if (! Str::contains($normalizedQuestion, [
            'what is',
            'what does',
            'how about',
            'tell me',
            'more about',
            'explain',
            'describe',
            'definition',
            'meaning',
        ])) {
            return null;
        }

        $topicTerms = $isHypothermiaQuestion
            ? ['hypothermia', 'thermoregulation']
            : ['oxygen'];

        $hasUsefulRetrievedContent = collect($sources)->contains(function (array $source) use ($topicTerms): bool {
            if (($source['source_origin'] ?? null) === 'curriculum') {
                return false;
            }

            $content = RagSourceFormatter::plain((string) ($source['content'] ?? ''));
            $normalized = Str::lower($content);

            return mb_strlen($content) >= 60
                && ! $this->isModuleTitleOnlySource($source)
                && Str::contains($normalized, $topicTerms);
        });

        if ($hasUsefulRetrievedContent) {
            return null;
        }

        if ($isOxygenTherapyQuestion) {
            $source = collect($sources)->first(function (array $source): bool {
                if (($source['source_origin'] ?? null) !== 'curriculum') {
                    return false;
                }

                $content = Str::lower(RagSourceFormatter::plain((string) ($source['content'] ?? '')));

                return Str::contains($content, 'oxygen therapy')
                    && Str::contains($content, ['indications and safe use of oxygen', 'pulse oximetry']);
            });

            if (! $source) {
                return null;
            }

            return 'Oxygen therapy is the safe use of supplemental oxygen when it is indicated. In this curriculum, Module 4 covers indications and safe oxygen use, pulse oximetry, oxygen delivery devices, prescribing, and monitoring [1].';
        }

        $source = collect($sources)->first(function (array $source): bool {
            if (($source['source_origin'] ?? null) !== 'curriculum') {
                return false;
            }

            $content = Str::lower(RagSourceFormatter::plain((string) ($source['content'] ?? '')));

            return Str::contains($content, 'neonatal thermoregulation')
                && Str::contains($content, ['radiant warmer', 'incubator']);
        });

        if (! $source) {
            return null;
        }

        return 'Hypothermia means the newborn is too cold, so care focuses on keeping the baby warm and monitoring temperature. In this curriculum, it is covered under Module 5: Neonatal Thermoregulation, including neonatal thermoregulation, use of a radiant warmer, and use of an incubator and settings [1].';
    }

    private function curriculumScheduleAnswer(string $question, array $sources): ?string
    {
        if (! $this->isCurriculumScheduleQuestion($question)) {
            return null;
        }

        $direct = $this->curriculumScheduleSource($question);
        if ($direct) {
            return $this->formatCurriculumScheduleAnswer($direct['content'], $direct['source']);
        }

        $terms = $this->scheduleTopicTerms($question);
        $source = collect($sources)
            ->filter(fn (array $source): bool => ($source['source_origin'] ?? null) === 'curriculum')
            ->map(function (array $source) use ($terms): array {
                $content = RagSourceFormatter::plain((string) ($source['content'] ?? ''));
                $haystack = Str::lower($content.' '.($source['locator'] ?? '').' '.($source['document'] ?? ''));
                $score = collect($terms)->sum(fn (string $term): int => Str::contains($haystack, $term) ? 1 : 0);

                return ['score' => $score, 'source' => $source, 'content' => $content];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] > 0)
            ->sortByDesc('score')
            ->first();

        if (! $source) {
            return null;
        }

        return $this->formatCurriculumScheduleAnswer($source['content'], $source['source']);
    }

    private function curriculumScheduleSource(string $question): ?array
    {
        $terms = $this->scheduleTopicTerms($question);
        if ($terms === []) {
            return null;
        }

        $path = database_path('seeders/data/mentorship_curriculum_2025_10_13.php');
        if (! is_file($path)) {
            return null;
        }

        $curriculum = require $path;
        if (! is_array($curriculum)) {
            return null;
        }

        return collect($curriculum)
            ->flatMap(fn (array $modules, string $program): array => collect($modules)
                ->map(function (array $module) use ($program, $terms): ?array {
                    $title = (string) ($module['module'] ?? '');
                    $sessions = collect($module['sessions'] ?? [])->filter(fn ($session): bool => is_array($session))->values();
                    $haystack = Str::lower(implode(' ', array_filter([
                        $program,
                        $title,
                        $sessions->pluck('session')->implode(' '),
                        $sessions->pluck('methodology')->implode(' '),
                    ])));
                    $score = collect($terms)->sum(fn (string $term): int => Str::contains($haystack, $term) ? 1 : 0);

                    if ($score <= 0) {
                        return null;
                    }

                    $sessionLines = $sessions
                        ->map(fn (array $session): string => '- '.$session['session'].' ('.$session['methodology'].', '.$session['time_minutes'].' minutes)')
                        ->implode("\n");
                    $content = trim("{$title}\n\nThis curriculum module covers:\n{$sessionLines}");

                    return [
                        'score' => $score,
                        'source' => [
                            'source_origin' => 'curriculum',
                            'document' => Str::headline($program).' Mentorship curriculum',
                            'locator_type' => 'module',
                            'locator' => $title,
                            'content' => $content,
                        ],
                        'content' => $content,
                    ];
                })
                ->filter()
                ->all())
            ->sortByDesc('score')
            ->first();
    }

    private function formatCurriculumScheduleAnswer(string $content, array $source): ?string
    {
        preg_match_all('/^-\s*(.+?)\s*\((.+?),\s*(\d+)\s*minutes?\)$/mi', $content, $matches, PREG_SET_ORDER);
        if ($matches === []) {
            return null;
        }

        $moduleTitle = trim(Str::before($content, "\n"));
        if ($moduleTitle === '') {
            $moduleTitle = (string) ($source['locator'] ?? 'This module');
        }

        $totalMinutes = collect($matches)->sum(fn (array $match): int => (int) $match[3]);
        $lines = collect($matches)
            ->map(fn (array $match, int $index): string => ($index + 1).'. '.trim($match[1]).' - '.((int) $match[3]).' minutes ('.trim($match[2]).')')
            ->implode("\n");

        return "{$moduleTitle} takes {$totalMinutes} minutes total ({$this->minutesText($totalMinutes)}) [1].\n\nSession breakdown:\n{$lines}";
    }

    private function scheduleTopicTerms(string $question): array
    {
        $scheduleTerms = [
            'module', 'modules', 'session', 'sessions', 'should', 'take', 'takes',
            'long', 'duration', 'breakdown', 'minute', 'minutes', 'time',
        ];

        return collect($this->searchTerms($question))
            ->reject(fn (string $term): bool => in_array($term, $scheduleTerms, true))
            ->values()
            ->all();
    }

    private function isCurriculumScheduleQuestion(string $question): bool
    {
        $normalized = Str::lower($question);

        return Str::contains($normalized, ['module', 'session', 'sessions'])
            && Str::contains($normalized, ['how long', 'duration', 'breakdown', 'session breakdown', 'take', 'takes', 'minutes', 'time']);
    }

    private function shouldTryCurriculumLookup(string $question): bool
    {
        return $this->isCurriculumScheduleQuestion($question);
    }

    private function minutesText(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        $hourText = $hours === 1 ? '1 hour' : "{$hours} hours";

        return $remainingMinutes > 0
            ? "{$hourText} {$remainingMinutes} minutes"
            : $hourText;
    }

    private function sectionIntentBoost(string $question, string $haystack): int
    {
        $normalized = Str::lower($question);
        $score = 0;

        if (Str::contains($normalized, ['key message', 'key messages', 'main message', 'takeaway', 'takeaways'])) {
            if (Str::contains($haystack, ['summary', 'key fact', 'key facts', 'conclusion', 'remember', 'take home'])) {
                $score += 5;
            }
        }

        if (Str::contains($normalized, ['module']) && Str::contains($haystack, ['summary', 'introduction', 'objectives'])) {
            $score += 1;
        }

        return $score;
    }

    private function isOutlineUsefulQuestion(string $question): bool
    {
        return Str::contains(Str::lower($question), [
            'summarize',
            'summary',
            'overview',
            'module',
            'modules',
            'topic',
            'topics',
            'table of content',
            'contents',
            'list',
            'show me',
            'what are',
            'which',
            'manual',
        ]);
    }

    private function searchTerms(string $question): array
    {
        $stopWords = [
            'about', 'all', 'and', 'are', 'can', 'does', 'for', 'from', 'give', 'how',
            'if', 'some', 'thing', 'things',
            'bring', 'describe', 'display', 'list', 'manual', 'me', 'open', 'of', 'pick',
            'please', 'pull', 'select', 'show', 'view', 'want', 'full',
            'more', 'summarize', 'summary', 'tell', 'the', 'this', 'to', 'what', 'which',
            'with', 'you',
        ];

        preg_match_all('/\b(?:[a-z][a-z0-9_-]{2,}|\d{1,3})\b/i', Str::lower($question), $matches);

        return collect($matches[0] ?? [])
            ->reject(fn (string $term): bool => in_array($term, $stopWords, true))
            ->map(fn (string $term): string => Str::endsWith($term, 's') && strlen($term) > 4 && ! in_array($term, ['sepsis'], true) ? substr($term, 0, -1) : $term)
            ->unique()
            ->take(18)
            ->values()
            ->all();
    }

    private function shouldTryStoredDocumentLookup(string $question): bool
    {
        $terms = $this->searchTerms($question);

        if ($terms === [] || count($terms) > 4) {
            return false;
        }

        $normalizedQuestion = Str::lower($question);
        $nonLocalExtensions = ['docx', 'xlsx', 'csv', 'txt', 'md', 'markdown', 'html', 'htm', 'json'];

        if (Str::contains($normalizedQuestion, ['docx', 'file'])) {
            return true;
        }

        try {
            return RagDocument::query()
                ->where('status', RagDocument::STATUS_READY)
                ->whereIn('extension', $nonLocalExtensions)
                ->where(function ($query) use ($terms): void {
                    foreach ($terms as $term) {
                        $query->orWhere('title', 'like', "%{$term}%");
                    }
                })
                ->exists();
        } catch (\Throwable $e) {
            Log::debug('RAG stored document lookup check skipped', [
                'error' => $this->sanitizeError($e->getMessage()),
            ]);

            return false;
        }
    }

    private function usageMetadata(array $data): ?array
    {
        $metadata = [];

        if (is_array($data['token_usage'] ?? null)) {
            $metadata['token_usage'] = $data['token_usage'];
        }

        if (is_array($data['timings'] ?? null)) {
            $metadata['timings'] = $data['timings'];
        }

        return $metadata ?: null;
    }

    public function sanitizeError(string $message): string
    {
        if (str_contains($message, 'cURL error 28') || str_contains(strtolower($message), 'timed out')) {
            return 'AI service timed out while generating an answer. Try again, lower the Sources value, or confirm the local model is not still loading.';
        }

        $message = preg_replace('/https?:\/\/[^\s]+/i', '[url]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $message) ?? $message;

        return Str::limit(trim(strip_tags($message)), 500);
    }

    private function request(int $timeout): PendingRequest
    {
        $baseUrl = rtrim((string) config('rag.base_url', 'http://127.0.0.1:8001'), '/');

        if (! Str::startsWith($baseUrl, ['http://', 'https://'])) {
            throw new RuntimeException('RAG base URL must use http or https.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->connectTimeout((int) config('rag.connect_timeout', 5))
            ->timeout($timeout)
            ->retry((int) config('rag.retry_count', 1), 250, throw: false);
    }

    private function usesInAppEngine(): bool
    {
        return config('rag.engine') === 'external';
    }

    private function usesHybridEngine(): bool
    {
        return config('rag.engine') === 'hybrid';
    }

    private function askHybrid(string $question, int $topK, int $started): array
    {
        $retrievalStarted = hrtime(true);
        $profile = $this->retrievalProfile($question, $topK);
        $clampedTopK = $profile['top_k'];
        $searches = [];
        $primaryQueries = $this->plannedSearchQueries($question, $profile['query_limit'], (bool) $profile['use_query_planner']);
        $sources = $this->runHybridSearches($primaryQueries, $clampedTopK, $searches);
        $visualMode = $this->visualRequestMode($question);

        if ($visualMode === 'present' && $this->hasMediaSource($sources)) {
            $sources = $this->prioritizeMediaSources($sources);

            return [
                'answer' => $this->visualAnswer($sources),
                'citations' => $sources,
                'retrieved_sources' => $sources,
                'model' => 'local-media',
                'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'token_usage' => $this->responseMetadata(null, $searches, $this->retrievalTrace($profile, $primaryQueries, [], $searches, $sources, $retrievalStarted)),
            ];
        }

        $fallbackQueries = [];
        if (! $this->hasUsefulHybridSources($sources)) {
            $fallbackQueries = $this->fallbackSearchQueries($question, $primaryQueries, $profile['fallback_limit']);
            $sources = collect($sources)
                ->merge($this->runHybridSearches($fallbackQueries, $clampedTopK, $searches))
                ->unique(fn (array $source): string => $this->sourceKey($source))
                ->take(max($clampedTopK, min(12, $clampedTopK * 2)))
                ->values()
                ->all();
        }

        if (! $this->hasUsefulHybridSources($sources) || $this->shouldTryCurriculumLookup($question)) {
            $sources = $this->mergeCurriculumSources($this->curriculumLookupQuestion($question, $primaryQueries), $sources, $clampedTopK);
            $sources = $this->mergeOutlineSources($question, $sources, $clampedTopK);
        }

        if (! $this->hasUsefulHybridSources($sources) || $this->shouldTryStoredDocumentLookup($question)) {
            $sources = $this->mergeStoredDocumentSources($question, $sources, $clampedTopK);
        }

        $sources = $this->prioritizeContentSources($sources, $clampedTopK, $question, (bool) $visualMode);
        $trace = $this->retrievalTrace($profile, $primaryQueries, $fallbackQueries, $searches, $sources, $retrievalStarted);

        if ($sources === []) {
            throw new RuntimeException('No relevant indexed documents are available for search.');
        }

        if ($visualMode && $this->hasMediaSource($sources)) {
            $sources = $this->prioritizeMediaSources($sources);
        }

        if ($curriculumAnswer = $this->curriculumAnswer($question, $sources)) {
            return [
                'answer' => $curriculumAnswer,
                'citations' => $sources,
                'retrieved_sources' => $sources,
                'model' => 'local-curriculum',
                'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'token_usage' => $this->responseMetadata(null, $searches, $trace),
            ];
        }

        $answer = $this->provider->answer($question, $sources);

        return [
            'answer' => $this->stripThink($answer['answer']),
            'citations' => $sources,
            'retrieved_sources' => $sources,
            'model' => $answer['model'],
            'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'token_usage' => $this->responseMetadata($answer['token_usage'] ?? null, $searches, $trace),
        ];
    }

    private function runHybridSearches(array $queries, int $topK, array &$searches): array
    {
        $batchSearches = [];
        $timeout = min((int) config('rag.request_timeout'), (int) config('rag.search_timeout', 8));
        $failures = 0;
        $maxFailures = max(1, (int) config('rag.search_max_failures', 2));

        foreach ($queries as $query) {
            try {
                $response = $this->request($timeout)
                    ->post('/search', [
                        'question' => $query,
                        'top_k' => $topK,
                    ]);

                $data = $this->decodeResponse($response->throw()->json());
                $searches[] = $data;
                $batchSearches[] = $data;
                $failures = 0;
            } catch (\Throwable $e) {
                $failures++;
                $error = $this->sanitizeError($e->getMessage());

                Log::debug('RAG hybrid search skipped failed query', [
                    'query' => Str::limit($query, 120, ''),
                    'error' => $error,
                ]);

                if ($failures >= $maxFailures || $this->isSearchServiceUnavailable($e->getMessage())) {
                    break;
                }
            }

            $sources = collect($batchSearches)
                ->flatMap(fn (array $data): array => $this->normalizeSources($data['sources'] ?? []))
                ->unique(fn (array $source): string => $this->sourceKey($source))
                ->values()
                ->all();

            if ($this->hasUsefulHybridSources($sources)) {
                break;
            }
        }

        return collect($batchSearches)
            ->flatMap(fn (array $data): array => $this->normalizeSources($data['sources'] ?? []))
            ->unique(fn (array $source): string => $this->sourceKey($source))
            ->take(max($topK, min(12, $topK * 2)))
            ->values()
            ->all();
    }

    private function isSearchServiceUnavailable(string $message): bool
    {
        $message = Str::lower($message);

        return str_contains($message, 'couldn\'t connect to server')
            || str_contains($message, 'could not connect to server')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'could not resolve host');
    }

    private function retrievalProfile(string $question, int $requestedTopK): array
    {
        $normalized = Str::lower($question);
        $requestedTopK = $this->clampTopK($requestedTopK);

        if ($this->isStructuredModuleQuestion($question)) {
            return [
                'name' => 'structured',
                'top_k' => max($requestedTopK, min((int) config('rag.top_k.max', 10), 7)),
                'query_limit' => max(3, min(5, (int) config('rag.query_planner.max_queries', 6))),
                'fallback_limit' => 3,
                'allow_second_pass' => true,
                'use_query_planner' => true,
            ];
        }

        if ($this->isCompositeQuestion($question)) {
            return [
                'name' => 'composite',
                'top_k' => max($requestedTopK, min((int) config('rag.top_k.max', 10), 7)),
                'query_limit' => max(4, min(6, (int) config('rag.query_planner.max_queries', 6))),
                'fallback_limit' => 4,
                'allow_second_pass' => true,
                'use_query_planner' => true,
            ];
        }

        $deepPatterns = [
            'tell me more',
            'more about',
            'explain in detail',
            'discuss',
            'overview',
            'everything about',
            'care of',
            'management of',
            'how do we manage',
            'compare',
            'summarize',
            'summary',
            'teach me',
            'module',
            'modules',
            'guideline',
            'guidelines',
            'manual',
        ];

        $fastPatterns = [
            'what does',
            'stands for',
            'stand for',
            'definition of',
            'define ',
            'which page',
            'who ',
            'when ',
        ];

        if (Str::contains($normalized, $deepPatterns)) {
            return [
                'name' => 'deep',
                'top_k' => max($requestedTopK, min((int) config('rag.top_k.max', 10), 7)),
                'query_limit' => max(4, (int) config('rag.query_planner.max_queries', 6)),
                'fallback_limit' => 4,
                'allow_second_pass' => true,
                'use_query_planner' => true,
            ];
        }

        if (Str::contains($normalized, $fastPatterns) && count($this->searchTerms($question)) <= 3) {
            return [
                'name' => 'fast',
                'top_k' => max(2, min($requestedTopK, 3)),
                'query_limit' => 2,
                'fallback_limit' => 1,
                'allow_second_pass' => false,
                'use_query_planner' => false,
            ];
        }

        return [
            'name' => 'standard',
            'top_k' => max($requestedTopK, min((int) config('rag.top_k.max', 10), 5)),
            'query_limit' => min(max(3, (int) config('rag.query_planner.max_queries', 6)), 5),
            'fallback_limit' => 3,
            'allow_second_pass' => false,
            'use_query_planner' => false,
        ];
    }

    private function retrievalTrace(array $profile, array $primaryQueries, array $fallbackQueries, array $searches, array $sources, int $started): array
    {
        return [
            'profile' => $profile['name'] ?? 'standard',
            'top_k' => $profile['top_k'] ?? null,
            'query_limit' => $profile['query_limit'] ?? null,
            'fallback_limit' => $profile['fallback_limit'] ?? null,
            'allow_second_pass' => $profile['allow_second_pass'] ?? false,
            'use_query_planner' => $profile['use_query_planner'] ?? false,
            'primary_queries' => array_values($primaryQueries),
            'fallback_queries' => array_values($fallbackQueries),
            'search_count' => count($searches),
            'source_count' => count($sources),
            'selected_documents' => collect($sources)
                ->pluck('document')
                ->filter()
                ->unique()
                ->take(8)
                ->values()
                ->all(),
            'selected_locations' => collect($sources)
                ->map(fn (array $source): array => [
                    'document' => $source['document'] ?? null,
                    'locator_type' => $source['locator_type'] ?? null,
                    'locator' => $source['locator'] ?? null,
                ])
                ->take(10)
                ->values()
                ->all(),
            'retrieval_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
        ];
    }

    private function responseMetadata(mixed $tokenUsage, array $searches, array $trace): array
    {
        return [
            'token_usage' => $tokenUsage,
            'timings' => $searches[0]['timings'] ?? null,
            'retrieval_trace' => $trace,
        ];
    }

    private function plannedSearchQueries(string $question, ?int $limit = null, bool $useQueryPlanner = true): array
    {
        $fallbackQueries = $this->primarySearchQueries($question);
        $plannedQueries = $useQueryPlanner
            ? $this->provider->searchQueries($question, $fallbackQueries, $this->queryPlannerHints())
            : $fallbackQueries;

        return collect($plannedQueries)
            ->merge($fallbackQueries)
            ->map(fn (string $query): string => Str::limit(trim($query), 180, ''))
            ->filter()
            ->unique(fn (string $query): string => Str::lower($query))
            ->take(max(1, $limit ?? (int) config('rag.query_planner.max_queries', 6)))
            ->values()
            ->all();
    }

    private function queryPlannerHints(): array
    {
        $path = database_path('seeders/data/mentorship_curriculum_2025_10_13.php');
        if (! is_file($path)) {
            return [];
        }

        $curriculum = require $path;
        if (! is_array($curriculum)) {
            return [];
        }

        return collect($curriculum)
            ->flatMap(fn (array $modules, string $program): array => collect($modules)
                ->map(function (array $module) use ($program): string {
                    $sessions = collect($module['sessions'] ?? [])
                        ->filter(fn ($session): bool => is_array($session))
                        ->pluck('session')
                        ->take(4)
                        ->implode('; ');

                    return trim(Str::headline($program).': '.($module['module'] ?? '').($sessions !== '' ? " ({$sessions})" : ''));
                })
                ->all())
            ->filter()
            ->values()
            ->all();
    }

    private function fallbackSearchQueries(string $question, array $alreadyTried, ?int $limit = null): array
    {
        return collect($this->expandedSearchQueries($question))
            ->merge($this->primarySearchQueries($question))
            ->map(fn (string $query): string => Str::limit(trim($query), 180, ''))
            ->filter()
            ->reject(fn (string $query): bool => collect($alreadyTried)->contains(
                fn (string $tried): bool => Str::lower($tried) === Str::lower($query)
            ))
            ->unique(fn (string $query): string => Str::lower($query))
            ->take(max(1, $limit ?? 4))
            ->values()
            ->all();
    }

    private function curriculumLookupQuestion(string $question, array $plannedQueries): string
    {
        return collect([$question])
            ->merge($plannedQueries)
            ->map(fn (string $query): string => trim($query))
            ->filter()
            ->unique(fn (string $query): string => Str::lower($query))
            ->take(8)
            ->implode(' ');
    }

    private function primarySearchQueries(string $question): array
    {
        $terms = $this->searchTerms($question);
        $queries = array_merge(
            $this->structuredModuleSearchQueries($question),
            $this->facetSearchQueries($question),
            [trim($question)]
        );

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
                array_push($queries, "{$topic} summary", "{$topic} key facts");
            }
        }

        array_push($queries, ...$this->bridgeSearchQueries($question, $terms));
        array_push($queries, ...$this->lexiconSearchQueries($question));

        array_push($queries, ...collect($terms)->take(3)->all());
        array_push($queries, ...$this->priorityScopeQueries($question, $terms));

        return collect($queries)
            ->map(fn (string $query): string => Str::limit(trim($query), 180, ''))
            ->filter()
            ->unique(fn (string $query): string => Str::lower($query))
            ->take(5)
            ->values()
            ->all();
    }

    private function structuredModuleSearchQueries(string $question): array
    {
        if (! $this->isStructuredModuleQuestion($question)) {
            return [];
        }

        $terms = collect($this->searchTerms($question))
            ->map(fn (string $term): string => match ($term) {
                'workplan' => 'work plan',
                default => $term,
            })
            ->values();

        if ($terms->isEmpty()) {
            return [];
        }

        $topic = $terms->implode(' ');
        $module = $terms->filter(fn (string $term): bool => $term === 'module')->first();
        $number = $terms->first(fn (string $term): bool => is_numeric($term));

        return collect([
            $topic,
            $module && $number !== null ? "module {$number} work plan" : null,
        ])
            ->filter()
            ->unique(fn (string $query): string => Str::lower($query))
            ->values()
            ->all();
    }

    private function isCompositeQuestion(string $question): bool
    {
        $normalized = Str::lower(preg_replace('/\s+/', ' ', $question) ?? $question);
        $tokens = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $clauseCount = count(preg_split('/[,;]|\band\b|\bwith\b|\bhas\b|\breports?\b/iu', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $hasQuestionIntent = Str::contains($normalized, ['what', 'how', 'detail', 'steps', 'actions', 'explain', 'describe']);

        return count($tokens) >= 20 && $clauseCount >= 3 && $hasQuestionIntent;
    }

    private function isStructuredModuleQuestion(string $question): bool
    {
        $normalized = Str::lower(trim($question));
        $terms = $this->searchTerms($question);
        $hasModuleReference = Str::contains($normalized, ['module', 'modules']);
        $hasInterrogative = Str::contains($normalized, [
            'what', 'how', 'which', 'tell me', 'explain', 'describe',
            'want', 'need', 'workplan', 'work plan', 'breakdown', 'content', 'outline', '?',
        ]);

        return $hasModuleReference && $hasInterrogative && count($terms) >= 4;
    }

    private function facetSearchQueries(string $question): array
    {
        if (! $this->isCompositeQuestion($question)) {
            return [];
        }

        $clauses = collect(preg_split(
            '/[,;]|\band\b|\bwith\b|\bhas\b|\breports?\b|\bhistory of\b/iu',
            $question,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [])
            ->map(fn (string $clause): string => trim($clause))
            ->filter(fn (string $clause): bool => count(preg_split('/\s+/', $clause, -1, PREG_SPLIT_NO_EMPTY) ?: []) >= 2)
            ->values();

        if ($clauses->count() < 2) {
            return [];
        }

        $facets = $clauses->take(5)->all();
        $combined = collect($facets)->implode(' ');

        return collect($facets)
            ->push($combined)
            ->map(fn (string $query): string => Str::limit($query, 180, ''))
            ->filter()
            ->unique(fn (string $query): string => Str::lower($query))
            ->values()
            ->all();
    }

    private function expandedSearchQueries(string $question): array
    {
        $terms = $this->searchTerms($question);

        return collect($this->inferredScopeQueries($terms))
            ->merge($this->terminologyQueries($terms))
            ->map(fn (string $query): string => Str::limit(trim($query), 180, ''))
            ->filter()
            ->unique(fn (string $query): string => Str::lower($query))
            ->take(4)
            ->values()
            ->all();
    }

    private function bridgeSearchQueries(string $question, array $terms): array
    {
        if ($terms === []) {
            return [];
        }

        $normalizedQuestion = Str::lower(preg_replace('/\s+/', ' ', $question) ?? $question);
        $termSet = collect($terms)->mapWithKeys(fn (string $term): array => [$term => true]);

        return collect($this->termBridges())
            ->filter(function (array $bridge) use ($normalizedQuestion, $termSet): bool {
                $trigger = Str::lower((string) ($bridge['trigger'] ?? ''));
                $synonyms = collect($bridge['synonyms'] ?? [])
                    ->filter(fn ($synonym): bool => is_string($synonym) && trim($synonym) !== '')
                    ->map(fn (string $synonym): string => Str::lower(trim($synonym)));

                $candidates = $synonyms->prepend($trigger)->filter()->values();

                return $candidates->contains(function (string $candidate) use ($normalizedQuestion, $termSet): bool {
                    if ($termSet->has($candidate)) {
                        return true;
                    }

                    return strlen($candidate) >= 4 && Str::contains($normalizedQuestion, $candidate);
                });
            })
            ->sortByDesc(fn (array $bridge): int => (int) ($bridge['priority'] ?? 0))
            ->flatMap(fn (array $bridge): array => is_array($bridge['queries'] ?? null) ? $bridge['queries'] : [])
            ->filter(fn ($query): bool => is_string($query) && trim($query) !== '')
            ->map(fn (string $query): string => trim($query))
            ->unique(fn (string $query): string => Str::lower($query))
            ->take(8)
            ->values()
            ->all();
    }

    private function lexiconSearchQueries(string $question): array
    {
        try {
            $expanded = app(\App\Services\Rag\Lexicon\Lexicon::class)->expand($question);

            return collect($expanded['queries'] ?? [])
                ->reject(fn (string $query): bool => Str::lower($query) === Str::lower(trim($question)))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::debug('RAG lexicon expansion skipped', [
                'error' => $this->sanitizeError($e->getMessage()),
            ]);

            return [];
        }
    }

    private function termBridges(): array
    {
        try {
            return Cache::remember(RagTermBridge::CACHE_KEY, 600, function (): array {
                return RagTermBridge::query()
                    ->where('enabled', true)
                    ->orderByDesc('priority')
                    ->orderBy('trigger')
                    ->get(['trigger', 'synonyms', 'queries', 'priority'])
                    ->map(fn (RagTermBridge $bridge): array => [
                        'trigger' => $bridge->trigger,
                        'synonyms' => $bridge->synonyms ?? [],
                        'queries' => $bridge->queries ?? [],
                        'priority' => $bridge->priority,
                    ])
                    ->all();
            });
        } catch (\Throwable $e) {
            Log::debug('RAG term bridges unavailable; using defaults', [
                'error' => $this->sanitizeError($e->getMessage()),
            ]);

            return $this->defaultTermBridges();
        }
    }

    private function defaultTermBridges(): array
    {
        return [
            [
                'trigger' => 'sepsis',
                'synonyms' => ['neonatal sepsis', 'danger signs', 'infection'],
                'queries' => [
                    'neonatal sepsis danger signs management',
                    'sepsis evaluation antibiotics antimicrobial therapy',
                    'sepsis urgent care newborn child',
                ],
                'priority' => 90,
            ],
            [
                'trigger' => 'hypothermia',
                'synonyms' => ['cold baby', 'low temperature', 'thermal care'],
                'queries' => [
                    'neonatal thermoregulation',
                    'hypothermia radiant warmer incubator temperature',
                    'newborn temperature thermal care',
                ],
                'priority' => 80,
            ],
            [
                'trigger' => 'oxygen',
                'synonyms' => ['oxygen therapy', 'spo2', 'oxygen saturation', 'pulse oximetry'],
                'queries' => [
                    'oxygen therapy safe oxygen use pulse oximetry',
                    'oxygen delivery devices prescribing monitoring',
                    'oxygen saturation respiratory distress',
                ],
                'priority' => 80,
            ],
            [
                'trigger' => 'resuscitation',
                'synonyms' => ['newborn resuscitation', 'neonatal resuscitation', 'resuscitation module'],
                'queries' => [
                    'Module 6 Newborn Resuscitation',
                    'newborn resuscitation sessions duration',
                    'resuscitation video algorithm skills teaching practicum case scenarios',
                ],
                'priority' => 80,
            ],
        ];
    }

    private function priorityScopeQueries(string $question, array $terms): array
    {
        $topic = collect($terms)->take(4)->implode(' ');
        if ($topic === '') {
            return [];
        }

        $normalized = Str::lower($question);
        $scopes = [];

        if (Str::contains($normalized, ['show', 'display', 'view', 'open', 'select', 'pick', 'image', 'slide', 'visual'])) {
            $scopes = ['assessment', 'management'];
        } elseif (Str::contains($normalized, ['tell me more', 'summarize', 'summary', 'overview', 'explain', 'describe', 'what is', 'what does'])) {
            $scopes = ['definition', 'overview', 'management'];
        } elseif (Str::contains($normalized, ['how', 'steps', 'procedure'])) {
            $scopes = ['steps', 'procedure'];
        }

        return collect($scopes)
            ->map(fn (string $scope): string => "{$topic} {$scope}")
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

    private function sourceKey(array $source): string
    {
        return implode('|', [
            $source['document_id'] ?? '',
            $source['document'] ?? '',
            $source['locator_type'] ?? '',
            $source['locator'] ?? '',
            hash('sha256', (string) ($source['content'] ?? '')),
        ]);
    }

    private function hasUsefulHybridSources(array $sources): bool
    {
        return collect($sources)->contains(function (array $source): bool {
            if (! empty($source['media'] ?? [])) {
                return true;
            }

            $content = RagSourceFormatter::plain((string) ($source['content'] ?? ''));

            return mb_strlen($content) >= 80
                && ! Str::startsWith($content, 'Document outline:')
                && ! $this->isModuleTitleOnlySource($source);
        });
    }

    private function isModuleTitleOnlySource(array $source): bool
    {
        $content = trim(RagSourceFormatter::plain((string) ($source['content'] ?? '')));
        if ($content === '') {
            return true;
        }

        $normalized = Str::lower(preg_replace('/\s+/', ' ', $content) ?? $content);

        if (! Str::contains($normalized, ['module', 'oxygen therapy'])) {
            return false;
        }

        if (Str::contains($normalized, [
            'only shows the title',
            'only detail available',
            'only content available',
            'only tells us',
            'only tells you',
            'all the excerpt tells us',
            'all the excerpt tells you',
            'excerpt does not include',
            "excerpt doesn't include",
            'does not include the actual',
            "doesn't include the actual",
            'not a definition or explanation',
            'full content for this module has not',
            "full content for this module hasn't",
            'content for this module has not',
            "content for this module hasn't",
            'module has not been included',
            "module hasn't been included",
        ])) {
            return true;
        }

        if (mb_strlen($content) > 260) {
            return false;
        }

        return ! Str::contains($normalized, [
            'covers:',
            'covered as',
            'indications',
            'safe use',
            'pulse oximetry',
            'delivery device',
            'delivery devices',
            'prescribing',
            'monitoring',
            'monitor oxygen',
            'oxygen saturation',
            'when to give oxygen',
            'definition',
            'defined',
            'means',
        ]);
    }

    private function visualRequestMode(string $question): ?string
    {
        $normalized = Str::lower($question);

        if (Str::contains($normalized, [
            'show me',
            'show the',
            'show',
            'display',
            'view',
            'open',
            'pull up',
            'bring up',
            'select',
            'pick',
            'find the image',
            'find image',
            'find picture',
            'find slide',
            'illustrate',
            'illustration',
            'visualize',
            'visualise',
        ])) {
            return 'present';
        }

        if (Str::contains($normalized, [
            'describe',
            'explain',
            'interpret',
            'walk me through',
            'what is in',
            'what does',
            'summarize the visual',
            'summarise the visual',
            'summarize the image',
            'summarise the image',
            'summarize the slide',
            'summarise the slide',
        ])) {
            return 'describe';
        }

        if (Str::contains($normalized, [
            'image',
            'images',
            'picture',
            'pictures',
            'visual',
            'diagram',
            'photo',
            'slide',
            'chart',
            'figure',
            'flowchart',
            'algorithm',
            'visual',
        ])) {
            return 'present';
        }

        return null;
    }

    private function hasMediaSource(array $sources): bool
    {
        return collect($sources)->contains(fn (array $source): bool => ! empty($source['media']));
    }

    private function prioritizeMediaSources(array $sources): array
    {
        return collect($sources)
            ->sortByDesc(fn (array $source): int => count($source['media'] ?? []))
            ->values()
            ->all();
    }

    private function visualAnswer(array $sources): string
    {
        $mediaSources = collect($sources)
            ->filter(fn (array $source): bool => ! empty($source['media']))
            ->values();

        $first = $mediaSources->first();
        $count = $mediaSources->sum(fn (array $source): int => count($source['media'] ?? []));
        $locator = filled($first['locator'] ?? null)
            ? Str::headline((string) ($first['locator_type'] ?? 'source')).' '.$first['locator']
            : 'the cited source';

        return implode("\n", [
            "I found the relevant visual for this in **{$first['document']}**, {$locator}.",
            '',
            $count === 1
                ? 'Open the source below to view the image.'
                : "Open the sources below to view the {$count} related images.",
        ]);
    }

    private function localHealth(): array
    {
        return Cache::remember('rag.health.'.config('rag.engine'), max(1, (int) config('rag.health_cache_seconds')), function () {
            try {
                $response = $this->request(5)->get('/health');

                return [
                    'ok' => $response->successful(),
                    'status' => $response->status(),
                    'body' => $response->successful() && is_array($response->json()) ? $response->json() : null,
                ];
            } catch (ConnectionException|RuntimeException $e) {
                return [
                    'ok' => false,
                    'status' => null,
                    'body' => null,
                    'error' => $this->sanitizeError($e->getMessage()),
                ];
            }
        });
    }

    private function decodeResponse(mixed $data): array
    {
        if (! is_array($data)) {
            throw new RuntimeException('RAG service returned malformed JSON.');
        }

        return $data;
    }

    private function sanitizeTitle(string $title): string
    {
        return Str::limit(trim(strip_tags($title)), 255, '');
    }

    private function cachedAnswer(string $question, int $started): ?array
    {
        try {
            $hit = app(SemanticAnswerCache::class)->lookup($question);
        } catch (\Throwable $e) {
            Log::debug('RAG answer cache lookup skipped', [
                'error' => $this->sanitizeError($e->getMessage()),
            ]);

            return null;
        }

        if (! $hit) {
            return null;
        }

        $sources = $hit->retrieved_sources ?? [];

        return [
            'answer' => $hit->answer,
            'citations' => $hit->citations ?? [],
            'retrieved_sources' => $sources,
            'model' => ($hit->answer_model ?: 'local-answer-cache').'-cache',
            'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'token_usage' => [
                'retrieval_trace' => [
                    'profile' => 'cache',
                    'search_count' => 0,
                    'source_count' => count($sources),
                    'selected_documents' => collect($sources)->pluck('document')->filter()->unique()->values()->all(),
                    'retrieval_ms' => 0,
                ],
            ],
            'cache_hit' => true,
            'cache_kind' => 'exact',
            'cache_similarity' => 1.0,
        ];
    }

    private function storeCachedAnswer(string $question, array $response): void
    {
        try {
            app(SemanticAnswerCache::class)->store($question, $response);
        } catch (\Throwable $e) {
            Log::debug('RAG answer cache store skipped', [
                'error' => $this->sanitizeError($e->getMessage()),
            ]);
        }
    }
}
