<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends mailables without ever letting a transport failure break the calling
 * business logic (class lifecycle transitions, mentorship flows, etc.).
 * Unlike the previous silent catch-and-report pattern, every failure is
 * logged with structured context — recipient, mailable and error message —
 * so delivery problems are diagnosable from the logs alone.
 */
class SafeMailer
{
    /**
     * @param  mixed  $recipient  A User model (email taken from it) or a plain email string.
     * @param  object  $mailable  The configured Mailable instance to send.
     * @param  string  $context  Stable label identifying the event, e.g. NotificationEvents::MENTORSHIP_CLASS_STARTED.
     */
    public static function send(mixed $recipient, object $mailable, string $context, array $extra = []): bool
    {
        return self::dispatch(fn () => Mail::to(self::resolveEmail($recipient))->send($mailable), $recipient, $mailable, $context, $extra);
    }

    /**
     * Queued variant of send() for bulk fan-out where request latency matters.
     */
    public static function queue(mixed $recipient, object $mailable, string $context, array $extra = []): bool
    {
        return self::dispatch(fn () => Mail::to(self::resolveEmail($recipient))->queue($mailable), $recipient, $mailable, $context, $extra);
    }

    private static function dispatch(callable $attempt, mixed $recipient, object $mailable, string $context, array $extra): bool
    {
        $email = self::resolveEmail($recipient);

        if (empty($email)) {
            return false;
        }

        try {
            $attempt();

            return true;
        } catch (\Throwable $e) {
            Log::error('Notification email failed', array_merge([
                'context' => $context,
                'mailable' => $mailable::class,
                'to' => $email,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ], $extra));

            report($e);

            return false;
        }
    }

    private static function resolveEmail(mixed $recipient): ?string
    {
        if (is_object($recipient)) {
            return $recipient->email ?? null;
        }

        return is_string($recipient) ? $recipient : null;
    }
}
