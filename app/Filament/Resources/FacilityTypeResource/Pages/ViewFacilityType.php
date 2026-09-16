<?php
namespace App\Filament\Resources\FacilityTypeResource\Pages;

use App\Filament\Resources\FacilityTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\FacilityTypeResource\RelationManagers\FacilitiesRelationManager;

class ViewFacilityType extends ViewRecord
{
    protected static string $resource = FacilityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_facility')
                ->label('Add Facility')
                ->icon('heroicon-o-building-office-2')
                ->color('success')
                ->url(fn (): string => 
                    \App\Filament\Resources\FacilityResource::getUrl('create', [
                        'facility_type_id' => $this->record->id
                    ])
                ),
            
            Actions\Action::make('facility_analytics')
                ->label('Analytics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->action(function (): void {
                    $this->notify('info', 'Facility type analytics dashboard coming soon');
                }),
            
            Actions\EditAction::make(),
            
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('This will delete the facility type. Facilities will lose their type assignment.')
                ->before(function (): void {
                    // Remove facility type assignment from facilities
                    $this->record->facilities()->update(['facility_type_id' => null]);
                }),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            FacilityTypeResource\RelationManagers\FacilitiesRelationManager::class,
        ];
    }
}