<?php

namespace App\Filament\Resources\RagEvalCaseResource\Pages;

use App\Filament\Resources\RagEvalCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRagEvalCases extends ListRecords
{
    protected static string $resource = RagEvalCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
