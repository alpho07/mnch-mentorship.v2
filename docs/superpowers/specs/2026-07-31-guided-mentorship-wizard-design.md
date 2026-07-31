# Guided Mentorship Wizard — Design Spec

## Problem

Creating a facility mentorship today means filling out one long single-page
form (`CreateMentorshipTraining` / `MentorshipTrainingResource::form()`),
then separately navigating to create a class, separately navigating to
assign modules, and separately navigating to enroll and invite mentees —
four different screens with no guidance connecting them. A first-time or
infrequent coordinator has no indication of what to do next after each
step.

The request: a conversational, guided, step-by-step journey that walks a
coordinator from "create a mentorship" all the way through "mentees are
enrolled and invited," as a **parallel** method — the existing single-page
form stays exactly as it is, untouched, and coordinators choose which
method to use each time.

## Goals

- One continuous guided journey covering: mentorship details → first class
  → modules → enroll mentees → send invitations.
- Reuses 100% of the existing business logic (`EnrollmentService`,
  `ModuleUsageService`, the same Mail classes) — no duplicated domain
  rules.
- Zero changes to the existing create form, class page, modules page, or
  mentee-enrollment page.
- Single sitting — no draft-resume UX. If abandoned partway, whatever was
  already created (mentorship, class, etc.) is left in place as a normal,
  ordinary record — not specially flagged or auto-cleaned.

## Non-Goals

- Not a literal chat-bubble interface. This is a guided multi-step Wizard
  (Filament's `Wizard` component, already used elsewhere in this codebase
  for `TrainingResource`), with friendly, question-style copy per step —
  not an avatar/chat thread.
- Not touching the "modules should auto-populate from the program" gap —
  that's a pre-existing, unrelated issue. The wizard's Modules step uses
  the same manual picker the existing Modules page uses today.
- Not adding draft-saving/resumability across sessions.
- Not replacing or modifying `CreateMentorshipTraining`,
  `ManageMentorshipClasses`, `ManageClassModules`, or `ManageClassMentees`.

## Entry Point

`MentorshipTrainingResource`'s list page header currently has one button,
"New Mentorship" (→ `CreateMentorshipTraining`). A second button is added
next to it:

- Label: **"Guided Setup"**
- Icon: `heroicon-o-sparkles`
- Links to the new page's URL (`MentorshipTrainingResource::getUrl('guided-setup')`)

Both buttons persist permanently. No feature flag, no toggle to disable
either path — the coordinator picks per-use.

## Architecture

One new custom Filament page (not a `CreateRecord` page — a standalone
page implementing `HasForms`, following the same pattern already used by
`ManageMentorshipClasses` / `ManageClassMentees` for pages that aren't
simple CRUD):

- **New file:** `app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php`
- **New view:** `resources/views/filament/pages/guided-mentorship-setup.blade.php`
- **Modified file:** `app/Filament/Resources/MentorshipTrainingResource.php`
  — add the `guided-setup` route and the header button on the list page.

