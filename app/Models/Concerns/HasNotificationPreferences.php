<?php

namespace App\Models\Concerns;

use App\Models\UserNotificationPreference;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;

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
     * The saved map, normalised so every known event/channel is present (missing
     * entries mean "on", which the form expresses as explicit true toggles).
     *
     * The returned array uses the same nested shape that Filament form fields
     * expect: event keys contain dots (e.g. "mentorship.class_started"), and
     * Filament treats dotted field names as nested state paths, so the data
     * must be expanded into a nested array via Arr::set(). The raw stored
     * JSON column keeps flat dotted keys (see wantsNotification() /
     * saveNotificationChannels()), so this method is the bridge between the
     * two representations.
     */
    public function notificationChannelMap(): array
    {
        $saved = $this->notificationPreference?->channels ?? [];

        if (! is_array($saved)) {
            $saved = [];
        }

        $map = [];

        foreach (\App\Support\NotificationEvents::all() as $event => $meta) {
            $eventChannels = is_array($saved[$event] ?? null) ? $saved[$event] : [];

            $values = [];

            foreach ([\App\Support\NotificationEvents::CHANNEL_MAIL, \App\Support\NotificationEvents::CHANNEL_DATABASE] as $channel) {
                $values[$channel] = (bool) ($eventChannels[$channel] ?? true);
            }

            // Expand the dotted event key into a nested path so Filament's
            // fill() matches the dotted field-name state paths.
            Arr::set($map, $event, $values);
        }

        return $map;
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
