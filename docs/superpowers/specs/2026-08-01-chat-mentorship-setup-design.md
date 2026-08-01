# Chat Mentorship Setup — Design Spec

## Problem

The Guided Mentorship Setup wizard (`GuidedMentorshipSetup`, documented field-
by-field in `docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md`) is a linear,
7-step Filament Wizard. It works, but it's still a form — one screen per
step, Back/Next buttons, no sense of a real conversation. The request is a
third creation method: a chat-style assistant that walks a coordinator
through creating a mentorship end-to-end ("Welcome, X! What kind of
mentorship do you want to create today?"), starting as a deterministic
"inference engine" (not a real LLM) but built on an abstraction that can
later be handed to a real LLM for freer-form conversation and tool-calling,
without a rewrite.

## Goals

- **Full parity with the guided wizard from day one**: all 7 steps' worth of
  data (run type, location, program & schedule, first class, modules
  including EmONC branching and per-module dates, mentee enrollment
  including the new-mentee sub-flow, send invitations), the same validation
  rules, the same cross-session draft resumability.
- **Zero duplicated business logic.** Every persistence rule already proven
  by the wizard's 32 tests is reused, not re-implemented.
- A conversation that *feels* like chat (message thread, natural-language
  question copy, human-readable echoes of what was picked) without
  requiring real NLU to be reliable — structured fields render as inline
  quick-reply cards / widgets, not typed free text.
- A foundation (the slot-filling abstraction) that a future phase can hand
  to a real LLM without re-architecting the persistence or Filament layers.

## Non-Goals (v1)

- No real NLU / free-text parsing of multiple slots from one message. Free
  text is only used for genuinely free fields (class name, description,
  new-mentee details) and simple yes/no.
- No general-purpose assistant behavior (status lookups, cancellation,
  small talk, unrelated Q&A). Scope is strictly "create one mentorship,"
  matching the wizard's own scope.
- Not replacing or modifying `GuidedMentorshipSetup`, `CreateMentorshipTraining`,
  `ManageMentorshipClasses`, `ManageClassModules`, or `ManageClassMentees` —
  this is a third, additive method.

## Architecture

### Shared service extraction (prerequisite)

Extract the five persistence methods and their helpers out of
`GuidedMentorshipSetup` into `app/Services/MentorshipWizardService.php`:
`createTraining()`, `createFirstClass()`, `assignModules()` (+
`removeWizardModule()`), `enrollMentees()`, `sendInvitations()`,
`validateModuleDates()`, `searchMenteeUsers()`/`menteeOptions()`,
`isEmoncProgram()`. Mechanical extraction — `Training`/`MentorshipClass` are
threaded through as explicit parameters instead of `$this->training`/
`$this->class`. `GuidedMentorshipSetup` is updated to delegate to the
service; **its existing 32-test suite must pass unmodified** as proof
nothing changed behaviorally. Both the wizard and the new chat page call
this one service — no business rule is ever written twice.

### New page

`app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`,
route key `chat-setup`, same base RBAC as the wizard
(`create_mentorship::training` via `MentorshipTrainingResource`), plus its
own `Setting::CHAT_SETUP_BUTTON_ENABLED` toggle (default true) mirroring
`GUIDED_SETUP_BUTTON_ENABLED`'s pattern exactly, including the `?training=`
resume bypass.

### Slot model

Slots are grouped into the same 5 persistence boundaries the wizard's steps
already respect (Training details → First Class → Modules → Enroll Mentees
→ Send Invitations — call these **stages**). A stage's `MentorshipWizardService`
call fires once every *required, currently-visible* slot in that stage is
filled; stages still run in sequence (Modules needs a `Training`+`Class` to
exist first), but slots *within* a stage can be filled in any order.

```php
Slot::make('facility_id')
    ->stage('training_details')
    ->question(fn ($answers) => "Which facility in {$answers['county_name']}?")
    ->render(Render::CARDS)
    ->optionsFrom(fn ($answers) => Facility::whereHas('subcounty', fn ($q) =>
        $q->where('county_id', $answers['county_id'])))
    ->dependsOn('county_id')
    ->required(),
```

Render kinds: `CARDS` (quick-reply, single pick — county/facility/program/
recipients/run-type), `MULTI_CARDS` (module/mentee multi-pick, reusing
`CardCheckboxList`/`EmoncModulePicker` styling inside a chat bubble),
`WIDGET` (inline Filament `DatePicker`/numeric stepper — dates,
`max_participants`), `FREE_TEXT` (class name, description, new-mentee
fields, yes/no). Conditional slots (EmONC hides dates; EmONC vs standard
module picker) use `isEmoncProgram()` as a `visibleWhen()` predicate — same
check the wizard's fields already use via `->visible()`.

Engine loop each turn: find the first unfilled, required, visible slot in
the current stage → render its question + a single-slot Filament form for
that slot's input → on submit, validate (reusing `validateModuleDates()`,
`maxSelections`, standard Filament field rules) → store the value → re-check
the stage; once complete, call the matching `MentorshipWizardService`
method, post a confirmation bot message, advance to the next stage.

