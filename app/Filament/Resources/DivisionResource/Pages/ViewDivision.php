<?php

// ========== DIVISION VIEW PAGES ==========

// File: App\Filament\Resources\DivisionResource\Pages\ViewDivision.php
namespace App\Filament\Resources\DivisionResource\Pages;

use App\Filament\Resources\DivisionResource;
use App\Models\Division;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewDivision extends ViewRecord
{
    protected static string $resource = DivisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_map')
                ->label('View Geographic Map')
                ->icon('heroicon-o-map')
                ->color('info')
                ->url('#') // Would link to a map view
                ->openUrlInNewTab(),
            
            Actions\Action::make('export_data')
                ->label('Export Division Data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): void {
                    // Export logic would go here
                    $this->notify('success', 'Division data export initiated');
                }),
            
            Actions\EditAction::make(),
            
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('This will delete the division and all its counties, subcounties, and facilities. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete division'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Division Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Division Name')
                            ->size('xl')
                            ->weight('bold')
                            ->color('primary'),
                        
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->prose()
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->headerActions([
                        Infolists\Components\Actions\Action::make('edit')
                            ->label('Edit Division')
                            ->icon('heroicon-o-pencil')
                            ->color('primary')
                            ->url(fn (Division $record): string => 
                                route('filament.admin.resources.divisions.edit', $record)
                            )
                            ->size('sm'),
                    ]),
                
                Infolists\Components\Section::make('Geographic Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('county_count')
                                    ->label('Counties')
                                    ->badge()
                                    ->size('lg')
                                    ->color('primary')
                                    ->icon('heroicon-o-building-office'),
                                
                                Infolists\Components\TextEntry::make('facility_count')
                                    ->label('Total Facilities')
                                    ->badge()
                                    ->size('lg')
                                    ->color('success')
                                    ->icon('heroicon-o-building-office-2'),
                                
                                Infolists\Components\TextEntry::make('hub_facilities_count')
                                    ->label('Hub Facilities')
                                    ->badge()
                                    ->size('lg')
                                    ->color('warning')
                                    ->icon('heroicon-o-star')
                                    ->getStateUsing(function (Division $record): int {
                                        return $record->counties()
                                            ->withCount(['subcounties as hub_facilities_count' => function ($query) {
                                                $query->whereHas('facilities', function ($q) {
                                                    $q->where('is_hub', true);
                                                });
                                            }])
                                            ->get()
                                            ->sum('hub_facilities_count');
                                    }),
                                
                                Infolists\Components\TextEntry::make('training_count')
                                    ->label('Total Trainings')
                                    ->badge()
                                    ->size('lg')
                                    ->color('info')
                                    ->icon('heroicon-o-academic-cap')
                                    ->getStateUsing(function (Division $record): int {
                                        return \App\Models\Training::whereHas('facility.subcounty.county', function ($query) use ($record) {
                                            $query->where('division_id', $record->id);
                                        })->count();
                                    }),
                            ]),
                    ])
                    ->columns(1),
                
                /*Infolists\Components\Section::make('Recent Activity')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('recent_counties')
                            ->label('Recently Added Counties')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->since(),
                            ])
                            ->getStateUsing(function (Division $record) {
                                return $record->counties()
                                    ->latest()
                                    ->limit(5)
                                    ->get()
                                    ->toArray();
                            })
                            ->columns(2)
                            ->placeholder('No recent counties added'),
                        
                        Infolists\Components\RepeatableEntry::make('recent_trainings')
                            ->label('Recent Trainings')
                            ->schema([
                                Infolists\Components\TextEntry::make('title')
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('facility.name')
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('start_date')
                                    ->date(),
                            ])
                            ->getStateUsing(function (Division $record) {
                                return \App\Models\Training::whereHas('facility.subcounty.county', function ($query) use ($record) {
                                    $query->where('division_id', $record->id);
                                })
                                ->with(['facility'])
                                ->latest()
                                ->limit(5)
                                ->get()
                                ->toArray();
                            })
                            ->columns(3)
                            ->placeholder('No recent trainings'),
                    ])
                    ->columns(1)
                    ->collapsible(),
                
                Infolists\Components\Section::make('System Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->icon('heroicon-o-calendar'),
                        
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime()
                            ->icon('heroicon-o-clock'),
                        
                        Infolists\Components\TextEntry::make('id')
                            ->label('System ID')
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),*/
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
            DivisionResource\RelationManagers\CountiesRelationManager::class,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Could add widgets here for charts/statistics
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Could add footer widgets
        ];
    }
}