<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Setting;
use App\Models\Training;
use App\Services\Chat\MentorshipChatScript;
use App\Services\Chat\Slot;
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
            'text' => 'Welcome, '.explode(' ', auth()->user()->name)[0].'! '.$this->nextUnfilledSlot()->getQuestion($this->answers),
            'timestamp' => now()->toIso8601String(),
        ];
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

    protected function appendTranscript(?array $message): void
    {
        if (! $message || ! $this->training) {
            return;
        }

        $this->training->appendChatTranscript($message);
    }
}
