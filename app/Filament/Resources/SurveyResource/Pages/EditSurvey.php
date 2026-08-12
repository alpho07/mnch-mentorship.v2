<?php

namespace App\Filament\Resources\SurveyResource\Pages;

use App\Filament\Resources\SurveyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSurvey extends EditRecord
{
    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dashboard')
                ->label('Dashboard')
                ->icon('heroicon-o-chart-bar')
                ->url(fn () => SurveyResource::getUrl('dashboard', ['record' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }
}
