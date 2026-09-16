<?php

// ========== COUNTY VIEW PAGES ==========

// File: App\Filament\Resources\CountyResource\Pages\ViewCounty.php
namespace App\Filament\Resources\CountyResource\Pages;

use App\Filament\Resources\CountyResource;
use App\Models\County;
use App\Models\Training;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewCounty extends ViewRecord
{
    protected static string $resource = CountyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_subcounty')
                ->label('Add Subcounty')
                ->icon('heroicon-o-building-office')
                ->color('success')
                ->url(fn (): string => route('filament.admin.resources.subcounties.create', [
                    'county_id' => $this->record->id
                ])),
            
            Actions\Action::make('view_map')
                ->label('View County Map')
                ->icon('heroicon-o-map')
                ->color('info')
                ->url('#') // Would link to a map view
                ->openUrlInNewTab(),
            
            Actions\Action::make('manage_users')
                ->label('Manage County Access')
                ->icon('heroicon-o-users')
                ->color('warning')
                ->modalHeading('Users with County Access')
                ->modalContent(fn (): string => view('filament.modals.county-users', [
                    'county' => $this->record,
                    'users' => $this->record->users()->with(['facility', 'department', 'roles'])->get()
                ])->render())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalWidth('5xl'),
            
            Actions\Action::make('training_report')
                ->label('Training Report')
                ->icon('heroicon-o-document-chart-bar')
                ->color('primary')
                ->action(function (): void {
                    // Generate training report logic
                    $this->notify('success', 'Training report generated for ' . $this->record->name);
                }),
            
            Actions\Action::make('export_county_data')
                ->label('Export County Data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): void {
                    // Export logic would go here
                    $this->notify('success', 'County data export initiated');
                }),
            
            Actions\EditAction::make(),
            
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('This will delete the county and all its subcounties and facilities. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete county'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('County Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('division.name')
                            ->label('Division')
                            ->badge()
                            ->size('lg')
                            ->color('primary')
                            ->icon('heroicon-o-map'),
                        
                        Infolists\Components\TextEntry::make('name')
                            ->label('County Name')
                            ->size('xl')
                            ->weight('bold')
                            ->color('success'),
                        
                        Infolists\Components\TextEntry::make('uid')
                            ->label('Unique Identifier')
                            ->badge()
                            ->color('gray')
                            ->icon('heroicon-o-identification'),
                        
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->prose()
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->headerActions([
                        Infolists\Components\Actions\Action::make('edit')
                            ->label('Edit County')
                            ->icon('heroicon-o-pencil')
                            ->color('primary')
                            ->url(fn (County $record): string => 
                                route('filament.admin.resources.counties.edit', $record)
                            )
                            ->size('sm'),
                    ]),
                
                Infolists\Components\Section::make('Geographic Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('subcounty_count')
                                    ->label('Subcounties')
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
                                    ->getStateUsing(function (County $record): int {
                                        return $record->facilities()->where('is_hub', true)->count();
                                    }),
                                
                                Infolists\Components\TextEntry::make('spoke_facilities_count')
                                    ->label('Spoke Facilities')
                                    ->badge()
                                    ->size('lg')
                                    ->color('info')
                                    ->icon('heroicon-o-arrow-path')
                                    ->getStateUsing(function (County $record): int {
                                        return $record->facilities()->where('is_hub', false)->whereNotNull('hub_id')->count();
                                    }),
                            ]),
                    ])
                    ->columns(1),
                
                Infolists\Components\Section::make('Training & User Activity')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_trainings')
                                    ->label('Total Trainings')
                                    ->badge()
                                    ->size('lg')
                                    ->color('primary')
                                    ->icon('heroicon-o-academic-cap')
                                    ->getStateUsing(function (County $record): int {
                                        return Training::whereHas('facility.subcounty', function ($query) use ($record) {
                                            $query->where('county_id', $record->id);
                                        })->count();
                                    }),
                                
                                Infolists\Components\TextEntry::make('active_trainings')
                                    ->label('Active Trainings')
                                    ->badge()
                                    ->size('lg')
                                    ->color('success')
                                    ->icon('heroicon-o-play-circle')
                                    ->getStateUsing(function (County $record): int {
                                        return Training::whereHas('facility.subcounty', function ($query) use ($record) {
                                            $query->where('county_id', $record->id);
                                        })->ongoing()->count();
                                    }),
                                
                                Infolists\Components\TextEntry::make('county_users')
                                    ->label('Users with Access')
                                    ->badge()
                                    ->size('lg')
                                    ->color('info')
                                    ->icon('heroicon-o-users')
                                    ->getStateUsing(function (County $record): int {
                                        return $record->users()->count();
                                    }),
                                
                                Infolists\Components\TextEntry::make('facility_staff')
                                    ->label('Facility Staff')
                                    ->badge()
                                    ->size('lg')
                                    ->color('warning')
                                    ->icon('heroicon-o-user-group')
                                    ->getStateUsing(function (County $record): int {
                                        return User::whereHas('facility.subcounty', function ($query) use ($record) {
                                            $query->where('county_id', $record->id);
                                        })->count();
                                    }),
                            ]),
                    ])
                    ->columns(1),
                
                /*Infolists\Components\Section::make('Subcounty Breakdown')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('subcounties_with_stats')
                            ->label('Subcounties Overview')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Subcounty')
                                    ->weight('bold'),
                                
                                Infolists\Components\TextEntry::make('uid')
                                    ->label('UID')
                                    ->badge()
                                    ->color('gray'),
                                
                                Infolists\Components\TextEntry::make('facility_count')
                                    ->label('Facilities')
                                    ->badge()
                                    ->color('success'),
                                
                                Infolists\Components\TextEntry::make('hub_facilities_count')
                                    ->label('Hub Facilities')
                                    ->badge()
                                    ->color('warning'),
                                
                                Infolists\Components\TextEntry::make('training_count')
                                    ->label('Trainings')
                                    ->badge()
                                    ->color('primary'),
                            ])
                            ->getStateUsing(function (County $record) {
                                return $record->subcounties()
                                    ->withCount([
                                        'facilities',
                                        'facilities as hub_facilities_count' => function ($query) {
                                            $query->where('is_hub', true);
                                        }
                                    ])
                                    ->get()
                                    ->map(function ($subcounty) {
                                        $trainingCount = Training::whereHas('facility', function ($query) use ($subcounty) {
                                            $query->where('subcounty_id', $subcounty->id);
                                        })->count();
                                        
                                        return [
                                            'name' => $subcounty->name,
                                            'uid' => $subcounty->uid,
                                            'facility_count' => $subcounty->facilities_count,
                                            'hub_facilities_count' => $subcounty->hub_facilities_count,
                                            'training_count' => $trainingCount,
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->columns(5)
                            ->placeholder('No subcounties in this county'),
                    ])
                    ->columns(1)
                    ->collapsible(),
                
                /*Infolists\Components\Section::make('Recent Training Activity')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('recent_trainings')
                            ->label('Recent Trainings in County')
                            ->schema([
                                Infolists\Components\TextEntry::make('title')
                                    ->weight('bold'),
                                
                                Infolists\Components\TextEntry::make('facility_name')
                                    ->label('Facility')
                                    ->badge()
                                    ->color('info'),
                                
                                Infolists\Components\TextEntry::make('subcounty_name')
                                    ->label('Subcounty')
                                    ->badge()
                                    ->color('success'),
                                
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'upcoming' => 'warning',
                                        'ongoing' => 'success',
                                        'completed' => 'gray',
                                        default => 'gray',
                                    }),
                                
                                Infolists\Components\TextEntry::make('start_date')
                                    ->date(),
                                
                                Infolists\Components\TextEntry::make('participant_count')
                                    ->label('Participants')
                                    ->badge()
                                    ->color('primary'),
                            ])
                            ->getStateUsing(function (County $record) {
                                return Training::whereHas('facility.subcounty', function ($query) use ($record) {
                                    $query->where('county_id', $record->id);
                                })
                                ->with(['facility.subcounty', 'participants'])
                                ->latest()
                                ->limit(10)
                                ->get()
                                ->map(function ($training) {
                                    return [
                                        'title' => $training->title,
                                        'facility_name' => $training->facility->name,
                                        'subcounty_name' => $training->facility->subcounty->name,
                                        'status' => $training->status,
                                        'start_date' => $training->start_date,
                                        'participant_count' => $training->participants->count(),
                                    ];
                                })
                                ->toArray();
                            })
                            ->columns(6)
                            ->placeholder('No recent training activity in this county'),
                    ])
                    ->columns(1)
                    ->collapsible(),*/
                
                Infolists\Components\Section::make('Performance Metrics')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('monthly_trainings')
                                    ->label('Trainings This Month')
                                    ->badge()
                                    ->size('lg')
                                    ->color('info')
                                    ->getStateUsing(function (County $record): int {
                                        return Training::whereHas('facility.subcounty', function ($query) use ($record) {
                                            $query->where('county_id', $record->id);
                                        })
                                        ->whereMonth('start_date', now()->month)
                                        ->whereYear('start_date', now()->year)
                                        ->count();
                                    }),
                                
                                Infolists\Components\TextEntry::make('completion_rate')
                                    ->label('Training Completion Rate')
                                    ->badge()
                                    ->size('lg')
                                    ->color('success')
                                    ->getStateUsing(function (County $record): string {
                                        $totalTrainings = Training::whereHas('facility.subcounty', function ($query) use ($record) {
                                            $query->where('county_id', $record->id);
                                        })->count();
                                        
                                        $completedTrainings = Training::whereHas('facility.subcounty', function ($query) use ($record) {
                                            $query->where('county_id', $record->id);
                                        })->completed()->count();
                                        
                                        if ($totalTrainings === 0) return '0%';
                                        
                                        $rate = ($completedTrainings / $totalTrainings) * 100;
                                        return number_format($rate, 1) . '%';
                                    }),
                                
                                Infolists\Components\TextEntry::make('avg_participants')
                                    ->label('Avg Participants/Training')
                                    ->badge()
                                    ->size('lg')
                                    ->color('warning')
                                    ->getStateUsing(function (County $record): string {
                                        $avgParticipants = Training::whereHas('facility.subcounty', function ($query) use ($record) {
                                            $query->where('county_id', $record->id);
                                        })
                                        ->withCount('participants')
                                        ->get()
                                        ->avg('participants_count');
                                        
                                        return number_format($avgParticipants ?: 0, 1);
                                    }),
                            ]),
                    ])
                    ->columns(1)
                    ->collapsible(),
                
                /*Infolists\Components\Section::make('System Information')
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
            CountyResource\RelationManagers\SubcountiesRelationManager::class,
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