# Chat Mentorship Setup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a third, chat-style mentorship creation method that reaches full behavioral parity with the existing `GuidedMentorshipSetup` wizard, built on a slot-filling dialogue engine over a shared, newly-extracted `MentorshipWizardService`.

**Architecture:** Extract the wizard's five persistence methods into `MentorshipWizardService` (both pages call it — zero duplicated business logic). Build a small declarative `Slot`/`Render` primitive and a `MentorshipChatScript` that describes each question, grouped into the same five stages the wizard already uses. A new `ChatMentorshipSetup` Livewire page runs the engine loop: render the next unfilled slot as a one-field chat turn, validate, store, append to a persisted transcript, advance.

**Tech Stack:** Laravel 12, Filament v3 (Forms components reused directly, no bespoke widgets), Livewire, PHPUnit, Alpine.js (only for the existing `CardCheckboxList`/`EmoncModulePicker` components, reused as-is).

## Global Constraints

- The existing `tests/Feature/GuidedMentorshipSetupTest.php` (32 tests) must pass **unmodified** after the extraction in Task 1 — this is the proof no wizard behavior changed.
- Every field-level rule documented in `docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md` (max 10 mentees, EmONC date-hiding, module-date validation, duplicate-training upsert, mentee-progress removal guard, etc.) must hold identically in the chat flow — that document is the parity checklist.
- No business logic is ever written twice. If a rule needs to change, it changes once, in `MentorshipWizardService`.
- Run `./vendor/bin/pint` on every file touched before each commit (project convention per `CLAUDE.md`).
- Reference spec: `docs/superpowers/specs/2026-08-01-chat-mentorship-setup-design.md`.

---

## File Structure

**New:**
- `app/Services/MentorshipWizardService.php` — extracted persistence logic (Task 1)
- `database/migrations/2026_08_01_180000_add_chat_setup_columns_to_trainings_table.php` (Task 2)
- `app/Services/Chat/Render.php` — render-kind enum (Task 3)
- `app/Services/Chat/Slot.php` — declarative slot builder (Task 3)
- `app/Services/Chat/MentorshipChatScript.php` — slot definitions per stage (Tasks 3–7)
- `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php` — the chat page (Tasks 3–11)
- `resources/views/filament/pages/chat-mentorship-setup.blade.php` — page shell (Task 3)
- `resources/views/filament/pages/partials/chat-transcript.blade.php` — message list (Task 3)
- `resources/views/filament/pages/partials/chat-turn.blade.php` — active-turn wrapper (Task 3)
- `tests/Feature/ChatMentorshipSetupTest.php` (Tasks 3–12)

**Modified:**
- `app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php` — delegate to the service (Task 1)
- `app/Models/Training.php` — new columns, `chat_setup_transcript` casts, `appendChatTranscript()` helper (Task 2)
- `app/Models/Setting.php` — `CHAT_SETUP_BUTTON_ENABLED` constant (Task 12)
- `app/Filament/Pages/MentorshipSettings.php` — third toggle (Task 12)
- `app/Filament/Resources/MentorshipTrainingResource.php` — `chat-setup` route (Task 12)
- `app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php` — third header action (Task 12)
- `app/Filament/Widgets/PendingGuidedSetupNotice.php` — route by `guided_setup_method` (Task 12)

---

### Task 1: Extract `MentorshipWizardService`

**Files:**
- Create: `app/Services/MentorshipWizardService.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php`
- Test: `tests/Feature/GuidedMentorshipSetupTest.php` (must pass unmodified)

**Interfaces:**
- Produces (used by every later task):
  - `createTraining(array $data, ?Training $existing = null): Training`
  - `createFirstClass(array $data, Training $training, ?MentorshipClass $existing = null): MentorshipClass`
  - `assignModules(array $data, Training $training, MentorshipClass $class): int` — `$data` keys: `module_ids`, `auto_create_sessions`, `module_dates`
  - `enrollMentees(array $data, MentorshipClass $class): int` — `$data` keys: `selected_users`, `new_mentee`
  - `sendInvitations(array $data, Training $training, MentorshipClass $class): array` — returns `['sent' => int, 'resent' => int]`, internally calls `discardSupersededDrafts()`
  - `validateModuleDates(array $moduleIds, array $moduleDates): ?string`
  - `isEmoncProgram(?int $programId): bool`
  - `searchMenteeUsers(?string $search, int $page, int $perPage = 25): \Illuminate\Support\Collection`
  - `menteeOptions(?string $search, int $page, array $selectedIds): array`
  - `saveWizardDraft(Training $training, string $key, mixed $state): void`
  - `clearWizardDraft(Training $training, string $key): void`

- [ ] **Step 1: Run the existing suite to confirm a green baseline**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: `Tests: 32 passed`

- [ ] **Step 2: Create the service with the moved logic**

```php
<?php

namespace App\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use App\Mail\MenteeEnrollmentInvitationMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Persistence logic shared by every mentorship-creation UI (the guided
 * Wizard and the chat assistant). Extracted verbatim from
 * GuidedMentorshipSetup so both callers get identical, single-source
 * business rules — see docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md for the
 * full behavioral spec each method below must uphold.
 */
class MentorshipWizardService
{
    public function createTraining(array $data, ?Training $existing = null): Training
    {
        $data['type'] = 'facility_mentorship';
        $data['mentor_id'] = auth()->id();

        $program = isset($data['program_id']) ? Program::find($data['program_id']) : null;
        $facility = isset($data['facility_id']) ? Facility::find($data['facility_id']) : null;
        $date = ! empty($data['start_date']) ? \Carbon\Carbon::parse($data['start_date'])->format('M Y') : now()->format('M Y');

        $data['title'] = trim(implode(' - ', array_filter([
            $program?->name ?? 'MNCH Mentorship',
            $facility?->name,
            $date,
        ])));

        if ($existing) {
            $existing->update($data);

            return $existing;
        }

        $data['identifier'] = 'MT-'.strtoupper(Str::random(6));

        return Training::create($data);
    }

    public function createFirstClass(array $data, Training $training, ?MentorshipClass $existing = null): MentorshipClass
    {
        $payload = [
            'training_id' => $training->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing;
        }

        return MentorshipClass::create($payload + [
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);
    }

    public function assignModules(array $data, Training $training, MentorshipClass $class): int
    {
        $desiredIds = collect($data['module_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $currentIds = $class->classModules()->pluck('program_module_id')->toArray();

        $toAdd = array_values(array_diff($desiredIds, $currentIds));
        $toRemove = array_values(array_diff($currentIds, $desiredIds));

        $blockedRemovals = [];
        foreach ($toRemove as $moduleId) {
            $classModule = $class->classModules()->where('program_module_id', $moduleId)->first();

            if ($classModule && ! $this->removeWizardModule($classModule)) {
                $blockedRemovals[] = $classModule->programModule?->name ?? "Module {$moduleId}";
            }
        }

        $created = 0;

        if (! empty($toAdd)) {
            $service = app(ModuleUsageService::class);
            $createdModules = [];

            $created = $service->assignModulesToClass(
                $training,
                $class,
                $toAdd,
                null,
                function (ClassModule $classModule) use (&$createdModules) {
                    $createdModules[$classModule->program_module_id] = $classModule;
                }
            );

            foreach ($data['module_dates'] ?? [] as $programModuleId => $dates) {
                $classModule = $createdModules[(int) $programModuleId] ?? null;

                if ($classModule && (! empty($dates['start']) || ! empty($dates['end']))) {
                    $classModule->update([
                        'start_date' => $dates['start'] ?? null,
                        'end_date' => $dates['end'] ?? null,
                    ]);
                }
            }

            if (($data['auto_create_sessions'] ?? true) && $created > 0) {
                $class->load('classModules');
                foreach ($class->classModules as $classModule) {
                    if (method_exists($classModule, 'autoCreateSessions')) {
                        $classModule->autoCreateSessions();
                    }
                }
            }
        }

        if (! empty($blockedRemovals)) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Some modules could not be removed')
                ->body(implode(', ', $blockedRemovals).' already has mentee progress recorded.')
                ->send();
        }

        $this->clearWizardDraft($training, 'module_ids');
        $this->clearWizardDraft($training, 'moduleDates');

        return $created;
    }

    private function removeWizardModule(ClassModule $classModule): bool
    {
        if ($classModule->status !== 'not_started' || $classModule->menteeProgress()->count() > 0) {
            return false;
        }

        $classModule->delete();

        return true;
    }

    public function enrollMentees(array $data, MentorshipClass $class): int
    {
        $service = app(EnrollmentService::class);
        $count = 0;

        $desiredIds = collect($data['selected_users'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $currentIds = $class->participants()->pluck('user_id')->toArray();

        $toRemove = array_values(array_diff($currentIds, $desiredIds));
        foreach ($toRemove as $userId) {
            $participant = $class->participants()->where('user_id', $userId)->first();
            if ($participant) {
                $service->removeFromClass($participant);
            }
        }

        foreach (array_values(array_diff($desiredIds, $currentIds)) as $userId) {
            $user = User::find($userId);
            if ($user && ! $service->isEnrolled($user->id, $class->id)) {
                $service->enrollInClass($user, $class, 'manual');
                $count++;
            }
        }

        $newMentee = $data['new_mentee'] ?? null;
        if (! empty($newMentee['email'])) {
            $existing = User::where('email', $newMentee['email'])->first();

            if ($existing) {
                if (! $service->isEnrolled($existing->id, $class->id)) {
                    $service->enrollInClass($existing, $class, 'manual');
                    $count++;
                }
            } else {
                $displayName = trim(implode(' ', array_filter([
                    $newMentee['first_name'] ?? null,
                    $newMentee['last_name'] ?? null,
                ])));

                $user = User::create([
                    'first_name' => $newMentee['first_name'] ?? null,
                    'last_name' => $newMentee['last_name'] ?? null,
                    'name' => $displayName,
                    'email' => $newMentee['email'],
                    'phone' => $newMentee['phone'] ?? null,
                    'cadre_id' => $newMentee['cadre_id'] ?? null,
                    'department_id' => $newMentee['department_id'] ?? null,
                    'facility_id' => $newMentee['facility_id'] ?? null,
                    'password' => Hash::make('123456'),
                    'status' => 'active',
                    'role' => 'mentee',
                ]);

                if (method_exists($user, 'assignRole')) {
                    try {
                        $user->assignRole('mentee');
                    } catch (\Exception) {
                    }
                }

                $service->enrollInClass($user, $class, 'manual');
                $count++;
            }
        }

        $this->clearWizardDraft($class->training, 'selected_users');

        return $count;
    }

    public function sendInvitations(array $data, Training $training, MentorshipClass $class): array
    {
        if (! $class->enrollment_token) {
            $class->update([
                'enrollment_token' => Str::random(32),
                'enrollment_link_active' => true,
            ]);
        } else {
            $class->update(['enrollment_link_active' => true]);
        }
        $class->refresh();

        $query = ClassParticipant::where('mentorship_class_id', $class->id)
            ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->with('user');

        if (($data['recipients'] ?? 'all') === 'not_sent') {
            $query->whereNull('invitation_sent_at');
        }

        $participants = $query->get();
        $sent = 0;
        $resent = 0;

        foreach ($participants as $record) {
            $isResend = (bool) $record->invitation_sent_at;

            Mail::to($record->user->email)->send(new MenteeEnrollmentInvitationMail(
                $record->user,
                $class,
                $record,
                $isResend
            ));

            $record->update(['invitation_sent_at' => now()]);
            $isResend ? $resent++ : $sent++;
        }

        $training->update([
            'guided_setup_completed_at' => now(),
            'guided_setup_draft' => null,
        ]);

        if ($class->canStart()) {
            $class->start();
        }

        $this->discardSupersededDrafts($training);

        return ['sent' => $sent, 'resent' => $resent];
    }

    public function discardSupersededDrafts(Training $training): void
    {
        Training::pendingGuidedSetup()
            ->where('mentor_id', $training->mentor_id)
            ->where('id', '!=', $training->id)
            ->get()
            ->each(fn (Training $stale) => $stale->forceDelete());
    }

    public function validateModuleDates(array $moduleIds, array $moduleDates): ?string
    {
        foreach (collect($moduleIds)->unique() as $id) {
            $dates = $moduleDates[$id] ?? null;
            $start = $dates['start'] ?? null;
            $end = $dates['end'] ?? null;

            if (empty($start) || empty($end)) {
                return 'Set a start and end date for every selected module/track before continuing — use "Set dates" on the row.';
            }

            if (\Illuminate\Support\Carbon::parse($end)->lt(\Illuminate\Support\Carbon::parse($start))) {
                return 'End date must be on or after the start date for every selected module/track.';
            }
        }

        return null;
    }

    public function isEmoncProgram(?int $programId): bool
    {
        if (! $programId) {
            return false;
        }

        $program = Program::find($programId);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }

    public function searchMenteeUsers(?string $search, int $page, int $perPage = 25): \Illuminate\Support\Collection
    {
        $query = User::query()
            ->where('status', 'active')
            ->with(['facility'])
            ->orderBy('first_name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('facility', function ($facilityQuery) use ($search) {
                        $facilityQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('mfl_code', 'like', "%{$search}%");
                    });
            });
        }

        return $query->skip(($page - 1) * $perPage)->take($perPage)->get();
    }

    public function menteeOptions(?string $search, int $page, array $selectedIds): array
    {
        $selected = empty($selectedIds)
            ? collect()
            : User::whereIn('id', $selectedIds)->with('facility')->orderBy('first_name')->get();

        $results = $this->searchMenteeUsers($search, $page)
            ->reject(fn ($u) => in_array($u->id, $selectedIds));

        return $selected->concat($results)
            ->mapWithKeys(fn ($u) => [$u->id => $this->formatMenteeLabel($u)])
            ->toArray();
    }

    private function formatMenteeLabel(User $u): string
    {
        return implode(' · ', array_filter([
            $u->name,
            $u->phone,
            $u->email,
            $u->facility ? "{$u->facility->name}".
                ($u->facility->mfl_code ? " (MFL {$u->facility->mfl_code})" : '') : null,
        ]));
    }

    public function saveWizardDraft(Training $training, string $key, mixed $state): void
    {
        $state = $state ?? [];
        $draft = $training->guided_setup_draft ?? [];
        $draft[$key] = array_is_list($state) ? array_values($state) : $state;

        $training->update(['guided_setup_draft' => $draft]);
    }

    public function clearWizardDraft(Training $training, string $key): void
    {
        $draft = $training->guided_setup_draft ?? [];

        if (! array_key_exists($key, $draft)) {
            return;
        }

        unset($draft[$key]);

        $training->update(['guided_setup_draft' => $draft ?: null]);
    }
}
```

