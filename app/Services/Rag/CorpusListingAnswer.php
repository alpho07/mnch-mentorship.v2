<?php

namespace App\Services\Rag;

use App\Support\RagSourceFormatter;
use Illuminate\Support\Str;

class CorpusListingAnswer
{
    public function make(array $sources): string
    {
        $lines = ['I could not find enough in the indexed documents to answer that reliably.', '', 'Closest material in the corpus:'];

        foreach (array_slice($sources, 0, 5) as $index => $source) {
            $locator = filled($source['locator'] ?? null)
                ? ' - '.Str::headline((string) ($source['locator_type'] ?? 'source')).' '.$source['locator']
                : '';
            $content = RagSourceFormatter::prose((string) ($source['content'] ?? ''));
            $status = mb_strlen($content) < 80 ? 'heading or thin excerpt' : Str::limit($content, 160, '');
            $lines[] = ($index + 1).'. '.($source['document'] ?? 'Document').$locator.' - '.$status;
        }

        if (count($lines) === 3) {
            $lines[] = 'No ready source excerpts were returned.';
        }

        $lines[] = '';
        $lines[] = 'Try naming the module, document, or a more specific clinical term.';

        return implode("\n", $lines);
    }
}
