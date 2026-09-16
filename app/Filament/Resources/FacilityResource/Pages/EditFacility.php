<?php

namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Resources\FacilityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditFacility extends EditRecord
{
    protected static string $resource = FacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('view_stock')
                ->label('View Stock Levels')
                ->icon('heroicon-o-squares-2x2')
                ->color('info')
                ->visible(fn (): bool => $this->getRecord()->is_central_store)
                ->url(fn (): string => 
                    route('filament.admin.resources.stock-levels.index', [
                        'tableFilters[facility][value]' => $this->getRecord()->id
                    ])
                ),
            Actions\Action::make('manage_distributions')
                ->label('Manage Distributions')
                ->icon('heroicon-o-share')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->is_central_store)
                ->url(fn (): string => 
                    route('filament.admin.resources.stock-requests.index', [
                        'tableFilters[central_store][value]' => $this->getRecord()->id
                    ])
                ),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $originalData = $this->getRecord()->getOriginal();
        
        // Check if central store status changed
        if ($originalData['is_central_store'] !== $record->is_central_store) {
            if ($record->is_central_store) {
                Notification::make()
                    ->title('Facility Converted to Central Store')
                    ->body("'{$record->name}' is now a central store. You can start managing inventory and distributions.")
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('setup_inventory')
                            ->label('Set Up Inventory')
                            ->url(route('filament.admin.resources.inventory-items.index')),
                    ])
                    ->send();
            } else {
                Notification::make()
                    ->title('Central Store Status Removed')
                    ->body("'{$record->name}' is no longer a central store. Stock levels and distributions remain unchanged.")
                    ->warning()
                    ->send();
            }
        }

        // Check if hub status changed
        if ($originalData['is_hub'] !== $record->is_hub) {
            if ($record->is_hub) {
                Notification::make()
                    ->title('Facility Converted to Hub')
                    ->body("'{$record->name}' is now a hub facility.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Hub Status Removed')
                    ->body("'{$record->name}' is no longer a hub facility. Spoke assignments remain unchanged.")
                    ->warning()
                    ->send();
            }
        }
    }
}