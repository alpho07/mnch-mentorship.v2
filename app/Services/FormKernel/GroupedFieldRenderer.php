<?php

namespace App\Services\FormKernel;

use Filament\Forms;

/**
 * Pure layout: turns a run-collapsed list of built fields into the actual
 * Filament components a section renders — grouped fieldsets, merged tables,
 * or the fields passed through untouched. No model dependency: operates
 * entirely on already-built Filament components and plain label/string
 * data, so it's identical for the assessment engine and the survey engine.
 */
class GroupedFieldRenderer
{
    /**
     * Second pass: turns each run into a rendered field/layout component.
     * Ungrouped runs (`group === null`) render their fields directly.
     * Table-row runs (see DynamicFormBuilder/SurveyFormBuilder's
     * buildGroupedField() convention) that share the same table title AND
     * appear consecutively merge into one table with a single shared header
     * row — everything else renders as its own component.
     */
    public static function renderRuns(array $runs): array
    {
        $fields = [];
        $tableBuffer = [];
        $tableTitle = null;

        $flushTable = function () use (&$fields, &$tableBuffer, &$tableTitle) {
            if ($tableBuffer !== []) {
                $fields[] = static::buildTableFieldset($tableTitle, $tableBuffer);
            }
            $tableBuffer = [];
            $tableTitle = null;
        };

        foreach ($runs as $run) {
            if ($run['group'] === null) {
                $flushTable();
                array_push($fields, ...$run['fields']);

                continue;
            }

            $parts = explode('|', $run['group']);

            if (count($parts) !== 3) {
                $flushTable();
                $fields[] = static::buildGroupFieldset($run['group'], $run['fields']);

                continue;
            }

            [$title, $rowLabelHeader, $rowLabel] = $parts;

            if ($tableTitle !== null && $tableTitle !== $title) {
                $flushTable();
            }

            $tableTitle = $title;
            $tableBuffer[] = ['header' => $rowLabelHeader, 'label' => $rowLabel, 'fields' => $run['fields']];
        }

        $flushTable();

        return $fields;
    }

    /**
     * A plain group: small groups (<=7 fields) lay out side by side,
     * table-style. Larger groups stay a single readable column.
     */
    public static function buildGroupFieldset(string $label, array $fields)
    {
        $columns = count($fields) <= 7 ? count($fields) : 1;

        if ($columns > 1) {
            static::normalizeColumnSpans($fields);
        }

        $fieldset = Forms\Components\Fieldset::make($label)
            ->schema($fields)
            ->columns(['default' => $columns, 'sm' => $columns, 'md' => $columns, 'lg' => $columns, 'xl' => $columns, '2xl' => $columns])
            ->columnSpanFull();

        if ($columns > 1) {
            $fieldset->extraAttributes(['class' => 'aqs-info-table']);
        }

        return $fieldset;
    }

    /**
     * Undoes any per-field-type forced ->columnSpanFull() so fields sit
     * side by side under a shared table/group header instead of stacking
     * full-width.
     */
    public static function normalizeColumnSpans(array $fields): void
    {
        foreach ($fields as $field) {
            if (method_exists($field, 'columnSpan')) {
                $field->columnSpan(1);
            }
        }
    }

    /**
     * Renders $rows as one table with a genuine, dedicated header row
     * followed by one full data row per entry in $rows.
     */
    public static function buildTableFieldset(string $title, array $rows)
    {
        $cells = [];

        $cells[] = Forms\Components\Placeholder::make('table_header_rowlabel_'.md5($title))
            ->label($rows[0]['header'])
            ->content('')
            ->extraAttributes(['class' => 'aqs-header-cell']);

        foreach ($rows[0]['fields'] as $field) {
            $cells[] = Forms\Components\Placeholder::make('table_header_col_'.md5($title).'_'.count($cells))
                ->label(method_exists($field, 'getLabel') ? $field->getLabel() : '')
                ->content('')
                ->extraAttributes(['class' => 'aqs-header-cell']);
        }

        foreach ($rows as $index => $row) {
            $rowLabelCell = Forms\Components\Placeholder::make('table_row_label_'.md5($title.$row['label'].$index))
                ->hiddenLabel()
                ->content($row['label']);

            $cells[] = $rowLabelCell;

            foreach ($row['fields'] as $field) {
                if (method_exists($field, 'hiddenLabel')) {
                    $field->hiddenLabel();
                }

                if (method_exists($field, 'columnSpan')) {
                    $field->columnSpan(1);
                }

                $cells[] = $field;
            }
        }

        $columnsPerRow = 1 + count($rows[0]['fields']);

        return Forms\Components\Fieldset::make($title)
            ->schema($cells)
            ->columns(['default' => $columnsPerRow, 'sm' => $columnsPerRow, 'md' => $columnsPerRow, 'lg' => $columnsPerRow, 'xl' => $columnsPerRow, '2xl' => $columnsPerRow])
            ->extraAttributes(['class' => 'aqs-data-table'])
            ->columnSpanFull();
    }
}
