# Guided Mentorship Setup — Current-State Reference

Documents the guided wizard exactly as implemented today (2026-08-01), field
by field and rule by rule. This is a **reference**, not a design doc — it
exists so a future conversational replacement can be built to full behavioral
parity. Where the original design spec
(`docs/superpowers/specs/2026-07-31-guided-mentorship-wizard-design.md`) has
since drifted from the code (e.g. draft-resumability was added after that
spec explicitly ruled it out as a non-goal), the code below is treated as the
source of truth.

Primary file: `app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php` (1137 lines).

## 1. What it is

A single Filament page (`GuidedMentorshipSetup`, not a `CreateRecord` page)
hosting one `Forms\Components\Wizard` with 7 steps, walking a coordinator
through: mentorship details → first class → modules → enroll mentees → send
invitations. It is a **parallel** path to the existing single-page
`CreateMentorshipTraining` form — both remain available, and each step calls
the exact same underlying services/mutation logic those separate pages use,
so there is no duplicated business logic.

- Route: `MentorshipTrainingResource::getUrl('guided-setup')` →
  `/admin/mentorship/guided-setup` (`getPages()` key `'guided-setup'`).
- View: `resources/views/filament/pages/guided-mentorship-setup.blade.php`.
- `protected static bool $shouldRegisterNavigation = false` — reached only
  via the list page's button or the pending-setup banner's "Continue" link,
  never shown in the sidebar itself.

## 2. Access control

```php
public static function canAccess(array $parameters = []): bool
{
    if (! parent::canAccess($parameters)) return false;      // Filament Shield: create_mentorship::training permission
    if (request()->filled('training')) return true;          // resuming — always allowed
    return Setting::getBool(Setting::GUIDED_SETUP_BUTTON_ENABLED);
}
```

- Base gate is the same Shield permission as reaching
  `MentorshipTrainingResource` at all (`create_mentorship::training` /
  `view_any_mentorship::training` — see the test helper
  `actingAsCoordinator()`). No extra permission beyond that.
- A global on/off switch (`Setting::GUIDED_SETUP_BUTTON_ENABLED`, boolean,
  cached forever under `setting:guided_setup_button_enabled`, default
  **true**) lives at **Mentorship Settings** (`app/Filament/Pages/MentorshipSettings.php`,
  gated by the `update_program` permission). Toggling it off:
  - Disables (greys out, tooltip "Turned off in Mentorship Settings") the
    "New Mentorship Guided Setup" header button on the list page
    (`ListMentorshipTrainings::getHeaderActions()`).
  - Blocks a **fresh** visit to `/guided-setup` (no `?training=` query
    param) via `canAccess()`.
  - Does **not** block resuming an in-progress wizard — a `?training=`
    param on the URL bypasses the check entirely, so turning the method off
    can never strand a mentor mid-way through with modules/mentees already
    committed to the DB.
  - A sibling setting, `Setting::NEW_MENTORSHIP_BUTTON_ENABLED`, independently
    toggles the plain "New Mentorship" button the same way.
- Per-program gating is separate: `Program::isSelectableBy($user)` — see
  §4.3.

## 3. State model

Livewire component properties (`GuidedMentorshipSetup` class):

| Property | Type | Purpose |
|---|---|---|
| `$data` | `?array` | Filament form state, `statePath('data')` |
| `$trainingId` (`#[Url(as: 'training')]`) | `?int` | Resume key — Training PK |
| `$classId` (`#[Url(as: 'class')]`) | `?int` | Resume key — MentorshipClass PK |
| `$urlIsPilot` (`#[Url(as: 'pilot')]`) | `?int` | Mirrors `is_pilot` before a Training exists |
| `$urlCountyId` (`#[Url(as: 'county')]`) | `?int` | Mirrors `county_id` before a Training exists |
| `$urlFacilityId` (`#[Url(as: 'facility')]`) | `?int` | Mirrors `facility_id` before a Training exists |
| `$training` | `?Training` | The record being built |
| `$class` | `?MentorshipClass` | The record being built |
| `$completed` | `bool` | Flips true after Send Invitations succeeds — swaps the Blade view to the Done panel |
| `$classStarted` | `bool` | Whether `sendInvitations()` also auto-started the class |
| `$moduleDates` | `array` | EmONC-only per-module `{program_module_id: {start, end}}` side-channel, not a form field |
| `$enrolledCount`, `$invitedCount` | `int` | Surfaced on the Done screen |

