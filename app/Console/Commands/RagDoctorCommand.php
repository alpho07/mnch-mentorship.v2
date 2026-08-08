<?php

namespace App\Console\Commands;

use App\Models\RagAnswerCache;
use App\Models\RagChunk;
use App\Models\RagDocument;
use App\Models\RagLexiconEdge;
use App\Models\RagLexiconTerm;
use App\Models\RagRetrievalTrace;
use App\Services\Rag\RagClient;
use App\Services\Rag\Settings\RagSettings;
use Illuminate\Console\Command;

class RagDoctorCommand extends Command
{
    protected $signature = 'rag:doctor';

    protected $description = 'Show adaptive RAG health, corpus, trace, cache, and lexicon status.';

    public function handle(RagClient $client, RagSettings $settings): int
    {
        $health = $client->health();

        $this->line('RAG enabled: '.(config('rag.enabled') ? 'yes' : 'no'));
        $this->line('Engine: '.config('rag.engine'));
        $this->line('Local/chat health: '.(($health['ok'] ?? false) ? 'ok' : 'not ok'));
        $this->line('Corpus version: '.$settings->corpusVersion());
        $this->line('Settings version: '.$settings->version());
        $this->line('Gate mode: '.$settings->get('gate.mode', 'shadow'));
        $this->line('Grounding mode: '.$settings->get('grounding.mode', 'shadow'));
        $this->line('Answer cache: '.($settings->get('answer_cache.enabled', true) ? 'enabled' : 'disabled'));

        $this->table(['Metric', 'Count'], [
            ['ready documents', RagDocument::query()->where('status', RagDocument::STATUS_READY)->count()],
            ['chunks', RagChunk::query()->count()],
            ['lexicon terms', RagLexiconTerm::query()->count()],
            ['lexicon edges', RagLexiconEdge::query()->count()],
            ['answer cache rows', RagAnswerCache::query()->count()],
            ['retrieval traces', RagRetrievalTrace::query()->count()],
        ]);

        return self::SUCCESS;
    }
}
