<?php

namespace App\Mail\Indicators;

use App\Models\Indicators\IndicatorReportPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportRejectedMail extends Mailable {

    use Queueable,
        SerializesModels;

    public function __construct(
            public readonly IndicatorReportPeriod $period,
    ) {
        
    }

    public function envelope(): Envelope {
        return new Envelope(
                subject: '[MNCH] Report Returned for Corrections — '
                . $this->period->reportType->name
                . ' | ' . $this->period->period_label,
        );
    }

    public function content(): Content {
        return new Content(
                view: 'mail.indicators.report-rejected',
        );
    }

    public function attachments(): array {
        return [];
    }
}