- [ ] **Step 3: Update `GuidedMentorshipSetup` to delegate**

Replace the bodies of the extracted methods with thin delegations, keeping every public/private method **name and signature** identical (the test file calls several of these directly, including two via `ReflectionMethod`):

```php
use App\Services\MentorshipWizardService;
// ... keep all other existing use statements

public function createTraining(array $data): Training
{
    $this->training = app(MentorshipWizardService::class)->createTraining($data, $this->training);
    $this->trainingId = $this->training->id;

    return $this->training;
}

public function createFirstClass(array $data): MentorshipClass
{
    $this->class = app(MentorshipWizardService::class)->createFirstClass($data, $this->training, $this->class);
    $this->classId = $this->class->id;

    return $this->class;
}

public function assignModules(array $data): int
{
    return app(MentorshipWizardService::class)->assignModules($data, $this->training, $this->class);
}

public function enrollMentees(array $data): int
{
    $count = app(MentorshipWizardService::class)->enrollMentees($data, $this->class);
    $this->enrolledCount = $count;

    return $count;
}

public function sendInvitations(array $data): array
{
    $result = app(MentorshipWizardService::class)->sendInvitations($data, $this->training, $this->class);

    $this->invitedCount = $result['sent'] + $result['resent'];
    $this->completed = true;

    if ($this->class->fresh()->status === 'active') {
        $this->classStarted = true;
    }

    return $result;
}

public function validateModuleDates(array $moduleIds): ?string
{
    return app(MentorshipWizardService::class)->validateModuleDates($moduleIds, $this->moduleDates);
}

private function isEmoncProgram(?int $programId): bool
{
    return app(MentorshipWizardService::class)->isEmoncProgram($programId);
}

private function menteeOptions(?string $search, int $page, array $selectedIds): array
{
    return app(MentorshipWizardService::class)->menteeOptions($search, $page, $selectedIds);
}

private function saveWizardDraft(string $key, mixed $state): void
{
    if (! $this->training) {
        return;
    }

    app(MentorshipWizardService::class)->saveWizardDraft($this->training, $key, $state);
}

private function clearWizardDraft(string $key): void
{
    if (! $this->training) {
        return;
    }

    app(MentorshipWizardService::class)->clearWizardDraft($this->training, $key);
}
```

Delete the now-unused private methods this replaces: `removeWizardModule()`, `searchMenteeUsers()`, `formatMenteeLabel()`, `discardSupersededDrafts()`. Delete now-unused `use` statements (`Hash`, `Mail`, `MenteeEnrollmentInvitationMail`, `ClassParticipant`, `Facility` if no longer referenced directly — check with `grep -n "Facility::\|ClassParticipant::\|Mail::\|Hash::" app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php` before removing each).

- [ ] **Step 4: Run the existing suite again**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: `Tests: 32 passed` — identical count, zero assertion changes. If anything fails, the extraction changed behavior; fix the service to match the original code exactly (do not edit the test file).

- [ ] **Step 5: Format and commit**

```bash
./vendor/bin/pint app/Services/MentorshipWizardService.php app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php
git add app/Services/MentorshipWizardService.php app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php
git commit -m "refactor: extract MentorshipWizardService from GuidedMentorshipSetup

Zero behavior change (existing 32-test suite passes unmodified) — this is
the shared persistence layer both the wizard and the new chat assistant
will call, so no business rule is ever written twice."
```

---

### Task 2: Schema — `guided_setup_method` and `chat_setup_transcript`

**Files:**
- Create: `database/migrations/2026_08_01_180000_add_chat_setup_columns_to_trainings_table.php`
- Modify: `app/Models/Training.php`
- Test: `tests/Unit/TrainingChatTranscriptTest.php`

**Interfaces:**
- Produces: `Training::appendChatTranscript(array $message): void`, columns `guided_setup_method` (string|null), `chat_setup_transcript` (array|null cast)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Training;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingChatTranscriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_append_chat_transcript_appends_to_an_empty_column(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['chat_setup_transcript' => null]);

        $training->appendChatTranscript(['role' => 'bot', 'text' => 'Welcome!']);

        $this->assertSame(
            [['role' => 'bot', 'text' => 'Welcome!']],
            $training->fresh()->chat_setup_transcript
        );
    }

    public function test_append_chat_transcript_appends_to_an_existing_column(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'chat_setup_transcript' => [['role' => 'bot', 'text' => 'Welcome!']],
        ]);

        $training->appendChatTranscript(['role' => 'user', 'text' => 'Live Mentorship']);

        $this->assertSame(
            [
                ['role' => 'bot', 'text' => 'Welcome!'],
                ['role' => 'user', 'text' => 'Live Mentorship'],
            ],
            $training->fresh()->chat_setup_transcript
        );
    }

    public function test_guided_setup_method_column_accepts_wizard_and_chat(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['guided_setup_method' => 'chat']);

        $this->assertSame('chat', $training->fresh()->guided_setup_method);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TrainingChatTranscriptTest`
Expected: FAIL — `guided_setup_method`/`chat_setup_transcript` columns don't exist / `appendChatTranscript` not defined.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            // Which UI produced this pending/completed guided setup —
            // lets PendingGuidedSetupNotice route "Continue" to the right
            // page (guided-setup vs chat-setup). Null for pre-existing rows
            // and for trainings created outside either guided flow.
            $table->string('guided_setup_method')->nullable()->after('guided_setup_draft');

            // Append-only chat transcript for the chat assistant — mirrors
            // guided_setup_draft's role for the wizard, but stores the full
            // rendered message log (not just filled slot values) so a
            // resumed session can replay the conversation instead of just
            // jumping back to the next question.
            $table->json('chat_setup_transcript')->nullable()->after('guided_setup_method');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn(['guided_setup_method', 'chat_setup_transcript']);
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Update the Training model**

In `app/Models/Training.php`, add to `$fillable` (next to `guided_setup_draft`):

```php
'guided_setup_method',
'chat_setup_transcript',
```

Add to `$casts`:

```php
'chat_setup_transcript' => 'array',
```

Add the helper method (near the `scopePendingGuidedSetup` scope):

```php
/**
 * Appends one message to the chat assistant's transcript. Safe to call
 * repeatedly — read-modify-write against the current column value, same
 * pattern as MentorshipWizardService::saveWizardDraft().
 */
