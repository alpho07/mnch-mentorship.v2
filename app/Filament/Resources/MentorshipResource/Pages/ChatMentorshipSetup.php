<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\MentorshipClass;
use App\Models\Setting;
use App\Models\Training;
use App\Services\Chat\MentorshipChatScript;
use App\Services\Chat\Slot;
use App\Services\MentorshipWizardService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Url;

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

    public ?MentorshipClass $class = null;

    public bool $completed = false;

    public bool $classStarted = false;

    public int $invitedCount = 0;

    public array $moduleDates = [];

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

        $next = $this->nextUnfilledSlot();

        if ($next) {
            $this->messages[] = [
                'role' => 'bot',
                'text' => $next->getQuestion($this->answers),
                'timestamp' => now()->toIso8601String(),
            ];
        }

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

        $this->syncTranscript();
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
