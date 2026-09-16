<?php

namespace App\Filament\Resources\ScopeResource\Pages;

use App\Filament\Resources\ScopeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScopes extends ListRecords
{
    protected static string $resource = ScopeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
