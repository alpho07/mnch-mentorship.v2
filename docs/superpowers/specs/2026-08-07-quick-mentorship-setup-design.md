# Quick Mentorship Setup — Unified Creation Flow Design Spec

**Status:** Approved, ready for implementation plan
**Phase:** Production-Safe System Audit, Phase 5 (Workflow Optimization) continuation

## Problem

Four fully-separate implementations exist for creating a `facility_mentorship` Training, each independently toggleable from `ListMentorshipTrainings::getHeaderActions()`:

1. **Plain form** (`CreateMentorshipTraining.php`, 132 lines) — creates only the Training. Class/modules/mentees/invites happen later, elsewhere.
2. **Guided Wizard** (`GuidedMentorshipSetup.php`, 791 lines) — 7-step wizard covering Training → first Class → Modules → Mentees → Invitations.
3. **Chat Setup** (`ChatMentorshipSetup.php` + `HasMentorshipChatSlots.php` + `MentorshipChatScript.php`) — same end-to-end pipeline as the Wizard, via a deterministic click-driven chat UI.
4. **MnchGPT** (`MnchGptSetup.php`, 512 lines) — same pipeline again, via a free-text LLM-driven chat UI.

Four parallel surfaces for one underlying task is a lot of choice for a mentor and a lot of surface area to maintain. A survey of all four (see prior research) found they already agree on every field and rule that matters, and three of them (Wizard/Chat/MnchGPT) already share their persistence logic through one service, `MentorshipWizardService`. The only real variation between them is *how* the same data gets collected.

## Design

Add a fifth, additive flow — **Quick Setup** — that captures the full pipeline in the fewest possible moving parts: one page, one URL, progressively-revealed sections, no chat, no LLM, no separate wizard-step pages. The four existing flows are untouched; each already has its own Settings toggle, and Quick Setup gets one too, so any of the four can be retired later once you're satisfied with this one replacing it.

### 1. New page: `QuickMentorshipSetup`

`app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php`, structured like `GuidedMentorshipSetup.php` (a plain Filament `Page` with `InteractsWithForms`, not a `CreateRecord`) — because, like the Wizard, it must create/update four different model types across the session (`Training`, `MentorshipClass`, `ClassModule`s, `ClassParticipant`s), not just one record.

Registered in `MentorshipTrainingResource::getPages()`:
```php
'quick-setup' => Pages\QuickMentorshipSetup::route('/quick-setup'),
```

`canAccess()` follows the exact pattern already used by `GuidedMentorshipSetup`/`ChatMentorshipSetup` (`GuidedMentorshipSetup.php:49-60`): a `?training=` query string (resuming) is always allowed regardless of the toggle; a fresh visit requires `Setting::getBool(Setting::QUICK_SETUP_BUTTON_ENABLED)`.

### 2. New Setting + button

`app/Models/Setting.php` gains:
```php
public const QUICK_SETUP_BUTTON_ENABLED = 'quick_setup_button_enabled';
```

`ListMentorshipTrainings::getHeaderActions()` gains a 5th action, same shape as the existing four:
```php
Actions\Action::make('quick_setup')
    ->label('Quick Setup')
    ->icon('heroicon-o-bolt')
    ->color('gray')
    ->url(fn () => MentorshipTrainingResource::getUrl('quick-setup'))
    ->disabled(! $quickSetupEnabled)
    ->tooltip($quickSetupEnabled ? null : 'Turned off in Mentorship Settings'),
```

`MentorshipSettings.php` (the admin settings page) gains a toggle for it alongside the other three, following its existing pattern exactly.

### 3. One page, five progressive sections

All rendered on a single Filament `Form` in page order — no separate URLs, no Next/Back step chrome:

