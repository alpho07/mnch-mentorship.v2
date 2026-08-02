# MNCHGPT — LLM-Powered Mentorship Assistant Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a fourth mentorship-creation method, "MNCHGPT," that fills the same setup fields as the existing Chat Setup from free-form text via DeepSeek-V3 tool-calling, plus answers read-only analytics questions (mentorship/mentee counts, trends, dashboard coverage, assessment summaries) — all through the same conversational surface, with the deterministic engine as the sole source of truth for what's valid.

**Architecture:** Extract the existing `ChatMentorshipSetup` page's slot-answering logic into a shared trait so a new `MnchGptSetup` page can reuse it unchanged. Add a small `ChatTool`/`ChatToolRegistry` core that any tool family plugs into, backed by `LlmMentorshipAssistantService` (a DeepSeek HTTP client running the standard two-step tool-calling loop). Four tool-provider classes register the actual capabilities: setup-slot filling (reuses the existing `Slot::validate()`), mentorship stats, dashboard analytics, and assessment summaries — the latter three each backed by a new thin query service that mirrors the exact role-scoping the equivalent existing page already enforces.

**Tech Stack:** Laravel 12, Filament v3, Livewire, DeepSeek-V3 API (OpenAI-compatible tool-calling), PHPUnit.

## Global Constraints

