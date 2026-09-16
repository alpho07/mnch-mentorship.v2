<?php

namespace App\Jobs;

use App\Mail\AssessmentReportMail;
use App\Models\AssessmentEmailJob;
use App\Services\AssessmentPdfReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAssessmentReportEmail implements ShouldQueue {

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly AssessmentEmailJob $emailJob,
    ) {}

    public function handle(AssessmentPdfReportService $pdfService): void {
        $this->emailJob->update(['status' => 'processing']);

        $assessment  = $this->emailJob->assessment;
        $pdf         = $pdfService->generateExecutiveReport($assessment);
        $pdfContent  = $pdf->output();

        $facilityName = $assessment->facility->name ?? 'report';
        $date = $assessment->assessment_date instanceof \Carbon\Carbon
            ? $assessment->assessment_date->format('Y-m-d')
            : (string) $assessment->assessment_date;

        $filename = sprintf(
            'MNCH-Assessment-%s-%s.pdf',
            preg_replace('/[^a-zA-Z0-9\-_]/', '-', $facilityName),
            $date,
        );

        foreach ($this->emailJob->emails as $email) {
            Mail::to($email)->send(
                new AssessmentReportMail($assessment, $pdfContent, $filename)
            );
        }

        $this->emailJob->update([
            'status'   => 'sent',
            'sent_at'  => now(),
            'error'    => null,
        ]);
    }

    public function failed(Throwable $e): void {
        $this->emailJob->update([
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ]);
    }
}
