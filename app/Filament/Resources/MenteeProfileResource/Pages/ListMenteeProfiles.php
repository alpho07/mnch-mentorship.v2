<?php

namespace App\Filament\Resources\MenteeProfileResource\Pages;

use App\Filament\Resources\MenteeProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMenteeProfiles extends ListRecords
{
    protected static string $resource = MenteeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