Steps 1–2 (Run Type, Location) run **before** a `Training` row exists, so
their state can't be resumed from the DB on refresh — it's mirrored into the
URL query string instead (`pilot`, `county`, `facility`). From step 3 onward,
the `Training`/`MentorshipClass` records themselves are the durable state,
addressed via `training`/`class` URL params.

## 4. `mount()` — resume logic (runs on every page load)

Order of precedence, later steps override earlier ones:

1. Static defaults: `module_ids = []`, `selected_users = []`,
   `auto_create_sessions = true` (must be seeded explicitly — fill() is
   called with an explicit array, which bypasses component `->default()`).
2. If `$urlIsPilot`/`$urlCountyId`/`$urlFacilityId` are present, seed
   `is_pilot`/`county_id`/`facility_id` from them (pre-Training resume).
3. If `$trainingId` resolves to a real `Training`: overwrite
   `is_pilot`, `county_id`, `facility_id`, `program_id`, `start_date`,
   `end_date`, `max_participants` from the record (authoritative once it
   exists), and restore `$this->moduleDates` from
   `$training->guided_setup_draft['moduleDates']`.
4. If `$classId` resolves to a real `MentorshipClass`: seed `class_name`,
   `class_start_date`, `class_end_date`, `class_description` from it.
   For `module_ids`/`selected_users`, **the draft is authoritative once it
   has been touched, even if it lists fewer ids than what's really
   assigned** — an explicit empty draft array means "I unchecked
   everything," not staleness to correct. Only fall back to what's really
   in the DB (`classModules()->pluck(...)` / `participants()->pluck(...)`)
   when the draft has **never** set that key at all
   (`array_key_exists` check, not empty-check).

This dual-source design is why there are two distinct, separately-tested
mount() behaviors: "no draft yet → mirror DB" vs "draft exists → draft wins
even if narrower."

## 5. The `guided_setup_draft` mechanism (cross-session resumability)

Added after the original design spec (which had explicitly ruled out
draft-resumability) — this is now core behavior.

- `Training.guided_setup_draft` — JSON/array column, nullable.
- `Training.guided_setup_completed_at` — timestamp, null until Send
  Invitations succeeds.
- `saveWizardDraft(string $key, mixed $state)`: no-ops if `$this->training`
  doesn't exist yet; otherwise merges `$state` into the existing draft array
  under `$key` and saves. Flat id lists (`module_ids`, `selected_users`) are
  re-indexed via `array_values()`; the `moduleDates` map (keyed by
  `program_module_id`, not a list) is preserved as-is — distinguished via
  `array_is_list()`.
- `clearWizardDraft(string $key)`: drops one key once its picks become real
  DB rows, so a stale draft entry can't be misapplied on a later resume.
  Saves `null` (not `[]`) if the draft becomes empty.