1. **Basics** — `is_pilot`, `county_id`, `facility_id`, `program_id` (via the existing `ProgramPicker` component), `start_date`/`end_date` (hidden for EmONC, same `MentorshipWizardService::isEmoncProgram()` check the Wizard uses), `max_participants`. Combines the Wizard's separate Run Type / Location / Program & Schedule steps into one section, since none of them require anything to exist in the DB first. A small "Continue" action validates this section and calls `MentorshipWizardService::createTraining()` (create-or-update, same as the Wizard), setting `guided_setup_method = 'quick'` on the Training (mirroring how Chat sets `'chat'` — see §5), then reveals section 2.
2. **First Class** — `class_name`, `class_start_date`/`class_end_date`, `class_description`. "Continue" calls `createFirstClass()`, reveals section 3.
3. **Modules** — `module_ids` via the same module-picker component the Wizard uses (`CardCheckboxList`/`EmoncModulePicker`), with the same per-module EmONC start/end date modal and `validateModuleDates()` enforcement. "Continue" calls `assignModules()`, reveals section 4.
4. **Mentees** — search-existing (`menteeOptions()`/`searchMenteeUsers()`, capped by `max_participants`) or add-new-inline (`new_mentee`: email/first/last/phone/cadre/department/facility). "Continue" calls `enrollMentees()`, reveals section 5.
5. **Invite** — `recipients` (all vs. not-yet-invited). Final "Create Mentorship" action calls `sendInvitations()`, which already (with zero new code) sets `guided_setup_completed_at`, clears the draft, starts the class if ready, and discards superseded drafts.

Every section's "Continue"/final action is a thin Livewire method that validates only that section's fields and delegates straight to the matching `MentorshipWizardService` method — no new business logic anywhere in this page.

### 4. Autosave/resume — reusing existing infrastructure, not building new

- `#[Url(as: 'training')]`/`#[Url(as: 'class')]` mirrors, same as the Wizard (`GuidedMentorshipSetup.php:64-68`), so a refresh resumes correctly.
- Section 1's raw field values mirror to the URL too (`urlIsPilot`/`urlCountyId`/`urlFacilityId`), same reason as the Wizard: nothing is in the DB yet at that point to resume from otherwise.
- Module dates and any other side-channel state persist via the existing `saveWizardDraft()`/`clearWizardDraft()` methods against the same `guided_setup_draft` column — not a new column, not a parallel draft mechanism.
- `mount()` restores from `training_id`/`class_id`/`guided_setup_draft` exactly as the Wizard's `mount()` does.

### 5. Resume banner integration

`Training.guided_setup_method` already exists as a column and already discriminates the "Continue" link's destination for Chat drafts (`PendingGuidedSetupNotice.php:70`: `$training->guided_setup_method === 'chat' ? 'chat-setup' : 'guided-setup'`). Extend that single line to a `match`:

```php
$routeKey = match ($training->guided_setup_method) {
    'chat' => 'chat-setup',
    'quick' => 'quick-setup',
    default => 'guided-setup',
};
```

Quick Setup sets `guided_setup_method = 'quick'` when `createTraining()` first runs (same moment Chat sets `'chat'` — `HasMentorshipChatSlots.php:467`). This means an abandoned Quick Setup session is automatically picked up by the existing `PendingGuidedSetupNotice` widget and "Continue" banner — no new widget, no new scope, no new column.

### 6. What's explicitly NOT built

- No new business rules — every validation/persistence rule comes from `MentorshipWizardService` as-is.
- No changes to `CreateMentorshipTraining`, `GuidedMentorshipSetup`, `ChatMentorshipSetup`, or `MnchGptSetup`.
- No chat interface, no LLM, no fuzzy-matching (that was specifically valuable for free-text chat input; a structured form has no free text to disambiguate).
- No decision here about retiring any of the four existing flows — that's a Settings toggle you flip later, at your own pace, once Quick Setup has proven itself.

## Testing

- Feature tests for each section: field visibility gating (a later section isn't visible until the prior one's "Continue" succeeds), validation (EmONC date requirements, module-date completeness, mentee cap), and correct delegation to the matching `MentorshipWizardService` method.
- Feature test: refreshing mid-flow (simulated by re-mounting with the same `training`/`class` URL params) restores the correct section and prior field values.
- Feature test: an abandoned Quick Setup draft appears in `PendingGuidedSetupNotice` and its "Continue" link points to `quick-setup`.
- Feature test: full end-to-end run produces a Training (`guided_setup_method = 'quick'`, `guided_setup_completed_at` set) + Class + Modules + Participants + sent invitations, matching what the Wizard's own end-to-end test asserts.
- Regression: existing tests for the other three flows, `MentorshipWizardService`, and `PendingGuidedSetupNotice`'s Chat-branch behavior all still pass unchanged.

## Out of scope

- Retiring, hiding, or modifying any of the four existing flows.
- Any change to `MentorshipWizardService`'s public methods or behavior — this design deliberately treats it as a stable, already-correct dependency.
- A settings-page redesign beyond adding the one new toggle in the existing pattern.
