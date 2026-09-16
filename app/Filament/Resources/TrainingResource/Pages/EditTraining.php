<?php

namespace App\Filament\Resources\TrainingResource\Pages;

use App\Filament\Resources\TrainingResource;
use App\Models\Training;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditTraining extends EditRecord
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->icon('heroicon-o-eye'),

            Actions\Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (Training $record): string =>
                    static::getResource()::getUrl('view', ['record' => $record])
                )
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Training Updated')
            ->body('The training details have been saved successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure facility is set for mentorship trainings
        if ($data['type'] === 'facility_mentorship' && empty($data['facility_id'])) {
            $data['facility_id'] = auth()->user()->facility_id;
        }

        // Auto-update status based on dates
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            $now = now();
            $startDate = \Carbon\Carbon::parse($data['start_date']);
            $endDate = \Carbon\Carbon::parse($data['end_date']);

            if ($data['status'] === 'published' || $data['status'] === 'registration_open') {
                if ($startDate <= $now && $endDate >= $now) {
                    $data['status'] = 'ongoing';
                } elseif ($endDate < $now) {
                    $data['status'] = 'completed';
                }
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Update participant records if facility changed for mentorship training
        if ($this->getRecord()->isFacilityMentorship()) {
            $this->getRecord()->participants()
                ->whereNull('facility_id')
                ->update(['facility_id' => $this->getRecord()->facility_id]);
        }
    }
}
