<?php
// EditMonthlyReport.php
namespace App\Filament\Resources\MonthlyReportResource\Pages;

use App\Filament\Resources\MonthlyReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMonthlyReport extends EditRecord
{
    protected static string $resource = MonthlyReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->canEdit()),
            Actions\Action::make('submit')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->action(function () {
                    $this->getRecord()->update([
                        'status' => 'submitted',
                        'submitted_at' => now(),
                    ]);
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
                })
                ->visible(fn (): bool => $this->getRecord()->canSubmit())
                ->requiresConfirmation()
                ->modalHeading('Submit Report')
                ->modalDescription('Are you sure you want to submit this report? You will not be able to edit it after submission.')
                ->modalSubmitActionLabel('Submit'),
        ];
    }

    protected function beforeSave(): void
    {
        // Ensure user can only edit reports they have access to
        if (!auth()->user()->canAccessFacility($this->getRecord()->facility_id)) {
            abort(403, 'You do not have access to edit reports for this facility.');
        }

        // Prevent editing of non-editable reports
        if (!$this->getRecord()->canEdit()) {
            abort(403, 'This report cannot be edited in its current status.');
        }
    }
}
