<?php

namespace App\Services\FormKernel;

/**
 * Pure, model-agnostic clustering of an ordered list into "line item runs":
 * consecutive entries sharing a group key AND an indent level >= 1 are
 * assigned sequential letters (a, b, c, ...); everything else gets no
 * letter. A run of exactly one indented item isn't a list, so it gets no
 * letter either — matches the spreadsheet convention of only lettering
 * genuine multi-item splits (e.g. "Suction catheter sizes: a) Fr-6 b) Fr-8").
 * Shared between AssessmentQuestion (DynamicFormBuilder) and Commodity
 * (EditHealthProducts) — both expose an ordered list, a group key, and an
 * indent level, nothing more is required.
 */
class LineItemGrouper
{
    /**
     * @param  iterable<int, object>  $items  Already in display order.
     * @param  callable(object): mixed  $groupKey
     * @param  callable(object): int  $indentLevel
     * @return array<int, array{item: object, letter: ?string, group_key: mixed, is_group_start: bool}>
     */
    public static function annotate(iterable $items, callable $groupKey, callable $indentLevel): array
    {
        $items = array_values(is_array($items) ? $items : iterator_to_array($items));
        $count = count($items);

        $keys = [];
        $levels = [];
        foreach ($items as $index => $item) {
            $keys[$index] = $groupKey($item);
            $levels[$index] = $indentLevel($item);
        }

        $annotated = [];
        $index = 0;

        while ($index < $count) {
            if ($levels[$index] < 1 || $keys[$index] === null) {
                $annotated[] = [
                    'item' => $items[$index],
                    'letter' => null,
                    'group_key' => $keys[$index],
                    'is_group_start' => false,
                ];
                $index++;

                continue;
            }

            $runStart = $index;
            $runEnd = $index;
            while ($runEnd < $count && $levels[$runEnd] >= 1 && $keys[$runEnd] === $keys[$runStart]) {
                $runEnd++;
            }
            $runLength = $runEnd - $runStart;

            for ($position = $runStart; $position < $runEnd; $position++) {
                $annotated[] = [
                    'item' => $items[$position],
                    'letter' => $runLength >= 2 ? static::letterFor($position - $runStart) : null,
                    'group_key' => $keys[$position],
                    'is_group_start' => $position === $runStart,
                ];
            }

            $index = $runEnd;
        }

        return $annotated;
    }

    /**
     * 0-based position -> letter: 0='a', 1='b', ..., 25='z', 26='aa', ...
     * (base-26, Excel-column style — the spreadsheet never needs past 'h').
     */
    public static function letterFor(int $position): string
    {
        $letter = '';
        $position++;

        while ($position > 0) {
            $position--;
            $letter = chr(97 + ($position % 26)).$letter;
            $position = intdiv($position, 26);
        }

        return $letter;
    }
}
