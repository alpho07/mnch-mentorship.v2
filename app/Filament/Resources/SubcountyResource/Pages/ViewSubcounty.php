<?php
namespace App\Filament\Resources\SubcountyResource\Pages;

use App\Filament\Resources\SubcountyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSubcounty extends ViewRecord
{
    protected static string $resource = SubcountyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }
}