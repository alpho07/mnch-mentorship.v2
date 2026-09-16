# Phase 5b — Empty-State Audit

> Date: 2026-07-31
> Project: MNCH Mentorship Platform
> Scope: Phase 5 (cross-cutting standards) of the design overhaul, second sub-piece — empty states only.

---

## 1. Goal

Bring every remaining bare "No records found"-style empty state up to the guide's §14 standard (heading + explanation of why it's empty + a primary action where one genuinely exists) across mentor-side Filament resources, mentorship-management sub-pages, and coordinator-facing analytics views. Already-compliant surfaces from Phase 1 (mentee dashboard, class-progress, module-detail) are untouched.

---

## 2. Context

An `Explore` agent audit surveyed every Filament resource table and coordinator analytics view for missing/bare empty states, and each finding was independently re-verified by reading the actual file before being included here. One originally-flagged item, `ManageClassModules` ("No Modules Added Yet"), was downgraded and dropped from scope during that verification — its "Add Modules" action already renders in the page header at all times, visible even when the table is empty, so the empty state is already effectively actionable; adding a duplicate button inside the empty-state box would be redundant, not a real fix.

---

## 3. Fixes

### Bare resources — add heading + description + action
All four currently have zero `emptyState*` configuration and fall back to Filament's generic default.

| File | New heading | New description | Action |
|---|---|---|---|
| `app/Filament/Resources/ResourceResource.php` (`table()`, ends line 415) | "No Resources Yet" | "Add articles, guides, or files to the knowledge base." | `CreateAction::make()` |
| `app/Filament/Resources/CategoryResource.php` (`table()`, ends line 133) | "No Categories Yet" | "Categories help organize knowledge base resources." | `CreateAction::make()` |
| `app/Filament/Resources/ResourceTypeResource.php` (`table()`, ends line 144) | "No Resource Types Yet" | "Resource types classify knowledge base content (e.g. guide, video, form)." | `CreateAction::make()` |
| `app/Filament/Resources/AccessGroupResource.php` (`table()`, ends line 133) | "No Access Groups Yet" | "Access groups control which restricted resources a facility or role can see." | `CreateAction::make()` |

### Action exists, heading/description missing — add both
| File | New heading | New description |
|---|---|---|
| `app/Filament/Resources/ActivityResource.php:83` | "No Activities Yet" | "Activities (CME, Hands-on Demo, Drill) are assigned to mentorship modules." |
| `app/Filament/Resources/ProgramModuleResource.php:222` | "No Modules Yet" | "Modules make up a program's curriculum." |
| `app/Filament/Resources/ProgramModuleQuizResource.php:177` | "No Quizzes Yet" | "Quizzes are attached to a module as its pre-test or post-test." |

### Heading/description exist, real action missing — add it
- **`ManageModuleMentees.php:601-602`** ("No Mentees Enrolled" / "Enroll mentees in the class first.") — add an action linking to `MentorshipTrainingResource::getUrl('class-mentees', ['training' => $this->training->id, 'class' => $this->class->id])`, label "Enroll Mentees". This page's own `getHeaderActions()` has no enrollment entry point — enrollment happens on a different page — so this is a genuine, non-redundant fix.
- **`ManageMentorshipCoMentors.php:195-196`** ("No Classes Yet" / "Create a class cohort to assign co-mentors.") — add an action linking to `MentorshipTrainingResource::getUrl('classes', ['record' => $this->record->id])`, label "Create Class". Verified this page's header has only tab-switcher actions (Classes/Co-Mentors), no class-creation entry point — genuine fix.

### Coordinator analytics views
- **`resources/views/analytics/dashboard/mentor-mode.blade.php:334-338`** — split the merged sentence into a heading + explanation, and add the exact "Clear filters" link already used elsewhere on this page (`href="?mode=mentor"`, class `mf-clear`) — reused verbatim, not a new mechanism.
- **`resources/views/analytics/dashboard/index.blade.php:631-636`** (training-mode sidebar, "No training programs found for the selected period") — add a "Reset Period" link that clears the `year` query param via `request()->fullUrlWithQuery(['year' => null])`.
- **`resources/views/analytics/dashboard/emonc-mode.blade.php:340-344`** — cosmetic-only: split into a distinct heading + the existing explanatory text. No action added — confirmed the stated cause (programs must be tagged "Maternal EmONC" in their name) is configured elsewhere, not on this page, so there is genuinely nothing actionable here. This matches the guide's own allowance that a blocked/gated state without a fixable cause on the current page doesn't require inventing an action.

---

## 4. Business rules / edge cases

- None of these changes alter query logic, data, or business rules — this is presentation-layer only (Filament `emptyState*` builder methods and Blade markup).
- Every added action must route to an already-existing page/action — no new pages or routes are created in this phase.
- The `ManageClassModules` "already adequate" case is documented here explicitly so it isn't silently dropped or later mistaken for an oversight.

---

## 5. Testing

Given this is presentation-only (no new business logic, no new resolvers), the primary verification is manual: log in and visit each of the 12 in-scope surfaces in an empty-data state and confirm the heading, explanation, and (where applicable) action render and the action navigates correctly. No new PHPUnit test is warranted for a static string/builder-method change with no conditional logic to break — matching the project's own testing conventions (feature tests exist for behavior, not for verifying literal string content of a label).

---

## 6. Acceptance criteria

- [ ] All 4 bare Filament resources have heading + description + create action.
- [ ] All 3 "action but no heading" Filament resources have heading + description added, existing action untouched.
- [ ] `ManageModuleMentees` and `ManageMentorshipCoMentors` empty states each have a working action link to the correct page.
- [ ] `mentor-mode.blade.php`'s empty state has a distinct heading and a working "Clear filters" link.
- [ ] `index.blade.php`'s training-mode sidebar empty state has a working "Reset Period" link.
- [ ] `emonc-mode.blade.php`'s empty state has a distinct heading (no action, by design).
- [ ] `ManageClassModules` is left untouched, with this decision documented (not a silent omission).

---

## 7. Recommended implementation order

Single pass — all 12 fixes are independent, mechanical, low-risk changes to existing files. No dependencies between them; can be done in any order, verified together with one manual browser pass at the end.
