# Facility Readiness Assessment 2026 — Phase 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the two rendering bugs found while live-testing the real 2026 content — indentation not appearing, and MoH-forms table headers showing raw internal IDs — then verify visually in browser.

**Architecture:** Both fixes are small, targeted changes to existing shared rendering code (`DynamicFormBuilder`, `GroupedFieldRenderer`). No schema, scoring, or data changes.

**Tech Stack:** Laravel 12, Filament v3, PHPUnit.

## Global Constraints

- Neither fix may change 2025 `STANDARD_FACILITY_ASSESSMENT` rendering — both are additive/corrective to shared code paths, verified via the existing regression suite.
- `GroupedFieldRenderer` is shared with the Survey platform (`SurveyFormBuilder`) — the Bug 2 fix must not assume assessment-specific structure.
- Run `php artisan test --filter='FacilityAssessment2026|Assessment|Survey'` after each task; run full `composer test` before the final task's commit.

---

### Task 1: Fix indentation — `extraFieldWrapperAttributes` instead of `extraAttributes`

**Files:**
- Modify: `app/Services/DynamicFormBuilder.php`
- Test: `tests/Feature/AssessmentLineItemQuestionsTest.php` (extend)

**Interfaces:**
- No new public interface — corrects the existing indent mechanism from Phase 1 Task 3.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/AssessmentLineItemQuestionsTest.php`:

```php
    public function test_indented_number_question_gets_extra_field_wrapper_style_not_extra_attributes(): void
    {
        $type = AssessmentType::create(['name' => 'Indent Wrapper Test', 'code' => 'INDENT_WRAPPER_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_wrap',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'INDENT_NUMBER_Q',
            'question_text' => 'Bed count', 'question_type' => 'number', 'indent_level' => 1,
            'order' => 1, 'is_active' => true,
        ]);

        $fields = \App\Services\DynamicFormBuilder::buildForSection($section->id, null);
        $field = $fields[0];

        $this->assertSame(['style' => 'margin-left: 1.5rem;'], $field->getExtraFieldWrapperAttributes());
        $this->assertArrayNotHasKey('style', $field->getExtraAttributes());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_indented_number_question_gets_extra_field_wrapper_style_not_extra_attributes`
Expected: FAIL — `getExtraFieldWrapperAttributes()` returns `[]`, the style is still on `getExtraAttributes()`.

- [ ] **Step 3: Apply the fix**

In `app/Services/DynamicFormBuilder.php`, find the indent-applying line (currently reads `$field->extraAttributes(['style' => 'margin-left: 1.5rem;'], merge: true);` inside the `if ($question->indent_level > 0 ...)` block within `buildForSection()`) and replace:

```php
            if ($question->indent_level > 0 && method_exists($field, 'extraFieldWrapperAttributes')) {
                $field->extraFieldWrapperAttributes(['style' => 'margin-left: 1.5rem;'], merge: true);
            }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_indented_number_question_gets_extra_field_wrapper_style_not_extra_attributes`
Expected: PASS

- [ ] **Step 5: Run the full AssessmentLineItemQuestionsTest to confirm no regression**

Run: `php artisan test --filter=AssessmentLineItemQuestionsTest`
Expected: PASS (both tests) — the existing lettered-rendering test doesn't assert on `style` specifically, only on visible letter text, so it's unaffected by this change.

- [ ] **Step 6: Run the broader regression check**

Run: `php artisan test --filter='FacilityAssessment2026|Assessment'`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Services/DynamicFormBuilder.php tests/Feature/AssessmentLineItemQuestionsTest.php
git commit -m "fix: indent conditionally-revealed questions via extraFieldWrapperAttributes, not extraAttributes"
```

---

### Task 2: Fix MoH-forms table header labels

**Files:**
- Modify: `app/Services/FormKernel/GroupedFieldRenderer.php`
- Test: `tests/Unit/FormKernel/GroupedFieldRendererTest.php` (new)

**Interfaces:**
- Produces: `GroupedFieldRenderer::resolveFieldLabel(mixed $field): string` (private, but the table-header behavior it fixes is covered by the test below through the public `buildTableFieldset()` entry point).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\FormKernel;

use App\Services\FormKernel\GroupedFieldRenderer;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Tests\TestCase;

class GroupedFieldRendererTest extends TestCase
{
    public function test_table_header_resolves_label_from_a_group_wrapped_field(): void
    {
        $radio = Radio::make('question_response_1')->label('Available');
        $group = Group::make([$radio]);

        $rows = [
            [
                'header' => 'Form',
                'label' => 'MoH 204 A',
                'fields' => [$group],
            ],
        ];

        $fieldset = GroupedFieldRenderer::buildTableFieldset('Data Collection Tools', $rows);
        $headerCell = $fieldset->getChildComponents()[1];

        $this->assertSame('Available', $headerCell->getLabel());
    }

    public function test_table_header_still_resolves_label_from_a_bare_field(): void
    {
        $input = TextInput::make('question_response_2')->label('Count');

        $rows = [
            [
                'header' => 'Form',
                'label' => 'Some Row',
                'fields' => [$input],
            ],
        ];

        $fieldset = GroupedFieldRenderer::buildTableFieldset('Bare Field Table', $rows);
        $headerCell = $fieldset->getChildComponents()[1];

        $this->assertSame('Count', $headerCell->getLabel());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GroupedFieldRendererTest`
Expected: FAIL — `test_table_header_resolves_label_from_a_group_wrapped_field` fails because the header cell's label is `null`/empty, not `'Available'`.

- [ ] **Step 3: Apply the fix**

In `app/Services/FormKernel/GroupedFieldRenderer.php`, replace the header-column-building line inside `buildTableFieldset()`:

```php
        foreach ($rows[0]['fields'] as $field) {
            $cells[] = Forms\Components\Placeholder::make('table_header_col_'.md5($title).'_'.count($cells))
                ->label(method_exists($field, 'getLabel') ? $field->getLabel() : '')
                ->content('')
                ->extraAttributes(['class' => 'aqs-header-cell']);
        }
```

with:

```php
        foreach ($rows[0]['fields'] as $field) {
            $cells[] = Forms\Components\Placeholder::make('table_header_col_'.md5($title).'_'.count($cells))
                ->label(static::resolveFieldLabel($field))
                ->content('')
                ->extraAttributes(['class' => 'aqs-header-cell']);
        }
```

Then add the new method (placed after `buildTableFieldset()`):

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=GroupedFieldRendererTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Run the broader regression check**

Run: `php artisan test --filter='FacilityAssessment2026|Assessment|Survey'`
Expected: PASS — this is shared kernel code also used by `SurveyFormBuilder`; the Survey filter catches any table-rendering usage there too.

- [ ] **Step 6: Commit**

```bash
git add app/Services/FormKernel/GroupedFieldRenderer.php tests/Unit/FormKernel/GroupedFieldRendererTest.php
git commit -m "fix: resolve table header labels from group-wrapped fields, not just bare fields"
```

---

### Task 3: Browser verification pass

**Files:** none (verification only — screenshots, no code changes expected unless verification surfaces a new issue, in which case stop and report before proceeding).

- [ ] **Step 1: Seed a fresh 2026 test assessment in the local dev database**

Run: `php artisan db:seed --class="Database\Seeders\FacilityAssessment2026\FacilityAssessment2026Seeder"` (idempotent — safe to re-run against the already-seeded dev DB from the Phase 3 investigation).

- [ ] **Step 2: Re-visit Infrastructure, answer "Do you have a newborn unit" = Yes, screenshot**

Confirm the bed-capacity fields now visibly nest under their gating question (non-zero, consistent left offset matching the Health Products page's indent).

- [ ] **Step 3: Re-visit Information Systems, screenshot the MoH-forms table**

Confirm column headers read "Available" / "Complete", not raw hash-containing IDs.

- [ ] **Step 4: Re-visit Quality of Care, screenshot**

Confirm the indented follow-ups (`QOC_NEONATAL_MOH527` etc.) still indent correctly and consistently with Infrastructure's now-fixed indent.

- [ ] **Step 5: Re-visit Skills Lab and Health Products, screenshot**

Confirm both are unaffected by either fix (Skills Lab's conditional reveal has no `indent_level`, so Task 1 shouldn't change it; Health Products doesn't use `GroupedFieldRenderer::buildTableFieldset()`, so Task 2 shouldn't change it).

- [ ] **Step 6: Run the full project test suite**

Run: `composer test`
Expected: PASS — final regression check before considering Phase 3 complete.

- [ ] **Step 7: Report findings**

If every screenshot confirms the fix, no further action. If any screen still looks wrong, stop and report the specific discrepancy rather than guessing at a further fix — matches how both bugs in this phase were found (live observation, not assumption).
