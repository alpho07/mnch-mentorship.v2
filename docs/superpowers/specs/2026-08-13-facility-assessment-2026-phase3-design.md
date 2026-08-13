# Facility Readiness Assessment 2026 — Phase 3: Visual Polish

**Status:** Approved
**Date:** 2026-08-13
**Depends on:** Phase 1 (engine) and Phase 2 (2026 content), both merged to `main`.

## Context

Phase 1's design doc deferred "final visual design of indentation, lettering typography, badge styling" to Phase 3, on the assumption the underlying mechanisms rendered correctly and only needed cosmetic refinement. Live-testing the real 2026 content in a browser (seeding a test assessment, walking Infrastructure/Skills Lab/Health Products/Information Systems/Quality of Care) found that assumption wrong in two places: the mechanisms don't just need refinement, they have real bugs. Both are root-caused below with exact file:line references.

## Bug 1: Indentation never renders for plain fields

**Symptom:** Infrastructure's conditionally-revealed bed-capacity questions (`INFRA_NBU_GENERAL_FUNCTIONAL` etc., `number` type, `indent_level = 1`) render completely flush-left — visually indistinguishable from their parent "Do you have a newborn unit" question, both on a fresh reactive reveal and on a full server-rendered page load with the answer already saved.

**Root cause:** `DynamicFormBuilder::buildForSection()` (`app/Services/DynamicFormBuilder.php:79`) applies the margin via `$field->extraAttributes(['style' => 'margin-left: 1.5rem;'], merge: true)`. Confirmed via direct PHP inspection that `extraAttributes()` correctly registers on the `$field` object (`getExtraAttributes()` returns the style) — but confirmed via live DOM inspection of the rendered page that the style attribute is **absent from every one of 8 ancestor elements**, including `.fi-fo-field-wrp` (the semantic "field row" container). `extraAttributes()` targets the component's own root wrapper, which for an atomic `TextInput` field is not the same DOM node as `.fi-fo-field-wrp`.

Filament ships a **different, purpose-built API for exactly this**: `extraFieldWrapperAttributes()` (trait `HasExtraFieldWrapperAttributes`, confirmed present on the `Field` base class every question type extends). Confirmed via `vendor/filament/forms/resources/views/components/field-wrapper/index.blade.php:57` that this method's output is merged directly onto `.fi-fo-field-wrp`.

Quality of Care's indented follow-ups (`QOC_NEONATAL_MOH527` etc., `yes_no` type) show a subtle ~24px shift today — because `yes_no` fields are built as a `Group` wrapping [Radio, Textarea], and `extraAttributes()` on a `Group` happens to land on a container that does affect visible position, unlike a bare `TextInput`. This is an accidental, inconsistent side effect of the field's internal structure, not a reliable mechanism — switching to `extraFieldWrapperAttributes()` makes indentation work identically and correctly for every question type, not just the ones where it happens to land somewhere useful today.

**Fix:** In `DynamicFormBuilder::buildForSection()`, replace:
```php
if ($question->indent_level > 0 && method_exists($field, 'extraAttributes')) {
    $field->extraAttributes(['style' => 'margin-left: 1.5rem;'], merge: true);
}
```
with:
```php
if ($question->indent_level > 0 && method_exists($field, 'extraFieldWrapperAttributes')) {
    $field->extraFieldWrapperAttributes(['style' => 'margin-left: 1.5rem;'], merge: true);
}
```

## Bug 2: MoH-forms table headers show raw internal IDs

**Symptom:** Information Systems' 24-form Available/Completeness table renders column headers as literal text `Table header col c434a7631fe2e98f373fd6c3661cd153 1` / `... 2` instead of "Available" / "Complete" — affecting all 24 forms × 2 columns (48 header cells total).

**Root cause:** `GroupedFieldRenderer::buildTableFieldset()` (`app/Services/FormKernel/GroupedFieldRenderer.php:139-144`) builds each header cell via:
```php
Forms\Components\Placeholder::make('table_header_col_'.md5($title).'_'.count($cells))
    ->label(method_exists($field, 'getLabel') ? $field->getLabel() : '')
```
`$field` here is each column's already-built component from `$rows[0]['fields']` — for a `yes_no`-type table column, that's the `Group` wrapper `QuestionFieldBuilder::buildYesNoPartialField()` returns (Radio + conditional Textarea), not the Radio itself. `Group` does have a `getLabel()` method (confirmed via direct inspection — inherited from a shared base), so `method_exists()` passes, but it returns `null` (Groups aren't meant to carry their own label — the semantic label lives on the Radio inside). Calling `->label(null)` is indistinguishable to Filament from "no label was set," so it falls back to auto-humanizing the `Placeholder`'s own `make()` name string — the MD5-hash-containing internal ID — into the visible header text.

**Fix:** Add a recursive label-resolution helper to `GroupedFieldRenderer` that, when a component's own label is empty, looks at its child components (available on any layout component via `getChildComponents()`) for the first one with a real label:
```php
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
```
Used at both header-building call sites (`table_header_rowlabel_*` already gets its label from `$rows[0]['header']`, a plain string, unaffected; `table_header_col_*` switches from the direct `getLabel()` call to `static::resolveFieldLabel($field)`).

This is generic, not yes_no-specific — it fixes the same latent bug for **any** grouped-table column whose built field happens to be a layout component rather than a bare Field, and since `GroupedFieldRenderer` is shared kernel code also used by `SurveyFormBuilder`, the fix benefits the Survey platform's table rendering too, not just this assessment.

## Polish pass (after both bugs are fixed)

Re-verify in browser, screenshot each of the 7 dynamic-question sections plus Health Products, confirm:
- Infrastructure's bed-capacity fields visibly nest under their gating question.
- Quality of Care's follow-ups keep their (now-consistent) indent.
- Information Systems' table renders "Available"/"Complete" headers correctly.
- Skills Lab's a)/b) branching (no indent_level used there, per Phase 2 design — pure conditional reveal) still displays correctly — confirms Bug 1's fix didn't regress the (already-correct) non-indented conditional-reveal case.
- Health Products' lettered/indented commodities (already confirmed working) are unaffected by either fix.

No CSS/typography changes beyond what's needed to make the two fixes visually correct — deeper redesign (color, spacing system, badge treatments) is out of scope; this phase is "make the existing mechanism actually work," not a redesign.

## Non-goals

- Any change to 2025 `STANDARD_FACILITY_ASSESSMENT` rendering.
- Auto-computed bed-capacity totals (still deferred, per Phase 2 design doc).
- Redesigning the Health Products page's already-working layout.
- Any change to scoring, data model, or conditional-visibility logic — this phase is rendering-only.

## Testing

- Feature test: a question with `indent_level = 1` produces a Filament field whose `getExtraFieldWrapperAttributes()` contains the margin style (unit-level, mirrors how Phase 1 tested the original `extraAttributes` call).
- Feature test: `GroupedFieldRenderer::buildTableFieldset()` with a Group-wrapped (yes_no-shaped) column field produces a header `Placeholder` whose label is the child's real label ("Available"), not an empty/humanized fallback.
- Browser verification (screenshots) of the 5 affected pages before considering the phase complete, matching how the bugs were originally found.
