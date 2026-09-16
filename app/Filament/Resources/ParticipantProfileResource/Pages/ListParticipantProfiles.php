<?php

namespace App\Filament\Resources\ParticipantProfileResource\Pages;

use App\Filament\Resources\ParticipantProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParticipantProfiles extends ListRecords
{
    protected static string $resource = ParticipantProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