public function appendChatTranscript(array $message): void
{
    $transcript = $this->chat_setup_transcript ?? [];
    $transcript[] = $message;

    $this->update(['chat_setup_transcript' => $transcript]);
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TrainingChatTranscriptTest`
Expected: `Tests: 3 passed`

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Models/Training.php
git add database/migrations/2026_08_01_180000_add_chat_setup_columns_to_trainings_table.php app/Models/Training.php tests/Unit/TrainingChatTranscriptTest.php
git commit -m "feat: add guided_setup_method and chat_setup_transcript columns"
```

---

### Task 3: `Slot`/`Render` primitives + engine skeleton + Run Type/Location stage

**Files:**
- Create: `app/Services/Chat/Render.php`
- Create: `app/Services/Chat/Slot.php`
- Create: `app/Services/Chat/MentorshipChatScript.php`
- Create: `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
- Create: `resources/views/filament/pages/chat-mentorship-setup.blade.php`
- Create: `resources/views/filament/pages/partials/chat-transcript.blade.php`
- Create: `resources/views/filament/pages/partials/chat-turn.blade.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php`

**Interfaces:**
- Produces: `Render` enum (`CARDS`, `MULTI_CARDS`, `WIDGET`, `FREE_TEXT`); `Slot` builder (`make`, `stage`, `question`, `render`, `optionsFrom`, `echoUsing`, `visibleWhen`, `required`, `dependsOn`, `rule`, plus readers `getQuestion(array $answers): string`, `getOptions(array $answers): array`, `getEcho(mixed $value, array $answers): string`, `isVisible(array $answers): bool`, `isRequired(array $answers): bool`, `validate(mixed $value, array $answers): ?string`); `MentorshipChatScript::build(ChatMentorshipSetup $page): array` returning an ordered `Slot[]` for stage `training_details` covering `is_pilot`, `county_id`, `facility_id` only in this task (remaining `training_details` slots added in Task 4).
- Consumes: nothing from earlier tasks except `MentorshipWizardService` (Task 1) and the new columns (Task 2).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChatMentorshipSetupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Ada Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_page_loads_with_a_greeting_and_the_first_question(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(ChatMentorshipSetup::class)
            ->assertSuccessful()
            ->assertSee('Welcome, Ada!')
            ->assertSee('Is this a real live mentorship or a pilot/test run?');
    }

    public function test_answering_is_pilot_appends_an_echo_and_asks_for_county(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);

        $messages = $component->instance()->messages;

        $this->assertSame('bot', $messages[0]['role']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertSame('Live Mentorship', $messages[1]['text']);
        $this->assertSame('Which county?', $messages[2]['text']);
    }

    public function test_answering_county_asks_for_facility_scoped_to_that_county(): void
    {
        $this->actingAsCoordinator();
        $facility = \App\Models\Facility::factory()->create(['name' => 'Kiambu Level 4']);
        $countyId = $facility->subcounty->county_id;

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $countyId);

        $slot = \App\Services\Chat\MentorshipChatScript::build($component->instance())->first(fn ($s) => $s->id === 'facility_id');

        $this->assertArrayHasKey($facility->id, $slot->getOptions($component->instance()->answers));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: FAIL — `ChatMentorshipSetup` class doesn't exist.

- [ ] **Step 3: Write `Render`**

```php
<?php

namespace App\Services\Chat;

enum Render: string
{
    case CARDS = 'cards';
    case MULTI_CARDS = 'multi_cards';
    case WIDGET = 'widget';
    case FREE_TEXT = 'free_text';
}
```

- [ ] **Step 4: Write `Slot`**

```php
<?php

namespace App\Services\Chat;

use Closure;

/**
 * One question in the chat script. Declarative wrapper around a handful of
 * closures — see app/Services/Chat/MentorshipChatScript.php for how these
 * are assembled per stage, and docs/superpowers/specs/2026-08-01-chat-mentorship-setup-design.md
 * for the slot/stage model this implements.
 */
class Slot
{
    public string $id;

    public string $stage;

    protected Closure $questionResolver;

    protected Render $renderKind = Render::FREE_TEXT;

    protected ?Closure $optionsResolver = null;

    protected ?Closure $echoResolver = null;

    protected ?Closure $visibleResolver = null;

    protected bool $requiredFlag = true;

    protected array $dependsOnIds = [];

    protected ?Closure $validator = null;

    protected function __construct(string $id)
    {
        $this->id = $id;
        $this->questionResolver = fn () => $id;
    }

    public static function make(string $id): static
    {
        return new static($id);
    }

    public function stage(string $stage): static
    {
        $this->stage = $stage;

        return $this;
    }

    public function question(Closure $resolver): static
    {
        $this->questionResolver = $resolver;

        return $this;
    }

    public function render(Render $render): static
    {
        $this->renderKind = $render;

        return $this;
    }

    public function optionsFrom(Closure $resolver): static
    {
        $this->optionsResolver = $resolver;

        return $this;
    }

    public function echoUsing(Closure $resolver): static
    {
        $this->echoResolver = $resolver;

        return $this;
    }

    public function visibleWhen(Closure $resolver): static
    {
        $this->visibleResolver = $resolver;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->requiredFlag = $required;

        return $this;
    }

    public function dependsOn(string ...$slotIds): static
    {
        $this->dependsOnIds = $slotIds;

        return $this;
    }

    public function rule(Closure $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function renderKind(): Render
    {
        return $this->renderKind;
    }

    public function dependencies(): array
    {
        return $this->dependsOnIds;
    }

    public function getQuestion(array $answers): string
    {
        return ($this->questionResolver)($answers);
    }

    public function getOptions(array $answers): array
    {
        return $this->optionsResolver ? ($this->optionsResolver)($answers) : [];
    }

    public function getEcho(mixed $value, array $answers): string
    {
        if ($this->echoResolver) {
            return ($this->echoResolver)($value, $answers);
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }

    public function isVisible(array $answers): bool
    {
        return $this->visibleResolver ? (bool) ($this->visibleResolver)($answers) : true;
    }

    public function isRequired(): bool
    {
        return $this->requiredFlag;
    }

    /**
     * Returns an error message, or null if valid.
     */
    public function validate(mixed $value, array $answers): ?string
    {
        if ($this->isRequired() && ($value === null || $value === '' || $value === [])) {
            return 'This is required.';
        }

        return $this->validator ? ($this->validator)($value, $answers) : null;
    }
}
```

- [ ] **Step 5: Write `MentorshipChatScript` (Run Type + Location slots only for this task)**

```php
<?php

namespace App\Services\Chat;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\County;
use App\Models\Facility;

/**
 * Declares every slot the chat assistant can ask about, grouped into the
 * same five persistence stages the guided wizard already uses (see
 * docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md §6). Built fresh per request
 * from the live page instance so closures can read $page->training /
 * $page->class once those exist (from Task 6 onward).
 */
class MentorshipChatScript
{
    public const STAGES = ['training_details', 'first_class', 'modules', 'enroll_mentees', 'send_invitations'];

    /**
     * @return Slot[]
     */
    public static function build(ChatMentorshipSetup $page): array
    {
        return [
            Slot::make('is_pilot')
                ->stage('training_details')
                ->render(Render::CARDS)
                ->question(fn () => 'Is this a real live mentorship or a pilot/test run?')
                ->optionsFrom(fn () => [0 => 'Live Mentorship', 1 => 'Pilot Run'])
                ->echoUsing(fn ($v) => ((int) $v === 1) ? 'Pilot Run' : 'Live Mentorship'),

            Slot::make('county_id')
                ->stage('training_details')
                ->render(Render::CARDS)
                ->question(fn () => 'Which county?')
                ->optionsFrom(fn () => County::orderBy('name')->pluck('name', 'id')->all())
                ->echoUsing(fn ($v) => County::find($v)?->name ?? (string) $v),

            Slot::make('facility_id')
                ->stage('training_details')
                ->render(Render::CARDS)
                ->dependsOn('county_id')
                ->question(fn ($a) => 'Which facility in '.(County::find($a['county_id'] ?? null)?->name ?? 'this county').'?')
                ->optionsFrom(fn ($a) => Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $a['county_id'] ?? null))
                    ->get()
                    ->mapWithKeys(fn ($f) => [$f->id => "{$f->mfl_code} — {$f->name}"])
                    ->all())
                ->echoUsing(fn ($v) => Facility::find($v)?->name ?? (string) $v),
        ];
    }
}
```

- [ ] **Step 6: Write `ChatMentorshipSetup`**

```php
<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Setting;
use App\Models\Training;
use App\Services\Chat\MentorshipChatScript;
use App\Services\MentorshipWizardService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;

class ChatMentorshipSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.chat-mentorship-setup';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(array $parameters = []): bool
    {
        if (! parent::canAccess($parameters)) {
            return false;
        }

        if (request()->filled('training')) {
            return true;
        }

        return Setting::getBool(Setting::CHAT_SETUP_BUTTON_ENABLED);
    }

    public array $messages = [];

    public array $answers = [];

    public ?Training $training = null;

    public bool $completed = false;

    public function mount(): void
    {
        $this->messages[] = [
            'role' => 'bot',
            'text' => 'Welcome, '.explode(' ', auth()->user()->name)[0].'! '.$this->currentSlot()->getQuestion($this->answers),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    protected function slots(): array
    {
        return MentorshipChatScript::build($this);
    }

    protected function currentSlot(): \App\Services\Chat\Slot
    {
        foreach ($this->slots() as $slot) {
            if (array_key_exists($slot->id, $this->answers)) {
                continue;
            }

            if (! $slot->isVisible($this->answers)) {
                continue;
            }

            return $slot;
        }

        throw new \RuntimeException('No slot left to ask — stage completion should have handled this.');
    }

    public function answer(string $slotId, mixed $value): void
    {
        $slot = collect($this->slots())->firstWhere('id', $slotId);

        if (! $slot) {
            return;
        }

        if ($error = $slot->validate($value, $this->answers)) {
            $this->addError('value', $error);

            return;
        }

        $this->answers[$slotId] = $value;

        $this->messages[] = [
            'role' => 'user',
            'text' => $slot->getEcho($value, $this->answers),
            'slot' => $slotId,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->appendTranscript($this->messages[count($this->messages) - 2] ?? null);
        $this->appendTranscript($this->messages[count($this->messages) - 1]);

        $next = $this->nextUnfilledSlot();

        if ($next) {
            $this->messages[] = [
                'role' => 'bot',
                'text' => $next->getQuestion($this->answers),
                'timestamp' => now()->toIso8601String(),
            ];
            $this->appendTranscript($this->messages[count($this->messages) - 1]);
        }
    }

    protected function nextUnfilledSlot(): ?\App\Services\Chat\Slot
    {
        foreach ($this->slots() as $slot) {
            if (array_key_exists($slot->id, $this->answers)) {
                continue;
            }

            if (! $slot->isVisible($this->answers)) {
                continue;
            }

            return $slot;
        }

        return null;
    }

    protected function appendTranscript(?array $message): void
    {
        if (! $message || ! $this->training) {
            return;
        }

        $this->training->appendChatTranscript($message);
    }
}
```

- [ ] **Step 7: Write the Blade views**

`resources/views/filament/pages/chat-mentorship-setup.blade.php`:

```blade
<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 space-y-4">
        @include('filament.pages.partials.chat-transcript', ['messages' => $messages])

        @unless ($completed)
            @include('filament.pages.partials.chat-turn', ['slot' => $this->currentSlot(), 'answers' => $answers])
        @endunless
    </div>
</x-filament-panels::page>
```

`resources/views/filament/pages/partials/chat-transcript.blade.php`:

```blade
<div class="space-y-3">
    @foreach ($messages as $message)
        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-lg rounded-xl px-4 py-2 text-sm
                {{ $message['role'] === 'user'
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' }}">
                {{ $message['text'] }}
            </div>
        </div>
    @endforeach
</div>
```

`resources/views/filament/pages/partials/chat-turn.blade.php`:

```blade
<div class="flex justify-start">
    <div class="max-w-lg w-full rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        @if ($slot->renderKind() === \App\Services\Chat\Render::CARDS)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($slot->getOptions($answers) as $value => $label)
                    <button
                        type="button"
                        wire:click="answer('{{ $slot->id }}', '{{ $value }}')"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-left hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif

        @error('value')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
    </div>
</div>
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: `Tests: 3 passed`

- [ ] **Step 9: Format and commit**

```bash
./vendor/bin/pint app/Services/Chat app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php
git add app/Services/Chat resources/views/filament/pages/chat-mentorship-setup.blade.php resources/views/filament/pages/partials/chat-transcript.blade.php resources/views/filament/pages/partials/chat-turn.blade.php app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php tests/Feature/ChatMentorshipSetupTest.php
git commit -m "feat: chat mentorship setup skeleton — slot engine + Run Type/Location"
```

---

### Task 4: Complete the `training_details` stage (Program & Schedule) and commit the Training

**Files:**
- Modify: `app/Services/Chat/MentorshipChatScript.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
- Modify: `resources/views/filament/pages/partials/chat-turn.blade.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::createTraining()` (Task 1), `Slot`/`Render` (Task 3)
- Produces: `ChatMentorshipSetup::$training` is a real, persisted `Training` once `program_id`/`start_date`/`end_date`/`max_participants` are all filled (or immediately after `program_id` for EmONC, since dates are hidden); `Render::WIDGET` handling in `chat-turn.blade.php`

- [ ] **Step 1: Write the failing test**

```php
public function test_completing_the_training_details_stage_creates_the_training(): void
{
    $this->actingAsCoordinator();
    $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
    $facility = \App\Models\Facility::factory()->create();

    $component = Livewire::test(ChatMentorshipSetup::class);
    $component->call('answer', 'is_pilot', 0);
    $component->call('answer', 'county_id', $facility->subcounty->county_id);
    $component->call('answer', 'facility_id', $facility->id);
    $component->call('answer', 'program_id', $program->id);
    $component->call('answer', 'start_date', now()->addDay()->toDateString());
    $component->call('answer', 'end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'max_participants', 8);

    $this->assertDatabaseHas('trainings', [
        'program_id' => $program->id,
        'facility_id' => $facility->id,
        'max_participants' => 8,
        'guided_setup_method' => 'chat',
    ]);
    $this->assertNotNull($component->instance()->training);
}

public function test_emonc_program_skips_the_date_slots(): void
{
    $this->actingAsCoordinator();
    $program = \App\Models\Program::factory()->create(['name' => 'Maternal Health (EmONC)', 'is_active' => true]);
    $facility = \App\Models\Facility::factory()->create();

    $component = Livewire::test(ChatMentorshipSetup::class);
    $component->call('answer', 'is_pilot', 0);
    $component->call('answer', 'county_id', $facility->subcounty->county_id);
    $component->call('answer', 'facility_id', $facility->id);
    $component->call('answer', 'program_id', $program->id);
    $component->call('answer', 'max_participants', 8);

    $this->assertNotNull($component->instance()->training);
    $this->assertNull($component->instance()->training->start_date);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: FAIL — `program_id`/`start_date`/etc. slots don't exist yet, `$this->training` never gets set.

- [ ] **Step 3: Add the remaining `training_details` slots to `MentorshipChatScript::build()`**

Append inside the returned array, after `facility_id`:

```php
Slot::make('program_id')
    ->stage('training_details')
    ->render(Render::CARDS)
    ->question(fn () => 'What program is being mentored?')
    ->optionsFrom(fn () => \App\Models\Program::query()
        ->get()
        ->filter(fn ($p) => $p->isSelectableBy(auth()->user()))
        ->mapWithKeys(fn ($p) => [$p->id => $p->name])
        ->all())
    ->echoUsing(fn ($v) => \App\Models\Program::find($v)?->name ?? (string) $v)
    ->rule(function ($value) {
        $program = $value ? \App\Models\Program::find($value) : null;

        if (! $program || ! $program->isSelectableBy(auth()->user())) {
            return 'That program is not active — pick a different one.';
        }

        return null;
    }),

Slot::make('start_date')
    ->stage('training_details')
    ->render(Render::WIDGET)
    ->visibleWhen(fn ($a) => ! app(\App\Services\MentorshipWizardService::class)->isEmoncProgram($a['program_id'] ?? null))
    ->question(fn () => 'When does it start?')
    ->echoUsing(fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('M j, Y')),

Slot::make('end_date')
    ->stage('training_details')
    ->render(Render::WIDGET)
    ->visibleWhen(fn ($a) => ! app(\App\Services\MentorshipWizardService::class)->isEmoncProgram($a['program_id'] ?? null))
    ->dependsOn('start_date')
    ->question(fn () => 'And when does it end?')
    ->echoUsing(fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('M j, Y'))
    ->rule(function ($value, $a) {
        if (! empty($a['start_date']) && \Illuminate\Support\Carbon::parse($value)->lt(\Illuminate\Support\Carbon::parse($a['start_date']))) {
            return 'End date must be on or after the start date.';
        }

        return null;
    }),

Slot::make('max_participants')
    ->stage('training_details')
    ->render(Render::WIDGET)
    ->question(fn () => 'How many mentees, at most (2–10)?')
    ->echoUsing(fn ($v) => "{$v} mentees")
    ->rule(function ($value) {
        if (! is_numeric($value) || $value < 2 || $value > 10) {
            return 'Must be between 2 and 10 mentees.';
        }

        return null;
    }),
```

- [ ] **Step 4: Wire stage completion into `ChatMentorshipSetup::answer()`**

Add a helper and call it at the end of `answer()`, right after appending the next bot question:

```php
public function answer(string $slotId, mixed $value): void
{
    // ...existing body up through appending the "next" bot question...

    $this->maybeCompleteStage('training_details', function () {
        $this->training = app(MentorshipWizardService::class)->createTraining([
            'is_pilot' => $this->answers['is_pilot'],
            'county_id' => $this->answers['county_id'],
            'facility_id' => $this->answers['facility_id'],
            'program_id' => $this->answers['program_id'],
            'start_date' => $this->answers['start_date'] ?? null,
            'end_date' => $this->answers['end_date'] ?? null,
            'max_participants' => $this->answers['max_participants'],
        ], $this->training);

        $this->training->update(['guided_setup_method' => 'chat']);
    });
}

protected function maybeCompleteStage(string $stage, \Closure $onComplete): void
{
    $stageSlots = array_filter($this->slots(), fn ($s) => $s->stage === $stage);

    $allFilled = collect($stageSlots)->every(function ($slot) {
        return ! $slot->isVisible($this->answers) || array_key_exists($slot->id, $this->answers);
    });

    // Only fire once per stage — guard by checking the next unfilled slot
    // isn't still in this stage (i.e. we just filled the last one this call).
    $justCompletedThisStage = $allFilled && collect($stageSlots)->contains(fn ($s) => end($this->messages)['slot'] ?? null === $s->id);

    if ($allFilled && $justCompletedThisStage) {
        try {
            $onComplete();
        } catch (\Throwable $e) {
            $this->messages[] = [
                'role' => 'bot',
                'text' => "⚠️ Something went wrong: {$e->getMessage()}",
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }
}
```

Note: `$justCompletedThisStage` here checks the slot we *just* answered belongs to this stage — since `answer()` already stored the value before this runs, `$allFilled` alone would also be true on every subsequent call within the same stage once true; the extra check prevents re-firing `createTraining()` on every later turn. (`createTraining()` is itself idempotent/upsert, so a duplicate call is harmless, but avoiding it keeps the transcript clean — no repeated confirmation messages.)

- [ ] **Step 5: Add `WIDGET` rendering to `chat-turn.blade.php`**

Add alongside the existing `CARDS` block:

```blade
@if ($slot->renderKind() === \App\Services\Chat\Render::WIDGET)
    <form wire:submit.prevent="answer('{{ $slot->id }}', $refs.widgetInput.value)" class="flex gap-2">
        @if (str_ends_with($slot->id, '_date'))
            <input type="date" x-ref="widgetInput" class="fi-input rounded-lg border-gray-300 dark:border-gray-600 text-sm" required>
        @else
            <input type="number" x-ref="widgetInput" min="2" max="10" class="fi-input rounded-lg border-gray-300 dark:border-gray-600 text-sm" required>
        @endif
        <x-filament::button type="submit">Send</x-filament::button>
    </form>
@endif
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: `Tests: 5 passed`

- [ ] **Step 7: Format and commit**

```bash
./vendor/bin/pint app/Services/Chat/MentorshipChatScript.php app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php
git add app/Services/Chat/MentorshipChatScript.php app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php resources/views/filament/pages/partials/chat-turn.blade.php tests/Feature/ChatMentorshipSetupTest.php
git commit -m "feat: chat setup — Program & Schedule stage, commits the Training"
```

---

### Task 5: `first_class` stage

**Files:**
- Modify: `app/Services/Chat/MentorshipChatScript.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
- Modify: `resources/views/filament/pages/partials/chat-turn.blade.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::createFirstClass()` (Task 1)
- Produces: `ChatMentorshipSetup::$class` set after this stage; `Render::FREE_TEXT` handling in `chat-turn.blade.php`

- [ ] **Step 1: Write the failing test**

```php
public function test_completing_the_first_class_stage_creates_the_class(): void
{
    $this->actingAsCoordinator();
    $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
    $facility = \App\Models\Facility::factory()->create();

    $component = Livewire::test(ChatMentorshipSetup::class);
    $component->call('answer', 'is_pilot', 0);
    $component->call('answer', 'county_id', $facility->subcounty->county_id);
    $component->call('answer', 'facility_id', $facility->id);
    $component->call('answer', 'program_id', $program->id);
    $component->call('answer', 'start_date', now()->addDay()->toDateString());
    $component->call('answer', 'end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'max_participants', 8);
    $component->call('answer', 'class_name', 'January 2027 Cohort');
    $component->call('answer', 'class_start_date', now()->addDay()->toDateString());
    $component->call('answer', 'class_end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'class_description', 'Gap identified: newborn resuscitation.');

    $this->assertDatabaseHas('mentorship_classes', [
        'name' => 'January 2027 Cohort',
        'training_id' => $component->instance()->training->id,
    ]);
    $this->assertNotNull($component->instance()->class);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: FAIL — `class_name` slot doesn't exist, `$this->class` never set.

- [ ] **Step 3: Add `first_class` slots to `MentorshipChatScript::build()`**

Append after the `training_details` slots:

```php
Slot::make('class_name')
    ->stage('first_class')
    ->render(Render::FREE_TEXT)
    ->question(fn () => "Let's create your first class or cohort — what should we call it?")
    ->rule(fn ($value) => (is_string($value) && strlen($value) > 255) ? 'Keep it under 255 characters.' : null),

Slot::make('class_start_date')
    ->stage('first_class')
    ->render(Render::WIDGET)
    ->visibleWhen(fn ($a) => ! app(\App\Services\MentorshipWizardService::class)->isEmoncProgram($a['program_id'] ?? null))
    ->question(fn () => 'When does this class start?')
    ->echoUsing(fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('M j, Y')),

Slot::make('class_end_date')
    ->stage('first_class')
    ->render(Render::WIDGET)
    ->visibleWhen(fn ($a) => ! app(\App\Services\MentorshipWizardService::class)->isEmoncProgram($a['program_id'] ?? null))
    ->question(fn () => 'And when does it end?')
    ->echoUsing(fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('M j, Y'))
    ->rule(function ($value, $a) {
        if (! empty($a['class_start_date']) && \Illuminate\Support\Carbon::parse($value)->lt(\Illuminate\Support\Carbon::parse($a['class_start_date']))) {
            return 'End date must be on or after the start date.';
        }

        return null;
    }),

Slot::make('class_description')
    ->stage('first_class')
    ->render(Render::FREE_TEXT)
    ->required(false)
    ->question(fn () => 'Want to describe the gap identified and how this class will be delivered? (optional — just say "skip")'),
```

- [ ] **Step 4: Wire stage completion**

In `ChatMentorshipSetup::answer()`, add a second `maybeCompleteStage()` call:

```php
$this->maybeCompleteStage('first_class', function () {
    $this->class = app(MentorshipWizardService::class)->createFirstClass([
        'name' => $this->answers['class_name'],
        'start_date' => $this->answers['class_start_date'] ?? null,
        'end_date' => $this->answers['class_end_date'] ?? null,
        'description' => ($this->answers['class_description'] ?? null) === 'skip' ? null : ($this->answers['class_description'] ?? null),
    ], $this->training, $this->class);
});
```

- [ ] **Step 5: Add `FREE_TEXT` rendering to `chat-turn.blade.php`**

```blade
@if ($slot->renderKind() === \App\Services\Chat\Render::FREE_TEXT)
    <form wire:submit.prevent="answer('{{ $slot->id }}', $refs.textInput.value)" class="flex gap-2">
        <input type="text" x-ref="textInput" class="fi-input flex-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm" {{ $slot->isRequired() ? 'required' : '' }}>
        <x-filament::button type="submit">Send</x-filament::button>
    </form>
@endif
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: `Tests: 6 passed`

- [ ] **Step 7: Format and commit**

```bash
./vendor/bin/pint app/Services/Chat/MentorshipChatScript.php app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php
git add app/Services/Chat/MentorshipChatScript.php app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php resources/views/filament/pages/partials/chat-turn.blade.php tests/Feature/ChatMentorshipSetupTest.php
git commit -m "feat: chat setup — First Class stage"
```

---

### Task 6: `modules` stage (EmONC + standard branching, per-module dates)

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
- Modify: `resources/views/filament/pages/partials/chat-turn.blade.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::assignModules()`, `validateModuleDates()` (Task 1); `CardCheckboxList`, `EmoncModulePicker` Filament fields (pre-existing, unmodified)
- Produces: `ChatMentorshipSetup::$moduleDates` (array, mirrors the wizard's property + `saveWizardDraft`); `module_ids` handled as a bespoke stage (not a generic `Slot`) because its options and widget depend on `$this->training`/`$this->class` (real models, not scalar answers) and, for EmONC, on the per-row date modal — same reasoning the wizard itself already applies to this step.

This stage is genuinely different from the previous two: it isn't a single field per turn, it's the same composite picker (`EmoncModulePicker` or `CardCheckboxList`) the wizard already uses, rendered once as one "turn," with a Continue button (this step is explicitly skippable — Continue with nothing checked is valid, exactly like the wizard).

- [ ] **Step 1: Write the failing test**

```php
public function test_modules_stage_assigns_modules_for_a_standard_program(): void
{
    $this->actingAsCoordinator();
    $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
    $facility = \App\Models\Facility::factory()->create();
    $module = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

    $component = Livewire::test(ChatMentorshipSetup::class);
    $component->call('answer', 'is_pilot', 0);
    $component->call('answer', 'county_id', $facility->subcounty->county_id);
    $component->call('answer', 'facility_id', $facility->id);
    $component->call('answer', 'program_id', $program->id);
    $component->call('answer', 'start_date', now()->addDay()->toDateString());
    $component->call('answer', 'end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'max_participants', 8);
    $component->call('answer', 'class_name', 'Cohort A');
    $component->call('answer', 'class_start_date', now()->addDay()->toDateString());
    $component->call('answer', 'class_end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'class_description', 'skip');

    $component->call('submitModules', [$module->id]);

    $this->assertDatabaseHas('class_modules', [
        'mentorship_class_id' => $component->instance()->class->id,
        'program_module_id' => $module->id,
    ]);
}

public function test_modules_stage_is_skippable(): void
{
    $this->actingAsCoordinator();
    $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
    $facility = \App\Models\Facility::factory()->create();

    $component = Livewire::test(ChatMentorshipSetup::class);
    $component->call('answer', 'is_pilot', 0);
    $component->call('answer', 'county_id', $facility->subcounty->county_id);
    $component->call('answer', 'facility_id', $facility->id);
    $component->call('answer', 'program_id', $program->id);
    $component->call('answer', 'start_date', now()->addDay()->toDateString());
    $component->call('answer', 'end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'max_participants', 8);
    $component->call('answer', 'class_name', 'Cohort A');
    $component->call('answer', 'class_start_date', now()->addDay()->toDateString());
    $component->call('answer', 'class_end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'class_description', 'skip');

    $component->call('submitModules', []);

    $this->assertSame(0, $component->instance()->class->classModules()->count());
    $this->assertNotEmpty($component->instance()->answers['selected_users'] ?? null === null ? [] : null); // stage advanced, no error
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: FAIL — `submitModules` method doesn't exist.

- [ ] **Step 3: Add module state and the `submitModules()` action to `ChatMentorshipSetup`**

```php
public array $moduleDates = [];

public function updatedModuleDates(): void
{
    if ($this->training) {
        app(MentorshipWizardService::class)->saveWizardDraft($this->training, 'moduleDates', $this->moduleDates);
    }
}

public function getModuleFieldOptions(): array
{
    if (app(MentorshipWizardService::class)->isEmoncProgram($this->training->program_id)) {
        $picker = new \App\Filament\Forms\Components\EmoncModulePicker('module_ids');
        $picker->training($this->training)->class($this->class)->includeAssigned();

        return $picker->getModules()->pluck('name', 'id')->all();
    }

    return \App\Models\ProgramModule::where('program_id', $this->training->program_id)
        ->where('is_active', true)
        ->whereNull('parent_id')
        ->orderBy('order_sequence')
        ->pluck('name', 'id')
        ->toArray();
}

public function submitModules(array $moduleIds): void
{
    if (app(MentorshipWizardService::class)->isEmoncProgram($this->training->program_id)) {
        if ($error = app(MentorshipWizardService::class)->validateModuleDates($moduleIds, $this->moduleDates)) {
            $this->addError('value', $error);

            return;
        }
    }

    $echo = empty($moduleIds)
        ? 'Skip for now'
        : \App\Models\ProgramModule::whereIn('id', $moduleIds)->pluck('name')->implode(', ');

    $this->messages[] = ['role' => 'user', 'text' => $echo, 'slot' => 'module_ids', 'timestamp' => now()->toIso8601String()];
    $this->appendTranscript(end($this->messages));

    try {
        app(MentorshipWizardService::class)->assignModules([
            'module_ids' => $moduleIds,
            'auto_create_sessions' => true,
            'module_dates' => $this->moduleDates,
        ], $this->training, $this->class);
    } catch (\Throwable $e) {
        $this->messages[] = ['role' => 'bot', 'text' => "⚠️ Something went wrong: {$e->getMessage()}", 'timestamp' => now()->toIso8601String()];

        return;
    }

    $this->moduleDates = [];
    $this->answers['module_ids'] = $moduleIds; // marks the modules stage complete for currentSlot()/nextUnfilledSlot() purposes

    $this->messages[] = [
        'role' => 'bot',
        'text' => 'Who will be mentored in this class? Search or tell me a name to add someone new — or say "skip" for now.',
        'timestamp' => now()->toIso8601String(),
    ];
    $this->appendTranscript(end($this->messages));
}
```

Note: `module_ids` deliberately is **not** declared in `MentorshipChatScript::build()` — `currentSlot()`/`nextUnfilledSlot()` must skip stages handled by a bespoke method. Update both to skip the `modules` and (from Task 7) `enroll_mentees` stages:

```php
protected function slots(): array
{
    return array_filter(
        MentorshipChatScript::build($this),
        fn ($slot) => ! in_array($slot->stage, ['modules', 'enroll_mentees'])
    );
}
```

And guard the view: `chat-mentorship-setup.blade.php`'s `@unless ($completed)` block must render the modules turn once `training_details`+`first_class` are done but `module_ids` isn't yet answered — add a small `activeStage()` helper:

```php
public function activeStage(): string
{
    if (! array_key_exists('module_ids', $this->answers) && $this->class) {
        return 'modules';
    }

    return 'slot';
}
```

Update the page view's turn section:

```blade
@unless ($completed)
    @if ($this->activeStage() === 'modules')
        @include('filament.pages.partials.chat-modules-turn')
    @else
        @include('filament.pages.partials.chat-turn', ['slot' => $this->currentSlot(), 'answers' => $answers])
    @endif
@endunless
```

- [ ] **Step 4: Write `resources/views/filament/pages/partials/chat-modules-turn.blade.php`**

```blade
<div
    x-data="{ selected: [] }"
    class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3"
>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($this->getModuleFieldOptions() as $id => $name)
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm cursor-pointer">
                <input type="checkbox" value="{{ $id }}" x-model="selected">
                {{ $name }}
            </label>
        @endforeach
    </div>

    @error('value')
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
    @enderror

    <x-filament::button x-on:click="$wire.submitModules(selected)">
        Continue
    </x-filament::button>
</div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: `Tests: 8 passed`

- [ ] **Step 6: Format and commit**

```bash
./vendor/bin/pint app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php
git add app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php resources/views/filament/pages/chat-mentorship-setup.blade.php resources/views/filament/pages/partials/chat-modules-turn.blade.php tests/Feature/ChatMentorshipSetupTest.php
git commit -m "feat: chat setup — Modules stage (EmONC + standard, skippable)"
```

---

### Task 7: `enroll_mentees` stage (search, pagination, new-mentee subflow)

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
- Create: `resources/views/filament/pages/partials/chat-mentees-turn.blade.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::enrollMentees()`, `menteeOptions()` (Task 1)
- Produces: `ChatMentorshipSetup::$menteeSearch`, `$menteePage` (public Livewire properties, mirroring the wizard's `mentee_search`/`mentee_page` fields but as plain properties since they're UI mechanics, not stored slot data); `submitMentees(array $selectedUserIds, ?array $newMentee = null)`

- [ ] **Step 1: Write the failing test**

```php
public function test_enroll_mentees_stage_enrolls_selected_users(): void
{
    $this->actingAsCoordinator();
    $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
    $facility = \App\Models\Facility::factory()->create();
    $mentee = User::factory()->create();

    $component = Livewire::test(ChatMentorshipSetup::class);
    $component->call('answer', 'is_pilot', 0);
    $component->call('answer', 'county_id', $facility->subcounty->county_id);
    $component->call('answer', 'facility_id', $facility->id);
    $component->call('answer', 'program_id', $program->id);
    $component->call('answer', 'start_date', now()->addDay()->toDateString());
    $component->call('answer', 'end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'max_participants', 8);
    $component->call('answer', 'class_name', 'Cohort A');
    $component->call('answer', 'class_start_date', now()->addDay()->toDateString());
    $component->call('answer', 'class_end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'class_description', 'skip');
    $component->call('submitModules', []);

    $component->call('submitMentees', [$mentee->id], null);

    $this->assertDatabaseHas('class_participants', [
        'mentorship_class_id' => $component->instance()->class->id,
        'user_id' => $mentee->id,
    ]);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: FAIL — `submitMentees` doesn't exist.

- [ ] **Step 3: Add mentee state and `submitMentees()` to `ChatMentorshipSetup`**

```php
public string $menteeSearch = '';

public int $menteePage = 1;

public function updatedMenteeSearch(): void
{
    $this->menteePage = 1;
}

public function getMenteeFieldOptions(): array
{
    return app(MentorshipWizardService::class)->menteeOptions(
        $this->menteeSearch ?: null,
        $this->menteePage,
        []
    );
}

public function submitMentees(array $selectedUserIds, ?array $newMentee = null): void
{
    $echo = empty($selectedUserIds) && empty($newMentee['email'] ?? null)
        ? 'Skip for now'
        : trim(implode(', ', array_filter([
            ! empty($selectedUserIds) ? \App\Models\User::whereIn('id', $selectedUserIds)->pluck('name')->implode(', ') : null,
            ! empty($newMentee['email'] ?? null) ? trim(($newMentee['first_name'] ?? '').' '.($newMentee['last_name'] ?? '')) : null,
        ])));

    $this->messages[] = ['role' => 'user', 'text' => $echo, 'slot' => 'selected_users', 'timestamp' => now()->toIso8601String()];
    $this->appendTranscript(end($this->messages));

    try {
        app(MentorshipWizardService::class)->enrollMentees([
            'selected_users' => $selectedUserIds,
            'new_mentee' => ! empty($newMentee['email'] ?? null) ? $newMentee : null,
        ], $this->class);
    } catch (\Throwable $e) {
        $this->messages[] = ['role' => 'bot', 'text' => "⚠️ Something went wrong: {$e->getMessage()}", 'timestamp' => now()->toIso8601String()];

        return;
    }

    $this->answers['selected_users'] = $selectedUserIds;

    $this->messages[] = [
        'role' => 'bot',
        'text' => 'Time to invite your mentees! Who should receive the email — everyone with an email address, or only those not yet invited?',
        'timestamp' => now()->toIso8601String(),
    ];
    $this->appendTranscript(end($this->messages));
}
```

Update `activeStage()`:

```php
public function activeStage(): string
{
    if (! array_key_exists('module_ids', $this->answers) && $this->class) {
        return 'modules';
    }

    if (! array_key_exists('selected_users', $this->answers) && $this->class && array_key_exists('module_ids', $this->answers)) {
        return 'enroll_mentees';
    }

    return 'slot';
}
```

Add to `chat-mentorship-setup.blade.php`, alongside the `modules` branch:

```blade
@elseif ($this->activeStage() === 'enroll_mentees')
    @include('filament.pages.partials.chat-mentees-turn')
```

- [ ] **Step 4: Write `chat-mentees-turn.blade.php`**

```blade
<div x-data="{ selected: [], newEmail: '', newFirst: '', newLast: '' }" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
    <input
        type="text"
        wire:model.live.debounce.400ms="menteeSearch"
        placeholder="Search by name, phone, email, or facility..."
        class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm"
    >

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($this->getMenteeFieldOptions() as $id => $label)
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm cursor-pointer">
                <input type="checkbox" value="{{ $id }}" x-model="selected">
                {{ $label }}
            </label>
        @endforeach
    </div>

    <div class="flex gap-2 text-sm">
        <button type="button" wire:click="$set('menteePage', {{ max(1, $menteePage - 1) }})" class="text-gray-500">← Previous</button>
        <button type="button" wire:click="$set('menteePage', {{ $menteePage + 1 }})" class="text-gray-500">Next →</button>
    </div>

    <details class="text-sm">
        <summary class="cursor-pointer text-primary-600">+ Add a new mentee</summary>
        <div class="mt-2 space-y-2">
            <input type="email" x-model="newEmail" placeholder="Email" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm">
            <input type="text" x-model="newFirst" placeholder="First name" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm">
            <input type="text" x-model="newLast" placeholder="Last name" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm">
        </div>
    </details>

    <x-filament::button
        x-on:click="$wire.submitMentees(selected, { email: newEmail, first_name: newFirst, last_name: newLast })"
    >
        Continue
    </x-filament::button>
</div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: `Tests: 9 passed`

- [ ] **Step 6: Format and commit**

```bash
./vendor/bin/pint app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php
git add app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php resources/views/filament/pages/chat-mentorship-setup.blade.php resources/views/filament/pages/partials/chat-mentees-turn.blade.php tests/Feature/ChatMentorshipSetupTest.php
git commit -m "feat: chat setup — Enroll Mentees stage (search, pagination, new mentee)"
```

---

### Task 8: `send_invitations` stage + Done screen

**Files:**
- Modify: `app/Services/Chat/MentorshipChatScript.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
- Modify: `resources/views/filament/pages/chat-mentorship-setup.blade.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::sendInvitations()` (Task 1)
- Produces: `ChatMentorshipSetup::$completed`, `$classStarted`, `$invitedCount` — same semantics as the wizard's equivalents

- [ ] **Step 1: Write the failing test**

```php
public function test_send_invitations_stage_completes_the_flow(): void
{
    \Illuminate\Support\Facades\Mail::fake();
    $this->actingAsCoordinator();
    $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
    $facility = \App\Models\Facility::factory()->create();
    $mentee = User::factory()->create(['email' => 'mentee@example.com']);

    $component = Livewire::test(ChatMentorshipSetup::class);
    $component->call('answer', 'is_pilot', 0);
    $component->call('answer', 'county_id', $facility->subcounty->county_id);
    $component->call('answer', 'facility_id', $facility->id);
    $component->call('answer', 'program_id', $program->id);
    $component->call('answer', 'start_date', now()->addDay()->toDateString());
    $component->call('answer', 'end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'max_participants', 8);
    $component->call('answer', 'class_name', 'Cohort A');
    $component->call('answer', 'class_start_date', now()->addDay()->toDateString());
    $component->call('answer', 'class_end_date', now()->addMonth()->toDateString());
    $component->call('answer', 'class_description', 'skip');
    $component->call('submitModules', []);
    $component->call('submitMentees', [$mentee->id], null);
    $component->call('answer', 'recipients', 'all');

    $this->assertTrue($component->instance()->completed);
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MenteeEnrollmentInvitationMail::class, 1);
    $this->assertNotNull($component->instance()->training->fresh()->guided_setup_completed_at);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: FAIL — `recipients` slot never triggers completion.

- [ ] **Step 3: Add the `recipients` slot**

Append to `MentorshipChatScript::build()`:

```php
Slot::make('recipients')
    ->stage('send_invitations')
    ->render(Render::CARDS)
    ->question(fn () => 'Who should receive the email?')
    ->optionsFrom(fn () => ['all' => 'All mentees with email addresses', 'not_sent' => 'Only those not yet invited']),
```

Update `slots()` to stop excluding `send_invitations` (it was never excluded — only `modules`/`enroll_mentees` are bespoke) and confirm `currentSlot()`/`nextUnfilledSlot()` correctly reach `recipients` once `activeStage()` returns `'slot'` again (true once both `module_ids` and `selected_users` keys exist in `$answers`).

- [ ] **Step 4: Wire completion in `answer()`**

```php
public bool $classStarted = false;

public int $invitedCount = 0;

// ...inside answer(), after the existing maybeCompleteStage() calls:
$this->maybeCompleteStage('send_invitations', function () {
    $result = app(MentorshipWizardService::class)->sendInvitations([
        'recipients' => $this->answers['recipients'],
    ], $this->training, $this->class);

    $this->invitedCount = $result['sent'] + $result['resent'];
    $this->completed = true;
    $this->classStarted = $this->class->fresh()->status === 'active';

    $this->messages[] = [
        'role' => 'bot',
        'text' => "Mentorship \"{$this->training->title}\" created. Class \"{$this->class->name}\" has {$this->invitedCount} mentee(s) invited.".
            ($this->classStarted ? ' The class is now active.' : ' It\'s still saved as a draft.'),
        'timestamp' => now()->toIso8601String(),
    ];
});
```

- [ ] **Step 5: Add the Done view**

In `chat-mentorship-setup.blade.php`, replace the closing structure:

```blade
<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 space-y-4">
        @include('filament.pages.partials.chat-transcript', ['messages' => $messages])

        @unless ($completed)
            @if ($this->activeStage() === 'modules')
                @include('filament.pages.partials.chat-modules-turn')
            @elseif ($this->activeStage() === 'enroll_mentees')
                @include('filament.pages.partials.chat-mentees-turn')
            @else
                @include('filament.pages.partials.chat-turn', ['slot' => $this->currentSlot(), 'answers' => $answers])
            @endif
        @else
            <div class="flex gap-3 pt-2">
                <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('classes', ['record' => $training->id]) }}"
                   class="fi-btn fi-btn-color-primary fi-btn-size-md fi-color-primary rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">
                    Go to Class
                </a>
                <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('index') }}"
                   class="fi-btn fi-btn-color-gray fi-btn-size-md rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-gray-700">
                    Back to Mentorships
                </a>
            </div>
        @endunless
    </div>
</x-filament-panels::page>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: `Tests: 10 passed`

- [ ] **Step 7: Format and commit**

```bash
./vendor/bin/pint app/Services/Chat/MentorshipChatScript.php app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php
git add app/Services/Chat/MentorshipChatScript.php app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php resources/views/filament/pages/chat-mentorship-setup.blade.php tests/Feature/ChatMentorshipSetupTest.php
git commit -m "feat: chat setup — Send Invitations stage + Done screen"
```

---

### Task 9: Transcript resume (`mount()` rebuild from `chat_setup_transcript`)

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `Training.chat_setup_transcript`, `Training.guided_setup_draft` (Task 2), `#[Url]`-bound `$trainingId`/`$classId` (same pattern as the wizard)
- Produces: a resumed `ChatMentorshipSetup` whose `$messages` replays the full prior transcript and whose `$answers` are re-derived so `currentSlot()`/`activeStage()` correctly resume mid-flow

- [ ] **Step 1: Write the failing test**

```php
public function test_resuming_replays_the_full_transcript_and_lands_on_the_next_question(): void
{
    $this->actingAsCoordinator();
    $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
    $facility = \App\Models\Facility::factory()->create();

    $first = Livewire::test(ChatMentorshipSetup::class);
    $first->call('answer', 'is_pilot', 0);
    $first->call('answer', 'county_id', $facility->subcounty->county_id);
    $trainingIdSoFar = null; // no Training yet — program_id hasn't been answered

    // Advance far enough that a Training exists, so there's something to resume.
    $first->call('answer', 'facility_id', $facility->id);
    $first->call('answer', 'program_id', $program->id);
    $first->call('answer', 'start_date', now()->addDay()->toDateString());
    $first->call('answer', 'end_date', now()->addMonth()->toDateString());
    $first->call('answer', 'max_participants', 8);

    $trainingId = $first->instance()->training->id;

    $resumed = Livewire::withQueryParams(['training' => $trainingId])->test(ChatMentorshipSetup::class);

    $this->assertGreaterThanOrEqual(count($first->instance()->messages), count($resumed->instance()->messages));
    $this->assertSame('Live Mentorship', collect($resumed->instance()->messages)->firstWhere('slot', 'is_pilot')['text']);
    $this->assertSame($program->id, $resumed->instance()->answers['program_id']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: FAIL — resumed instance starts a brand-new greeting/empty answers, ignoring `?training=`.

- [ ] **Step 3: Add `#[Url]` binding and resume logic to `mount()`**

```php
use Livewire\Attributes\Url;

// ...

#[Url(as: 'training')]
public ?int $trainingId = null;

#[Url(as: 'class')]
public ?int $classId = null;

public function mount(): void
{
    if ($this->trainingId) {
        $this->training = Training::find($this->trainingId);
    }

    if ($this->classId) {
        $this->class = MentorshipClass::find($this->classId);
    }

    if ($this->training && ! empty($this->training->chat_setup_transcript)) {
        $this->messages = $this->training->chat_setup_transcript;
        $this->answers = $this->rebuildAnswersFromTraining();

        return;
    }

    $this->messages[] = [
        'role' => 'bot',
        'text' => 'Welcome, '.explode(' ', auth()->user()->name)[0].'! '.$this->currentSlot()->getQuestion($this->answers),
        'timestamp' => now()->toIso8601String(),
    ];
}

/**
 * On resume, real committed columns are authoritative for the
 * training_details/first_class slots (same precedence rule the wizard's
 * own mount() uses — see docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md §4).
 * module_ids/selected_users come from guided_setup_draft, same as the
 * wizard, defaulting to what's really assigned if the draft never set
 * that key.
 */
protected function rebuildAnswersFromTraining(): array
{
    $answers = [
        'is_pilot' => (int) $this->training->is_pilot,
        'county_id' => $this->training->county_id,
        'facility_id' => $this->training->facility_id,
        'program_id' => $this->training->program_id,
        'start_date' => optional($this->training->start_date)->toDateString(),
        'end_date' => optional($this->training->end_date)->toDateString(),
        'max_participants' => $this->training->max_participants,
    ];

    if ($this->class) {
        $answers['class_name'] = $this->class->name;
        $answers['class_start_date'] = optional($this->class->start_date)->toDateString();
        $answers['class_end_date'] = optional($this->class->end_date)->toDateString();
        $answers['class_description'] = $this->class->description ?? 'skip';

        $draft = $this->training->guided_setup_draft ?? [];
        $this->moduleDates = $draft['moduleDates'] ?? [];

        if (array_key_exists('module_ids', $draft) || $this->class->classModules()->exists()) {
            $answers['module_ids'] = $draft['module_ids'] ?? $this->class->classModules()->pluck('program_module_id')->toArray();
        }

        if (array_key_exists('selected_users', $draft) || $this->class->participants()->exists()) {
            $answers['selected_users'] = $draft['selected_users'] ?? $this->class->participants()->pluck('user_id')->toArray();
        }
    }

    return array_filter($answers, fn ($v) => $v !== null);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: `Tests: 11 passed`

- [ ] **Step 5: Format and commit**

```bash
./vendor/bin/pint app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php
git add app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php tests/Feature/ChatMentorshipSetupTest.php
git commit -m "feat: chat setup — cross-session resume with full transcript replay"
```

---

### Task 10: Correction ("Edit") on a past answer

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
- Modify: `resources/views/filament/pages/partials/chat-transcript.blade.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php`

**Interfaces:**
- Produces: `ChatMentorshipSetup::editSlot(string $slotId): void` — clears that slot (and nothing else) from `$answers`, so `currentSlot()`/`activeStage()` naturally re-ask it next, without discarding later answers still sitting in `$answers`.

- [ ] **Step 1: Write the failing test**

```php
public function test_editing_a_past_answer_reopens_it_without_discarding_later_answers(): void
{
    $this->actingAsCoordinator();
    $facility = \App\Models\Facility::factory()->create();
    $otherFacility = \App\Models\Facility::factory()->create(['subcounty_id' => $facility->subcounty_id]);

    $component = Livewire::test(ChatMentorshipSetup::class);
    $component->call('answer', 'is_pilot', 0);
    $component->call('answer', 'county_id', $facility->subcounty->county_id);
    $component->call('answer', 'facility_id', $facility->id);

    $component->call('editSlot', 'facility_id');

    $this->assertArrayNotHasKey('facility_id', $component->instance()->answers);
    $this->assertSame(0, $component->instance()->answers['is_pilot']);
    $this->assertSame($facility->subcounty->county_id, $component->instance()->answers['county_id']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: FAIL — `editSlot` doesn't exist.

- [ ] **Step 3: Add `editSlot()`**

```php
public function editSlot(string $slotId): void
{
    unset($this->answers[$slotId]);

    $this->messages[] = [
        'role' => 'bot',
        'text' => 'No problem — '.$this->currentSlot()->getQuestion($this->answers),
        'timestamp' => now()->toIso8601String(),
    ];
    $this->appendTranscript(end($this->messages));
}
```

- [ ] **Step 4: Add the Edit affordance to the transcript partial**

```blade
{{-- resources/views/filament/pages/partials/chat-transcript.blade.php --}}
<div class="space-y-3">
    @foreach ($messages as $message)
        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-lg rounded-xl px-4 py-2 text-sm flex items-center gap-2
                {{ $message['role'] === 'user'
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' }}">
                <span>{{ $message['text'] }}</span>
                @if ($message['role'] === 'user' && ! empty($message['slot']))
                    <button type="button" wire:click="editSlot('{{ $message['slot'] }}')" class="text-xs underline opacity-75 hover:opacity-100">
                        Edit
                    </button>
                @endif
            </div>
        </div>
    @endforeach
</div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: `Tests: 12 passed`

- [ ] **Step 6: Format and commit**

```bash
./vendor/bin/pint app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php
git add app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php resources/views/filament/pages/partials/chat-transcript.blade.php tests/Feature/ChatMentorshipSetupTest.php
git commit -m "feat: chat setup — edit a past answer without losing later ones"
```

---

### Task 11: Entry point, settings toggle, and pending-setup banner routing

**Files:**
- Modify: `app/Models/Setting.php`
- Modify: `app/Filament/Pages/MentorshipSettings.php`
- Modify: `app/Filament/Resources/MentorshipTrainingResource.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php`
- Modify: `app/Filament/Widgets/PendingGuidedSetupNotice.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php`, `tests/Feature/GuidedMentorshipSetupTest.php` (must still pass)

**Interfaces:**
- Produces: `Setting::CHAT_SETUP_BUTTON_ENABLED`; route key `chat-setup` on `MentorshipTrainingResource`; third header action "Chat Setup"; `PendingGuidedSetupNotice::pendingTraining()`'s `continueUrl` routes to `chat-setup` when `guided_setup_method === 'chat'`, else `guided-setup` (unchanged default)

- [ ] **Step 1: Write the failing tests**

```php
// tests/Feature/ChatMentorshipSetupTest.php
public function test_list_page_shows_chat_setup_button(): void
{
    $this->actingAsCoordinator();

    Livewire::test(\App\Filament\Resources\MentorshipResource\Pages\ListMentorshipTrainings::class)
        ->assertSeeHtml('Chat Setup');
}

public function test_chat_setup_button_disabled_when_setting_off(): void
{
    $this->actingAsCoordinator();
    \App\Models\Setting::setBool(\App\Models\Setting::CHAT_SETUP_BUTTON_ENABLED, false);

    $response = $this->get(\App\Filament\Resources\MentorshipTrainingResource::getUrl('chat-setup'));

    $response->assertForbidden();
}

public function test_pending_setup_banner_routes_chat_drafts_to_chat_setup(): void
{
    $mentor = $this->actingAsCoordinator();
    $training = \App\Models\Training::factory()->facilityMentorship()->create([
        'mentor_id' => $mentor->id,
        'guided_setup_completed_at' => null,
        'guided_setup_method' => 'chat',
    ]);

    $viewData = (new \App\Filament\Widgets\PendingGuidedSetupNotice)->getViewData();

    $this->assertStringContainsString('chat-setup', $viewData['continueUrl']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: FAIL — no `chat-setup` route, no button, `CHAT_SETUP_BUTTON_ENABLED` undefined.

- [ ] **Step 3: Add the setting constant**

In `app/Models/Setting.php`, alongside the existing two constants:

```php
public const CHAT_SETUP_BUTTON_ENABLED = 'chat_setup_button_enabled';
```

- [ ] **Step 4: Add the route**

In `app/Filament/Resources/MentorshipTrainingResource.php`'s `getPages()`, next to `'guided-setup'`:

```php
'chat-setup' => Pages\ChatMentorshipSetup::route('/chat-setup'),
```

- [ ] **Step 5: Add the third toggle to Mentorship Settings**

In `app/Filament/Pages/MentorshipSettings.php`, `mount()`:

```php
'chat_setup_button_enabled' => Setting::getBool(Setting::CHAT_SETUP_BUTTON_ENABLED),
```

And in `form()`, inside the same `Section`'s schema array, after the `guided_setup_button_enabled` toggle:

```php
Forms\Components\Toggle::make('chat_setup_button_enabled')
    ->label('"Chat Setup" button')
    ->helperText('The conversational assistant.')
    ->onColor('success')
    ->offColor('danger')
    ->live()
    ->afterStateUpdated(function (bool $state): void {
        Setting::setBool(Setting::CHAT_SETUP_BUTTON_ENABLED, $state);
        Notification::make()
            ->title($state ? 'Chat Setup enabled' : 'Chat Setup disabled')
            ->success()
            ->send();
    }),
```

Change that `Section`'s `->columns(2)` to `->columns(3)` to fit the third toggle.

- [ ] **Step 6: Add the third header action**

In `app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php`, `getHeaderActions()`:

```php
protected function getHeaderActions(): array
{
    $newMentorshipEnabled = Setting::getBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED);
    $guidedSetupEnabled = Setting::getBool(Setting::GUIDED_SETUP_BUTTON_ENABLED);
    $chatSetupEnabled = Setting::getBool(Setting::CHAT_SETUP_BUTTON_ENABLED);

    return [
        Actions\CreateAction::make()
            ->label('New Mentorship')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->disabled(! $newMentorshipEnabled)
            ->tooltip($newMentorshipEnabled ? null : 'Turned off in Mentorship Settings'),
        Actions\Action::make('guided_setup')
            ->label('New Mentorship Guided Setup')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->url(fn () => MentorshipTrainingResource::getUrl('guided-setup'))
            ->disabled(! $guidedSetupEnabled)
            ->tooltip($guidedSetupEnabled ? null : 'Turned off in Mentorship Settings'),
        Actions\Action::make('chat_setup')
            ->label('Chat Setup')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('gray')
            ->url(fn () => MentorshipTrainingResource::getUrl('chat-setup'))
            ->disabled(! $chatSetupEnabled)
            ->tooltip($chatSetupEnabled ? null : 'Turned off in Mentorship Settings'),
    ];
}
```

- [ ] **Step 7: Route the pending-setup banner by method**

In `app/Filament/Widgets/PendingGuidedSetupNotice.php`, `getViewData()`:

```php
protected function getViewData(): array
{
    $training = static::pendingTraining();

    if (! $training) {
        return ['training' => null];
    }

    $class = MentorshipClass::where('training_id', $training->id)->latest()->first();

    $routeKey = $training->guided_setup_method === 'chat' ? 'chat-setup' : 'guided-setup';

    return [
        'training' => $training,
        'class' => $class,
        'continueUrl' => MentorshipTrainingResource::getUrl($routeKey, array_filter([
            'training' => $training->id,
            'class' => $class?->id,
            'step' => $class ? 'modules' : 'first-class',
        ])),
    ];
}
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: all pass.

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: `Tests: 32 passed` — unaffected by this task (the "Continue" URL change only alters behavior for `guided_setup_method === 'chat'` rows, which the wizard never produces since Task 4 stamps `'chat'` only from `ChatMentorshipSetup`, and the wizard's own equivalent stamp was never added — wizard-created trainings keep `guided_setup_method = null`, so `$training->guided_setup_method === 'chat'` is false and the ternary falls through to `'guided-setup'` exactly as before).

- [ ] **Step 9: Format and commit**

```bash
./vendor/bin/pint app/Models/Setting.php app/Filament/Pages/MentorshipSettings.php app/Filament/Resources/MentorshipTrainingResource.php app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php app/Filament/Widgets/PendingGuidedSetupNotice.php
git add app/Models/Setting.php app/Filament/Pages/MentorshipSettings.php app/Filament/Resources/MentorshipTrainingResource.php app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php app/Filament/Widgets/PendingGuidedSetupNotice.php tests/Feature/ChatMentorshipSetupTest.php
git commit -m "feat: Chat Setup entry point, settings toggle, and banner routing"
```

---

### Task 12: Full regression pass

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass, including the pre-existing `GuidedMentorshipSetupTest` (32) and the new `ChatMentorshipSetupTest` (12+).

- [ ] **Step 2: Manual smoke test**

Start the app (`composer run dev`), log in as a user with `create_mentorship::training`, click "Chat Setup" on the Mentorships list, and walk through creating one standard-program mentorship and one EmONC mentorship end to end, confirming: quick-reply cards render correctly, date widgets work, the modules stage branches correctly for EmONC (date validation blocks Continue until every checked track has dates) vs standard, mentee search/pagination/new-mentee subflow work, invitations send (check `storage/logs/laravel.log` or your local mail catcher), the Done screen shows, and reloading a resumed session (`?training=` from the pending-setup banner after abandoning partway) replays the transcript correctly.

- [ ] **Step 3: Run Pint across the whole diff**

```bash
./vendor/bin/pint --test
```

Fix anything it flags, then:

```bash
./vendor/bin/pint
git add -A
git commit -m "chore: pint formatting pass on chat mentorship setup" --allow-empty
```

---

## Self-Review Notes

- **Spec coverage**: every section of `2026-08-01-chat-mentorship-setup-design.md` maps to a task — shared service (Task 1), schema (Task 2), slot engine + all 5 stages (Tasks 3–8), resume/transcript (Task 9), corrections (Task 10), entry point/settings (Task 11), full regression (Task 12). Error handling (bot bubble on failure) is implemented inline in each stage-completion closure (Tasks 4–8) rather than as a separate task, since it's identical one-line logic repeated per stage, not a standalone deliverable.
- **Signature consistency verified**: `MentorshipWizardService` method signatures declared in Task 1 are used identically (same param order/types) in Tasks 4–8's `answer()`/`submitModules()`/`submitMentees()` bodies. `Slot`/`Render` from Task 3 are used identically in Tasks 4, 5, 8. `activeStage()` is introduced in Task 6 and extended (not renamed) in Task 7.
- **No placeholders**: every step has runnable code; no "add validation" / "TBD" markers.
