# Monthly Reports Empty-State Fix — Design Spec

**Status:** Approved, ready for implementation plan
**Phase:** Production-Safe System Audit, Phase 3 (Low-Risk UX Improvements)
**Related:** `docs/PHASE1-DISCOVERY-BASELINE.md` §9.1a (monthly_reports restored), §9.1 (facility-scoping fix)

## Problem

`monthly_reports` and its FK dependency `report_templates` were restored earlier this session (previously missing migrations entirely — see Phase 1 risk 9.1a). The feature is now schema-complete and access-controlled correctly, but **functionally unusable**: `report_templates` has zero rows, so `CreateMonthlyReport`'s `report_template_id` `Select` field (`app/Filament/Resources/MonthlyReportResource.php:40-46`, `->relationship('reportTemplate', 'name')->required()`) renders with no options. An admin opening the Create form today sees an empty required dropdown with no explanation — they cannot create a Monthly Report at all, and nothing on screen tells them why or what to do about it.

## Correction from initial framing

During design, checking the 44 real `Indicator` rows already in the database (`indicator_groups` breakdown) showed they span **both** newborn-care modules (Essential Newborn Care, Oxygen Therapy, Thermoregulation, Newborn Resuscitation, Danger Signs and Sepsis, Care of Small/Sick Newborns) **and** pediatric/child-care modules (Respiratory Distress, Dehydration, SAM, Altered Consciousness, Diabetes, Documentation) — not purely newborn as first assumed. The starter template is scoped as `report_type: general` covering all 44, rather than `newborn`-typed covering a subset, since that's what the actual data supports without fabricating a split that isn't there.

## Design

### 1. Seed one real starter `ReportTemplate`

A new seeder, `ReportTemplateSeeder`, creates exactly one record:

```php
ReportTemplate::firstOrCreate(
    ['code' => 'MONTHLY_FACILITY_INDICATORS'],
    [
        'name' => 'Monthly Facility Indicators Report',
        'description' => 'Monthly facility-level report covering all current indicators (newborn and pediatric/child care modules).',
        'report_type' => 'general',
        'frequency' => 'monthly',
        'is_active' => true,
    ]
);
```

Then attaches all 44 existing `Indicator` rows via the `report_template_indicators` pivot (`sort_order` following each indicator's own `sort_order`/`id`, `is_required` defaulting to `true`), using `ReportTemplate::indicators()->syncWithoutDetaching()` so re-running the seeder is safe.

This is **not** added to `DatabaseSeeder`'s active call list (that list is already broken by `MenteeSeeder`'s hardcoded path — see Phase 1 risk notes — so nothing in it actually runs via a plain `db:seed` today). It's run standalone via `php artisan db:seed --class=ReportTemplateSeeder --force`, matching how the EmONC seeders were run earlier this session.

### 2. Defensive empty-state helper text

`report_template_id`'s `Select` gets a conditional `->helperText()`: when `ReportTemplate::active()->count() === 0`, show "No report templates exist yet — create one first" with a link to `ReportTemplateResource::getUrl('create')`. When at least one exists (the normal case, once the seeder runs), no helper text is shown — unchanged from today.

This is deliberately **not** `createOptionForm()` (Filament's inline-create-from-dropdown pattern, already used elsewhere in this codebase for simpler single-field pickers like Facility/Training). A `ReportTemplate` needs `report_type`, `frequency`, and indicator selections to be meaningful — an inline modal for that is a bigger, separate feature, not a low-risk empty-state fix. This helper text is purely defensive (covers the case where someone later deactivates the seeded template); the seeder in part 1 is what actually fixes today's blocking problem.

## Files

- Create: `database/seeders/ReportTemplateSeeder.php`
- Modify: `app/Filament/Resources/MonthlyReportResource.php` (add conditional `->helperText()` to the existing `report_template_id` field, no other changes to that field)

## Testing

- Feature test: seeding creates exactly one `ReportTemplate` with 44 attached indicators, is idempotent (running twice doesn't duplicate).
- Feature test: `CreateMonthlyReport`'s form shows no helper text when a template exists; shows the guidance text (via `Livewire::test(...)->assertSee(...)`) when `ReportTemplate::query()->delete()` first (simulating the empty case).

## Out of scope (explicitly deferred)

- `createOptionForm()` inline template creation.
- Any other Monthly Reports UX polish beyond this specific empty-dropdown problem — not audited further this round, per the approved scope.
- EmONC flow verification — handled separately as a browser-verification task, not part of this design (no UX design decision needed there yet; verifying it renders is the prerequisite step).
