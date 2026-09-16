<?php

namespace App\Filament\Resources\TrainingResource\Pages;
namespace App\Filament\Resources\TrainingResource\Pages;

use App\Filament\Resources\TrainingResource;
use App\Models\Training;
use App\Services\TrainingReportService;
use App\Services\BulkParticipantImportService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use Filament\Notifications\Notification;

class ViewTraining extends ViewRecord
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil'),

            Actions\Action::make('duplicate')
                ->label('Duplicate Training')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->action(function (Training $record) {
                    $newTraining = $record->replicate();
                    $newTraining->title = $record->title . ' (Copy)';
                    $newTraining->identifier = null; // Will be auto-generated
                    $newTraining->status = 'draft';
                    $newTraining->start_date = null;
                    $newTraining->end_date = null;
                    $newTraining->save();

                    // Copy relationships
                    $newTraining->departments()->sync($record->departments->pluck('id'));
                    $newTraining->modules()->sync($record->modules->pluck('id'));
                    $newTraining->methodologies()->sync($record->methodologies->pluck('id'));

                    Notification::make()
                        ->title('Training Duplicated')
                        ->body('A copy of the training has been created.')
                        ->success()
                        ->send();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $newTraining]));
                }),

            Actions\Action::make('bulk_import')
                ->label('Import Participants')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    Forms\Components\FileUpload::make('import_file')
                        ->label('Upload CSV File')
                        ->acceptedFileTypes(['text/csv'])
                        ->required()
                        ->helperText('Upload a CSV file with participant data'),

                    Forms\Components\Checkbox::make('send_notifications')
                        ->label('Send email notifications to participants')
                        ->default(false),
                ])
                ->action(function (Training $record, array $data) {
                    $importService = new BulkParticipantImportService();
                    $result = $importService->importFromFile($data['import_file'], $record);

                    if ($result['success']) {
                        Notification::make()
                            ->title('Import Successful')
                            ->body("Imported {$result['imported_count']} participants successfully")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Import Issues')
                            ->body("Imported {$result['imported_count']} participants with " . count($result['errors']) . " errors")
                            ->warning()
                            ->send();
                    }
                }),

            Actions\Action::make('download_template')
                ->label('Download Import Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function (Training $record) {
                    $importService = new BulkParticipantImportService();
                    $template = $importService->generateTemplate($record);

                    return response()->streamDownload(
                        function () use ($template) {
                            echo $template;
                        },
                        "participant-template-{$record->identifier}.csv",
                        ['Content-Type' => 'text/csv']
                    );
                }),

            Actions\Action::make('export_report')
                ->label('Export Full Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('warning')
                ->action(function (Training $record) {
                    $reportService = new TrainingReportService();
                    $filePath = $reportService->exportParticipantReportToCsv($record);

                    return response()->download($filePath)->deleteFileAfterSend();
                }),

            Actions\Action::make('change_status')
                ->label('Change Status')
                ->icon('heroicon-o-arrow-path')
                ->color('secondary')
                ->form([
                    Forms\Components\Select::make('status')
                        ->label('New Status')
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                            'registration_open' => 'Registration Open',
                            'registration_closed' => 'Registration Closed',
                            'ongoing' => 'Ongoing',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                        ])
                        ->required(),

                    Forms\Components\Textarea::make('status_reason')
                        ->label('Reason for Status Change')
                        ->placeholder('Optional reason for this change...')
                        ->rows(2),
                ])
                ->action(function (Training $record, array $data) {
                    $oldStatus = $record->status;
                    $record->update(['status' => $data['status']]);

                    Notification::make()
                        ->title('Status Updated')
                        ->body("Training status changed from {$oldStatus} to {$data['status']}")
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('Are you sure you want to delete this training? This will also remove all participants and assessment data.')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Training Deleted')
                        ->body('The training and all related data have been removed.')
                ),
        ];
    }

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();
        return "Code: {$record->identifier} | {$record->program?->name} | " . ucfirst(str_replace('_', ' ', $record->type));
    }
}
