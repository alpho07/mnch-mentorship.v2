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
    use HasMentorshipChatSlots;
    use InteractsWithForms;

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
        $registry = new ChatToolRegistry;
        $registry->register(MentorshipSetupToolProvider::tool($this));

        foreach (\App\Services\Chat\Tools\MentorshipStatsToolProvider::tools() as $tool) {
            $registry->register($tool);
        }

        foreach (\App\Services\Chat\Tools\DashboardAnalyticsToolProvider::tools() as $tool) {
            $registry->register($tool);
        }

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
