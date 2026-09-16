<?php

// Enhanced CreateFacility Page
namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Resources\FacilityResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateFacility extends CreateRecord
{
    protected static string $resource = FacilityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        
        if ($record->is_central_store) {
            Notification::make()
                ->title('Central Store Created')
                ->body("Central store '{$record->name}' has been created successfully. You can now add inventory items and manage stock levels.")
                ->success()
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('add_stock')
                        ->label('Add Initial Stock')
                        ->url(route('filament.admin.resources.stock-levels.create', [
                            'facility_id' => $record->id
                        ])),
                    \Filament\Notifications\Actions\Action::make('view_dashboard')
                        ->label('View Central Store Dashboard')
                        ->url('/admin'),
                ])
                ->send();
        }

        if ($record->is_hub) {
            Notification::make()
                ->title('Hub Facility Created')
                ->body("Hub facility '{$record->name}' has been created. You can now assign spoke facilities to this hub.")
                ->success()
                ->send();
        }
    }
}