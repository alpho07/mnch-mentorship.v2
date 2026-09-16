<?php

namespace App\Filament\Resources\MenteeStatusResource\Pages;

use App\Filament\Resources\MenteeStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMenteeStatuses extends ListRecords
{
    protected static string $resource = MenteeStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
