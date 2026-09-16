<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AccountVerificationMail extends Mailable {

    use Queueable,
        SerializesModels;

    public string $verificationUrl;
    public string $roleName;

    public function __construct(public User $user) {
        // Signed URL valid for 7 days — no extra DB token column needed.
        $this->verificationUrl = URL::temporarySignedRoute(
                'account.verify.show',
                now()->addDays(7),
                ['user' => $user->id]
        );

        $this->roleName = $user->roles->first()?->name ?? 'User';
    }

    public function envelope(): Envelope {
        return new Envelope(
                subject: 'Your MNCH Mentorship Platform Account — Action Required',
        );
    }

    public function content(): Content {
        return new Content(view: 'emails.account-verification');
    }
}
