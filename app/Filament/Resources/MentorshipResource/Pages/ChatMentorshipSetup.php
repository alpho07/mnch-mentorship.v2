<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Setting;
use App\Models\Training;
use App\Services\Chat\MentorshipChatScript;
use App\Services\Chat\Slot;
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

    protected function currentSlot(): Slot
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

    protected function nextUnfilledSlot(): ?Slot
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
