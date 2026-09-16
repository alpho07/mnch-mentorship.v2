<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\PartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPartner extends EditRecord
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->color('info'),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\Action::make('view_trainings')
                ->label('View Trainings')
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->url(fn (): string => 
                    route('filament.admin.resources.global-trainings.index', [
                        'tableFilters[partner][values][0]' => $this->record->id
                    ])
                )
                ->visible(fn (): bool => $this->record->training_count > 0),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Partner Updated')
            ->body('The partner organization has been updated successfully.');
    }
}