- Every setup value the LLM proposes MUST pass through the existing `Slot::validate()` before being committed — never trust the LLM's output directly.
- Every query tool's `authorize(User $user)` MUST re-check the same role/scope rule the equivalent existing page already enforces (mirrored from `MentorshipStatsOverview`, `AnalyticsDashboardController`, or `AssessmentResource::getEloquentQuery()` as noted per task) — a tool must not even be registered into the schema if `authorize()` fails.
- The existing `ChatMentorshipSetup` page's behavior must not change — verified by the existing `tests/Feature/ChatMentorshipSetupTest.php` suite passing unmodified after the trait extraction.
- No new Composer dependency for the DeepSeek integration — plain Laravel `Http` facade calls to its OpenAI-compatible REST endpoint.
- `remainingRequirements()` is deterministic (no LLM involvement) — it must never omit or hallucinate an item.
- Full regression suite must stay green after every task (pre-existing `ExampleTest`/`LookupApiTest` failures are the only acceptable exceptions, per the project's established baseline).

---

## Task 1: Extract `HasMentorshipChatSlots` trait from `ChatMentorshipSetup`

**Files:**
- Create: `app/Filament/Resources/MentorshipResource/Pages/Concerns/HasMentorshipChatSlots.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`
- Test: `tests/Feature/ChatMentorshipSetupTest.php` (existing — must pass unmodified, do not edit)

**Interfaces:**
- Produces: `HasMentorshipChatSlots` trait exposing everything `ChatMentorshipSetup` currently has except `canAccess()` and the `$view`/`$resource`/`$shouldRegisterNavigation` static properties — i.e. all public properties (`$messages`, `$answers`, `$training`, `$class`, `$completed`, `$classStarted`, `$invitedCount`, `$moduleDates`, `$trainingId`, `$classId`, `$menteeSearch`, `$menteePage`, `$menteesNeedingEmail`, `$pendingEmails`, `$pendingMenteeSelection`, `$pendingNewMentee`) and every method (`updatedModuleDates()`, `activeStage()`, `isModulesStageEmonc()`, `getEmoncModuleTree()`, `getModuleFieldOptions()`, `submitModules()`, `updatedMenteeSearch()`, `getMenteeFieldOptions()`, `checkAndSubmitMentees()`, `saveMenteeEmailsAndContinue()`, `cancelMenteeEmailPrompt()`, `submitMentees()`, `mount()`, `rebuildAnswersFromTraining()`, `slots()`, `nextUnfilledSlot()`, `answer()`, `editSlot()`, `maybeCompleteStage()`, `syncTranscript()`). `mount()` stays in the trait exactly as-is — it doesn't reference anything page-specific.

This is a pure Extract Trait refactor — copy the body of every property/method listed above from `ChatMentorshipSetup.php` verbatim into the new trait, then replace `ChatMentorshipSetup`'s body with `use HasMentorshipChatSlots;` plus only what stays page-specific: `use InteractsWithForms;`, `protected static string $resource = MentorshipTrainingResource::class;`, `protected static string $view = 'filament.pages.chat-mentorship-setup';`, `protected static bool $shouldRegisterNavigation = false;`, and `canAccess()`.

- [ ] **Step 1: Run the existing test suite to capture the baseline**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: All tests PASS (this is your baseline — the same result must hold after the extraction).

- [ ] **Step 2: Create the trait with the full extracted body**

```php
<?php

namespace App\Filament\Resources\MentorshipResource\Pages\Concerns;

use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use App\Services\Chat\MentorshipChatScript;
use App\Services\Chat\Slot;
use App\Services\MentorshipWizardService;
use Livewire\Attributes\Url;

/**
 * Shared slot-answering engine for both the click-driven ChatMentorshipSetup
 * page and the free-text MnchGptSetup page — extracted verbatim from
 * ChatMentorshipSetup so both stay in sync automatically. See
 * docs/superpowers/specs/2026-08-03-mnchgpt-llm-assistant-design.md.
 */
trait HasMentorshipChatSlots
{
    public array $messages = [];

    public array $answers = [];

    public ?Training $training = null;

    public ?MentorshipClass $class = null;

    public bool $completed = false;

    public bool $classStarted = false;

    public int $invitedCount = 0;

    public array $moduleDates = [];

    #[Url(as: 'training')]
    public ?int $trainingId = null;

    #[Url(as: 'class')]
    public ?int $classId = null;

    public string $menteeSearch = '';

    public int $menteePage = 1;

    /** @var array<int, array{id: int, name: string}> mentees pending an email before they can be invited */
    public array $menteesNeedingEmail = [];

    /** @var array<int, string> keyed by user id, bound live to the email-prompt modal's inputs */
    public array $pendingEmails = [];

    public array $pendingMenteeSelection = [];

    public ?array $pendingNewMentee = null;

    public function updatedModuleDates(): void
    {
        if ($this->training) {
            app(MentorshipWizardService::class)->saveWizardDraft($this->training, 'moduleDates', $this->moduleDates);
        }
    }

    /**
     * Which turn to render below the transcript: the two composite stages
     * (module picking, mentee search/enroll) aren't declared as generic
     * Slot objects — their options and widgets depend on real $training/
     * $class model instances and, for modules, a per-row date modal — same
     * reasoning the wizard itself already applies to these two steps (see
     * docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md §6, Modules & Enroll
     * Mentees). Everything else goes through nextUnfilledSlot().
     */
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

    public function isModulesStageEmonc(): bool
    {
        return app(MentorshipWizardService::class)->isEmoncProgram($this->training->program_id);
    }

    /**
     * Parent modules with their tracks attached (as ->availableChildren),
     * exactly what EmoncModulePicker::getModules() already returns for the
     * wizard — reused as-is so the chat picker shows the same tracks (e.g.
     * PPH's 11) instead of collapsing each parent+tracks down to just the
     * parent, which is what a flat pluck('name', 'id') would do.
     */
    public function getEmoncModuleTree(): \Illuminate\Support\Collection
    {
        $picker = new \App\Filament\Forms\Components\EmoncModulePicker('module_ids');
        $picker->training($this->training)->class($this->class)->includeAssigned();

        return $picker->getModules();
    }

    public function getModuleFieldOptions(): array
    {
        return \App\Models\ProgramModule::where('program_id', $this->training->program_id)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('order_sequence')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function submitModules(array $moduleIds): void
    {
        if ($this->isModulesStageEmonc()) {
            if ($error = app(MentorshipWizardService::class)->validateModuleDates($moduleIds, $this->moduleDates)) {
                $this->addError('value', $error);

                return;
            }
        }

        $echo = empty($moduleIds)
            ? 'Skip for now'
            : \App\Models\ProgramModule::whereIn('id', $moduleIds)->pluck('name')->implode(', ');

        $this->messages[] = ['role' => 'user', 'text' => $echo, 'slot' => 'module_ids', 'timestamp' => now()->toIso8601String()];

        try {
            app(MentorshipWizardService::class)->assignModules([
                'module_ids' => $moduleIds,
                'auto_create_sessions' => true,
                'module_dates' => $this->moduleDates,
            ], $this->training, $this->class);
        } catch (\Throwable $e) {
            $this->messages[] = ['role' => 'bot', 'text' => "⚠️ Something went wrong: {$e->getMessage()}", 'timestamp' => now()->toIso8601String()];
            $this->syncTranscript();

            return;
        }

        $this->moduleDates = [];
        $this->answers['module_ids'] = $moduleIds;

        $this->messages[] = [
            'role' => 'bot',
            'text' => 'Who will be mentored in this class? Search or tell me a name to add someone new — or say "skip" for now.',
            'timestamp' => now()->toIso8601String(),
        ];
        $this->syncTranscript();
    }

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

    /**
     * Gate in front of submitMentees(): enforces the max_participants cap
     * (mirrors CardCheckboxList::maxSelections() in the wizard — same rule,
     * same "already-checked can still be unchecked past the cap" client
     * behavior in chat-mentees-turn.blade.php, this is the server-side
     * backstop), then checks whether any selected *existing* user has no
     * email on file. sendInvitations() silently skips anyone without an
     * email — rather than let that happen invisibly, pause here and let
     * the coordinator add one (or explicitly leave it blank to skip that
     * mentee) before actually enrolling anyone.
     */
    public function checkAndSubmitMentees(array $selectedUserIds, ?array $newMentee = null): void
    {
        $max = $this->training->max_participants;

        if ($max && count($selectedUserIds) > $max) {
            $this->addError('value', "You can select at most {$max} mentees.");

            return;
        }

        $missing = User::whereIn('id', $selectedUserIds)
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
            ->get(['id', 'name']);

        if ($missing->isNotEmpty()) {
            $this->menteesNeedingEmail = $missing->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();
            $this->pendingEmails = [];
            $this->pendingMenteeSelection = $selectedUserIds;
            $this->pendingNewMentee = $newMentee;

            return;
        }

        $this->submitMentees($selectedUserIds, $newMentee);
    }

    public function saveMenteeEmailsAndContinue(): void
    {
        foreach ($this->menteesNeedingEmail as $mentee) {
            $email = trim($this->pendingEmails[$mentee['id']] ?? '');

            if ($email !== '') {
                User::where('id', $mentee['id'])->update(['email' => $email]);
            }
        }

        $selectedUserIds = $this->pendingMenteeSelection;
        $newMentee = $this->pendingNewMentee;
        $this->cancelMenteeEmailPrompt();

        $this->submitMentees($selectedUserIds, $newMentee);
    }

    public function cancelMenteeEmailPrompt(): void
    {
        $this->menteesNeedingEmail = [];
        $this->pendingEmails = [];
        $this->pendingMenteeSelection = [];
        $this->pendingNewMentee = null;
    }

    public function submitMentees(array $selectedUserIds, ?array $newMentee = null): void
    {
        $echo = empty($selectedUserIds) && empty($newMentee['email'] ?? null)
            ? 'Skip for now'
            : trim(implode(', ', array_filter([
                ! empty($selectedUserIds) ? User::whereIn('id', $selectedUserIds)->pluck('name')->implode(', ') : null,
                ! empty($newMentee['email'] ?? null) ? trim(($newMentee['first_name'] ?? '').' '.($newMentee['last_name'] ?? '')) : null,
            ])));

        $this->messages[] = ['role' => 'user', 'text' => $echo, 'slot' => 'selected_users', 'timestamp' => now()->toIso8601String()];

        try {
            app(MentorshipWizardService::class)->enrollMentees([
                'selected_users' => $selectedUserIds,
                'new_mentee' => ! empty($newMentee['email'] ?? null) ? $newMentee : null,
            ], $this->class);
        } catch (\Throwable $e) {
            $this->messages[] = ['role' => 'bot', 'text' => "⚠️ Something went wrong: {$e->getMessage()}", 'timestamp' => now()->toIso8601String()];
            $this->syncTranscript();

            return;
        }

        $this->answers['selected_users'] = $selectedUserIds;

        $this->messages[] = [
            'role' => 'bot',
            'text' => 'Time to invite your mentees! Who should receive the email — everyone with an email address, or only those not yet invited?',
            'timestamp' => now()->toIso8601String(),
        ];
        $this->syncTranscript();
    }

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
            'text' => 'Welcome, '.explode(' ', auth()->user()->name)[0].'! '.$this->nextUnfilledSlot()->getQuestion($this->answers),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * On resume, real committed columns are authoritative for the
     * training_details/first_class slots (same precedence rule the
     * wizard's own mount() uses — see
     * docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md §4). module_ids/
     * selected_users come from guided_setup_draft, same as the wizard,
     * defaulting to what's really assigned if the draft never set that key.
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

    public function slots(): array
    {
        return MentorshipChatScript::build($this);
    }

    /**
     * The next question to ask, or null once every currently-defined slot
     * is answered (e.g. all of training_details is filled but no later
     * stage exists in the script yet, or the flow is genuinely done). The
     * view treats null as "nothing generic to render right now."
     */
    public function nextUnfilledSlot(): ?Slot
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

        $this->maybeCompleteStage($slotId, 'training_details', function () {
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

        $this->maybeCompleteStage($slotId, 'first_class', function () {
            $this->class = app(MentorshipWizardService::class)->createFirstClass([
                'name' => $this->answers['class_name'],
                'start_date' => $this->answers['class_start_date'] ?? null,
                'end_date' => $this->answers['class_end_date'] ?? null,
                'description' => ($this->answers['class_description'] ?? null) === 'skip' ? null : ($this->answers['class_description'] ?? null),
            ], $this->training, $this->class);
        });

        $this->maybeCompleteStage($slotId, 'send_invitations', function () {
            $result = app(MentorshipWizardService::class)->sendInvitations([
                'recipients' => $this->answers['recipients'],
            ], $this->training, $this->class);

            $this->invitedCount = $result['sent'] + $result['resent'];
            $this->completed = true;
            $this->classStarted = $this->class->fresh()->status === 'active';

            $this->messages[] = [
                'role' => 'bot',
                'text' => "Mentorship \"{$this->training->title}\" created. Class \"{$this->class->name}\" has {$this->invitedCount} mentee(s) invited.".
                    ($this->classStarted ? ' The class is now active.' : " It's still saved as a draft."),
                'timestamp' => now()->toIso8601String(),
            ];
        });

        // Only announce the next *generic* slot's question here once we're
        // genuinely back to plain slot-answering. If completing training_
        // details/first_class just moved us into the bespoke Modules or
        // Enroll Mentees stage (activeStage() !== 'slot'), module_ids/
        // selected_users aren't generic Slot objects at all — calling
        // nextUnfilledSlot() here would skip straight past them to
        // whatever generic slot comes next in the script (recipients),
        // announcing "Who should receive the email?" before the mentorship
        // even has modules or mentees. submitModules()/submitMentees()
        // each append their own transition message once *they* complete.
        if ($this->activeStage() === 'slot') {
            $next = $this->nextUnfilledSlot();

            if ($next) {
                $this->messages[] = [
                    'role' => 'bot',
                    'text' => $next->getQuestion($this->answers),
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        }

        $this->syncTranscript();
    }

    public function editSlot(string $slotId): void
    {
        // module_ids/selected_users aren't generic Slot objects (see the
        // comment in answer()) — editing those bubbles goes through
        // submitModules()/submitMentees() re-rendering the bespoke turn,
        // not this generic re-ask flow, so there's nothing to do here for
        // them (their own bubble UI still checks the Continue action).
        if (! collect($this->slots())->contains('id', $slotId)) {
            return;
        }

        unset($this->answers[$slotId]);

        $next = $this->nextUnfilledSlot();

        if ($next) {
            $this->messages[] = [
                'role' => 'bot',
                'text' => 'No problem — '.$next->getQuestion($this->answers),
                'timestamp' => now()->toIso8601String(),
            ];
            $this->syncTranscript();
        }
    }

    /**
     * Fires $onComplete the moment every required, visible slot in $stage
     * has just been filled — guarded on $justAnsweredSlotId belonging to
     * $stage so it only fires once per stage, since
     * MentorshipWizardService::createTraining()/etc. are upserts and a
     * repeat call would be harmless but would spam duplicate confirmation
     * messages into the transcript.
     */
    protected function maybeCompleteStage(string $justAnsweredSlotId, string $stage, \Closure $onComplete): void
    {
        $stageSlots = array_filter($this->slots(), fn ($s) => $s->stage === $stage);

        if (empty($stageSlots) || ! collect($stageSlots)->contains(fn ($s) => $s->id === $justAnsweredSlotId)) {
            return;
        }

        $allFilled = collect($stageSlots)->every(
            fn ($slot) => ! $slot->isVisible($this->answers) || array_key_exists($slot->id, $this->answers)
        );

        if (! $allFilled) {
            return;
        }

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

    /**
     * Overwrites the persisted transcript with the full current
     * $this->messages array — not an incremental append, since $this
     * ->training frequently doesn't exist yet for the first several turns
     * (it's only created once the training_details stage completes,
     * partway through a call to answer()), so there's no reliable "append
     * from here" point. Safe/cheap to call repeatedly; a no-op until a
     * Training exists.
     */
    protected function syncTranscript(): void
    {
        if (! $this->training) {
            return;
        }

        $this->training->update(['chat_setup_transcript' => $this->messages]);
    }
}
```

- [ ] **Step 3: Replace `ChatMentorshipSetup`'s body to use the trait**

```php
<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipResource\Pages\Concerns\HasMentorshipChatSlots;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Setting;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;

class ChatMentorshipSetup extends Page implements HasForms
{
    use InteractsWithForms;
    use HasMentorshipChatSlots;

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
}
```

- [ ] **Step 4: Run the existing test suite to verify the extraction didn't change behavior**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: PASS — identical result to Step 1's baseline. If anything fails, the extraction moved something incorrectly; compare against the original file (available via `git show HEAD:app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php`) line by line rather than guessing.

- [ ] **Step 5: Run the full regression suite**

Run: `php artisan test`
Expected: Same pass/fail counts as the project's established baseline (all green except the pre-existing `ExampleTest`/`LookupApiTest` failures).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/Concerns/HasMentorshipChatSlots.php app/Filament/Resources/MentorshipResource/Pages/ChatMentorshipSetup.php
git commit -m "refactor: extract HasMentorshipChatSlots trait from ChatMentorshipSetup"
```

---

## Task 2: `remainingRequirements()` deterministic checklist

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/Concerns/HasMentorshipChatSlots.php`
- Test: `tests/Unit/MentorshipChatRemainingRequirementsTest.php` (create)

**Interfaces:**
- Consumes: `$this->slots()` (from Task 1, returns `Slot[]`), `$this->answers`, `$this->class`, `$this->training`.
- Produces: `remainingRequirements(): array` — each entry `['stage' => string, 'label' => string, 'filled' => bool]`. Only unfilled (`filled === false`) entries matter to callers, but the method returns filled ones too (with `filled => true`) so a future UI could show progress, not just what's missing. Task 7 relies on filtering `array_filter($requirements, fn ($r) => ! $r['filled'])` to render the checklist.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipChatRemainingRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_empty_answers_returns_the_full_checklist_including_composite_stages(): void
    {
        $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup();
        $page->mount();

        $requirements = $page->remainingRequirements();
        $labels = array_column($requirements, 'label');

        $this->assertContains('Select training modules', $labels);
        $this->assertContains('Enroll mentees', $labels);
        $this->assertContains('Send invitation emails', $labels);
        $this->assertTrue(collect($requirements)->every(fn ($r) => $r['filled'] === false));
    }

    public function test_filling_a_slot_removes_exactly_its_own_entry(): void
    {
        $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup();
        $page->mount();

        $before = collect($page->remainingRequirements())->pluck('filled', 'label');
        $this->assertFalse($before['Is this a real live mentorship or a pilot/test run?'] ?? null);

        $page->answers['is_pilot'] = 0;

        $after = collect($page->remainingRequirements())->pluck('filled', 'label');
        $this->assertTrue($after['Is this a real live mentorship or a pilot/test run?']);
        // Nothing else flipped.
        $this->assertFalse($after['Which county?']);
    }

    public function test_reaching_the_modules_stage_removes_its_placeholder_once_module_ids_is_set(): void
    {
        $this->actingAsCoordinator();
        $training = Training::factory()->facilityMentorship()->create();
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);

        $page = new ChatMentorshipSetup();
        $page->training = $training;
        $page->class = $class;
        $page->answers = [
            'is_pilot' => 0,
            'county_id' => 1,
            'facility_id' => 1,
            'program_id' => 1,
            'max_participants' => 5,
            'class_name' => 'Cohort 1',
        ];

        $labelsBefore = array_column($page->remainingRequirements(), 'label');
        $this->assertContains('Select training modules', $labelsBefore);

        $page->answers['module_ids'] = [1, 2];

        $labelsAfter = array_column($page->remainingRequirements(), 'label');
        $this->assertNotContains('Select training modules', $labelsAfter);
        $this->assertContains('Enroll mentees', $labelsAfter);
    }

    public function test_returns_empty_once_everything_including_invitations_is_done(): void
    {
        $this->actingAsCoordinator();
        $training = Training::factory()->facilityMentorship()->create();
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create();
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => $mentee->id]);

        $page = new ChatMentorshipSetup();
        $page->training = $training;
        $page->class = $class;
        $page->answers = [
            'is_pilot' => 0,
            'county_id' => 1,
            'facility_id' => 1,
            'program_id' => 1,
            'max_participants' => 5,
            'class_name' => 'Cohort 1',
            'class_description' => 'skip',
            'module_ids' => [1],
            'selected_users' => [$mentee->id],
            'recipients' => 'all',
        ];

        $remaining = array_filter($page->remainingRequirements(), fn ($r) => ! $r['filled']);

        $this->assertSame([], array_values($remaining));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MentorshipChatRemainingRequirementsTest`
Expected: FAIL with "Call to undefined method ChatMentorshipSetup::remainingRequirements()"

- [ ] **Step 3: Implement `remainingRequirements()`**

Add to `HasMentorshipChatSlots` (after `nextUnfilledSlot()`):

```php
    /**
     * Deterministic, non-LLM checklist of everything not yet done, spanning
     * the whole creation process — not just the current stage. Walks every
     * declared Slot in script order, then appends the two composite stages
     * (modules, mentee enrollment) as fixed entries once the flow has
     * reached them (module_ids/selected_users aren't Slot objects — see
     * activeStage()). Used by MnchGptSetup to render a persistent progress
     * checklist and to give the LLM accurate context instead of letting it
     * guess or rely on its own memory of the conversation.
     *
     * @return array<int, array{stage: string, label: string, filled: bool}>
     */
    public function remainingRequirements(): array
    {
        $requirements = [];

        foreach ($this->slots() as $slot) {
            if (! $slot->isRequired() || ! $slot->isVisible($this->answers)) {
                continue;
            }

            $requirements[] = [
                'stage' => $slot->stage,
                'label' => $slot->getQuestion($this->answers),
                'filled' => array_key_exists($slot->id, $this->answers),
            ];
        }

        if ($this->class) {
            $requirements[] = [
                'stage' => 'modules',
                'label' => 'Select training modules',
                'filled' => array_key_exists('module_ids', $this->answers),
            ];
            $requirements[] = [
                'stage' => 'enroll_mentees',
                'label' => 'Enroll mentees',
                'filled' => array_key_exists('selected_users', $this->answers),
            ];
        }

        return $requirements;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=MentorshipChatRemainingRequirementsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/Concerns/HasMentorshipChatSlots.php tests/Unit/MentorshipChatRemainingRequirementsTest.php
git commit -m "feat: add deterministic remainingRequirements() checklist"
```

---

## Task 3: `ChatTool` interface + `ChatToolRegistry`

**Files:**
- Create: `app/Services/Chat/ChatTool.php`
- Create: `app/Services/Chat/SimpleChatTool.php`
- Create: `app/Services/Chat/ChatToolRegistry.php`
- Test: `tests/Unit/ChatToolRegistryTest.php` (create)

**Interfaces:**
- Produces: `ChatTool` interface (`name()`, `description()`, `schema()`, `authorize(User $user): bool`, `execute(array $args, User $user): array`); `SimpleChatTool` (closure-based generic implementation, constructor `(string $name, string $description, array $schema, \Closure $authorize, \Closure $execute)`); `ChatToolRegistry` with `register(ChatTool $tool): static`, `schemasFor(User $user): array` (OpenAI tool-schema format, filtered to authorized tools), `execute(string $name, array $args, User $user): array` (throws `\RuntimeException` if the tool isn't registered or `authorize()` fails — this must never be reachable in practice since `schemasFor()` already filtered, but is the last line of defense).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Chat\ChatToolRegistry;
use App\Services\Chat\SimpleChatTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_schemas_for_only_includes_authorized_tools(): void
    {
        $allowedUser = User::factory()->create();
        $deniedUser = User::factory()->create();

        $registry = new ChatToolRegistry();
        $registry->register(new SimpleChatTool(
            name: 'do_thing',
            description: 'Does a thing.',
            schema: ['type' => 'object', 'properties' => []],
            authorize: fn (User $u) => $u->is($allowedUser),
            execute: fn (array $args, User $u) => ['ok' => true],
        ));

        $allowedSchemas = $registry->schemasFor($allowedUser);
        $deniedSchemas = $registry->schemasFor($deniedUser);

        $this->assertCount(1, $allowedSchemas);
        $this->assertSame('do_thing', $allowedSchemas[0]['function']['name']);
        $this->assertCount(0, $deniedSchemas);
    }

    public function test_execute_runs_the_tools_execute_closure(): void
    {
        $user = User::factory()->create();
        $registry = new ChatToolRegistry();
        $registry->register(new SimpleChatTool(
            name: 'add',
            description: 'Adds two numbers.',
            schema: ['type' => 'object', 'properties' => ['a' => ['type' => 'number'], 'b' => ['type' => 'number']]],
            authorize: fn (User $u) => true,
            execute: fn (array $args, User $u) => ['sum' => $args['a'] + $args['b']],
        ));

        $result = $registry->execute('add', ['a' => 2, 'b' => 3], $user);

        $this->assertSame(['sum' => 5], $result);
    }

    public function test_execute_throws_for_an_unauthorized_tool(): void
    {
        $deniedUser = User::factory()->create();
        $registry = new ChatToolRegistry();
        $registry->register(new SimpleChatTool(
            name: 'secret',
            description: 'Secret tool.',
            schema: ['type' => 'object', 'properties' => []],
            authorize: fn (User $u) => false,
            execute: fn (array $args, User $u) => ['leaked' => true],
        ));

        $this->expectException(\RuntimeException::class);

        $registry->execute('secret', [], $deniedUser);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ChatToolRegistryTest`
Expected: FAIL with "Class App\Services\Chat\ChatToolRegistry not found"

- [ ] **Step 3: Create `ChatTool` interface**

```php
<?php

namespace App\Services\Chat;

use App\Models\User;

/**
 * One capability the LLM can invoke — either a mentorship-setup slot filler
 * or a read-only analytics query. See
 * docs/superpowers/specs/2026-08-03-mnchgpt-llm-assistant-design.md.
 */
interface ChatTool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON schema for the tool's parameters object (the "parameters" value
     * in an OpenAI-format tool definition).
     */
    public function schema(): array;

    public function authorize(User $user): bool;

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed> data for the model to summarize, or (for
     *   setup tools) a result the caller inspects for validation outcomes
     */
    public function execute(array $args, User $user): array;
}
```

- [ ] **Step 4: Create `SimpleChatTool`**

```php
<?php

namespace App\Services\Chat;

use App\Models\User;
use Closure;

/**
 * Generic closure-based ChatTool implementation — lets each tool-provider
 * class stay a single file with several small tools defined inline, rather
 * than one class per tool.
 */
class SimpleChatTool implements ChatTool
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly array $schema,
        private readonly Closure $authorize,
        private readonly Closure $execute,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function schema(): array
    {
        return $this->schema;
    }

    public function authorize(User $user): bool
    {
        return (bool) ($this->authorize)($user);
    }

    public function execute(array $args, User $user): array
    {
        return ($this->execute)($args, $user);
    }
}
```

- [ ] **Step 5: Create `ChatToolRegistry`**

```php
<?php

namespace App\Services\Chat;

use App\Models\User;

class ChatToolRegistry
{
    /** @var array<string, ChatTool> */
    private array $tools = [];

    public function register(ChatTool $tool): static
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    /**
     * OpenAI-format tool schema list, filtered to what this user is
     * actually authorized to use — an unauthorized tool isn't just hidden
     * from execution, it's never even offered to the model as a capability.
     *
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array}}>
     */
    public function schemasFor(User $user): array
    {
        return collect($this->tools)
            ->filter(fn (ChatTool $tool) => $tool->authorize($user))
            ->map(fn (ChatTool $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->schema(),
                ],
            ])
            ->values()
            ->all();
    }

    public function execute(string $name, array $args, User $user): array
    {
        $tool = $this->tools[$name] ?? null;

        if (! $tool || ! $tool->authorize($user)) {
            throw new \RuntimeException("Tool [{$name}] is not available to this user.");
        }

        return $tool->execute($args, $user);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=ChatToolRegistryTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Services/Chat/ChatTool.php app/Services/Chat/SimpleChatTool.php app/Services/Chat/ChatToolRegistry.php tests/Unit/ChatToolRegistryTest.php
git commit -m "feat: add ChatTool interface and ChatToolRegistry"
```

---

## Task 4: `LlmMentorshipAssistantService` (DeepSeek tool-calling loop)

**Files:**
- Create: `app/Services/Chat/LlmMentorshipAssistantService.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Test: `tests/Unit/LlmMentorshipAssistantServiceTest.php` (create)

**Interfaces:**
- Consumes: `ChatToolRegistry::schemasFor(User $user)` / `::execute(string $name, array $args, User $user)` (from Task 3).
- Produces: `LlmMentorshipAssistantService::respond(string $userMessage, array $history, ChatToolRegistry $registry, User $user, array $context = []): array` returning `['reply' => string, 'tool_calls' => array<int, array{name: string, arguments: array, result: array}>]`. `$history` is `array<int, array{role: string, content: string}>` (prior turns, oldest first). `$context` is arbitrary extra data serialized into the system prompt (Task 7 passes `remainingRequirements()` output here).

- [ ] **Step 1: Add DeepSeek config**

In `config/services.php`, add:

```php
    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],
