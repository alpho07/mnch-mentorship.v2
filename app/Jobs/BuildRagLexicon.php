<?php

namespace App\Jobs;

use App\Models\RagChunk;
use App\Models\RagLexiconEdge;
use App\Models\RagLexiconTerm;
use App\Models\RagTermBridge;
use App\Services\Rag\Lexicon\Tokenizer;
use App\Services\Rag\Settings\RagSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BuildRagLexicon implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $uniqueFor = 1800;

    public function uniqueId(): string
    {
        return 'rag-lexicon';
    }

    public function handle(Tokenizer $tokenizer, RagSettings $settings): void
    {
        $corpusVersion = $settings->corpusVersion();
        $minLength = (int) $settings->get('lexicon.min_term_length', 3);
        $stopwordDf = (float) $settings->get('lexicon.stopword_df', 0.60);
        $edgesPerTerm = (int) $settings->get('lexicon.edges_per_term', 8);
        $pmiMinCooccur = (int) $settings->get('lexicon.pmi_min_cooccur', 4);

        $localLexicon = $this->localServiceLexicon();
        if ($localLexicon !== null) {
            $this->writeLocalServiceLexicon($localLexicon, $corpusVersion);
            $this->copyManualBridges($corpusVersion);
            $this->mineCurriculum($corpusVersion, $tokenizer);

            return;
        }

        $chunks = RagChunk::query()
            ->with('document:id,title,status')
            ->whereHas('document', fn ($query) => $query->where('status', 'ready'))
            ->get(['id', 'rag_document_id', 'content']);

        $totalChunks = max(1, $chunks->count());
        $termStats = [];
        $cooccurrence = [];
        $acronymTerms = [];
        $acronymEdges = [];

        foreach ($chunks as $chunk) {
            foreach ($this->acronyms((string) $chunk->content) as $acronym => $expansion) {
                $normalisedAcronym = Str::lower($acronym);
                $acronymTerms[$normalisedAcronym] = true;
                $acronymEdges[$normalisedAcronym][$expansion] = true;
                $acronymEdges[Str::lower($expansion)][$acronym] = true;
            }

            $terms = collect($tokenizer->tokens((string) $chunk->content))
                ->filter(fn (string $term): bool => mb_strlen($term) >= $minLength || preg_match('/^[a-z0-9]{2,8}$/i', $term) === 1)
                ->unique()
                ->values();

            foreach ($terms as $term) {
                $termStats[$term]['term'] ??= $term;
                $termStats[$term]['chunks'][$chunk->id] = true;
                $termStats[$term]['documents'][$chunk->rag_document_id] = true;
            }

            foreach ($terms->take(40) as $left) {
                foreach ($terms->take(40) as $right) {
                    if ($left === $right) {
                        continue;
                    }
                    $cooccurrence[$left][$right] = ($cooccurrence[$left][$right] ?? 0) + 1;
                }
            }
        }

        DB::transaction(function () use ($tokenizer, $settings, $corpusVersion, $termStats, $cooccurrence, $acronymTerms, $acronymEdges, $totalChunks, $stopwordDf, $edgesPerTerm, $pmiMinCooccur): void {
            RagLexiconTerm::query()->where('corpus_version', $corpusVersion)->delete();
            RagLexiconEdge::query()->where('corpus_version', $corpusVersion)->where('source', 'auto')->delete();

            foreach ($termStats as $normalised => $stats) {
                $chunkFrequency = count($stats['chunks'] ?? []);
                $dfRatio = $chunkFrequency / $totalChunks;

                RagLexiconTerm::query()->create([
                    'term' => $stats['term'],
                    'normalised' => $normalised,
                    'document_frequency' => count($stats['documents'] ?? []),
                    'chunk_frequency' => $chunkFrequency,
                    'df_ratio' => round($dfRatio, 5),
                    'is_stopword' => $dfRatio > $stopwordDf,
                    'is_acronym' => (bool) ($acronymTerms[$normalised] ?? false),
                    'trigrams' => $tokenizer->trigrams($normalised),
                    'corpus_version' => $corpusVersion,
                ]);
            }

            $this->copyManualBridges($corpusVersion);
            $this->writeAcronymEdges($corpusVersion, $acronymEdges);
            $this->mineCurriculum($corpusVersion, $tokenizer);
            $this->mineCooccurrence($corpusVersion, $cooccurrence, $termStats, $edgesPerTerm, $pmiMinCooccur, $totalChunks);
        });
    }

    private function localServiceLexicon(): ?array
    {
        if (config('rag.engine') !== 'hybrid') {
            return null;
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('rag.base_url'), '/'))
                ->timeout(30)
                ->get('/lexicon');

            return $response->successful() && is_array($response->json()) ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeLocalServiceLexicon(array $payload, int $corpusVersion): void
    {
        DB::transaction(function () use ($payload, $corpusVersion): void {
            RagLexiconTerm::query()->where('corpus_version', $corpusVersion)->delete();
            RagLexiconEdge::query()->where('corpus_version', $corpusVersion)->where('source', 'auto')->delete();

            foreach (($payload['terms'] ?? []) as $term) {
                if (! is_array($term) || blank($term['term'] ?? null)) {
                    continue;
                }

                $normalised = Str::lower((string) $term['term']);
                RagLexiconTerm::query()->create([
                    'term' => (string) $term['term'],
                    'normalised' => $normalised,
                    'document_frequency' => (int) ($term['document_frequency'] ?? 0),
                    'chunk_frequency' => (int) ($term['chunk_frequency'] ?? 0),
                    'df_ratio' => (float) ($term['df_ratio'] ?? 0),
                    'is_stopword' => (float) ($term['df_ratio'] ?? 0) > (float) config('rag.lexicon.stopword_df', 0.60),
                    'is_acronym' => preg_match('/^[a-z0-9]{2,8}$/i', $normalised) === 1 && Str::upper($normalised) === (string) $term['term'],
                    'trigrams' => $term['trigrams'] ?? [],
                    'corpus_version' => $corpusVersion,
                ]);
            }

            foreach (($payload['edges'] ?? []) as $edge) {
                if (! is_array($edge) || blank($edge['from'] ?? null) || blank($edge['to'] ?? null)) {
                    continue;
                }

                RagLexiconEdge::query()->create([
                    'from_term' => Str::lower((string) $edge['from']),
                    'to_term' => Str::limit((string) $edge['to'], 256, ''),
                    'kind' => 'cooccurrence',
                    'source' => 'auto',
                    'weight' => (float) ($edge['count'] ?? 1),
                    'priority' => 80,
                    'enabled' => true,
                    'corpus_version' => $corpusVersion,
                ]);
            }
        });
    }

    private function acronyms(string $content): array
    {
        $pairs = [];
        preg_match_all('/\b([A-Z][A-Z0-9]{1,7})\b\s*[\(\[]([^)\]]{4,100})[\)\]]/u', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($this->initialAgreement($match[1], $match[2]) >= 0.6) {
                $pairs[$match[1]] = trim($match[2]);
            }
        }

        preg_match_all('/\b([\p{Lu}\p{Ll}][\p{L}&\-\s]{5,100})\s*[\(\[]([A-Z][A-Z0-9]{1,7})[\)\]]/u', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($this->initialAgreement($match[2], $match[1]) >= 0.6) {
                $pairs[$match[2]] = trim($match[1]);
            }
        }

        return $pairs;
    }

    private function initialAgreement(string $acronym, string $expansion): float
    {
        preg_match_all('/\b[\p{L}]/u', $expansion, $matches);
        $initials = collect($matches[0] ?? [])->map(fn (string $letter): string => Str::upper($letter))->implode('');
        $acronym = Str::upper($acronym);

        if ($acronym === '' || $initials === '') {
            return 0;
        }

        $matched = 0;
        foreach (str_split($acronym) as $letter) {
            if (str_contains($initials, $letter)) {
                $matched++;
            }
        }

        return $matched / strlen($acronym);
    }

    private function writeAcronymEdges(int $corpusVersion, array $acronymEdges): void
    {
        foreach ($acronymEdges as $from => $targets) {
            foreach (array_keys($targets) as $target) {
                RagLexiconEdge::query()->create([
                    'from_term' => Str::lower($from),
                    'to_term' => Str::limit((string) $target, 256, ''),
                    'kind' => ctype_upper(str_replace([' ', '&', '-'], '', (string) $target)) ? 'expansion_acronym' : 'acronym_expansion',
                    'source' => 'auto',
                    'weight' => 1,
                    'priority' => 20,
                    'enabled' => true,
                    'corpus_version' => $corpusVersion,
                ]);
            }
        }
    }

    private function copyManualBridges(int $corpusVersion): void
    {
        if (! class_exists(RagTermBridge::class)) {
            return;
        }

        RagLexiconEdge::query()
            ->where('corpus_version', $corpusVersion)
            ->where('source', 'manual')
            ->delete();

        foreach (RagTermBridge::query()->where('enabled', true)->get() as $bridge) {
            foreach (($bridge->queries ?? []) as $query) {
                RagLexiconEdge::query()->create([
                    'from_term' => Str::lower($bridge->trigger),
                    'to_term' => Str::limit((string) $query, 256, ''),
                    'kind' => 'manual',
                    'source' => 'manual',
                    'weight' => 1,
                    'priority' => max(1, 100 - (int) $bridge->priority),
                    'enabled' => true,
                    'notes' => 'Copied from rag_term_bridges.',
                    'corpus_version' => $corpusVersion,
                ]);
            }
        }
    }

    private function mineCurriculum(int $corpusVersion, Tokenizer $tokenizer): void
    {
        $path = database_path('seeders/data/mentorship_curriculum_2025_10_13.php');
        if (! is_file($path)) {
            return;
        }

        $curriculum = require $path;
        if (! is_array($curriculum)) {
            return;
        }

        foreach ($curriculum as $program => $modules) {
            foreach ($modules as $module) {
                $title = (string) ($module['module'] ?? '');
                $sessions = collect($module['sessions'] ?? [])->filter(fn ($session): bool => is_array($session));
                $aliases = collect([$title, Str::headline((string) $program).' '.$title])
                    ->merge($sessions->pluck('session'))
                    ->merge($sessions->pluck('methodology'))
                    ->filter()
                    ->values();

                $fromTerms = collect($tokenizer->tokens($title))
                    ->filter(fn (string $term): bool => mb_strlen($term) >= 4)
                    ->unique();

                foreach ($fromTerms as $fromTerm) {
                    foreach ($aliases->take(10) as $alias) {
                        RagLexiconEdge::query()->create([
                            'from_term' => $fromTerm,
                            'to_term' => Str::limit((string) $alias, 256, ''),
                            'kind' => 'curriculum_alias',
                            'source' => 'auto',
                            'weight' => 0.9,
                            'priority' => 30,
                            'enabled' => true,
                            'corpus_version' => $corpusVersion,
                        ]);
                    }
                }
            }
        }
    }

    private function mineCooccurrence(int $corpusVersion, array $cooccurrence, array $termStats, int $edgesPerTerm, int $minCooccur, int $totalChunks): void
    {
        foreach ($cooccurrence as $from => $rights) {
            arsort($rights);
            foreach (array_slice($rights, 0, $edgesPerTerm, true) as $to => $count) {
                if ($count < $minCooccur) {
                    continue;
                }

                $fromFreq = max(1, count($termStats[$from]['chunks'] ?? []));
                $toFreq = max(1, count($termStats[$to]['chunks'] ?? []));
                $pmi = log(($count / $totalChunks) / (($fromFreq / $totalChunks) * ($toFreq / $totalChunks)), 2);

                RagLexiconEdge::query()->create([
                    'from_term' => $from,
                    'to_term' => $to,
                    'kind' => 'cooccurrence',
                    'source' => 'auto',
                    'weight' => round(max(0, $pmi), 5),
                    'priority' => 80,
                    'enabled' => true,
                    'corpus_version' => $corpusVersion,
                ]);
            }
        }
    }
}