The page hosts a single `Forms\Components\Wizard` with 7 steps plus a
final "Done" confirmation screen (rendered outside the Wizard once the
last step's action completes). Each step calls
`Wizard\Step::afterValidation(fn () => $this->persistStepN())` to persist
that step's slice of data the moment the coordinator clicks "Next" —
using the exact same underlying service calls the existing pages already
use. This means if the coordinator's connection drops or they navigate
away partway through, whatever was already persisted (e.g. the Training
and MentorshipClass by step 4) stays in the database as a normal,
unflagged record — same as if they'd used the existing multi-page flow
and just stopped partway.

Component-level state (`public ?Training $training`, `public
?MentorshipClass $class`, `public array $selectedUserIds`, etc.) tracks
what's been created so far across steps, the same way
`ManageMentorshipClasses` already tracks `$selectedClass` across its own
page's state.

## Steps

### Step 1 — Run Type

- Copy: *"Is this a real live mentorship or a pilot/test run?"*
- Field: `is_pilot` radio (Live Mentorship / Pilot Run) — identical
  options/descriptions to the current form.
- No persistence on this step (in-memory only, carried to step 3).

### Step 2 — Location

- Copy: *"Where is this mentorship being conducted?"*
- Fields: `county_id` (searchable select) → `facility_id` (cascading
  select, filtered by county) — identical to the current form's Location
  section.
- No persistence on this step (in-memory only, carried to step 3).

### Step 3 — Program & Schedule

- Copy: *"What program is being mentored, and when?"*
- Fields: `ProgramPicker` (program_id), `start_date`, `end_date` (hidden
  for EmONC, same `isEmonc()` visibility rule as today),
  `max_participants`.
- **On Next:** creates the `Training` record. Reuses the exact same
  mutation logic as `CreateMentorshipTraining::mutateFormDataBeforeCreate()`
  — `type = 'facility_mentorship'`, auto-generated `identifier`
  (`MT-XXXXXX`), auto-generated `title` from program/facility/date,
  `mentor_id = auth()->id()`. Stored on the page as `$this->training`.

### Step 4 — First Class

- Copy: *"Let's create your first class/cohort."*
- Fields: `name`, `start_date`, `end_date` (hidden for EmONC),
  `description` — identical fields to the existing "Create New
  Class/Cohort" action on `ManageMentorshipClasses`.
- **On Next:** creates the `MentorshipClass` record (`training_id =
  $this->training->id`, `status = 'draft'`, `created_by = auth()->id()`).
  Stored on the page as `$this->class`.

### Step 5 — Modules

- Copy: *"Now let's add modules to this class."*
- Field: reuses whichever picker `ManageClassModules` uses today for this
  program — `EmoncModulePicker` for EmONC programs, or the searchable
  `CheckboxList` (`standardModulePickerSchema`) for standard programs.
  Options come from `ModuleUsageService::getAvailableModules($this->training,
  $this->class)`.
- Also includes the same `auto_create_sessions` toggle (default `true`).
  Does **not** include the optional module start/end dates or notes
  fields from the existing "Add Modules" action — those remain editable
  later from the Modules page if needed.
- **On Next:** calls `ModuleUsageService::assignModulesToClass($this->training,
  $this->class, $moduleIds)`, then (if `auto_create_sessions` is checked)
  calls `autoCreateSessions()` on each created `ClassModule`, matching
  `ManageClassModules`'s existing behavior exactly.
- This step is skippable (Next with zero modules selected is allowed) —
  matches today's behavior where a class can exist with no modules yet.

### Step 6 — Enroll Mentees

- Copy: *"Who will be mentored in this class?"*
- Combines both existing mentee-adding mechanisms into one screen:
  - A searchable `CheckboxList` of existing active users (same
    search-by-name/phone/email/facility behavior as the current "Add from
    List" action).
  - A toggle/section "+ Add a new mentee" that reveals the same inline
    create-fields as the current "Add Mentee" action (email lookup,
    first/middle/last name, phone, cadre, department, facility) — email
    lookup pre-fills and locks fields exactly as it does today if a match
    is found.
- **On Next:** for each selected existing user, calls
  `EnrollmentService::enrollInClass($user, $this->class, 'manual')`. For
  each newly-created mentee, creates the `User` record (same fields,
  default password `123456`, `role = 'mentee'`, `assignRole('mentee')`)
  then calls the same `enrollInClass()`. This step is skippable (Next
  with zero mentees is allowed, matching today's "class can exist with no
  mentees yet" behavior).

### Step 7 — Send Invitations

- Copy: *"Time to invite your mentees!"*
- Field: `recipients` radio — "All mentees with email addresses" / "Only
  those not yet invited" — same options and live preview counts as the
  current "Send Invitations" action.
- **On submit (final step):** ensures an enrollment token exists
  (`ensureEnrollmentToken()` equivalent), then sends
  `MenteeEnrollmentInvitationMail` to the matching participants and stamps
  `invitation_sent_at`, identical to the existing action's logic.
- Skippable — if there are zero enrolled mentees (step 6 was skipped),
  this step shows a note that there's no one to invite yet and just
  advances to Done.

### Done

- Confirmation screen (not a Wizard step — a `public bool $completed`
  property on the page flips to `true` once step 7's submit action
  finishes, and the Blade view swaps from the Wizard form to this
  confirmation panel): *"Mentorship '{title}' created. Class '{name}' has
  N mentee(s) invited."*
- Two buttons: "Go to Class" (→ `ManageClassMentees` for this class, the
  full-featured page, for anything further like starting the class) and
  "Back to Mentorships" (→ list page).

## Abandonment Behavior

If a coordinator quits partway (closes the tab, navigates away), whatever
was already persisted up to that point stays exactly as a normal record —
no special "incomplete" flag, no background cleanup job. A mentorship
abandoned after step 3 just looks like a mentorship with no classes yet;
one abandoned after step 6 looks like a class with mentees but no
invitations sent yet. Both are fully visible and manageable through the
existing pages, exactly as if the coordinator had used the standard
multi-page flow and stopped partway there.

## Validation & Error Handling

Each step validates only its own fields on "Next" (Filament Wizard's
built-in per-step validation). If a step's persistence call throws (e.g.
a `\LogicException` from an underlying service), show a Filament danger
notification with the exception message and keep the coordinator on the
current step — no silent failures, no partial-then-broken state.

## What This Does NOT Change

- `CreateMentorshipTraining` — untouched.
- `ManageMentorshipClasses` — untouched.
- `ManageClassModules` — untouched.
- `ManageClassMentees` — untouched.
- `EnrollmentService`, `ModuleUsageService`, `MenteeEnrollmentInvitationMail`
  — untouched, only called.
- RBAC — the guided setup page is reachable by the same users who can
  already reach `MentorshipTrainingResource`'s create page (no new
  permission gate beyond that).

## Testing Approach

Feature tests using `Livewire::test(GuidedMentorshipSetup::class)`,
covering:
- Each step's persistence side effect (Training created after step 3,
  MentorshipClass after step 4, ClassModule rows after step 5,
  ClassParticipant rows after step 6, invitation emails sent after step 7
  — using `Mail::fake()`).
- Skippability of steps 5 and 6.
- Abandonment: stopping after step 4 leaves a real Training + real
  MentorshipClass in the database with no special flags.
- EmONC vs standard program branching in steps 3 (date visibility) and 5
  (module picker component).
