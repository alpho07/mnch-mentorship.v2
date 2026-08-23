<?php

namespace App\Models\Concerns;

use App\Models\UserNotificationPreference;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Adds per-user notification channel preferences on top of the standard
 * Notifiable behaviour. Opt-out model: with no saved preference row (or a
 * missing event/channel key) every channel stays enabled, so existing
 * notification flows are unchanged until a user explicitly turns something
 * off.
 */
trait HasNotificationPreferences
{
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class);
    }

    /**
     * Whether the user wants the given channel for the given event.
     * $channel is a Laravel/transport channel name: 'mail', 'database'
     * (in-app bell) or 'broadcast' (real-time push).
     */
    public function wantsNotification(string $event, string $channel = 'mail'): bool
    {
        $prefs = $this->notificationPreference;

        if (! $prefs || ! is_array($prefs->channels)) {
            return true;
        }

        // Direct array access — $event itself contains dots, so data_get()
        // would wrongly treat it as nested segments.
        $eventChannels = $prefs->channels[$event] ?? null;

        if (! is_array($eventChannels)) {
            return true;
        }

        return $eventChannels[$channel] ?? true;
    }

    /**
     * The raw saved map — used to pre-fill the preferences form. Missing
     * entries mean "on", which the form expresses as explicit true toggles.
     */
    public function notificationChannelMap(): array
    {
        $saved = $this->notificationPreference?->channels ?? [];

        if (! is_array($saved)) {
            $saved = [];
        }

        foreach (\App\Support\NotificationEvents::all() as $event => $meta) {
            foreach ([\App\Support\NotificationEvents::CHANNEL_MAIL, \App\Support\NotificationEvents::CHANNEL_DATABASE] as $channel) {
                $eventChannels = is_array($saved[$event] ?? null) ? $saved[$event] : [];

                $saved[$event][$channel] = $eventChannels[$channel] ?? true;
            }
            unset($saved[$event]['broadcast']);
        }

        return $saved;
    }

    public function saveNotificationChannels(array $channels): void
    {
        $preference = UserNotificationPreference::updateOrCreate(
            ['user_id' => $this->getKey()],
            ['channels' => $channels],
        );

        // Keep any already-loaded relation consistent for reads later in
        // the same request/lifecycle (Livewire, tests).
        $this->setRelation('notificationPreference', $preference);
    }
}