```

In `.env.example`, add:

```
DEEPSEEK_API_KEY=
DEEPSEEK_BASE_URL=https://api.deepseek.com
DEEPSEEK_MODEL=deepseek-chat
```

- [ ] **Step 2: Write the failing tests**

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Chat\ChatToolRegistry;
use App\Services\Chat\LlmMentorshipAssistantService;
use App\Services\Chat\SimpleChatTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmMentorshipAssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_message_with_no_tool_call_returns_the_models_reply_directly(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Sure, how can I help?']],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $service = new LlmMentorshipAssistantService();

        $result = $service->respond('hello', [], new ChatToolRegistry(), $user);

        $this->assertSame('Sure, how can I help?', $result['reply']);
        $this->assertSame([], $result['tool_calls']);
    }

    public function test_a_tool_call_is_executed_and_results_are_sent_back_for_a_final_reply(): void
    {
        $registry = new ChatToolRegistry();
        $registry->register(new SimpleChatTool(
            name: 'get_number',
            description: 'Returns a number.',
            schema: ['type' => 'object', 'properties' => []],
            authorize: fn (User $u) => true,
            execute: fn (array $args, User $u) => ['value' => 42],
        ));

        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => 'get_number', 'arguments' => '{}'],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['role' => 'assistant', 'content' => 'The number is 42.']],
                    ],
                ]),
        ]);

        $user = User::factory()->create();
        $service = new LlmMentorshipAssistantService();

        $result = $service->respond('what is the number?', [], $registry, $user);

        $this->assertSame('The number is 42.', $result['reply']);
        $this->assertCount(1, $result['tool_calls']);
        $this->assertSame('get_number', $result['tool_calls'][0]['name']);
        $this->assertSame(['value' => 42], $result['tool_calls'][0]['result']);
    }

    public function test_a_network_failure_degrades_gracefully(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'server error'], 500),
        ]);

        $user = User::factory()->create();
        $service = new LlmMentorshipAssistantService();

        $result = $service->respond('hello', [], new ChatToolRegistry(), $user);

        $this->assertSame(
            "Sorry, I couldn't process that — try again or use the buttons below.",
            $result['reply']
        );
        $this->assertSame([], $result['tool_calls']);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --filter=LlmMentorshipAssistantServiceTest`
Expected: FAIL with "Class App\Services\Chat\LlmMentorshipAssistantService not found"

- [ ] **Step 4: Implement the service**

