<?php

namespace App\Filament\Resources\ResourceCommentResource\Pages;

use App\Filament\Resources\ResourceCommentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResourceComments extends ListRecords
{
    protected static string $resource = ResourceCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
