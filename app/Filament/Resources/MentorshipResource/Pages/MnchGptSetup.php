<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipResource\Pages\Concerns\HasMentorshipChatSlots;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Setting;
use App\Services\Chat\ChatToolRegistry;
use App\Services\Chat\LlmMentorshipAssistantService;
use App\Services\Chat\Tools\MentorshipMenteesToolProvider;
use App\Services\Chat\Tools\MentorshipModulesToolProvider;
use App\Services\Chat\Tools\MentorshipSetupToolProvider;
use App\Services\MentorshipWizardService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;

class MnchGptSetup extends Page implements HasForms
{
    use HasMentorshipChatSlots {
        answer as protected traitAnswer;
        submitModules as protected traitSubmitModules;
    }
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.mnchgpt-setup';

    protected static bool $shouldRegisterNavigation = false;

    private const MAX_PROACTIVE_OPTIONS = 10;

    /**
     * Unlike modules (curated curriculum content, always shown in full),
     * the mentee table is unbounded — proactively listing "everyone" isn't
     * meaningful. This caps the initial browsable sample shown the moment
     * the enroll_mentees stage begins to the same size as any other
     * proactive list; a real search still returns its own (uncapped)
     * candidate shortlist via MentorshipMenteesToolProvider.
     */
    private const MAX_PROACTIVE_MENTEE_SAMPLE = 10;

    /**
     * module_ids/selected_users aren't generic Slot objects (see
     * HasMentorshipChatSlots::answer()'s comment on this), so they can't be
     * resolved through the bare-number/letter fast path below — that path
     * calls $this->answer($slot, ...), which silently no-ops for any slot
     * id that isn't declared in slots(). A numbered list is still shown for
     * these stages (see determineNextStep()); replies just always go
     * through the normal LLM flow instead of the instant-resolve shortcut.
     */
    private const COMPOSITE_STAGE_SLOTS = ['module_ids', 'selected_users'];

    public ?array $pendingOptions = null;

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

