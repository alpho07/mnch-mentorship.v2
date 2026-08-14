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
        $tableVisible = null;

        $flushTable = function () use (&$fields, &$tableBuffer, &$tableTitle, &$tableVisible) {
            if ($tableBuffer !== []) {
                $fields[] = static::buildTableFieldset($tableTitle, $tableBuffer, $tableVisible);
            }
            $tableBuffer = [];
            $tableTitle = null;
            $tableVisible = null;
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
                $fields[] = static::buildGroupFieldset($run['group'], $run['fields'], $run['visible'] ?? null);

                continue;
            }

            [$title, $rowLabelHeader, $rowLabel] = $parts;

            if ($tableTitle !== null && $tableTitle !== $title) {
                $flushTable();
            }

            $tableTitle = $title;
            // Every row feeding into one table is expected to share the
            // same condition (e.g. every MoH-form row gated on the same
            // documentation-type answer) — carried forward from whichever
            // row sets it first, same "one shared closure for the whole
            // wrapper" idea buildGroupFieldset() already uses.
            $tableVisible ??= $run['visible'] ?? null;
            $tableBuffer[] = ['header' => $rowLabelHeader, 'label' => $rowLabel, 'fields' => $run['fields']];
        }

        $flushTable();

        return $fields;
    }

    /**
     * A plain group: small groups (<=7 fields) lay out side by side,
     * table-style, in a plain Fieldset (its <legend> already gets the
     * blue table-header banner via .aqs-info-table). Larger groups (a
     * kit's many checklist items) stay a single readable column, but
     * render as a collapsible Section instead — Fieldset has no native
     * collapse support, and a checklist with 9-20+ items benefits from
     * being collapsible. Its header gets the same banner look via
     * .aqs-kit-section, targeting Section's own header markup instead of
     * a <legend>.
     *
     * $visible, when given (a Closure or bool — whatever Filament's own
     * ->visible() accepts), is applied to the returned wrapper itself, not
     * just its child fields — without it, a group whose every field shares
     * one hidden-by-default condition (e.g. bed-capacity counts gated on
     * "Do you have a newborn unit") would render as an empty box with just
     * a legend before that condition is met, since each field hides
     * individually but the wrapper doesn't know to.
     */
    public static function buildGroupFieldset(string $label, array $fields, $visible = null)
    {
        $isLargeGroup = count($fields) > 7;

        if (! $isLargeGroup) {
            $columns = count($fields);

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

            if ($visible !== null) {
                $fieldset->visible($visible);
            }

            return $fieldset;
        }

        $section = Forms\Components\Section::make($label)
            ->schema($fields)
            ->collapsible()
            ->collapsed(false)
            ->columnSpanFull()
            ->extraAttributes(['class' => 'aqs-kit-section']);

        if ($visible !== null) {
            $section->visible($visible);
        }

        return $section;
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
     * followed by one full data row per entry in $rows. $visible, when
     * given, hides the whole table at once (e.g. the MoH-form registers
     * table only when the facility's documentation type includes paper or
     * hybrid) — same idea as buildGroupFieldset()'s own $visible param.
     */
    public static function buildTableFieldset(string $title, array $rows, $visible = null)
    {
        $cells = [];

        $cells[] = Forms\Components\Placeholder::make('table_header_rowlabel_'.md5($title))
            ->label($rows[0]['header'])
            ->content('')
            ->extraAttributes(['class' => 'aqs-header-cell']);

        foreach ($rows[0]['fields'] as $field) {
            $cells[] = Forms\Components\Placeholder::make('table_header_col_'.md5($title).'_'.count($cells))
                ->label(static::resolveFieldLabel($field))
                ->content('')
                ->extraAttributes(['class' => 'aqs-header-cell']);
        }

        foreach ($rows as $index => $row) {
            $rowLabelCell = Forms\Components\Placeholder::make('table_row_label_'.md5($title.$row['label'].$index))
                ->hiddenLabel()
                ->content($row['label']);

            $cells[] = $rowLabelCell;

            foreach ($row['fields'] as $field) {
                static::hideFieldLabel($field);

                if (method_exists($field, 'columnSpan')) {
                    $field->columnSpan(1);
                }

                $cells[] = $field;
            }
        }

        $columnsPerRow = 1 + count($rows[0]['fields']);

        $fieldset = Forms\Components\Fieldset::make($title)
            ->schema($cells)
            ->columns(['default' => $columnsPerRow, 'sm' => $columnsPerRow, 'md' => $columnsPerRow, 'lg' => $columnsPerRow, 'xl' => $columnsPerRow, '2xl' => $columnsPerRow])
            ->extraAttributes(['class' => 'aqs-data-table'])
            ->columnSpanFull();

        if ($visible !== null) {
            $fieldset->visible($visible);
        }

        return $fieldset;
    }

    /**
     * A column's built field may be a bare Field (its own ->getLabel() is
     * the answer) or a layout component like Group (yes_no's Radio +
     * conditional Textarea) whose own getLabel() exists but returns null —
     * Groups don't carry a semantic label themselves. Falls through to the
     * first child component with a real label in that case, recursively,
     * so nested layout components resolve correctly too.
     */
    private static function resolveFieldLabel($field): string
    {
        if (method_exists($field, 'getLabel') && filled($field->getLabel())) {
            return $field->getLabel();
        }

        if (method_exists($field, 'getChildComponents')) {
            foreach ($field->getChildComponents() as $child) {
                $label = static::resolveFieldLabel($child);
                if (filled($label)) {
                    return $label;
                }
            }
        }

        return '';
    }

    /**
     * A table cell's built field may be a bare Field (its own
     * ->hiddenLabel() suppresses the repeated "Available"/"Completeness"
     * text on every row) or a layout component like Group (yes_no's Radio
     * + conditional Textarea). Group also responds to hiddenLabel(), but
     * calling only that was a no-op — Group never renders a label of its
     * own, so the call succeeded and did nothing while the inner Radio's
     * genuine label kept rendering on every single row instead of just
     * the header. Always recurses into child components too, regardless
     * of whether the wrapper itself has hiddenLabel(), so nested leaf
     * fields get suppressed as well.
     */
    private static function hideFieldLabel($field): void
    {
        if (method_exists($field, 'hiddenLabel')) {
            $field->hiddenLabel();
        }

        if (method_exists($field, 'getChildComponents')) {
            foreach ($field->getChildComponents() as $child) {
                static::hideFieldLabel($child);
            }
        }
    }
}
