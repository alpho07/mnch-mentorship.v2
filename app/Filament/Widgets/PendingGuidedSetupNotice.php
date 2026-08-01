<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\MentorshipClass;
use App\Models\Training;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/**
 * Surfaces a "you have a pending mentorship" banner when the current user
 * started the guided setup wizard but never reached Send Invitations —
 * lets them resume where they left off, or discard the abandoned draft.
 */
class PendingGuidedSetupNotice extends Widget
{
    protected static string $view = 'filament.widgets.pending-guided-setup-notice';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::pendingTraining() !== null;
    }

    public static function pendingTraining(): ?Training
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return Training::pendingGuidedSetup()
            ->where('mentor_id', $user->id)
            ->latest()
            ->first();
    }

    public function discard(): void
    {
        $training = static::pendingTraining();

        if (! $training || $training->mentor_id !== auth()->id()) {
            return;
        }

        // A real DELETE (not soft) — this is an abandoned, never-finished
        // draft, and forceDelete() lets the FK cascadeOnDelete() constraints
        // clean up its class/modules/participants in one go.
        $training->forceDelete();

        Notification::make()
            ->success()
            ->title('Draft discarded')
            ->send();
    }

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
}