- Livewire's `updatedModuleDates()` hook fires automatically whenever
  `$moduleDates` changes (set via the picker's modal) and persists it via
  `saveWizardDraft('moduleDates', ...)` — this is the one piece of state
  that's a plain page property rather than a form field, because it's a
  side-channel to `module_ids` rather than something needing its own form
  validation.
- Draft keys currently used: `module_ids`, `selected_users`, `moduleDates`.
- On successful `sendInvitations()`: `guided_setup_completed_at = now()` and
  `guided_setup_draft = null` (full clear).

### Resume entry point: `PendingGuidedSetupNotice` widget

- Shown on the Mentorships list page header (`ListMentorshipTrainings::getHeaderWidgets()`,
  hidden when the `home` tab is active).
- `canView()`: true iff the current user has a `Training` matching
  `Training::pendingGuidedSetup()` (facility_mentorship type,
  `guided_setup_completed_at IS NULL`) with `mentor_id = auth()->id()`
  (latest one if multiple).
- Renders a "Continue" link to
  `guided-setup?training={id}&class={classId}&step={modules|first-class}`
  (step name depends on whether a class already exists) and a "Discard"
  action that **force-deletes** the Training (cascades to class/modules/
  participants via FK `cascadeOnDelete`) — same style of hard-delete as the
  auto-supersession below.

### Auto-supersession of abandoned drafts

On a successful `sendInvitations()`, `discardSupersededDrafts()` force-deletes
**every other** `pendingGuidedSetup()` Training belonging to the same mentor.
Rationale: if a mentor started a wizard, got partway, walked away, and
started a fresh one instead of resuming, finishing the fresh one should stop
the banner nagging about the abandoned one — not leave two "pending" records
competing.

## 6. Step-by-step

The `Wizard` is configured `->persistStepInQueryString('step')->skippable(false)`
— skippable(false) means **wizard-level** step-skipping (jumping ahead in
the stepper UI) is off, but individual steps can still have empty/no-op
input and simply pass validation (see Modules/Enroll Mentees below — those
are "skippable" in the sense of "allowed to submit with nothing selected,"
not in the sense of bypassing the step's UI).

### Step 1 — Run Type
- Copy: *"Is this a real live mentorship or a pilot/test run?"*
- Field: `is_pilot` — `Radio`, options `0 => Live Mentorship`, `1 => Pilot
  Run`, descriptions explaining dashboard/analytics inclusion vs exclusion.
  `default(0)`, `required()`, `inline(false)`, `live()`.
- `afterStateUpdated`: mirrors the picked value into `$this->urlIsPilot`
  (URL sync, see §3).
- No DB persistence at this step (in-memory / URL only until step 3).

### Step 2 — Location
- Copy: *"Where is this mentorship being conducted?"*
- Fields (2-column grid):
  - `county_id` — searchable, preloaded `Select` of all `County` ordered by
    name. `required()`, `live()`. On change: clears `facility_id`, mirrors
    to `$urlCountyId`, clears `$urlFacilityId`.
  - `facility_id` — searchable `Select`, options computed from
    `Facility::whereHas('subcounty', county_id = selected)`, labelled
    `"{mfl_code} — {name}"`. `disabled()` until a county is picked.
    `required()`, `live()`. On change: mirrors to `$urlFacilityId`.
- No DB persistence at this step.

### Step 3 — Program & Schedule
- Copy: *"What program is being mentored, and when?"*
- Fields:
  - `program_id` — custom `ProgramPicker` component (card grid, not a
    dropdown — see §7.1). `required()`. Custom validation rule: rejects the
    submission ("That program is not active — pick a different one.") if
    `Program::isSelectableBy(auth()->user())` is false for the picked
    program, even though the picker itself renders inactive programs as
    visible-but-disabled cards (defense in depth against a manually-forced
    disabled selection).
  - `start_date` / `end_date` (3-col grid alongside `max_participants`):
    **hidden and not required for EmONC programs** (`isEmoncProgram()` —
    see §8). Otherwise: `required()`, native picker off, `start_date`
    `minDate(today())`, `end_date` `minDate($get('start_date') ?? now())`
    and `afterOrEqual('start_date')`.
  - `max_participants` — numeric `TextInput`, `default(10)`,
    `minValue(2)`, `maxValue(10)` — **hard cap of 10 mentees regardless of
    program**.
- `afterValidation`: calls `createTraining()` (§8.1) wrapped in try/catch —
  any thrown exception routes through `stepFailed()` (danger notification +
  `Halt`, keeping the user on this step; see §9).

### Step 4 — First Class
- Copy: *"Let's create your first class or cohort."*
- Read-only intro placeholder showing the selected program's name.
- Fields:
  - `class_name` — `TextInput`, `required()`, `maxLength(255)`.
  - `class_start_date` / `class_end_date` (2-col grid): **hidden/not
    required for EmONC**. Otherwise `required()`; `class_start_date`
    `minDate($this->training->start_date)`, `class_end_date`
    `minDate($get('class_start_date') ?: $training->start_date)`, both
    `maxDate($this->training->end_date)`, `afterOrEqual('class_start_date')`
    on the end date. Helper text spells out the allowed window when both
    training dates are known.
  - `class_description` — `Textarea`, 3 rows, optional.
- `afterValidation`: calls `createFirstClass()` (§8.2), same try/catch →
  `stepFailed()` pattern.

### Step 5 — Modules
- Copy: *"Now let's add modules to this class. You can skip this and add
  them later."* — genuinely skippable (Next with zero selected is valid).
- If `$this->training`/`$this->class` aren't set yet (shouldn't normally
  happen given step ordering, but guarded): shows a placeholder instead of
  the picker.
- Program-name intro placeholder, same style as step 4.
- **Branches by program type** (§8):
  - **EmONC programs** → `EmoncModulePicker` (`module_ids`), configured
    `->training()->class()->includeAssigned()->live()`. Re-seeds
    `$this->moduleDates` from any already-persisted `ClassModule.start_date/
    end_date` for rows not already in the in-memory array (handles "Next
    then Back" — see inline comment at line ~422). Custom validation rule
    calls `validateModuleDates()` (§6.1) and fails the step if any checked
    module/track lacks a valid date range.
  - **Standard programs** → `CardCheckboxList` (`module_ids`) over
    `ProgramModule::where(program_id, is_active=true, parent_id=null)`
    ordered by `order_sequence`. No date requirement.
  - Both pickers: `live()`, `afterStateUpdated` → `saveWizardDraft('module_ids', $state)`
    on every change (not just on Next).
- `auto_create_sessions` — `Toggle`, `default(true)`, **`disabled()` +
  `dehydrated(true)`** — always on, not user-editable, but must still be
  seeded in `mount()`'s explicit fill array (disabled fields don't get
  `->default()` applied automatically) or it silently renders unchecked.
- `afterValidation`: calls `assignModules()` (§8.3) with `module_ids`,
  `auto_create_sessions`, and the current `$this->moduleDates` side-channel.

#### 6.1 EmONC module-date validation (`validateModuleDates`)
For every unique id in the checked set: both `moduleDates[$id]['start']`
and `['end']` must be present (else: *"Set a start and end date for every
selected module/track before continuing — use 'Set dates' on the row."*),
and `end >= start` (else: *"End date must be on or after the start date
for every selected module/track."*). Same-day ranges are valid. Returns
`null` when everything checked passes.

### Step 6 — Enroll Mentees
- Copy: *"Who will be mentored in this class? You can skip this and enroll
  mentees later."* — skippable.
- Program-name intro placeholder + reminder of which program mentees are
  being enrolled into.
- Search + paginated existing-user picker:
  - `mentee_search` — `TextInput`, `live(debounce: 400)`, resets
    `mentee_page` to 1 on change.
  - `mentee_page` — hidden field, default 1; Previous/Next actions
    increment/decrement it (`max(1, page-1)` / `page+1`, no upper bound
    check — an out-of-range page simply returns an empty result set).
  - `selected_users` — `CardCheckboxList`, options from `menteeOptions()`
    (§6.2), `maxSelections($this->training?->max_participants)` (server
    + client enforced cap tied to the value picked in step 3),
    `live()`, `afterStateUpdated` → `saveWizardDraft('selected_users', $state)`.
- Inline "add a new mentee" sub-flow, gated by hidden `show_new_mentee_form`:
  - A single action button, label switches to `Mentee "{search}" not found
    — click here to add` (warning color) when there's an active search
    term, else `+ Add a new mentee` (gray). Clicking it reveals the
    fieldset and, if the search string contains `@`, pre-fills
    `new_mentee.email`; otherwise splits on whitespace into
    `new_mentee.first_name` (first token) / `new_mentee.last_name` (rest).
  - Fieldset fields: `new_mentee.email` (email-validated, optional at the
    Filament-field level), `new_mentee.first_name`/`last_name`
    (`requiredWith('new_mentee.email')` — only required if an email was
    entered), `new_mentee.phone` (tel), `new_mentee.cadre_id` /
    `new_mentee.department_id` (selects), `new_mentee.facility_id`
    (select, options capped to first 200 facilities by name).
- `afterValidation`: calls `enrollMentees()` (§8.4) with `selected_users`
  and `new_mentee` (only passed through if `new_mentee.email` is filled).

#### 6.2 Mentee search/options (`searchMenteeUsers` / `menteeOptions`)
- Base query: `User::where(status='active')`, eager-loads `facility`,
  ordered by `first_name`.
- If a search term is present: matches (OR) `first_name`, `last_name`,
  `name`, `phone`, `email` LIKE `%term%`, or facility `name`/`mfl_code`
  LIKE `%term%`.
- Paginated 25/page (`skip`/`take`), no total-count/last-page indicator
  exposed to the UI — Previous/Next just page blindly.
- **Already-selected mentees are always fetched separately and pinned to
  the top** of the option list (so they stay visible/checked even if a
  later search or page change would otherwise filter them out of view),
  concatenated with the (deduplicated) paged results.
- Label format: `Name · Phone · Email · Facility (MFL code)` — any missing
  piece is simply omitted (`array_filter` + `implode(' · ', ...)`).

### Step 7 — Send Invitations
- Copy: *"Time to invite your mentees!"*
- Field: `recipients` — `Radio`, `all` (default) vs `not_sent`
  ("Only those not yet invited"), `required()`.
- This is the **final** step — its submit isn't a normal
  `Wizard\Step::afterValidation()` hook. The Wizard's own submit action is
  overridden (`->submitAction(view('...guided-wizard-submit'))`) to a plain
  `wire:submit="submit"` button labelled "Finish & Send Invitations" (spinner
  + "Creating mentorship & sending invitations..." while loading). The
  page's `submit()` method reads the whole form state and calls
  `sendInvitations()` (§8.5) directly — errors here are caught **inline**
  (a danger notification), not via `Halt`, since nothing upstream catches a
  `Halt` thrown outside a Wizard step's own `afterValidation`.

### Done (not a wizard step)
- `$completed` flips true after `sendInvitations()` succeeds; the Blade
  view (`guided-mentorship-setup.blade.php`) swaps entirely from `{{
  $this->form }}` to a confirmation panel:
  - *"Mentorship "{title}" created."*
  - If a class exists: *"Class "{name}" has {invitedCount} mentee(s)
    invited."* plus, conditionally: *"The class is now **active** — modules
    are open and mentors can begin."* (if auto-started) or *"It's still
    saved as a draft — add modules and enroll mentees before it can
    start."* (if not).
  - Buttons: "Go to Class" (→ `classes` route for the training, only shown
    if a class exists) and "Back to Mentorships" (→ list page), styled as
    raw Filament button classes (not `<x-filament::button>` components —
    a plain Blade view, not itself part of the form).

## 7. Supporting custom form components

### 7.1 `ProgramPicker` (`program_id`)
Card-grid field (view: `filament/forms/components/program-picker.blade.php`).
`getPrograms()`:
- Loads **every** `Program` (active or not) ordered by name — inactive ones
  are rendered, not hidden, with a disabled/"Not Active" treatment
  (`pgpicker-card--disabled` CSS class asserted in tests) so users
  understand why an expected program can't be picked, rather than it
  silently vanishing.
- Re-orders so the EmONC program ("Maternal ... EmONC" name match, same
  detection as `isEmoncProgram()`) is always the 3rd card, after whatever
  two precede it alphabetically — a fixed presentation preference, not
  business logic.
- Attaches each program's active, top-level (`parent_id IS NULL`)
  `ProgramModule`s and a computed `$p->canSelect = $p->isSelectableBy($user)`
  flag consumed by the Blade view for the disabled styling.

### 7.2 `EmoncModulePicker` (`module_ids`, EmONC only)
Card list grouped by parent module with child "tracks" nested underneath
(view: `filament/forms/components/emonc-module-picker.blade.php`).
`getModules()`:
- `includeAssigned(bool)` toggle — guided wizard sets this **true** (shows
  already-assigned rows checked, so unchecking removes them); the legacy
  one-shot "Add Modules" modal elsewhere leaves it **false** (its default —
  no removal flow there, so already-assigned rows are simply excluded).
- A parent with no children (a leaf module) is included unless already
  assigned (or always, if `includeAssigned`).
- A parent **with** children (tracks) is only shown if it has at least one
  available (not-yet-assigned, unless `includeAssigned`) track;
  `availableChildren` is attached as a computed relation for the view.
- Checking a row in the UI opens a modal collecting that row's start/end
  date, written into the page's `$moduleDates` side-channel (not part of
  the field's own Filament state).

### 7.3 `CardCheckboxList` (generic — `module_ids` for standard programs,
`selected_users` for mentees)
Flat bordered-card checkbox list (view:
`filament/forms/components/card-checkbox-list.blade.php`) for any plain
`id => label` option set. `maxSelections(int|null)` optionally caps the
count both client-side (unchecked cards become inert past the cap; already-
checked ones can still be unchecked) and server-side (a validation rule
rejecting an over-cap submission with *"You can select at most {n}."*).

## 8. Persistence methods (the actual business logic, one per step)

### 8.1 `createTraining(array $data): Training`
Deliberately documented in the source as **mirroring
`CreateMentorshipTraining::mutateFormDataBeforeCreate()` exactly**:
- Forces `type = 'facility_mentorship'`, `mentor_id = auth()->id()`.
- Auto-generates `title` = `"{program name} - {facility name} - {M Y of
  start_date, or now}"` (any missing piece simply omitted via
  `array_filter`+`implode(' - ', ...)`), defaulting the program part to
  `"MNCH Mentorship"` if none picked yet.
- **Upsert semantics**: if `$this->training` is already set (resuming a
  refresh, or clicking Back then Next again on this step), calls `update()`
  on the existing record instead of creating a duplicate. Otherwise
  generates `identifier = 'MT-' + 6 random uppercase chars` and creates.
- Sets `$this->trainingId` (syncs the URL) either way.

### 8.2 `createFirstClass(array $data): MentorshipClass`
Mirrors `ManageMentorshipClasses::createClass()`. Same upsert pattern: if
`$this->class` exists, `update()`; else `create()` with
`status = 'draft'`, `created_by = auth()->id()`. Sets `$this->classId`.

### 8.3 `assignModules(array $data): int`
Not a pure "add" — **syncs to the desired set** (since `module_ids` is
pre-filled with what's already assigned, a user can uncheck to remove):
- `$desiredIds` (deduped, cast to int) vs `$currentIds` (from
  `classModules()`) → `$toAdd` / `$toRemove` diffs.
- **Removal** (`removeWizardModule`, §8.3.1) is attempted per id first;
  blocked removals are collected by name and surfaced as **one warning
  notification** (not per-module) listing all blocked module names,
  suffixed *"already has mentee progress recorded."* — this does **not**
  halt the step; blocked removals just don't happen, the rest proceeds.
- **Addition**: delegates to `ModuleUsageService::assignModulesToClass()`
  (same service the standalone Modules page uses — expands parent modules
  with tracks into one `ClassModule` per track, skips anything already in
  the class, handles mentee-progress-exemption logic for already-active
  classes). Captures each newly-created `ClassModule` via the service's
  `$afterCreate` callback.
- For EmONC, applies each newly-created module's date range from
  `$data['module_dates']` (keyed by `program_module_id`) — **only to
  newly-added modules**, never re-stamping dates on ones that already
  existed (explicitly tested).
- If `auto_create_sessions` is true and at least one module was created:
  reloads `classModules` and calls `autoCreateSessions()` on each (if the
  method exists on that model instance).
- Cleanup: clears the `module_ids` draft key (now real DB rows) and fully
  resets `$this->moduleDates` (both the in-memory property and its draft
  key) — regardless of whether anything was added, since applied-or-removed
  picks shouldn't leak into a later pass over a different module.
- Returns the count of newly-created `ClassModule` rows.

#### 8.3.1 `removeWizardModule(ClassModule $classModule): bool`
Deliberately **looser** than the production "remove module" guard
elsewhere (skips the stricter session-count check, since the wizard's own
auto-populated sessions have no real attendance yet) but still refuses if
`status !== 'not_started'` or any `menteeProgress()` rows exist. Hard
`delete()` if allowed; returns whether the removal happened.

### 8.4 `enrollMentees(array $data): int`
Also **syncs to the desired set** (`selected_users` pre-filled with who's
already enrolled — unchecking removes them), mirroring
`ManageClassMentees`'s "Add from List"/"Add Mentee" logic:
- `$desiredIds` vs `$currentIds` (from `participants()`) → removals via
  `EnrollmentService::removeFromClass()` (deletes module progress +
  assessment results + the participant row, in that order, in a
  transaction) then additions via `EnrollmentService::enrollInClass($user,
  $class, 'manual')` for each newly-checked user not already enrolled
  (`isEnrolled()` guard prevents duplicates even if called twice).
- New-mentee sub-flow: if `new_mentee.email` present, looks up an existing
  `User` by that email first —
  - **Match found**: enrolls that existing user (if not already enrolled) —
    does **not** create a duplicate account.
  - **No match**: creates a `User` (`name` = trimmed `"first last"`,
    `password = Hash::make('123456')` — a **fixed default password**,
    `status = 'active'`, `role = 'mentee'`, then best-effort
    `assignRole('mentee')` via Spatie if the method exists, swallowing any
    exception), then enrolls them.
- `$this->enrolledCount` set to the total added this call; clears the
  `selected_users` draft key. Returns the count.

### 8.5 `sendInvitations(array $data): array`
Mirrors `ManageClassMentees`'s "Send Invitations" bulk action:
- Ensures `enrollment_token` exists (generates `Str::random(32)` if not)
  and sets `enrollment_link_active = true` either way.
- Query: all `ClassParticipant`s for this class whose `user.email` is
  non-null/non-empty; if `recipients === 'not_sent'`, additionally filters
  to `invitation_sent_at IS NULL`.
- For each: sends `MenteeEnrollmentInvitationMail` (passing whether it's a
  resend), stamps `invitation_sent_at = now()`, tallies `sent` vs `resent`
  separately (both count toward `$this->invitedCount`).
- Sets `$this->completed = true`, stamps
  `Training.guided_setup_completed_at = now()`, clears
  `Training.guided_setup_draft = null`.
- **Auto-starts the class** if `$this->class->canStart()` (mirrors
  `ClassLifecycleController::start()` — requires modules **and** enrolled
  mentees to exist; a class that skipped the Modules step stays in
  `draft`, is not force-started). Sets `$this->classStarted` accordingly.
- Calls `discardSupersededDrafts()` (§5).
- Works correctly with **zero enrolled mentees** — `sent = resent = 0`,
  `$completed` still flips true (explicitly tested).
- Returns `['sent' => int, 'resent' => int]`.

## 9. Error handling

- Steps 3–6 (`afterValidation` hooks): any thrown `\Throwable` from the
  persistence call is caught and passed to `stepFailed()`, which shows a
  `Notification::make()->danger()` with the exception's own message as the
  body, then `throw new Halt` — Filament's `Wizard` component internally
  catches `Halt` thrown inside `afterValidation()` and keeps the user on
  the current step rather than advancing or hard-crashing.
- Step 7 (`submit()`, invoked directly by `wire:submit`, not wrapped by the
  Wizard's own step machinery): the same try/catch pattern, but the
  exception is handled **inline** with a danger notification — no `Halt`
  here, since nothing upstream would catch it outside a step's
  `afterValidation`.
- No dedicated global error boundary beyond these two patterns; there's no
  generic "something went wrong, start over" fallback screen.

## 10. EmONC branch detection

```php
private function isEmoncProgram(?int $programId): bool
{
    $program = Program::find($programId);
    return $program
        && str_contains(strtolower($program->name), 'maternal')
        && str_contains(strtolower($program->name), 'emonc');
}
```
Purely a **name-substring check** (`"maternal"` AND `"emonc"` both present,
case-insensitive) — not a dedicated `program_type` column/flag. Used to
decide: whether start/end dates are shown/required on steps 3 & 4, and
which module picker (`EmoncModulePicker` vs `CardCheckboxList`) renders on
step 5. `ProgramPicker` independently reimplements the same substring check
to decide card ordering (§7.1) — the two checks are not centralized into one
shared helper today.

## 11. Test coverage inventory (what "parity" must satisfy)

`tests/Feature/GuidedMentorshipSetupTest.php` (32 tests) exercises, at
minimum:
- List page shows the button; the page itself loads for an authorized user.
- `createTraining`: persists correct attributes/title/identifier; **update,
  not duplicate**, on a second call with the same in-memory `$this->training`.
- `createFirstClass`: persists correctly linked to training; same
  update-not-duplicate behavior.
- `assignModules`: creates `ClassModule`s for a standard program; per-module
  EmONC dates applied only to newly-created rows, not re-stamped on
  existing ones; clears the `module_ids` draft after applying (and a fresh
  resume correctly shows the module as already-added/locked, not
  re-offered); skippable (zero modules → 0 created, no error); removes an
  unchecked module; **refuses** to remove one with recorded mentee
  progress; clears the `moduleDates` draft after applying.
- `validateModuleDates`: fails on missing dates; fails on end-before-start;
  passes on valid ranges including same-day.
- `mount()`: falls back to real DB assignments when no draft key exists;
  treats an explicit (even empty) draft as authoritative over real
  assignments; resumes Run Type/Location from URL mirrors before a Training
  exists; prefers the persisted Training over stale URL mirrors once it
  exists; restores module/mentee picks from the training draft (simulating
  a brand-new session via the pending-setup banner); restores `moduleDates`
  from the draft; re-seeds EmONC dates from the DB after `assignModules()`
  clears the in-memory property (the "Next then Back" case — this one
  drives a real HTTP GET, not just `Livewire::test()`, because dynamic
  Wizard-step-closure output isn't reliably reflected by the component
  test harness).
- `saveWizardDraft`: merges into an existing draft without clobbering other
  keys; preserves associative (non-list) keys for `moduleDates` specifically
  (`array_is_list()` routing).
- `updatedModuleDates()` hook fires on both a direct property assignment and
  a real Livewire `->set()` request cycle, and survives an unrelated
  second request in the same session (regression-tested explicitly — an
  earlier bug wiped it on the next hydrate cycle).
- `enrollMentees`: enrolls existing selected users; creates+enrolls a brand
  new mentee; skippable; does not duplicate an already-enrolled user across
  two calls; removes an unchecked mentee.
- `sendInvitations`: emails all enrolled-with-email mentees and marks
  `completed`; class **stays draft** when Modules was skipped (no
  auto-start); class **auto-starts** when it has both modules and mentees;
  completes cleanly with **zero** mentees; clears the draft on success;
  **force-deletes** the same mentor's other abandoned pending drafts on
  success (and confirms the pending-setup banner no longer fires for them).
- `Training::pendingGuidedSetup()` scope: excludes completed trainings and
  other mentors' trainings.
- Program picker: inactive programs render **visible but disabled**
  (`"Not Active"` text + `pgpicker-card--disabled` class), not hidden.

## 12. What guided setup deliberately does NOT touch

Per the original design spec, still true today: `CreateMentorshipTraining`,
`ManageMentorshipClasses`, `ManageClassModules`, `ManageClassMentees` are
untouched — the wizard only *calls* `EnrollmentService`, `ModuleUsageService`,
and `MenteeEnrollmentInvitationMail`, never duplicates their logic. RBAC is
piggybacked on the resource's own create permission, no separate gate.

## 13. Drift from the original design spec (2026-07-31)

For awareness when comparing against `2026-07-31-guided-mentorship-wizard-design.md`:
- **Draft resumability** (§5 above — `guided_setup_draft`,
  `guided_setup_completed_at`, the pending-setup banner, cross-session
  resume via `?training=`/`?class=`) was an explicit *non-goal* in the
  original spec ("Single sitting — no draft-resume UX") and was added
  later. It is now core, heavily-tested behavior.
- The original spec described modules/mentees steps as pure "add" flows;
  the shipped version syncs to a desired set (supports **removal** by
  unchecking), for both modules and mentees.
- A feature-flag toggle (`Setting::GUIDED_SETUP_BUTTON_ENABLED`) was added;
  the original spec said "No feature flag... the coordinator picks
  per-use," describing only permanent side-by-side buttons.
- Button label changed from "Guided Setup" to "New Mentorship Guided
  Setup" at some point (see `ListMentorshipTrainings::getHeaderActions()`).
