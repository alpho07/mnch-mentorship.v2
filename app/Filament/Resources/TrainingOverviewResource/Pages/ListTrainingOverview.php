<?php

namespace App\Filament\Resources\TrainingOverviewResource\Pages;

use App\Filament\Resources\TrainingOverviewResource;
use App\Filament\Resources\TrainingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Illuminate\Database\Eloquent\Model;

class ListTrainingOverview extends ListRecords
{
    protected static string $resource = TrainingOverviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_training')
                ->label('Create New Training')
                ->icon('heroicon-o-plus')
                ->url(TrainingResource::getUrl('create'))
                ->color('primary'),
        ];
    }

    public function getTitle(): string
    {
        return 'Training Overview';
    }

    public function getSubheading(): string
    {
        return 'Overview of all training programs with key metrics';
    }

  
}
