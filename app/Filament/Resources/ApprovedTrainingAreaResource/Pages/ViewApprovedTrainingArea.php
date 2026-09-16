<?php

namespace App\Filament\Resources\ApprovedTrainingAreaResource\Pages;

use App\Filament\Resources\ApprovedTrainingAreaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;

class ViewApprovedTrainingArea extends ViewRecord 
{
    protected static string $resource = ApprovedTrainingAreaResource::class;

    protected function getHeaderActions(): array 
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->before(function ($record, $action) {
                    if (!$record->canBeDeleted()) {
                        \Filament\Notifications\Notification::make() 
                            ->title('Cannot Delete')
                            ->body('This training area has associated trainings and cannot be deleted.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Training Area Information')
                    ->schema([
                        TextEntry::make('name')
                            ->weight(FontWeight::Bold)
                            ->size('lg'),

                        TextEntry::make('description')
                            ->prose()
                            ->columnSpanFull()
                            ->visible(fn ($state) => !empty($state)),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('is_active')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),

                                TextEntry::make('sort_order')
                                    ->label('Display Order')
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('training_count')
                                    ->label('Total Trainings')
                                    ->getStateUsing(fn ($record) => $record->trainings()->count())
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ]),

                Section::make('Training Statistics')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('active_trainings')
                                    ->label('Active Trainings')
                                    ->getStateUsing(fn ($record) => $record->trainings()->ongoing()->count())
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('upcoming_trainings')
                                    ->label('Upcoming Trainings')
                                    ->getStateUsing(fn ($record) => $record->trainings()->upcoming()->count())
                                    ->badge()
                                    ->color('warning'),

                                TextEntry::make('completed_trainings')
                                    ->label('Completed Trainings')
                                    ->getStateUsing(fn ($record) => $record->trainings()->completed()->count())
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('total_participants')
                                    ->label('Total Participants')
                                    ->getStateUsing(fn ($record) => 
                                        \App\Models\TrainingParticipant::whereHas('training', function ($q) use ($record) {
                                            $q->where('approved_training_area_id', $record->id);
                                        })->count()
                                    )
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ]),

                Section::make('System Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}