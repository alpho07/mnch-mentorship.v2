<?php

namespace App\Filament\Pages;

use App\Services\ExecutiveBriefService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * National executive brief on facility mentorship — a single page written
 * for ministerial readers rather than for programme staff.
 *
 * The page renders straight from ExecutiveBriefService, so the brief is
 * never stale: opening it re-reads the mentorship tables.
 */
class ExecutiveBrief extends Page 
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.pages.executive-brief';

    protected static ?string $navigationGroup = 'Reporting';

    protected static ?string $title = 'Executive Brief';

    protected static ?string $navigationLabel = 'Executive Brief';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'executive-brief';

    /**
     * Built per render rather than held in a public property: the brief
     * contains Collections and Carbon instances, which Livewire would
     * flatten to plain arrays on the first hydration and break every
     * ->isNotEmpty() and ->format() in the view.
     */
    protected function getViewData(): array
    {
        return ['brief' => app(ExecutiveBriefService::class)->build()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('downloadPdf'),
        ];
    }

    public function downloadPdf(): StreamedResponse
    {
        // Rebuilt rather than reusing the render pass so the PDF reflects
        // the database at the moment of download, not at page load.
        $brief = app(ExecutiveBriefService::class)->build();

        $pdf = Pdf::loadView('pdf.executive-brief', ['brief' => $brief]);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]);

        $filename = 'MOH-Newborn-Child-Health-Mentorship-Brief-'
            .$brief['meta']['generatedAt']->format('Y-m-d').'.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $filename);
    }
}
