<?php

namespace App\Services\Rag;

use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class DocumentTextExtractor
{
    public function extract(string $absolutePath, string $extension): array
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'pdf' => $this->pdf($absolutePath),
            'docx' => $this->docx($absolutePath),
            'pptx' => $this->pptx($absolutePath),
            'xlsx' => $this->xlsx($absolutePath),
            'csv', 'txt', 'md', 'markdown', 'json' => $this->plain($absolutePath),
            'html', 'htm' => $this->html($absolutePath),
            default => throw new RuntimeException("Unsupported document extension: {$extension}."),
        };
    }

    private function pdf(string $path): array
    {
        $pdf = (new PdfParser)->parseFile($path);
        $chunks = [];

        foreach ($pdf->getPages() as $index => $page) {
            $text = $this->clean($page->getText());

            if ($text !== '') {
                $chunks[] = [
                    'locator_type' => 'page',
                    'locator' => (string) ($index + 1),
                    'content' => $text,
                ];
            }
        }

        if ($chunks === []) {
            $text = $this->clean($pdf->getText());
            if ($text !== '') {
                $chunks[] = ['locator_type' => 'document', 'locator' => null, 'content' => $text];
            }
        }

        return $chunks;
    }

    private function docx(string $path): array
    {
        $xml = $this->zipEntry($path, 'word/document.xml');

        return [[
            'locator_type' => 'document',
            'locator' => null,
            'content' => $this->clean($this->xmlText($xml)),
        ]];
    }

    private function pptx(string $path): array
    {
        $zip = $this->openZip($path);
        $slides = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! preg_match('/^ppt\/slides\/slide(\d+)\.xml$/', $name, $matches)) {
                continue;
            }

            $xml = $zip->getFromIndex($i);
            $text = $this->clean($this->xmlText((string) $xml));

            if ($text !== '') {
                $slides[(int) $matches[1]] = [
                    'locator_type' => 'slide',
                    'locator' => (string) $matches[1],
                    'content' => $text,
                ];
            }
        }

        $zip->close();
        ksort($slides);

        return array_values($slides);
    }

    private function xlsx(string $path): array
    {
        $zip = $this->openZip($path);
        $sharedStrings = $this->sharedStrings($zip);
        $sheets = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! preg_match('/^xl\/worksheets\/sheet(\d+)\.xml$/', $name, $matches)) {
                continue;
            }

            $xml = (string) $zip->getFromIndex($i);
            $text = $this->clean($this->sheetText($xml, $sharedStrings));

            if ($text !== '') {
                $sheets[(int) $matches[1]] = [
                    'locator_type' => 'sheet',
                    'locator' => (string) $matches[1],
                    'content' => $text,
                ];
            }
        }

        $zip->close();
        ksort($sheets);

        return array_values($sheets);
    }

    private function plain(string $path): array
    {
        return [[
            'locator_type' => 'document',
            'locator' => null,
            'content' => $this->clean((string) file_get_contents($path)),
        ]];
    }

    private function html(string $path): array
    {
        return [[
            'locator_type' => 'document',
            'locator' => null,
            'content' => $this->clean(html_entity_decode(strip_tags((string) file_get_contents($path)))),
        ]];
    }

    public function chunk(array $sections): array
    {
        $max = max(1000, (int) config('rag.chunking.max_chars', 3500));
        $overlap = max(0, min((int) config('rag.chunking.overlap_chars', 400), $max - 1));
        $chunks = [];

        foreach ($sections as $section) {
            $content = $this->clean((string) ($section['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $offset = 0;
            $length = mb_strlen($content);

            while ($offset < $length) {
                $part = mb_substr($content, $offset, $max);
                $lastBreak = mb_strrpos($part, "\n");

                if ($lastBreak !== false && mb_strlen($part) > $max * 0.65) {
                    $part = mb_substr($part, 0, $lastBreak);
                }

                $part = $this->clean($part);
                if ($part !== '') {
                    $chunks[] = [
                        'locator_type' => $section['locator_type'] ?? 'document',
                        'locator' => $section['locator'] ?? null,
                        'content' => $part,
                    ];
                }

                if ($offset + mb_strlen($part) >= $length) {
                    break;
                }

                $step = max(1, mb_strlen($part) - $overlap);
                $offset += $step;
            }
        }

        return $chunks;
    }

    private function zipEntry(string $path, string $entry): string
    {
        $zip = $this->openZip($path);
        $xml = $zip->getFromName($entry);
        $zip->close();

        if (! is_string($xml)) {
            throw new RuntimeException("Document is missing {$entry}.");
        }

        return $xml;
    }

    private function openZip(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not read Office document archive.');
        }

        return $zip;
    }

    private function xmlText(string $xml): string
    {
        $xml = preg_replace('/<[^>]+>/', ' ', $xml) ?? $xml;

        return html_entity_decode($xml, ENT_QUOTES | ENT_XML1);
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! is_string($xml)) {
            return [];
        }

        preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s', $xml, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $item): string => $this->clean($this->xmlText($item)))
            ->all();
    }

    private function sheetText(string $xml, array $sharedStrings): string
    {
        preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $xml, $cells, PREG_SET_ORDER);
        $values = [];

        foreach ($cells as $cell) {
            $attrs = $cell[1] ?? '';
            $body = $cell[2] ?? '';
            preg_match('/<v>(.*?)<\/v>/s', $body, $valueMatch);
            $value = html_entity_decode($valueMatch[1] ?? $this->xmlText($body), ENT_QUOTES | ENT_XML1);

            if (str_contains($attrs, 't="s"') && is_numeric($value)) {
                $value = $sharedStrings[(int) $value] ?? '';
            }

            $value = $this->clean((string) $value);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return implode("\n", $values);
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