    /**
     * Overrides HasMentorshipChatSlots::mount() for this page only —
     * ChatMentorshipSetup keeps using the trait's version untouched. Opens
     * with an open question instead of jumping straight into is_pilot, so
     * the user can ask an unrelated analytics question first if that's
     * what they actually want.
     */
    public function mount(): void
    {
        if ($this->trainingId) {
            $this->training = \App\Models\Training::find($this->trainingId);
        }

        if ($this->classId) {
            $this->class = \App\Models\MentorshipClass::find($this->classId);
        }

        if ($this->training && ! empty($this->training->chat_setup_transcript)) {
            $this->messages = $this->training->chat_setup_transcript;
            $this->answers = $this->rebuildAnswersFromTraining();

            return;
        }

        $firstName = explode(' ', auth()->user()->name)[0];

        $this->messages[] = [
            'role' => 'bot',
            'text' => "Hello {$firstName}, welcome back! I'm MNCHGPT. How can I help today — would you like to ".
                'start creating a mentorship, or is there something else I can help with?',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Wraps HasMentorshipChatSlots::answer() (aliased as traitAnswer() above)
     * to add two things the trait doesn't:
     *
     * 1. class_description is the one first_class slot marked optional
     *    (isRequired() === false) — remainingRequirements() correctly
     *    leaves it off the checklist the model sees, but maybeCompleteStage()
     *    still won't create the class until *every* slot in the stage has
     *    an answer, optional or not (same rule the click-driven
     *    ChatMentorshipSetup relies on to show its own turn for it). With
     *    nothing telling the model this slot still needs a value, it was
     *    observed moving on to talk about later steps (recipients, modules)
     *    without ever filling it — leaving the class permanently stuck one
     *    slot short of created, and the modules stage never reached. Once
     *    every other first_class slot is answered, this auto-supplies
     *    "skip" for it — identical to what clicking "Skip" would send.
     * 2. An acknowledgment message the moment the modules stage begins. The
     *    trait silently falls straight into chat-modules-turn.blade.php's
     *    picker with no transition text — fine for the older, fully
     *    card-driven ChatMentorshipSetup page (left untouched, still calling
     *    the trait's version directly), but jarring once everything else
     *    here is conversational.
     */
    public function answer(string $slotId, mixed $value): void
    {
        $wasModulesStage = $this->activeStage() === 'modules';

        $this->traitAnswer($slotId, $value);
        $this->autoSkipOptionalClassDescriptionIfReady();

        if (! $wasModulesStage && $this->activeStage() === 'modules') {
            $text = "Great, the class details are set! Now let's pick training modules — ".
                'tell me the names or numbers, or say "skip" to add them later.';

            $step = $this->determineNextStep();
            $this->pendingOptions = $step;

            if ($step) {
                $text .= "\n\n".$this->renderOptionList($step['options']);
            }

            $this->messages[] = ['role' => 'bot', 'text' => $text, 'timestamp' => now()->toIso8601String()];
            $this->syncTranscript();
        }
    }

    /**
     * Wraps HasMentorshipChatSlots::submitModules() (aliased as
     * traitSubmitModules() above) purely to append the initial mentee
     * sample list the moment enroll_mentees begins — the trait's own
     * transition message ("Who will be mentored in this class?...") has no
     * real data attached, mirroring exactly why answer() above appends the
     * module list once the modules stage begins. Reached via normal
     * polymorphism whenever anything calls $page->submitModules() —
     * MentorshipModulesToolProvider's tool included — so no other call site
     * needs to change.
     */
    public function submitModules(array $moduleIds): void
    {
        $wasEnrollMenteesStage = $this->activeStage() === 'enroll_mentees';

        $this->traitSubmitModules($moduleIds);

        if (! $wasEnrollMenteesStage && $this->activeStage() === 'enroll_mentees') {
            $step = $this->determineNextStep();
            $this->pendingOptions = $step;

            if ($step) {
                $lastIndex = array_key_last($this->messages);

                if ($this->messages[$lastIndex]['role'] === 'bot') {
                    $this->messages[$lastIndex]['text'] .= "\n\n".$this->renderOptionList($step['options']);
                    $this->syncTranscript();
                }
            }
        }
    }

    private function autoSkipOptionalClassDescriptionIfReady(): void
    {
        if ($this->class || array_key_exists('class_description', $this->answers)) {
            return;
        }

        $otherFirstClassSlots = array_filter(
            $this->slots(),
            fn ($slot) => $slot->stage === 'first_class' && $slot->id !== 'class_description'
        );

        $allOthersFilled = collect($otherFirstClassSlots)->every(
            fn ($slot) => ! $slot->isVisible($this->answers) || array_key_exists($slot->id, $this->answers)
        );

        if ($allOthersFilled) {
            $this->traitAnswer('class_description', 'skip');
        }
    }

    public function sendMessage(string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        if ($this->pendingOptions && ! in_array($this->pendingOptions['slot'], self::COMPOSITE_STAGE_SLOTS, true)) {
            $index = $this->matchPendingOptionIndex($text);

            if ($index !== null) {
                $option = $this->pendingOptions['options'][$index];

                // answer() already appends both the user's echoed choice
                // (via the slot's getEcho(), e.g. "Live Mentorship" rather
                // than a bare "1") and the plain next-slot question as its
                // own bot message — reusing it here avoids re-implementing
                // that echo/validation/stage-completion logic. This method
                // only adds the numbered list on top, by appending to
                // whichever bot message answer() just pushed.
                $this->answer($this->pendingOptions['slot'], $option['id']);

                $step = $this->determineNextStep([]);
                $this->pendingOptions = $step;

                if ($step) {
                    $lastIndex = array_key_last($this->messages);

                    if ($this->messages[$lastIndex]['role'] === 'bot') {
                        $this->messages[$lastIndex]['text'] .= "\n\n".$this->renderOptionList($step['options']);
                    }
                }

                $this->syncTranscript();
                $this->dispatch('mnchgpt-reply');

                return;
            }
        }

        $this->messages[] = ['role' => 'user', 'text' => $text, 'timestamp' => now()->toIso8601String()];

        $step = $this->determineNextStep([]);

        $result = app(LlmMentorshipAssistantService::class)->respond(
            userMessage: $text,
            history: $this->historyForLlm(),
            registryFactory: fn () => $this->buildToolRegistry(),
            user: auth()->user(),
            context: [
                'remaining_requirements' => $this->remainingRequirements(),
                'next_options' => $step,
            ],
        );

        // Collected across *every* round of this exchange (result.tool_calls
        // is the full accumulated log from LlmMentorshipAssistantService::
        // respond()'s tool loop), not just the last one — if an earlier
        // round left a slot ambiguous but a later round in the same
        // exchange then resolved it (e.g. the model retried facility_id
        // with a more specific name), the first round's now-stale
        // candidates would otherwise still surface here even though that
        // slot is already answered. Observed live: the bot's own reply text
        // correctly moved on to ask about program_id, but the attached
        // option list was still the earlier, already-resolved facility
        // shortlist. Rejecting anything already in $this->answers keeps
        // only candidates for slots still genuinely unresolved.
        $candidatesFromThisTurn = collect($result['tool_calls'])
            ->pluck('result.candidates')
            ->filter()
            ->collapse()
            ->reject(fn ($candidates, $slotId) => array_key_exists($slotId, $this->answers))
            ->all();

        // A turn spent entirely on a query tool (get_mentorship_counts and
        // friends) isn't the user engaging with setup — no tool call at
        // all (plain chat) is treated as setup-related, since that's
        // exactly what happens the first time someone says "let's set up
        // a mentorship" before any slot has been filled yet.
        $toolNames = collect($result['tool_calls'])->pluck('name');
        $wasSetupRelated = $toolNames->isEmpty()
            || $toolNames->contains(fn ($name) => in_array($name, ['fill_mentorship_setup_slots', 'fill_mentorship_modules'], true));

        $step = $wasSetupRelated ? $this->determineNextStep($candidatesFromThisTurn) : null;
        $this->pendingOptions = $step;

        $reply = $result['reply'];

        if ($step) {
            $reply .= "\n\n".$this->renderOptionList($step['options']);
        }

        $this->messages[] = ['role' => 'bot', 'text' => $reply, 'timestamp' => now()->toIso8601String()];
        $this->syncTranscript();
        $this->dispatch('mnchgpt-reply');
    }

    /**
     * @param  array<int, array{id: mixed, label: string}>  $numberedOptions
     */
    private function renderOptionList(array $numberedOptions): string
    {
        return collect($numberedOptions)
            ->map(fn (array $option, int $number) => "{$number}. {$option['label']}")
            ->implode("\n");
    }

    protected function buildToolRegistry(): ChatToolRegistry
    {
        $registry = new ChatToolRegistry;
        $registry->register(MentorshipSetupToolProvider::tool($this));

        // module_ids isn't a generic Slot, so it needs its own tool — only
        // offered once the modules stage is actually reached, and only for
        // standard programs (EmONC needs per-track dates the chat can't
        // collect yet — see chat-emonc-modules-turn.blade.php).
        if ($this->activeStage() === 'modules' && ! $this->isModulesStageEmonc()) {
            $registry->register(MentorshipModulesToolProvider::tool($this));
        }

        // selected_users isn't a generic Slot either — only offered once
        // the enroll_mentees stage is actually reached (see
        // MentorshipMenteesToolProvider's own guard against being called
        // outside that stage).
        if ($this->activeStage() === 'enroll_mentees') {
            $registry->register(MentorshipMenteesToolProvider::tool($this));
        }

        foreach (\App\Services\Chat\Tools\MentorshipStatsToolProvider::tools() as $tool) {
            $registry->register($tool);
        }

        foreach (\App\Services\Chat\Tools\DashboardAnalyticsToolProvider::tools() as $tool) {
            $registry->register($tool);
        }

        foreach (\App\Services\Chat\Tools\AssessmentSummaryToolProvider::tools() as $tool) {
            $registry->register($tool);
        }

        foreach (\App\Services\Chat\Tools\ProgramModulesQueryToolProvider::tools() as $tool) {
            $registry->register($tool);
        }

        return $registry;
    }

    /**
     * Decides whether a numbered/lettered option list should accompany the
     * next question — never generated by the LLM, always from real slot
     * data. $candidatesFromLastTurn is the 'candidates' key from this
     * turn's tool execution result (MentorshipSetupToolProvider::tool()),
     * shaped [slotId => [['id'=>.., 'label'=>..], ...]].
     *
     * @param  array<string, array<int, array{id: mixed, label: string}>>  $candidatesFromLastTurn
     * @return array{slot: string, options: array<int, array{id: mixed, label: string}>}|null
     */
    public function determineNextStep(array $candidatesFromLastTurn = []): ?array
    {
        if (! empty($candidatesFromLastTurn)) {
            $slotId = array_key_first($candidatesFromLastTurn);

            return [
                'slot' => $slotId,
                'options' => self::numberOptions($candidatesFromLastTurn[$slotId]),
            ];
        }

        // module_ids isn't a generic Slot, so it's not reachable via
        // nextUnfilledSlot() below — without this, the model has no way to
        // know the real module names short of guessing from the tool
        // schema alone, and was observed telling the user it had "no
        // access to a list of available training modules" instead. The
        // options come straight from getModuleFieldOptions() (the same
        // source chat-modules-turn.blade.php's checkbox picker uses), never
        // from the model. Not capped by MAX_PROACTIVE_OPTIONS like the
        // slots below — module counts are curated curriculum content, not
        // an unbounded table like facilities.
        if ($this->activeStage() === 'modules') {
            if ($this->isModulesStageEmonc()) {
                return null;
            }

            $options = $this->getModuleFieldOptions();

            if (empty($options)) {
                return null;
            }

            return [
                'slot' => 'module_ids',
                'options' => self::numberOptions(
                    collect($options)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()->all()
                ),
            ];
        }

        // selected_users isn't a generic Slot object either, so once the
        // enroll_mentees stage begins, nextUnfilledSlot() would otherwise
        // skip straight past it to 'recipients' (send_invitations) — the
        // exact premature-exposure bug already fixed in
        // MentorshipSetupToolProvider::schemaFor()/execute(). Same guard,
        // same reason. Unlike modules, the mentee table is unbounded, so
        // this shows a capped initial browsable sample (the same
        // menteeOptions() source chat-mentees-turn.blade.php's own default,
        // no-search view uses) rather than everyone — without this, the
        // model had nothing but aggregate count tools to answer "who can I
        // enroll?" with, and was observed saying it had no way to retrieve
        // a mentee roster at all. A real search still returns its own,
        // uncapped shortlist via MentorshipMenteesToolProvider.
        if ($this->activeStage() === 'enroll_mentees') {
            $options = array_slice(
                app(MentorshipWizardService::class)->menteeOptions(null, 1, []),
                0,
                self::MAX_PROACTIVE_MENTEE_SAMPLE,
                true
            );

            if (empty($options)) {
                return null;
            }

            return [
                'slot' => 'selected_users',
                'options' => self::numberOptions(
                    collect($options)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()->all()
                ),
            ];
        }

        if ($this->activeStage() !== 'slot') {
            return null;
        }

        $next = $this->nextUnfilledSlot();

        if (! $next || $next->renderKind() !== \App\Services\Chat\Render::CARDS) {
            return null;
        }

        $options = $next->getOptions($this->answers);

        // No options at all (e.g. a county with no facilities registered
        // yet) is as unhelpful to proactively list as too many — nothing
        // to show, so fall back to the plain open question.
        if (empty($options) || count($options) > self::MAX_PROACTIVE_OPTIONS) {
            return null;
        }

        return [
            'slot' => $next->id,
            'options' => self::numberOptions(
                collect($options)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()->all()
            ),
        ];
    }

    /**
     * @param  array<int, array{id: mixed, label: string}>  $candidates
     * @return array<int, array{id: mixed, label: string}> 1-based
     */
    private static function numberOptions(array $candidates): array
    {
        $numbered = [];

        foreach (array_values($candidates) as $index => $candidate) {
            $numbered[$index + 1] = $candidate;
        }

        return $numbered;
    }

    /**
     * Bare "2" or single-letter "B" (case-insensitive, A=1, B=2, ...)
     * against the currently pending option list. Anything else (a longer
     * reply, an out-of-range number) returns null so the caller falls
     * through to the normal LLM flow.
     */
    private function matchPendingOptionIndex(string $text): ?int
    {
        if (preg_match('/^\d+$/', $text)) {
            $index = (int) $text;
        } elseif (preg_match('/^[a-zA-Z]$/', $text)) {
            $index = ord(strtoupper($text)) - ord('A') + 1;
        } else {
            return null;
        }

        return array_key_exists($index, $this->pendingOptions['options']) ? $index : null;
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