```php
<?php

namespace App\Services\Chat;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wraps DeepSeek-V3's OpenAI-compatible chat completions endpoint and runs
 * the standard two-step tool-calling loop: send message + tool schema,
 * execute any tool calls server-side, send results back for a final
 * natural-language reply. See
 * docs/superpowers/specs/2026-08-03-mnchgpt-llm-assistant-design.md.
 */
class LlmMentorshipAssistantService
{
    private const FALLBACK_REPLY = "Sorry, I couldn't process that — try again or use the buttons below.";

    /**
     * @param array<int, array{role: string, content: string}> $history
     * @return array{reply: string, tool_calls: array<int, array{name: string, arguments: array, result: array}>}
     */
    public function respond(string $userMessage, array $history, ChatToolRegistry $registry, User $user, array $context = []): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($context)]],
            $history,
            [['role' => 'user', 'content' => $userMessage]],
        );

        $tools = $registry->schemasFor($user);

        $first = $this->complete($messages, $tools);

        if ($first === null) {
            return ['reply' => self::FALLBACK_REPLY, 'tool_calls' => []];
        }

        $toolCalls = $first['tool_calls'] ?? [];

        if (empty($toolCalls)) {
            return ['reply' => $first['content'] ?? self::FALLBACK_REPLY, 'tool_calls' => []];
        }

        $executed = [];
        $messages[] = ['role' => 'assistant', 'content' => $first['content'], 'tool_calls' => $toolCalls];

        foreach ($toolCalls as $call) {
            $name = $call['function']['name'];
            $args = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];

            try {
                $result = $registry->execute($name, $args, $user);
            } catch (\Throwable $e) {
                Log::warning('Chat tool execution failed', ['tool' => $name, 'error' => $e->getMessage()]);
                $result = ['error' => 'That could not be completed.'];
            }

            $executed[] = ['name' => $name, 'arguments' => $args, 'result' => $result];

            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $call['id'],
                'content' => json_encode($result),
            ];
        }

        $second = $this->complete($messages, $tools);

        return [
            'reply' => $second['content'] ?? self::FALLBACK_REPLY,
            'tool_calls' => $executed,
        ];
    }

    /**
     * @return array{content: ?string, tool_calls: array}|null null on any
     *   request failure — callers treat that as the fallback path.
     */
    private function complete(array $messages, array $tools): ?array
    {
        try {
            $response = Http::withToken(config('services.deepseek.api_key'))
                ->timeout(20)
                ->post(rtrim(config('services.deepseek.base_url'), '/').'/chat/completions', array_filter([
                    'model' => config('services.deepseek.model'),
                    'messages' => $messages,
                    'tools' => $tools ?: null,
                ]));

            if (! $response->successful()) {
                Log::warning('DeepSeek request failed', ['status' => $response->status()]);

                return null;
            }

            $message = $response->json('choices.0.message');

            if ($message === null) {
                return null;
            }

            return [
                'content' => $message['content'] ?? null,
                'tool_calls' => $message['tool_calls'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('DeepSeek request threw', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function systemPrompt(array $context): string
    {
        $prompt = 'You are MNCHGPT, an assistant that helps set up mentorship '.
            'programs and answers questions about mentorship and assessment data. '.
            'Use the available tools to fill in mentorship details from what the '.
            'user tells you, or to look up data they ask about. Never invent facility, '.
            'program, or county names — only use the exact options a tool schema offers.';

        if (! empty($context['remaining_requirements'])) {
            $outstanding = collect($context['remaining_requirements'])
                ->where('filled', false)
                ->pluck('label')
                ->implode('; ');

            if ($outstanding !== '') {
                $prompt .= " Still outstanding for this mentorship: {$outstanding}. ".
                    'Always mention everything still outstanding in your reply, not just the next single item.';
            }
        }

        return $prompt;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=LlmMentorshipAssistantServiceTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add config/services.php .env.example app/Services/Chat/LlmMentorshipAssistantService.php tests/Unit/LlmMentorshipAssistantServiceTest.php
git commit -m "feat: add LlmMentorshipAssistantService (DeepSeek tool-calling loop)"
```

---

## Task 5: `MentorshipSetupToolProvider`

**Files:**
- Create: `app/Services/Chat/Tools/MentorshipSetupToolProvider.php`
- Test: `tests/Unit/MentorshipSetupToolProviderTest.php` (create)

**Interfaces:**
- Consumes: a page using `HasMentorshipChatSlots` (from Task 1) — needs `->slots()`, `->answers`, `->nextUnfilledSlot()`, `->answer(string, mixed)`.
- Produces: `MentorshipSetupToolProvider::tool($page): ChatTool` — a single batched `fill_mentorship_setup_slots` tool. Its `execute()` calls `$page->answer($slotId, $value)` for each valid proposed slot (reusing the exact same validation/persistence path a click already uses) and returns `['filled' => [...slot ids that succeeded...], 'rejected' => [...slot ids that failed validation...]]`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\User;
use App\Services\Chat\Tools\MentorshipSetupToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipSetupToolProviderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_schema_only_lists_currently_eligible_unfilled_slots(): void
    {
        $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup();
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $properties = array_keys($tool->schema()['properties']);

        $this->assertContains('is_pilot', $properties);
        $this->assertContains('county_id', $properties);
        // class_name belongs to a later stage, not eligible yet.
        $this->assertNotContains('class_name', $properties);
    }

    public function test_execute_fills_valid_slots_and_reports_rejected_ones(): void
    {
        $user = $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup();
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);

        $result = $tool->execute([
            'is_pilot' => 0,
            'max_participants' => 999, // invalid — over the 2-10 cap
        ], $user);

        $this->assertContains('is_pilot', $result['filled']);
        $this->assertContains('max_participants', $result['rejected']);
        $this->assertSame(0, $page->answers['is_pilot']);
        $this->assertArrayNotHasKey('max_participants', $page->answers);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MentorshipSetupToolProviderTest`
Expected: FAIL with "Class App\Services\Chat\Tools\MentorshipSetupToolProvider not found"

- [ ] **Step 3: Implement the provider**

```php
<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\Render;
use App\Services\Chat\SimpleChatTool;

/**
 * Batches every currently-eligible unfilled mentorship-setup Slot into a
 * single tool the LLM can call with as many of them as it could extract
 * from one message. Every proposed value is routed through the page's own
 * answer() — the exact same Slot::validate() a click-driven answer uses —
 * so the LLM never bypasses validation. See
 * docs/superpowers/specs/2026-08-03-mnchgpt-llm-assistant-design.md.
 */
class MentorshipSetupToolProvider
{
    public static function tool($page): ChatTool
    {
        return new SimpleChatTool(
            name: 'fill_mentorship_setup_slots',
            description: 'Fill in one or more mentorship setup fields extracted from the user\'s message.',
            schema: self::schemaFor($page),
            authorize: fn (User $user) => true,
            execute: function (array $args, User $user) use ($page) {
                $filled = [];
                $rejected = [];

                foreach ($args as $slotId => $value) {
                    if (! collect($page->slots())->contains('id', $slotId)) {
                        continue;
                    }

                    if (array_key_exists($slotId, $page->answers)) {
                        continue;
                    }

                    $before = $page->answers;
                    $page->answer($slotId, $value);

                    if (array_key_exists($slotId, $page->answers) && $page->answers !== $before) {
                        $filled[] = $slotId;
                    } else {
                        $rejected[] = $slotId;
                    }
                }

                return ['filled' => $filled, 'rejected' => $rejected];
            },
        );
    }

