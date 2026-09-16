<?php

namespace App\Mail;

use App\Models\MentorshipCoMentor;
use App\Models\Training;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoMentorInvitation extends Mailable
{
    use Queueable,
        SerializesModels;

    public string $invitationLink;

    public function __construct(
        public readonly Training $training,
        public readonly MentorshipCoMentor $coMentor,
        public readonly ?string $invitationMessage = null,
    ) {
        $this->invitationLink = $coMentor->invitation_link ?? url("/co-mentor/accept/{$coMentor->invitation_token}");
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to co-mentor — {$this->training->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.co-mentor-invitation',
        );
    }
}
