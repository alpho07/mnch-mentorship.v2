<?php

namespace App\Mail\Indicators;

use App\Models\Indicators\IndicatorReportPeriod;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportSubmittedMail extends Mailable {

    use Queueable,
        SerializesModels;

    public function __construct(
            public readonly IndicatorReportPeriod $period,
            public readonly User $submittedBy,
    ) {
        
    }

    public function envelope(): Envelope {
        return new Envelope(
                subject: '[MNCH] Report Submitted for Validation — '
                . $this->period->reportType->name
                . ' | ' . $this->period->facility->name
                . ' | ' . $this->period->period_label,
        );
    }

    public function content(): Content {
        return new Content(
                view: 'mail.indicators.report-submitted',
        );
    }

    public function attachments(): array {
        return [];
    }
}
 