    private static function schemaFor($page): array
    {
        $properties = [];

        foreach ($page->slots() as $slot) {
            if (array_key_exists($slot->id, $page->answers) || ! $slot->isVisible($page->answers)) {
                continue;
            }

            $properties[$slot->id] = self::propertyFor($slot, $page->answers);
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    private static function propertyFor($slot, array $answers): array
    {
        if ($slot->renderKind() === Render::CARDS) {
            $options = $slot->getOptions($answers);

            return [
                'type' => 'string',
                'description' => $slot->getQuestion($answers),
                'enum' => array_map('strval', array_keys($options)),
            ];
        }

        return [
            'type' => 'string',
            'description' => $slot->getQuestion($answers),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MentorshipSetupToolProviderTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Chat/Tools/MentorshipSetupToolProvider.php tests/Unit/MentorshipSetupToolProviderTest.php
git commit -m "feat: add MentorshipSetupToolProvider"
```

---

## Task 6: `Setting::MNCHGPT_BUTTON_ENABLED` + settings toggle

**Files:**
- Modify: `app/Models/Setting.php`
- Modify: `app/Filament/Pages/MentorshipSettings.php`
- Test: `tests/Feature/MentorshipSettingsTest.php` (existing — add to it)

**Interfaces:**
- Produces: `Setting::MNCHGPT_BUTTON_ENABLED` constant string `'mnchgpt_button_enabled'`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/MentorshipSettingsTest.php`:

```php
    public function test_mnchgpt_toggle_persists_to_the_setting_model(): void
    {
        $this->actingAsAdmin();

        Livewire::test(MentorshipSettings::class)
            ->set('data.mnchgpt_button_enabled', false);

        $this->assertFalse(Setting::getBool(Setting::MNCHGPT_BUTTON_ENABLED));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_mnchgpt_toggle_persists_to_the_setting_model`
Expected: FAIL — `data.mnchgpt_button_enabled` doesn't exist on the form / `Setting::MNCHGPT_BUTTON_ENABLED` undefined constant.

- [ ] **Step 3: Add the constant**

In `app/Models/Setting.php`, add alongside the existing constants:

```php
    public const MNCHGPT_BUTTON_ENABLED = 'mnchgpt_button_enabled';
```

- [ ] **Step 4: Add the toggle to `MentorshipSettings`**

In `mount()`, add to the `fill()` array:

```php
            'mnchgpt_button_enabled' => Setting::getBool(Setting::MNCHGPT_BUTTON_ENABLED),
```

In `form()`'s "Mentorship Creation Methods" schema array, add after the `chat_setup_button_enabled` toggle:

```php
                        Forms\Components\Toggle::make('mnchgpt_button_enabled')
                            ->label('"MNCHGPT" button')
                            ->helperText('The free-text, LLM-powered assistant.')
                            ->onColor('success')
                            ->offColor('danger')
                            ->live()
                            ->afterStateUpdated(function (bool $state): void {
                                Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, $state);
                                Notification::make()
                                    ->title($state ? 'MNCHGPT enabled' : 'MNCHGPT disabled')
                                    ->success()
                                    ->send();
                            }),
```

Change `->columns(3)` to `->columns(4)` on that Section (now four toggles).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=MentorshipSettingsTest`
Expected: All PASS, including the new test.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Setting.php app/Filament/Pages/MentorshipSettings.php tests/Feature/MentorshipSettingsTest.php
git commit -m "feat: add MNCHGPT_BUTTON_ENABLED setting and toggle"
```

---

## Task 7: `MnchGptSetup` page + button on the mentorships list

**Files:**
- Create: `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`
- Create: `resources/views/filament/pages/mnchgpt-setup.blade.php`
- Create: `resources/views/filament/pages/partials/mnchgpt-checklist.blade.php`
- Create: `resources/views/filament/pages/partials/mnchgpt-input.blade.php`
- Modify: `app/Filament/Resources/MentorshipTrainingResource.php` (register the page route)
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php` (add the button)
- Test: `tests/Feature/MnchGptSetupTest.php` (create)

**Interfaces:**
- Consumes: `HasMentorshipChatSlots` (Task 1/2), `ChatToolRegistry`/`ChatTool` (Task 3), `LlmMentorshipAssistantService` (Task 4), `MentorshipSetupToolProvider::tool()` (Task 5), `Setting::MNCHGPT_BUTTON_ENABLED` (Task 6).
- Produces: `MnchGptSetup::sendMessage(string $text): void` — the Livewire action the new free-text box calls.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup;
use App\Models\County;
use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MnchGptSetupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    private function fakeDeepSeekToolCall(string $toolName, array $arguments, string $finalReply): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => $toolName, 'arguments' => json_encode($arguments)],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => $finalReply]]],
                ]),
        ]);
    }

    public function test_page_is_hidden_when_the_setting_is_off(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, false);

        $this->assertFalse(MnchGptSetup::canAccess());
    }

    public function test_valid_extraction_fills_slots_and_advances_the_flow(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);
        $county = County::factory()->create();

        $this->fakeDeepSeekToolCall('fill_mentorship_setup_slots', [
            'is_pilot' => 0,
            'county_id' => (string) $county->id,
        ], 'Got it — live mentorship in that county. What facility?');

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'This is a real mentorship in that county');

        $this->assertSame(0, $component->get('answers')['is_pilot']);
        $this->assertSame($county->id, $component->get('answers')['county_id']);
    }

    public function test_invalid_extraction_is_dropped_and_falls_back_to_the_card_ui(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $this->fakeDeepSeekToolCall('fill_mentorship_setup_slots', [
            'max_participants' => 999,
        ], 'I tried to set that but it needs to be between 2 and 10.');

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'up to 999 mentees please');

        $this->assertArrayNotHasKey('max_participants', $component->get('answers'));
    }

    public function test_a_query_only_message_does_not_touch_answers(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'There are 3 live mentorships.']]],
            ]),
        ]);

        $component = Livewire::test(MnchGptSetup::class);
        $answersBefore = $component->get('answers');

        $component->call('sendMessage', 'how many live mentorships are there?');

        $this->assertSame($answersBefore, $component->get('answers'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MnchGptSetupTest`
Expected: FAIL with "Class App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup not found"

- [ ] **Step 3: Create the page**

```php
<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipResource\Pages\Concerns\HasMentorshipChatSlots;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Setting;
use App\Services\Chat\ChatToolRegistry;
use App\Services\Chat\LlmMentorshipAssistantService;
use App\Services\Chat\Tools\MentorshipSetupToolProvider;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;

class MnchGptSetup extends Page implements HasForms
{
    use InteractsWithForms;
    use HasMentorshipChatSlots;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.mnchgpt-setup';

    protected static bool $shouldRegisterNavigation = false;

    public bool $thinking = false;

    public static function canAccess(array $parameters = []): bool
    {
        if (! parent::canAccess($parameters)) {
            return false;
        }

        if (request()->filled('training')) {
            return true;
        }

        return Setting::getBool(Setting::MNCHGPT_BUTTON_ENABLED);
    }

    public function sendMessage(string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'text' => $text, 'timestamp' => now()->toIso8601String()];
        $this->thinking = true;

        $registry = $this->buildToolRegistry();

        $result = app(LlmMentorshipAssistantService::class)->respond(
            userMessage: $text,
            history: $this->historyForLlm(),
            registry: $registry,
            user: auth()->user(),
            context: ['remaining_requirements' => $this->remainingRequirements()],
        );

        $this->thinking = false;

        $this->messages[] = ['role' => 'bot', 'text' => $result['reply'], 'timestamp' => now()->toIso8601String()];
        $this->syncTranscript();
    }

    protected function buildToolRegistry(): ChatToolRegistry
    {
        $registry = new ChatToolRegistry();
        $registry->register(MentorshipSetupToolProvider::tool($this));

        return $registry;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    protected function historyForLlm(): array
    {
        return collect($this->messages)
            ->map(fn (array $m) => [
                'role' => $m['role'] === 'bot' ? 'assistant' : 'user',
                'content' => $m['text'],
            ])
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Create the view and partials**

`resources/views/filament/pages/mnchgpt-setup.blade.php`:

```blade
<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 space-y-4">
        @include('filament.pages.partials.mnchgpt-checklist', ['requirements' => $this->remainingRequirements()])

        @include('filament.pages.partials.chat-transcript', ['messages' => $messages])

        @unless ($completed)
            @if ($this->activeStage() === 'modules')
                @include('filament.pages.partials.chat-modules-turn')
            @elseif ($this->activeStage() === 'enroll_mentees')
                @include('filament.pages.partials.chat-mentees-turn')
            @else
                @include('filament.pages.partials.mnchgpt-input')

                @if ($this->nextUnfilledSlot())
                    @include('filament.pages.partials.chat-turn', ['slot' => $this->nextUnfilledSlot(), 'answers' => $answers])
                @endif
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

`resources/views/filament/pages/partials/mnchgpt-checklist.blade.php`:

```blade
@php $outstanding = array_filter($requirements, fn ($r) => ! $r['filled']); @endphp
@if(! empty($outstanding))
<div class="rounded-lg bg-gray-50 dark:bg-gray-800/60 p-4 text-sm">
    <p class="font-semibold text-gray-700 dark:text-gray-300 mb-2">Still needed:</p>
    <ul class="list-disc list-inside space-y-1 text-gray-600 dark:text-gray-400">
        @foreach($outstanding as $item)
            <li>{{ $item['label'] }}</li>
        @endforeach
    </ul>
</div>
@endif
```

`resources/views/filament/pages/partials/mnchgpt-input.blade.php`:

```blade
<form wire:submit="sendMessage($refs.messageInput.value); $refs.messageInput.value = ''" class="flex gap-2 pt-2">
    <input
        type="text"
        x-ref="messageInput"
        placeholder="Type anything — e.g. &quot;EmONC mentorship at Kisumu District Hospital, 8 mentees&quot;"
        wire:loading.attr="disabled"
        wire:target="sendMessage"
        class="fi-input flex-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm"
    >
    <button
        type="submit"
        wire:loading.attr="disabled"
        wire:target="sendMessage"
        class="fi-btn fi-btn-color-primary fi-btn-size-md rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"
    >
        <span wire:loading.remove wire:target="sendMessage">Send</span>
        <span wire:loading wire:target="sendMessage">Thinking…</span>
    </button>
</form>
```

- [ ] **Step 5: Register the page route and the list button**

In `app/Filament/Resources/MentorshipTrainingResource.php`, find the `getPages()` array (alongside the existing `'chat-setup' => ...` entry) and add:

```php
            'mnchgpt-setup' => \App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup::route('/mnchgpt-setup'),
```

In `app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php`'s `getHeaderActions()`, add a fourth action alongside the existing three, gated by the same `canAccess()` pattern the others already use for their disabled/tooltip state (mirror whichever existing action — e.g. the chat setup one — most closely, including its `->disabled()`/`->tooltip()` wiring against `Setting::getBool()`).

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=MnchGptSetupTest`
Expected: PASS

- [ ] **Step 7: Run the full regression suite**

Run: `php artisan test`
Expected: Same baseline as before (all green except the two pre-existing unrelated failures).

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php resources/views/filament/pages/mnchgpt-setup.blade.php resources/views/filament/pages/partials/mnchgpt-checklist.blade.php resources/views/filament/pages/partials/mnchgpt-input.blade.php app/Filament/Resources/MentorshipTrainingResource.php app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php tests/Feature/MnchGptSetupTest.php
git commit -m "feat: add MnchGptSetup page — free-text LLM-powered mentorship setup"
```

---

## Task 8: `MentorshipStatsService` + `MentorshipStatsToolProvider` (counts)

**Files:**
- Create: `app/Services/MentorshipStatsService.php`
- Modify: `app/Filament/Widgets/MentorshipStatsOverview.php`
- Create: `app/Services/Chat/Tools/MentorshipStatsToolProvider.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php` (register the new provider)
- Test: `tests/Unit/MentorshipStatsServiceTest.php` (create)
- Test: `tests/Feature/MentorshipStatsOverviewWidgetTest.php` (create — proves the widget still renders identically post-extraction)
- Test: `tests/Unit/MentorshipStatsToolProviderTest.php` (create)

**Interfaces:**
- Produces: `MentorshipStatsService::overallStats(User $user): array{mentorships: int, mentees: int}`, `::programStats(User $user): array<int, array{name: string, mentorships: int, mentees: int}>`, `::countsFor(User $user, ?string $programName = null): array` (the shape the tool returns — overall plus, if `$programName` matches, that program's own figures).

- [ ] **Step 1: Write the failing service test**

```php
<?php

namespace Tests\Unit;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorshipStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorshipStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_for_returns_overall_and_named_program_figures(): void
    {
        $mentor = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $mentor->assignRole('super_admin');

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'is_pilot' => false]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => User::factory()->create()->id]);

        $service = new MentorshipStatsService();
        $result = $service->countsFor($mentor, 'Newborn Care');

        $this->assertSame(1, $result['overall']['mentorships']);
        $this->assertSame(1, $result['overall']['mentees']);
        $this->assertNotNull($result['program']);
        $this->assertSame(1, $result['program']['mentorships']);
        $this->assertSame(1, $result['program']['mentees']);
    }

    public function test_a_facility_mentor_only_sees_their_own_mentorships(): void
    {
        $mentorA = User::factory()->create();
        $mentorB = User::factory()->create();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $mentorA->assignRole('facility_mentor');
        $mentorB->assignRole('facility_mentor');

        Training::factory()->facilityMentorship()->create(['mentor_id' => $mentorA->id, 'is_pilot' => false]);
        Training::factory()->facilityMentorship()->create(['mentor_id' => $mentorB->id, 'is_pilot' => false]);

        $service = new MentorshipStatsService();
        $result = $service->countsFor($mentorA);

        $this->assertSame(1, $result['overall']['mentorships']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MentorshipStatsServiceTest`
Expected: FAIL with "Class App\Services\MentorshipStatsService not found"

- [ ] **Step 3: Implement the service, extracted from the widget**

```php
<?php

namespace App\Services;

use App\Models\ClassParticipant;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single scoped source of truth for mentorship/mentee counts — shared by
 * MentorshipStatsOverview (the dashboard widget) and MentorshipStatsToolProvider
 * (the MNCHGPT query tool) so they can never drift apart on what a given
 * user is allowed to see.
 */
class MentorshipStatsService
{
    /**
     * Live mentorships only — pilot runs are excluded from every count here.
     */
    public function baseTrainingQuery(User $user): Builder
    {
        $query = Training::where('type', 'facility_mentorship')
            ->where('is_pilot', false);

        if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->forMentorOrCoMentor($user->id);
        }

        return $query;
    }

    public function menteesQuery(User $user, ?int $programId = null): Builder
    {
        return ClassParticipant::query()
            ->whereHas('mentorshipClass.training', function (Builder $query) use ($user, $programId) {
                $query->where('type', 'facility_mentorship')->where('is_pilot', false);

                if ($programId) {
                    $query->where('program_id', $programId);
                }

                if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
                    $query->forMentorOrCoMentor($user->id);
                }
            });
    }

    public function overallStats(User $user): array
    {
        $programs = $this->programStats($user);

        return [
            'mentorships' => $this->baseTrainingQuery($user)->count(),
            'mentees' => array_sum(array_column($programs, 'mentees')),
        ];
    }

    public function programStats(User $user): array
    {
        return Program::whereHas('trainings', fn (Builder $q) => $q
            ->where('type', 'facility_mentorship')
            ->where('is_pilot', false))
            ->orderBy('name')
            ->get()
            ->map(fn (Program $program) => [
                'name' => $program->name,
                'mentorships' => $this->baseTrainingQuery($user)->where('program_id', $program->id)->count(),
                'mentees' => $this->menteesQuery($user, $program->id)->distinct('class_participants.user_id')->count('class_participants.user_id'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{overall: array{mentorships: int, mentees: int}, program: ?array{name: string, mentorships: int, mentees: int}}
     */
    public function countsFor(User $user, ?string $programName = null): array
    {
        $programs = $this->programStats($user);

        $program = $programName
            ? collect($programs)->first(fn ($p) => strcasecmp($p['name'], $programName) === 0)
            : null;

        return [
            'overall' => [
                'mentorships' => $this->baseTrainingQuery($user)->count(),
                'mentees' => array_sum(array_column($programs, 'mentees')),
            ],
            'program' => $program,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MentorshipStatsServiceTest`
Expected: PASS

- [ ] **Step 5: Write the widget regression test (baseline before touching the widget)**

```php
<?php

namespace Tests\Feature;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipStatsOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_shows_correct_counts_on_the_mentorships_list_page(): void
    {
        $user = User::factory()->create(['name' => 'Viewer']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_mentorship::training']);
        $this->actingAs($user);

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'is_pilot' => false]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => User::factory()->create()->id]);

        $response = $this->get(\App\Filament\Resources\MentorshipTrainingResource::getUrl());

        $response->assertOk();
        $response->assertSee('Newborn Care');
        $response->assertSee('All Mentorships');
    }
}
```

Run: `php artisan test --filter=MentorshipStatsOverviewWidgetTest`
Expected: PASS against the widget's *current* (pre-refactor) implementation — this is your baseline.

- [ ] **Step 6: Delegate the widget's query logic to the new service**

Replace the contents of `app/Filament/Widgets/MentorshipStatsOverview.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Services\MentorshipStatsService;
use Filament\Widgets\Widget;

class MentorshipStatsOverview extends Widget
{
    protected static string $view = 'filament.widgets.mentorship-stats-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $service = app(MentorshipStatsService::class);
        $user = auth()->user();
        $programs = $service->programStats($user);

        return [
            'overall' => $service->overallStats($user),
            'programs' => $programs,
        ];
    }
}
```

- [ ] **Step 7: Run the widget test again to verify it still passes**

Run: `php artisan test --filter=MentorshipStatsOverviewWidgetTest`
Expected: PASS — identical result, proving the delegation preserved behavior.

- [ ] **Step 8: Write the failing tool provider test**

```php
<?php

namespace Tests\Unit;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use App\Services\Chat\Tools\MentorshipStatsToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorshipStatsToolProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_mentorship_counts_returns_overall_and_program_figures(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'is_pilot' => false, 'mentor_id' => $user->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => User::factory()->create()->id]);

        $tool = MentorshipStatsToolProvider::countsTool();

        $this->assertTrue($tool->authorize($user));

        $result = $tool->execute(['program_name' => 'Newborn Care'], $user);

        $this->assertSame(1, $result['overall']['mentorships']);
        $this->assertNotNull($result['program']);
    }
}
```

- [ ] **Step 9: Run test to verify it fails**

Run: `php artisan test --filter=MentorshipStatsToolProviderTest`
Expected: FAIL with "Class App\Services\Chat\Tools\MentorshipStatsToolProvider not found"

- [ ] **Step 10: Implement the provider**

```php
<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\SimpleChatTool;
use App\Services\MentorshipStatsService;

/**
 * Mentorship/mentee count tools, backed by MentorshipStatsService — the
 * exact same scoped source the dashboard widget uses, so a user can never
 * learn a number here they couldn't already see on the mentorships page.
 */
class MentorshipStatsToolProvider
{
    public static function tools(): array
    {
        return [self::countsTool()];
    }

    public static function countsTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_mentorship_counts',
            description: 'Get the number of live mentorships and mentees, overall or for one named program.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'program_name' => [
                        'type' => 'string',
                        'description' => 'Optional program name to narrow the counts to.',
                    ],
                ],
            ],
            authorize: fn (User $user) => true,
            execute: fn (array $args, User $user) => app(MentorshipStatsService::class)
                ->countsFor($user, $args['program_name'] ?? null),
        );
    }
}
```

- [ ] **Step 11: Run test to verify it passes**

Run: `php artisan test --filter=MentorshipStatsToolProviderTest`
Expected: PASS

- [ ] **Step 12: Register the provider in `MnchGptSetup`**

In `MnchGptSetup::buildToolRegistry()`, add:

```php
        foreach (\App\Services\Chat\Tools\MentorshipStatsToolProvider::tools() as $tool) {
            $registry->register($tool);
        }
```

- [ ] **Step 13: Run the full regression suite**

Run: `php artisan test`
Expected: Same baseline as before.

- [ ] **Step 14: Commit**

```bash
git add app/Services/MentorshipStatsService.php app/Filament/Widgets/MentorshipStatsOverview.php app/Services/Chat/Tools/MentorshipStatsToolProvider.php app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php tests/Unit/MentorshipStatsServiceTest.php tests/Feature/MentorshipStatsOverviewWidgetTest.php tests/Unit/MentorshipStatsToolProviderTest.php
git commit -m "feat: add MentorshipStatsService and get_mentorship_counts tool"
```

---

## Task 9: Mentorship trends

**Files:**
- Modify: `app/Services/MentorshipStatsService.php`
- Modify: `app/Services/Chat/Tools/MentorshipStatsToolProvider.php`
- Test: `tests/Unit/MentorshipStatsServiceTest.php` (existing — add to it)
- Test: `tests/Unit/MentorshipStatsToolProviderTest.php` (existing — add to it)

**Interfaces:**
- Produces: `MentorshipStatsService::trends(User $user, string $period = 'monthly', int $periodsBack = 6): array<int, array{period: string, mentorships: int, mentees: int}>` — `$period` is `'monthly'` or `'quarterly'`, groups by `Training::created_at` (mentorships) and `ClassParticipant::created_at` (mentee enrollments, joined through the same scoped mentorship set).

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/MentorshipStatsServiceTest.php`:

```php
    public function test_trends_groups_mentorships_and_mentees_by_month(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole('super_admin');

        \Illuminate\Support\Carbon::setTestNow('2026-08-15');

        $trainingThisMonth = Training::factory()->facilityMentorship()->create(['is_pilot' => false, 'created_at' => now()]);
        $trainingLastMonth = Training::factory()->facilityMentorship()->create(['is_pilot' => false, 'created_at' => now()->subMonth()]);

        $service = new MentorshipStatsService();
        $trends = $service->trends($user, 'monthly', 2);

        $this->assertCount(2, $trends);
        $this->assertSame(now()->subMonth()->format('Y-m'), $trends[0]['period']);
        $this->assertSame(now()->format('Y-m'), $trends[1]['period']);
        $this->assertSame(1, $trends[0]['mentorships']);
        $this->assertSame(1, $trends[1]['mentorships']);

        \Illuminate\Support\Carbon::setTestNow();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_trends_groups_mentorships_and_mentees_by_month`
Expected: FAIL with "Call to undefined method MentorshipStatsService::trends()"

- [ ] **Step 3: Implement `trends()`**

Add to `MentorshipStatsService`:

```php
    /**
     * @return array<int, array{period: string, mentorships: int, mentees: int}>
     */
    public function trends(User $user, string $period = 'monthly', int $periodsBack = 6): array
    {
        $format = $period === 'quarterly' ? '%Y-Q%q' : '%Y-%m';
        $unit = $period === 'quarterly' ? 'quarter' : 'month';

        $start = now()->sub($unit, $periodsBack - 1)->startOf($unit);

        $mentorshipsByPeriod = $this->baseTrainingQuery($user)
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($t) => $period === 'quarterly'
                ? $t->created_at->format('Y').'-Q'.$t->created_at->quarter
                : $t->created_at->format('Y-m'))
            ->map->count();

        $menteesByPeriod = $this->menteesQuery($user)
            ->where('class_participants.created_at', '>=', $start)
            ->get(['class_participants.created_at'])
            ->groupBy(fn ($p) => $period === 'quarterly'
                ? $p->created_at->format('Y').'-Q'.$p->created_at->quarter
                : $p->created_at->format('Y-m'))
            ->map->count();

        $result = [];
        for ($i = $periodsBack - 1; $i >= 0; $i--) {
            $bucket = now()->sub($unit, $i);
            $key = $period === 'quarterly' ? $bucket->format('Y').'-Q'.$bucket->quarter : $bucket->format('Y-m');

            $result[] = [
                'period' => $key,
                'mentorships' => $mentorshipsByPeriod[$key] ?? 0,
                'mentees' => $menteesByPeriod[$key] ?? 0,
            ];
        }

        return $result;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MentorshipStatsServiceTest`
Expected: PASS

- [ ] **Step 5: Write the failing tool test**

Add to `tests/Unit/MentorshipStatsToolProviderTest.php`:

```php
    public function test_get_mentorship_trends_returns_a_period_series(): void
    {
        $user = User::factory()->create();

        $tool = MentorshipStatsToolProvider::trendsTool();

        $result = $tool->execute(['period' => 'monthly', 'periods_back' => 3], $user);

        $this->assertCount(3, $result['trends']);
        $this->assertArrayHasKey('period', $result['trends'][0]);
        $this->assertArrayHasKey('mentorships', $result['trends'][0]);
        $this->assertArrayHasKey('mentees', $result['trends'][0]);
    }
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=test_get_mentorship_trends_returns_a_period_series`
Expected: FAIL with "Call to undefined method MentorshipStatsToolProvider::trendsTool()"

- [ ] **Step 7: Implement `trendsTool()` and register it**

Add to `MentorshipStatsToolProvider`:

```php
    public static function trendsTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_mentorship_trends',
            description: 'Get mentorship and mentee counts per period over time, to answer growth/trend questions.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'period' => ['type' => 'string', 'enum' => ['monthly', 'quarterly']],
                    'periods_back' => ['type' => 'integer', 'description' => 'How many periods back to include, default 6.'],
                ],
                'required' => ['period'],
            ],
            authorize: fn (User $user) => true,
            execute: fn (array $args, User $user) => [
                'trends' => app(MentorshipStatsService::class)->trends(
                    $user,
                    $args['period'] ?? 'monthly',
                    $args['periods_back'] ?? 6,
                ),
            ],
        );
    }
```

Update `tools()`:

```php
    public static function tools(): array
    {
        return [self::countsTool(), self::trendsTool()];
    }
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=MentorshipStatsToolProviderTest`
Expected: PASS

- [ ] **Step 9: Run the full regression suite**

Run: `php artisan test`
Expected: Same baseline as before.

- [ ] **Step 10: Commit**

```bash
git add app/Services/MentorshipStatsService.php app/Services/Chat/Tools/MentorshipStatsToolProvider.php tests/Unit/MentorshipStatsServiceTest.php tests/Unit/MentorshipStatsToolProviderTest.php
git commit -m "feat: add mentorship trends query and get_mentorship_trends tool"
```

---

## Task 10: `DashboardAnalyticsQueryService` + `DashboardAnalyticsToolProvider`

**Files:**
- Create: `app/Services/DashboardAnalyticsQueryService.php`
- Create: `app/Services/Chat/Tools/DashboardAnalyticsToolProvider.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php` (register the provider)
- Test: `tests/Unit/DashboardAnalyticsQueryServiceTest.php` (create)
- Test: `tests/Unit/DashboardAnalyticsToolProviderTest.php` (create)

**Interfaces:**
- Produces: `DashboardAnalyticsQueryService::countyCoverageSummary(User $user, string $countyName): ?array{county: string, facilities: int, mentorships: int, mentees: int}` (null if the county doesn't exist or the user isn't scoped to it), `::programSummary(User $user, string $programName): ?array{program: string, mentorships: int, mentees: int, by_county: array}`, `::trainingCompletionStats(User $user, ?string $programName = null): array{completed: int, total: int, completion_rate: float}`.

- [ ] **Step 1: Write the failing service tests**

```php
<?php

namespace Tests\Unit;

use App\Models\County;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Subcounty;
use App\Models\Training;
use App\Models\User;
use App\Services\DashboardAnalyticsQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardAnalyticsQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_coverage_summary_returns_scoped_counts(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $county = County::factory()->create(['name' => 'Kisumu']);
        $subcounty = Subcounty::create(['name' => 'Kisumu East', 'county_id' => $county->id]);
        $facility = Facility::factory()->create(['subcounty_id' => $subcounty->id]);
        Training::factory()->facilityMentorship()->create(['facility_id' => $facility->id, 'is_pilot' => false]);

        $service = new DashboardAnalyticsQueryService();
        $result = $service->countyCoverageSummary($admin, 'Kisumu');

        $this->assertSame('Kisumu', $result['county']);
        $this->assertSame(1, $result['facilities']);
        $this->assertSame(1, $result['mentorships']);
    }

    public function test_county_coverage_summary_returns_null_for_a_county_outside_the_users_scope(): void
    {
        $countyLead = User::factory()->create();
        Role::firstOrCreate(['name' => 'county_mentor_lead', 'guard_name' => 'web']);
        $countyLead->assignRole('county_mentor_lead');

        $ownCounty = County::factory()->create(['name' => 'Kisumu']);
        $otherCounty = County::factory()->create(['name' => 'Nairobi']);
        $countyLead->counties()->attach($ownCounty->id);

        $service = new DashboardAnalyticsQueryService();
        $result = $service->countyCoverageSummary($countyLead, 'Nairobi');

        $this->assertNull($result);
    }

    public function test_program_summary_returns_totals_and_county_breakdown(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $county = County::factory()->create(['name' => 'Kisumu']);
        $subcounty = Subcounty::create(['name' => 'Kisumu East', 'county_id' => $county->id]);
        $facility = Facility::factory()->create(['subcounty_id' => $subcounty->id]);
        Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'facility_id' => $facility->id, 'is_pilot' => false]);

        $service = new DashboardAnalyticsQueryService();
        $result = $service->programSummary($admin, 'Newborn Care');

        $this->assertSame(1, $result['mentorships']);
        $this->assertNotEmpty($result['by_county']);
    }

