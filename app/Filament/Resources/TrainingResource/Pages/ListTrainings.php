<?php

namespace App\Filament\Resources\TrainingResource\Pages;

use App\Filament\Resources\TrainingResource;
use App\Models\Training;
use App\Services\TrainingReportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTrainings extends ListRecords
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Training')
                ->icon('heroicon-o-plus'),

            Actions\Action::make('export_all')
                ->label('Export All')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $reportService = new TrainingReportService();
                    // Export all trainings summary
                    return response()->streamDownload(
                        function () use ($reportService) {
                            $trainings = Training::with(['participants', 'program', 'facility'])->get();

                            $headers = ['Training Code', 'Title', 'Program', 'Type', 'Status', 'Participants', 'Completion Rate', 'Start Date', 'End Date'];

                            $output = fopen('php://output', 'w');
                            fputcsv($output, $headers);

                            foreach ($trainings as $training) {
                                fputcsv($output, [
                                    $training->identifier,
                                    $training->title,
                                    $training->program?->name ?? 'N/A',
                                    ucfirst(str_replace('_', ' ', $training->type)),
                                    ucfirst($training->status),
                                    $training->participants()->count(),
                                    $training->completion_rate . '%',
                                    $training->start_date->format('Y-m-d'),
                                    $training->end_date->format('Y-m-d'),
                                ]);
                            }
                            fclose($output);
                        },
                        'all-trainings-' . now()->format('Y-m-d') . '.csv',
                        ['Content-Type' => 'text/csv']
                    );
                }),
        ];
    }
}
