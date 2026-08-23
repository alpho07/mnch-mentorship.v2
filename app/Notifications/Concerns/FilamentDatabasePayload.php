<?php

namespace App\Notifications\Concerns;

use App\Models\User;

/**
 * Shared behaviour for the inventory notification classes so they land in the
 * same stream as everything else:
 *
 * - `via()` respects the recipient's saved per-user channel preferences
 *   (HasNotificationPreferences) instead of hardcoding channels.
 * - `toDatabase()` payloads carry the keys Filament's bell-icon renderer
 *   expects (title / body / icon / color), merged over each class's legacy
 *   payload so any existing readers keep working.
 */
trait FilamentDatabasePayload
{
    public function via($notifiable): array
    {
        $all = ['mail', 'database', 'broadcast'];

        if (! $notifiable instanceof User) {
            return array_values(array_diff($all, ['broadcast']));
        }

        return array_values(array_filter(
            $all,
            fn (string $channel) => $notifiable->wantsNotification($this->eventKey(), $channel)
        ));
    }

    /**
     * Builds the database-channel payload: legacy custom keys preserved
     * untouched, with the Filament bell keys layered on top so the renderer
     * always sees a well-formed notification.
     */
    protected function filamentPayload(array $legacy = []): array
    {
        return array_merge($legacy, [
            'title' => $this->notificationTitle(),
            'body' => $this->notificationBody(),
            'icon' => $this->notificationIcon(),
            'color' => $this->notificationColor(),
        ]);
    }

    abstract public function eventKey(): string;

    abstract protected function notificationTitle(): string;

    abstract protected function notificationBody(): string;

    protected function notificationIcon(): string
    {
        return 'heroicon-o-bell-alert';
    }

    protected function notificationColor(): string
    {
        return 'info';
    }
}