    public function test_training_completion_stats_returns_a_completion_rate(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        Training::factory()->facilityMentorship()->create(['is_pilot' => false, 'status' => 'completed']);
        Training::factory()->facilityMentorship()->create(['is_pilot' => false, 'status' => 'active']);

        $service = new DashboardAnalyticsQueryService();
        $result = $service->trainingCompletionStats($admin);

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(50.0, $result['completion_rate']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DashboardAnalyticsQueryServiceTest`
Expected: FAIL with "Class App\Services\DashboardAnalyticsQueryService not found"

- [ ] **Step 3: Implement the service**

```php
<?php

namespace App\Services;

use App\Models\County;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Small, focused read-only queries for MNCHGPT's dashboard-analytics tools —
 * applies the same isAboveSite()/scopedCountyIds()/scopedFacilityIds() rules
 * (documented in CLAUDE.md) directly, rather than reusing
 * AnalyticsDashboardController's methods, which are large and
 * HTTP-response-oriented (built for the map UI, not a clean data return).
 */
class DashboardAnalyticsQueryService
{
    public function countyCoverageSummary(User $user, string $countyName): ?array
    {
        $county = County::whereRaw('LOWER(name) = ?', [strtolower($countyName)])->first();

        if (! $county || ! in_array($county->id, $user->scopedCountyIds()->all(), true)) {
            return null;
        }

        $facilityIds = Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $county->id))->pluck('id');

        return [
            'county' => $county->name,
            'facilities' => $facilityIds->count(),
            'mentorships' => Training::where('type', 'facility_mentorship')
                ->where('is_pilot', false)
                ->whereIn('facility_id', $facilityIds)
                ->count(),
            'mentees' => \App\Models\ClassParticipant::whereHas('mentorshipClass.training', fn (Builder $q) => $q
                ->where('type', 'facility_mentorship')
                ->where('is_pilot', false)
                ->whereIn('facility_id', $facilityIds))
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    public function programSummary(User $user, string $programName): ?array
    {
        $program = Program::whereRaw('LOWER(name) = ?', [strtolower($programName)])->first();

        if (! $program) {
            return null;
        }

        $facilityIds = $user->scopedFacilityIds();

        $trainings = Training::where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->where('program_id', $program->id)
            ->whereIn('facility_id', $facilityIds)
            ->with('facility.subcounty.county')
            ->get();

        $byCounty = $trainings->groupBy(fn ($t) => $t->facility?->subcounty?->county?->name ?? 'Unknown')
            ->map->count();

        return [
            'program' => $program->name,
            'mentorships' => $trainings->count(),
            'by_county' => $byCounty->toArray(),
        ];
    }

    public function trainingCompletionStats(User $user, ?string $programName = null): array
    {
        $query = Training::where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->whereIn('facility_id', $user->scopedFacilityIds());

        if ($programName) {
            $query->whereHas('program', fn ($q) => $q->whereRaw('LOWER(name) = ?', [strtolower($programName)]));
        }

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=DashboardAnalyticsQueryServiceTest`
Expected: PASS

- [ ] **Step 5: Write the failing tool provider test**

```php
<?php

namespace Tests\Unit;

use App\Models\County;
use App\Models\Facility;
use App\Models\Subcounty;
use App\Models\Training;
use App\Models\User;
use App\Services\Chat\Tools\DashboardAnalyticsToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardAnalyticsToolProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_county_coverage_summary_tool_returns_data_for_an_authorized_user(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $county = County::factory()->create(['name' => 'Kisumu']);
        $subcounty = Subcounty::create(['name' => 'Kisumu East', 'county_id' => $county->id]);
        $facility = Facility::factory()->create(['subcounty_id' => $subcounty->id]);
        Training::factory()->facilityMentorship()->create(['facility_id' => $facility->id, 'is_pilot' => false]);

        $tool = DashboardAnalyticsToolProvider::countyCoverageTool();

        $result = $tool->execute(['county_name' => 'Kisumu'], $admin);

        $this->assertSame('Kisumu', $result['county']);
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=DashboardAnalyticsToolProviderTest`
Expected: FAIL with "Class App\Services\Chat\Tools\DashboardAnalyticsToolProvider not found"

- [ ] **Step 7: Implement the provider**

```php
<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\SimpleChatTool;
use App\Services\DashboardAnalyticsQueryService;

class DashboardAnalyticsToolProvider
{
    public static function tools(): array
    {
        return [self::countyCoverageTool(), self::programSummaryTool(), self::trainingCompletionTool()];
    }

    public static function countyCoverageTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_county_coverage_summary',
            description: 'Get facility, mentorship, and mentee counts for a named county.',
            schema: [
                'type' => 'object',
                'properties' => ['county_name' => ['type' => 'string']],
                'required' => ['county_name'],
            ],
            authorize: fn (User $user) => true,
            execute: function (array $args, User $user) {
                $result = app(DashboardAnalyticsQueryService::class)->countyCoverageSummary($user, $args['county_name']);

                return $result ?? ['error' => 'That county was not found or is not accessible to you.'];
            },
        );
    }

    public static function programSummaryTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_program_summary',
            description: 'Get mentorship totals and a per-county breakdown for a named program.',
            schema: [
                'type' => 'object',
                'properties' => ['program_name' => ['type' => 'string']],
                'required' => ['program_name'],
            ],
            authorize: fn (User $user) => true,
            execute: function (array $args, User $user) {
                $result = app(DashboardAnalyticsQueryService::class)->programSummary($user, $args['program_name']);

                return $result ?? ['error' => 'That program was not found.'];
            },
        );
    }

    public static function trainingCompletionTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_training_completion_stats',
            description: 'Get training completion rate and participant counts, optionally for one named program.',
            schema: [
                'type' => 'object',
                'properties' => ['program_name' => ['type' => 'string']],
            ],
            authorize: fn (User $user) => true,
            execute: fn (array $args, User $user) => app(DashboardAnalyticsQueryService::class)
                ->trainingCompletionStats($user, $args['program_name'] ?? null),
        );
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=DashboardAnalyticsToolProviderTest`
Expected: PASS

- [ ] **Step 9: Register the provider in `MnchGptSetup`**

In `MnchGptSetup::buildToolRegistry()`, add:

```php
        foreach (\App\Services\Chat\Tools\DashboardAnalyticsToolProvider::tools() as $tool) {
            $registry->register($tool);
        }
```

- [ ] **Step 10: Run the full regression suite**

Run: `php artisan test`
Expected: Same baseline as before.

- [ ] **Step 11: Commit**

```bash
git add app/Services/DashboardAnalyticsQueryService.php app/Services/Chat/Tools/DashboardAnalyticsToolProvider.php app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php tests/Unit/DashboardAnalyticsQueryServiceTest.php tests/Unit/DashboardAnalyticsToolProviderTest.php
git commit -m "feat: add DashboardAnalyticsQueryService and its query tools"
```

---

## Task 11: `AssessmentSummaryQueryService` + `AssessmentSummaryToolProvider`

**Files:**
- Create: `app/Services/AssessmentSummaryQueryService.php`
- Create: `app/Services/Chat/Tools/AssessmentSummaryToolProvider.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php` (register the provider)
- Test: `tests/Unit/AssessmentSummaryQueryServiceTest.php` (create)
- Test: `tests/Unit/AssessmentSummaryToolProviderTest.php` (create)

**Interfaces:**
- Produces: `AssessmentSummaryQueryService::statusCounts(User $user, ?string $status = null): array<string, int>`, `::readinessScores(User $user, ?string $facilityName = null, ?float $belowPercentage = null): array<int, array{facility: string, overall_percentage: float, overall_grade: string}>`, `::facilityExecutiveSummary(User $user, string $facilityName): ?array{facility: string, insights: array}`.

- [ ] **Step 1: Write the failing service tests**

```php
<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\AssessmentSummaryQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentSummaryQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessment(User $assessor, Facility $facility, string $status = 'completed', ?float $percentage = 80.0): Assessment
    {
        $this->actingAs($assessor);
        $type = AssessmentType::firstOrCreate(
            ['code' => 'STANDARD_FACILITY_ASSESSMENT'],
            ['name' => 'Standard Facility Assessment', 'version' => '1.0', 'is_active' => true]
        );

        return Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'status' => $status,
            'overall_percentage' => $percentage,
            'overall_grade' => $percentage >= 70 ? 'green' : 'red',
        ]);
    }

    public function test_status_counts_are_scoped_to_the_assessors_own_assessments(): void
    {
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        $assessorA = User::factory()->create(['name' => 'A']);
        $assessorA->assignRole('assessor');
        $assessorB = User::factory()->create(['name' => 'B']);
        $assessorB->assignRole('assessor');

        $facility = Facility::factory()->create();
        $this->makeAssessment($assessorA, $facility, 'completed');
        $this->makeAssessment($assessorB, $facility, 'completed');

        $service = new AssessmentSummaryQueryService();
        $counts = $service->statusCounts($assessorA);

        $this->assertSame(1, $counts['completed'] ?? 0);
    }

    public function test_readiness_scores_filters_below_a_threshold(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $weakFacility = Facility::factory()->create(['name' => 'Weak Facility']);
        $strongFacility = Facility::factory()->create(['name' => 'Strong Facility']);
        $this->makeAssessment($admin, $weakFacility, 'completed', 40.0);
        $this->makeAssessment($admin, $strongFacility, 'completed', 90.0);

        $this->actingAs($admin);
        $service = new AssessmentSummaryQueryService();
        $scores = $service->readinessScores($admin, belowPercentage: 50.0);

        $this->assertCount(1, $scores);
        $this->assertSame('Weak Facility', $scores[0]['facility']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AssessmentSummaryQueryServiceTest`
Expected: FAIL with "Class App\Services\AssessmentSummaryQueryService not found"

- [ ] **Step 3: Implement the service**

```php
<?php

namespace App\Services;

use App\Http\Controllers\AssessmentExecutiveDashboardController;
use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;

/**
 * Read-only assessment queries for MNCHGPT — reuses
 * AssessmentResource::getEloquentQuery()'s exact scoping rule (assessor
 * sees only their own assessments; super_admin/admin/division see all).
 */
class AssessmentSummaryQueryService
{
    private function scopedQuery(User $user)
    {
        $query = Assessment::query();

        if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->where('assessor_id', $user->id);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(User $user, ?string $status = null): array
    {
        $query = $this->scopedQuery($user);

        if ($status) {
            return ['count' => (clone $query)->where('status', $status)->count()];
        }

        return [
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
        ];
    }

    /**
     * @return array<int, array{facility: string, overall_percentage: float, overall_grade: string}>
     */
    public function readinessScores(User $user, ?string $facilityName = null, ?float $belowPercentage = null): array
    {
        $query = $this->scopedQuery($user)
            ->where('status', 'completed')
            ->with('facility');

        if ($facilityName) {
            $query->whereHas('facility', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($facilityName).'%']));
        }

        if ($belowPercentage !== null) {
            $query->where('overall_percentage', '<', $belowPercentage);
        }

        return $query->get()
            ->map(fn (Assessment $a) => [
                'facility' => $a->facility?->name ?? 'Unknown',
                'overall_percentage' => (float) $a->overall_percentage,
                'overall_grade' => (string) $a->overall_grade,
            ])
            ->values()
            ->all();
    }

    public function facilityExecutiveSummary(User $user, string $facilityName): ?array
    {
        $facility = Facility::whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($facilityName).'%'])->first();

        if (! $facility) {
            return null;
        }

        $assessment = $this->scopedQuery($user)
            ->where('facility_id', $facility->id)
            ->where('status', 'completed')
            ->latest('assessment_date')
            ->first();

        if (! $assessment) {
            return null;
        }

        $controller = app(AssessmentExecutiveDashboardController::class);
        $reflection = new \ReflectionMethod($controller, 'buildDashboardData');
        $reflection->setAccessible(true);
        $data = $reflection->invoke($controller, $assessment);

        return [
            'facility' => $facility->name,
            'insights' => array_map(fn ($i) => $i['text'], $data['insights']),
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=AssessmentSummaryQueryServiceTest`
Expected: PASS

- [ ] **Step 5: Write the failing tool provider test**

```php
<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\Chat\Tools\AssessmentSummaryToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentSummaryToolProviderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdminWithAssessmentAccess(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $admin->givePermissionTo('view_any_assessment');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_get_assessment_status_counts_tool_returns_scoped_counts(): void
    {
        $admin = $this->actingAsAdminWithAssessmentAccess();

        $type = AssessmentType::firstOrCreate(
            ['code' => 'STANDARD_FACILITY_ASSESSMENT'],
            ['name' => 'Standard Facility Assessment', 'version' => '1.0', 'is_active' => true]
        );
        Assessment::create([
            'facility_id' => Facility::factory()->create()->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'status' => 'completed',
        ]);

        $tool = AssessmentSummaryToolProvider::statusCountsTool();

        $this->assertTrue($tool->authorize($admin));

        $result = $tool->execute([], $admin);

        $this->assertSame(1, $result['completed']);
    }

    public function test_tools_are_not_authorized_for_a_user_without_assessment_access(): void
    {
        $plainUser = User::factory()->create();

        $this->assertFalse(AssessmentSummaryToolProvider::statusCountsTool()->authorize($plainUser));
        $this->assertFalse(AssessmentSummaryToolProvider::readinessScoresTool()->authorize($plainUser));
        $this->assertFalse(AssessmentSummaryToolProvider::executiveSummaryTool()->authorize($plainUser));
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=AssessmentSummaryToolProviderTest`
Expected: FAIL with "Class App\Services\Chat\Tools\AssessmentSummaryToolProvider not found"

- [ ] **Step 7: Implement the provider**

```php
<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\AssessmentSummaryQueryService;
use App\Services\Chat\ChatTool;
use App\Services\Chat\SimpleChatTool;

class AssessmentSummaryToolProvider
{
    public static function tools(): array
    {
        return [self::statusCountsTool(), self::readinessScoresTool(), self::executiveSummaryTool()];
    }

    public static function statusCountsTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_assessment_status_counts',
            description: 'Get facility assessment counts by status (draft, in_progress, completed), or a specific status count.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['draft', 'in_progress', 'completed']],
                ],
            ],
            // Mirrors AssessmentResource::canAccess() — a user without this
            // permission can't see the Assessments resource at all, so this
            // tool must not even be offered to the model for them.
            authorize: fn (User $user) => $user->can('view_any_assessment'),
            execute: fn (array $args, User $user) => app(AssessmentSummaryQueryService::class)
                ->statusCounts($user, $args['status'] ?? null),
        );
    }

    public static function readinessScoresTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_facility_readiness_scores',
            description: 'Get facility readiness (assessment) scores, optionally filtered to one facility or a percentage threshold.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'facility_name' => ['type' => 'string'],
                    'below_percentage' => ['type' => 'number'],
                ],
            ],
            authorize: fn (User $user) => $user->can('view_any_assessment'),
            execute: fn (array $args, User $user) => [
                'scores' => app(AssessmentSummaryQueryService::class)->readinessScores(
                    $user,
                    $args['facility_name'] ?? null,
                    $args['below_percentage'] ?? null,
                ),
            ],
        );
    }

    public static function executiveSummaryTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_facility_executive_summary',
            description: 'Get the executive summary insights for a named facility\'s latest completed assessment.',
            schema: [
                'type' => 'object',
                'properties' => ['facility_name' => ['type' => 'string']],
                'required' => ['facility_name'],
            ],
            authorize: fn (User $user) => $user->can('view_any_assessment'),
            execute: function (array $args, User $user) {
                $result = app(AssessmentSummaryQueryService::class)
                    ->facilityExecutiveSummary($user, $args['facility_name']);

                return $result ?? ['error' => 'No completed assessment found for that facility.'];
            },
        );
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentSummaryToolProviderTest`
Expected: PASS

- [ ] **Step 9: Register the provider in `MnchGptSetup`**

In `MnchGptSetup::buildToolRegistry()`, add:

```php
        foreach (\App\Services\Chat\Tools\AssessmentSummaryToolProvider::tools() as $tool) {
            $registry->register($tool);
        }
