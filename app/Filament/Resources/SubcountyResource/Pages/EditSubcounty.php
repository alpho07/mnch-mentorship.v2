<?php

namespace App\Filament\Resources\SubcountyResource\Pages;

use App\Filament\Resources\SubcountyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubcounty extends EditRecord
{
    protected static string $resource = SubcountyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}

