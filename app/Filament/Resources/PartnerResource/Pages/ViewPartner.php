<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\PartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Support\Enums\FontWeight;

class ViewPartner extends ViewRecord
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->color('warning'),
            Actions\Action::make('view_trainings')
                ->label('View Trainings')
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->url(fn (): string => 
                    route('filament.admin.resources.global-trainings.index', [
                        'tableFilters[partner][values][0]' => $this->record->id
                    ])
                )
                ->visible(fn (): bool => $this->record->training_count > 0),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Partner Overview')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                                TextEntry::make('type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'ngo' => 'primary',
                                        'international' => 'success',
                                        'private' => 'warning',
                                        'faith_based' => 'info',
                                        'academic' => 'secondary',
                                        'development' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => 
                                        \App\Models\Partner::getTypeOptions()[$state] ?? ucfirst($state)
                                    ),
                            ]),

                        TextEntry::make('description')
                            ->prose()
                            ->columnSpanFull()
                            ->placeholder('No description provided'),

                        Grid::make(2)
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label('Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                                TextEntry::make('registration_number')
                                    ->label('Registration Number')
                                    ->placeholder('Not provided')
                                    ->copyable(),
                            ]),
                    ]),

                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('contact_person')
                                    ->label('Contact Person')
                                    ->icon('heroicon-o-user')
                                    ->placeholder('Not provided'),
                                TextEntry::make('email')
                                    ->icon('heroicon-o-envelope')
                                    ->placeholder('Not provided')
                                    ->copyable(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('phone')
                                    ->icon('heroicon-o-phone')
                                    ->placeholder('Not provided')
                                    ->copyable(),
                                TextEntry::make('website')
                                    ->icon('heroicon-o-globe-alt')
                                    ->placeholder('Not provided')
                                    ->url(fn (?string $state): ?string => $state ? (str_starts_with($state, 'http') ? $state : "https://{$state}") : null)
                                    ->openUrlInNewTab(),
                            ]),

                        TextEntry::make('address')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('Not provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Training Statistics')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('training_count')
                                    ->label('Total Trainings')
                                    ->getStateUsing(fn ($record) => $record->training_count)
                                    ->badge()
                                    ->color('primary'),

                                TextEntry::make('active_training_count')
                                    ->label('Active Trainings')
                                    ->getStateUsing(fn ($record) => $record->active_training_count)
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('completed_trainings')
                                    ->label('Completed Trainings')
                                    ->getStateUsing(fn ($record) => $record->trainings()->where('status', 'completed')->count())
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('participants_trained')
                                    ->label('Total Participants')
                                    ->getStateUsing(fn ($record) => 
                                        $record->trainings()->withCount('participants')->get()->sum('participants_count')
                                    )
                                    ->badge()
                                    ->color('warning'),
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