### UI shell

One Livewire page, one Blade view: a scrollable message thread plus one
active "turn" at the bottom.

- `public array $messages`: each entry `{role: bot|user, text, slot?,
  timestamp}`. Bot entries are the slot's question copy. User entries are a
  **human-readable echo** of the answer (e.g. "Kiambu County", "Live
  Mentorship", "Newborn Resuscitation, Kangaroo Care") via a per-slot
  `echo()` formatter — raw IDs never appear in the thread.
- The active turn is a tiny single-slot Filament form (just that slot's
  field — `Radio`/`Select`/`CheckboxList`/`DatePicker`/`TextInput`, the
  exact same field components and validation the wizard already uses, no
  bespoke widgets) rendered in a bubble below the transcript. Submitting it
  appends the bot question + user echo, persists, validates, stores the
  slot, re-renders for the next slot.
- **Corrections**: each past user-answer bubble has an "Edit" action that
  reopens *that* slot's input inline, pre-filled, without discarding later
  answers — no rigid step to rewind, unlike the wizard's Back button. If
  that slot's stage was already persisted, editing it just re-runs the same
  upsert (`createTraining` is already update-not-duplicate).
- **Greeting**: first bot message is always *"Welcome, {first_name}! What
  kind of mentorship do you want to create today?"*, framing the Run Type
  question as the opener — deterministic, not an open-ended prompt.

### State & persistence

Reuses existing infrastructure wherever the semantics already match:

- **`Training.guided_setup_draft`** (existing column) — shared as-is for
  `module_ids` / `selected_users` / `moduleDates`, same
  `saveWizardDraft()`/`clearWizardDraft()` semantics as the wizard. Pre-
  Training slots (`is_pilot`/`county_id`/`facility_id`) mirror into the URL
  query string exactly like the wizard's `urlIsPilot`/`urlCountyId`/
  `urlFacilityId` — same boundary: nothing to resume before a Training
  exists, matching current wizard behavior (not a regression).
- **`Training.guided_setup_completed_at`** and the `pendingGuidedSetup()`
  scope — shared completion marker for both entry methods, so
  `PendingGuidedSetupNotice`'s banner covers an abandoned chat session with
  no changes to its query logic.
- **New column `Training.guided_setup_method`** (`'wizard' | 'chat'`,
  nullable) — routes the pending-setup banner's "Continue" link to whichever
  UI the mentor actually started in.
- **New column `Training.chat_setup_transcript`** (json, nullable,
  append-only) — only populated once a Training exists (same boundary as
  the draft). A small `appendTranscript()` helper (same shape as
  `saveWizardDraft()`) writes after every turn. On resume, `mount()`
  rebuilds `$messages` from this column and re-derives the current slot
  cursor from what's already filled — full transcript replay.
- `discardSupersededDrafts()` keeps working unmodified — it already
  operates on `pendingGuidedSetup()` regardless of which UI produced the
  stale draft.

Net new migration: `guided_setup_method` + `chat_setup_transcript` columns
on `trainings`. Everything else is reuse.

### Error handling

A failed stage-completion call (`MentorshipWizardService` throwing) appends
a distinct bot bubble — *"⚠️ Something went wrong: {message}"* — and leaves
the current turn's mini-form open for retry, rather than a Filament toast
or silently advancing. Per-slot field validation (required, email format,
`maxSelections`, `validateModuleDates`) surfaces as normal inline Filament
validation under that turn's form.

## Entry Point

A third header action on the Mentorships list, alongside "New Mentorship"
and "New Mentorship Guided Setup":

- Label: **"Chat Setup"**
- Icon: `heroicon-o-chat-bubble-left-right`
- Gated by `Setting::CHAT_SETUP_BUTTON_ENABLED` (default true), toggle added
  to Mentorship Settings alongside the existing two, identical
  disabled+tooltip pattern.

## Testing Approach

- `MentorshipWizardService` extraction must leave the existing
  `GuidedMentorshipSetupTest` suite (32 tests) passing unmodified.
- New `ChatMentorshipSetupTest` covers: submitting slots one at a time and
  asserting the same DB side effects as the wizard's equivalents; echo-
  formatting correctness; editing a past answer without discarding later
  ones; transcript persistence and full resume replay; the error-bubble path
  on a failing stage; EmONC vs standard branching (module picker, per-module
  date validation); the settings toggle's disable/tooltip/resume-bypass
  behavior.

## Extensibility Boundary (deferred, not built now)

The slot abstraction exists specifically so a later phase can swap "what to
ask next" / "how to interpret this answer" for a real LLM — tool-calling
against the same `MentorshipWizardService` methods as its tools — without
touching persistence or Filament wiring. Multi-slot free-text extraction,
general-assistant behavior (status queries, cancellation, unrelated Q&A),
and any real NLU are explicitly out of scope until that phase.
