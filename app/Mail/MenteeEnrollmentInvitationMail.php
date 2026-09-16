<?php

namespace App\Mail;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MenteeEnrollmentInvitationMail extends Mailable
{
    use Queueable,
        SerializesModels;

    public string $enrollmentLink;

    public bool $isResend;

    public bool $isEmonc;

    public array $moduleRows;

    public function __construct(
        public readonly User $mentee,
        public readonly MentorshipClass $class,
        public readonly ClassParticipant $participant,
        bool $isResend = false,
    ) {
        $this->isResend = $isResend;
        $this->enrollmentLink = route('mentee.enroll', ['token' => $class->enrollment_token]);
        $this->isEmonc = $this->detectEmonc();
        $this->moduleRows = $this->buildModuleRows();
    }

    private function detectEmonc(): bool
    {
        $program = Program::find($this->class->training->program_id);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }

    private function buildModuleRows(): array
    {
        if (! $this->isEmonc) {
            return [];
        }

        return $this->class->classModules()
            ->with(['programModule.parent', 'programModule.activities'])
            ->orderBy('order_sequence')
            ->get()
            ->map(function ($classModule) {
                $programModule = $classModule->programModule;
                $trackName = $programModule?->parent?->name;

                return [
                    'module_name' => $programModule?->name ?? 'Module',
                    'track_name' => $trackName,
                    'activities' => $programModule?->activities?->pluck('name')->toArray() ?? [],
                ];
            })
            ->toArray();
    }

    public function envelope(): Envelope
    {
        $subject = $this->isResend ? "Reminder: Your Enrollment Link — {$this->class->name}" : "You've been invited to join {$this->class->name}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mentee-enrollment-invitation',
        );
    }
}
