<?php

namespace App\Console\Commands;

use App\Jobs\BuildRagLexicon;
use Illuminate\Console\Command;

class RagLexiconCommand extends Command
{
    protected $signature = 'rag:lexicon {--sync : Run immediately instead of queueing}';

    protected $description = 'Build or queue the adaptive RAG lexicon.';

    public function handle(): int
    {
        if ($this->option('sync')) {
            app(BuildRagLexicon::class)->handle(app(\App\Services\Rag\Lexicon\Tokenizer::class), app(\App\Services\Rag\Settings\RagSettings::class));
            $this->info('RAG lexicon built.');

            return self::SUCCESS;
        }

        BuildRagLexicon::dispatch();
        $this->info('RAG lexicon rebuild queued.');

        return self::SUCCESS;
    }
}