```

- [ ] **Step 10: Run the full regression suite**

Run: `php artisan test`
Expected: Same baseline as before.

- [ ] **Step 11: Commit**

```bash
git add app/Services/AssessmentSummaryQueryService.php app/Services/Chat/Tools/AssessmentSummaryToolProvider.php app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php tests/Unit/AssessmentSummaryQueryServiceTest.php tests/Unit/AssessmentSummaryToolProviderTest.php
git commit -m "feat: add AssessmentSummaryQueryService and its query tools"
```

---

## Task 12: End-to-end multi-tool-family conversation test + final regression

**Files:**
- Test: `tests/Feature/MnchGptEndToEndTest.php` (create)

**Interfaces:**
- Consumes: everything from Tasks 1–11.

- [ ] **Step 1: Write the end-to-end test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup;
use App\Models\ClassParticipant;
use App\Models\County;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MnchGptEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_setup_turn_and_a_query_turn_both_work_in_the_same_session(): void
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);
        $county = County::factory()->create();
        $program = Program::factory()->create();
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'is_pilot' => false]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => User::factory()->create()->id]);

        // Turn 1: setup extraction.
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode(['is_pilot' => 0, 'county_id' => (string) $county->id])],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'Got it.']]],
                ]),
        ]);

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'live mentorship in that county');

        $this->assertSame(0, $component->get('answers')['is_pilot']);

        // Turn 2: an analytics query — must not disturb the setup answers.
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => ['name' => 'get_mentorship_counts', 'arguments' => '{}'],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'There is 1 live mentorship with 1 mentee.']]],
                ]),
        ]);

        $answersBefore = $component->get('answers');
        $component->call('sendMessage', 'how many live mentorships are there?');

        $this->assertSame($answersBefore, $component->get('answers'));
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertSame('There is 1 live mentorship with 1 mentee.', $lastMessage['text']);
    }
}
```

