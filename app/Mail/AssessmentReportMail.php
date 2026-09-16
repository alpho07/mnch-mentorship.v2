<?php

namespace App\Mail;

use App\Models\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssessmentReportMail extends Mailable {

    use Queueable, SerializesModels;

    public function __construct(
        public readonly Assessment $assessment,
        private readonly string $pdfContent,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope {
        $facility = $this->assessment->facility->name ?? 'Facility';
        $date     = $this->assessment->assessment_date instanceof \Carbon\Carbon
            ? $this->assessment->assessment_date->format('d M Y')
            : (string) $this->assessment->assessment_date;

        return new Envelope(
            subject: "MNCH Assessment Report — {$facility} ({$date})",
        );
    }

    public function content(): Content {
        return new Content(view: 'emails.assessment-report');
    }

    public function attachments(): array {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
