<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipResource\Pages\Concerns\HasMentorshipChatSlots;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Setting;
use App\Services\Chat\ChatToolRegistry;
use App\Services\Chat\LlmMentorshipAssistantService;
use App\Services\Chat\Tools\MentorshipModulesToolProvider;
use App\Services\Chat\Tools\MentorshipSetupToolProvider;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;

class MnchGptSetup extends Page implements HasForms
{
    use HasMentorshipChatSlots;
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.mnchgpt-setup';

    protected static bool $shouldRegisterNavigation = false;

    private const MAX_PROACTIVE_OPTIONS = 10;

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

    public function sendMessage(string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        if ($this->pendingOptions) {
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

        $candidatesFromThisTurn = collect($result['tool_calls'])
            ->pluck('result.candidates')
            ->filter()
            ->collapse()
            ->all();

        $step = $this->determineNextStep($candidatesFromThisTurn);
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

        foreach (\App\Services\Chat\Tools\MentorshipStatsToolProvider::tools() as $tool) {
            $registry->register($tool);
        }

        foreach (\App\Services\Chat\Tools\DashboardAnalyticsToolProvider::tools() as $tool) {
            $registry->register($tool);
        }

        foreach (\App\Services\Chat\Tools\AssessmentSummaryToolProvider::tools() as $tool) {
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

        // module_ids/selected_users aren't generic Slot objects, so once
        // the modules/enroll_mentees stages begin, nextUnfilledSlot() would
        // otherwise skip straight past them to 'recipients' (send_
        // invitations) — the exact premature-exposure bug already fixed in
        // MentorshipSetupToolProvider::schemaFor()/execute(). Same guard,
        // same reason.
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
