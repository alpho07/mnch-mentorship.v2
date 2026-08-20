<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Services\AssessmentPdfReportService;
use App\Services\AssessmentExportService;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewAssessmentSummary extends ViewRecord {

    protected static string $resource = AssessmentResource::class;
    protected static string $view = 'filament.pages.view-assessment-summary';

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('select_sections')
                    ->label('Select Sections')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('gray')
                    ->form([
                        CheckboxList::make('sections')
                            ->label('Sections to include')
                            ->helperText('Facility Information, Section Performance, and Overall Score are always included. Applies here and to future PDF downloads — remembered for next time.')
                            ->options(AssessmentPdfReportService::TOGGLEABLE_SECTIONS)
                            ->default(fn () => auth()->user()->enabledReportSections())
                            ->columns(2),
                    ])
                    ->action(function (array $data) {
                        auth()->user()->update(['report_section_preferences' => $data['sections'] ?? []]);

                        $this->redirect(static::getUrl(['record' => $this->record]));
                    }),
                    Actions\Action::make('export_csv')
                    ->label('Export CSV (Raw Data)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () {
                        $service = app(AssessmentExportService::class);
                        $csv = $service->exportAssessmentToCSV($this->record);

                        $filename = sprintf(
                                'mnch-assessment-raw-data-%s-%s.csv',
                                $this->record->facility->name,
                                $this->record->assessment_date->format('Y-m-d')
                        );

                        return response()->streamDownload(function () use ($csv) {
                                    echo $csv;
                                }, $filename, [
                                    'Content-Type' => 'text/csv',
                        ]);
                    }),
                    Actions\Action::make('download_pdf')
                    ->label('Download PDF Report')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->tooltip('Uses your saved section selection — see "Select Sections" to change it')
                    ->action(function () {
                        $service = app(AssessmentPdfReportService::class);
                        $pdf = $service->generateExecutiveReport($this->record, auth()->user()->enabledReportSections());

                        $filename = sprintf(
                                'MNCH-Assessment-%s-%s.pdf',
                                $this->record->facility->name,
                                $this->record->assessment_date->format('Y-m-d')
                        );

                        return response()->streamDownload(function () use ($pdf) {
                                    echo $pdf->output();
                                }, $filename);
                    }),
            Actions\EditAction::make(),
                    Actions\Action::make('mark_complete')
                    ->label('Mark as Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->visible(fn() => $this->record->status !== 'completed')
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'completed_by' => auth()->id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                                ->title('Assessment marked as complete')
                                ->success()
                                ->send();
                    }),
        ];
    }

    public function getTitle(): string {
        return "Assessment Summary - {$this->record->facility->name}";
    }

    /**
     * Get the report HTML for web display — filtered by the user's saved
     * section selection, same as the PDF download.
     */
    public function getReportHtml(): string {
        $service = app(AssessmentPdfReportService::class);
        return $service->generateHtmlReport($this->record, auth()->user()->enabledReportSections());
    }
}