- [ ] **Step 2: Run the test**

Run: `php artisan test --filter=MnchGptEndToEndTest`
Expected: PASS

- [ ] **Step 3: Run Pint on every file touched across this whole plan**

Run: `./vendor/bin/pint app/Services/Chat app/Services/MentorshipStatsService.php app/Services/DashboardAnalyticsQueryService.php app/Services/AssessmentSummaryQueryService.php app/Filament/Resources/MentorshipResource/Pages app/Filament/Widgets/MentorshipStatsOverview.php app/Filament/Pages/MentorshipSettings.php app/Models/Setting.php config/services.php tests/Unit tests/Feature/MnchGptSetupTest.php tests/Feature/MnchGptEndToEndTest.php tests/Feature/MentorshipStatsOverviewWidgetTest.php tests/Feature/MentorshipSettingsTest.php`
Expected: All files pass or get auto-fixed.

- [ ] **Step 4: Run the full regression suite one final time**

Run: `php artisan test`
Expected: All green except the two pre-existing, unrelated baseline failures (`ExampleTest`, `LookupApiTest`).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/MnchGptEndToEndTest.php
git commit -m "test: add MNCHGPT end-to-end multi-tool-family conversation test"
```

---

## Final Step: Finish the development branch

- [ ] Use **superpowers:finishing-a-development-branch** to verify tests, present merge/PR/keep options, and clean up.
