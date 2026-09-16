<?php

namespace App\Support;

use Illuminate\Support\Str;

class RagSourceFormatter
{
    private const LIGATURES = [
        'ﬀ' => 'ff',
        'ﬁ' => 'fi',
        'ﬂ' => 'fl',
        'ﬃ' => 'ffi',
        'ﬄ' => 'ffl',
    ];

    public static function html(?string $value): string
    {
        $markdown = self::markdown($value);

        if ($markdown === '') {
            return '';
        }

        return Str::markdown(e($markdown));
    }

    public static function plain(?string $value): string
    {
        $markdown = self::markdown($value);

        if ($markdown === '') {
            return '';
        }

        return preg_replace('/\*\*(.*?)\*\*/', '$1', $markdown) ?? $markdown;
    }

    public static function prose(?string $value): string
    {
        $text = self::plain($value);

        if ($text === '') {
            return '';
        }

        $lines = collect(preg_split('/\n+/', $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->reject(fn (string $line): bool => $line === '')
            ->reject(fn (string $line): bool => preg_match('/^(?:speaker notes?:|references?|doi:|https?:\/\/)/i', $line) === 1)
            ->reject(fn (string $line): bool => preg_match('/^(?:module|section|chapter)\s+\d+\s*[:.-]?$/i', $line) === 1)
            ->reject(fn (string $line): bool => preg_match('/^[\dIVXLCDM]+[.)]\s*$/i', $line) === 1)
            ->map(fn (string $line): string => preg_replace('/^[•*\-]\s*/u', '', $line) ?? $line)
            ->filter(fn (string $line): bool => str_word_count($line) >= 3 || preg_match('/[.!?:]/', $line) === 1)
            ->values();

        return trim(preg_replace('/\s+/', ' ', $lines->implode(' ')) ?? $lines->implode(' '));
    }

    public static function cleanAnswer(?string $value): string
    {
        $text = strtr((string) $value, self::LIGATURES);
        $text = preg_replace('/(?<=[A-Za-z])(?=\d)/', ' ', $text) ?? $text;
        $text = preg_replace('/(?<=\d)(?=[A-Za-z])/', ' ', $text) ?? $text;
        $text = preg_replace('/^\s*\*{1,3}(?=\S)/m', '- ', $text) ?? $text;
        $text = preg_replace('/\*{3,}/', '', $text) ?? $text;

        return trim($text);
    }

    public static function markdown(?string $value): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($text === '') {
            return '';
        }

        $text = strtr($text, self::LIGATURES);
        $text = str_replace(["\r\n", "\r", "\t", "\x0B"], ["\n", "\n", ' ', "\n"], $text);
        $text = preg_replace('/\s+\|\s+/', "\n", $text) ?? $text;
        $text = preg_replace('/^(Role of .+? Phototherapy)\s+(?=Filtered sunlight\b)/i', "$1\n", $text) ?? $text;
        $text = preg_replace('/(^|\n)(Filtered sunlight)\s+(?=Filtered sunlight is\b)/i', "$1$2\n", $text) ?? $text;
        $text = preg_replace('/\s+(?=Filtered sunlight provides\b)/i', "\n", $text) ?? $text;
        $text = preg_replace('/\s+(?=Speaker notes?:)/i', "\n", $text) ?? $text;
        $text = preg_replace('/\s+(?=(?:A Randomized Trial|N Engl J Med|doi:|https?:\/\/))/i', "\n", $text) ?? $text;
        $text = preg_replace('/\s+(?=(?:Objectives|Summary|Introduction|Management of|Assessment of|Treatment of|Role of|Risks-?|Advantages|Complications|Prevention of|Monitoring|Clinical use)\b)/', "\n", $text) ?? $text;
        $text = preg_replace('/(?<=[.!?])\s+(?=(?:After|Before|During|When|If|Do not|Filtered|This|The|In |By |For |Use |Avoid |Ensure |Monitor |Check )\b)/', "\n", $text) ?? $text;
        $text = preg_replace('/\s+(?=Do not recommend\b)/i', "\n", $text) ?? $text;
        $text = preg_replace('/(?<=[A-Za-z])(?=\d)/', ' ', $text) ?? $text;
        $text = preg_replace('/(?<=\d)(?=[A-Za-z])/', ' ', $text) ?? $text;
        $text = preg_replace('/(?<=[a-z])(?=[A-Z][a-z])/', ' ', $text) ?? $text;
        $text = preg_replace('/[ ]{2,}/', ' ', $text) ?? $text;
        $text = preg_replace('/\*{2,}(?=\s|$)/', '', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*{1,3}(?=[A-Z])/', "\n* ", $text) ?? $text;
        $text = preg_replace('/\s+(?=(?:[•*]\s*|-\s+|\d{1,2}[.)]\s+|[IVXLCDM]+\.\s+[A-Z]|(?:MODULE|SECTION|CHAPTER|ANNEX)\s+\d+))/u', "\n", $text) ?? $text;

        $lines = collect(preg_split('/\n+/', $text) ?: [])
            ->map(fn (string $line): string => self::formatLine($line))
            ->filter()
            ->values()
            ->all();

        return implode("\n", $lines);
    }

    private static function formatLine(string $line): string
    {
        $line = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
        $line = rtrim($line, " \t\n\r\0\x0B*");

        if ($line === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}\.$/', $line)) {
            return '';
        }

        if (preg_match('/^Speaker notes?:\s*(.*)$/i', $line, $matches)) {
            return '**Speaker Notes**'.(trim($matches[1]) !== '' ? "\n".trim($matches[1]) : '');
        }

        if (preg_match('/^[•*]\s*(.+)$/u', $line, $matches)) {
            return '- '.trim($matches[1]);
        }

        if (preg_match('/^-\s*(.+)$/u', $line, $matches)) {
            return '- '.trim($matches[1]);
        }

        if (preg_match('/^\d{1,2}[.)]\s+/', $line)) {
            return $line;
        }

        if (preg_match('/^(.+?)\s+(Speaker notes?:)\s*(.*)$/i', $line, $matches)) {
            return trim($matches[1])."\n\n**Speaker Notes**".(trim($matches[3]) !== '' ? "\n".trim($matches[3]) : '');
        }

        if (preg_match('/^(Risks?)-\s*(.+)$/i', $line, $matches)) {
            return '**'.self::titleCaseHeading($matches[1])."**\n- ".trim($matches[2]);
        }

        if (self::looksLikeSlideHeading($line)) {
            return '**'.self::titleCaseHeading($line).'**';
        }

        if (self::looksLikeHeading($line)) {
            return '**'.self::titleCaseHeading($line).'**';
        }

        return $line;
    }

    private static function looksLikeSlideHeading(string $line): bool
    {
        if (mb_strlen($line) > 90 || str_ends_with($line, '.')) {
            return false;
        }

        return (bool) preg_match('/^(Role of|Management of|Assessment of|Treatment of|Prevention of|Monitoring|Clinical use|Objectives|Summary|Introduction|Complications|Advantages)\b/i', $line);
    }

    private static function looksLikeHeading(string $line): bool
    {
        if (mb_strlen($line) > 140 || str_ends_with($line, '.')) {
            return false;
        }

        $letters = preg_replace('/[^A-Za-z]/', '', $line) ?? '';

        return mb_strlen($letters) >= 6 && mb_strtoupper($letters) === $letters;
    }

    private static function titleCaseHeading(string $line): string
    {
        $heading = Str::of(Str::lower($line))->headline()->toString();

        $acronyms = [
            'Aop', 'Bls', 'Cpap', 'Dka', 'Enc', 'Ipc', 'Ifcdc', 'Kmc', 'Moh',
            'Ngt', 'Ogt', 'O2', 'Tb',
        ];

        foreach ($acronyms as $acronym) {
            $heading = preg_replace('/\b'.$acronym.'\b/', mb_strtoupper($acronym), $heading) ?? $heading;
        }

        return $heading;
    }
}
