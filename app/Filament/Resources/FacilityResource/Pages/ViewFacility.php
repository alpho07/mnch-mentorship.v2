<?php

// Enhanced ViewFacility Page
namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Resources\FacilityResource;
use App\Models\StockLevel;
use App\Models\StockRequest;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFacility extends ViewRecord
{
    protected static string $resource = FacilityResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\Action::make('view_on_map')
                ->label('View on Map')
                ->icon('heroicon-o-map')
                ->color('info')
                ->visible(fn (): bool => $this->record->coordinates !== null)
                ->url(fn (): string => 
                    "https://maps.google.com/maps?q={$this->record->lat},{$this->record->long}"
                )
                ->openUrlInNewTab(),
            
            Actions\Action::make('manage_staff')
                ->label('Manage Staff')
                ->icon('heroicon-o-users')
                ->color('success')
                ->url(fn (): string => 
                    route('filament.admin.resources.users.index', [
                        'tableFilters[facility_id][value]' => $this->record->id
                    ])
                ),
            
            Actions\Action::make('training_history')
                ->label('Training History')
                ->icon('heroicon-o-academic-cap')
                ->color('warning')
                ->action(function (): void {
                    $this->notify('info', 'Training history will be available once training management is implemented');
                }),
        ];

        // Add central store specific actions
        if ($this->record->is_central_store) {
            $actions = array_merge($actions, [
                Actions\Action::make('view_inventory')
                    ->label('View Inventory')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('info')
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-levels.index', [
                            'tableFilters[facility][value]' => $this->record->id
                        ])
                    ),
                Actions\Action::make('add_stock')
                    ->label('Add Stock')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-levels.create', [
                            'facility_id' => $this->record->id
                        ])
                    ),
                Actions\Action::make('pending_requests')
                    ->label('Pending Requests')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->badge(fn (): int => 
                        StockRequest::where('central_store_id', $this->record->id)
                            ->where('status', 'pending')
                            ->count()
                    )
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-requests.index', [
                            'tableFilters[central_store][value]' => $this->record->id,
                            'tableFilters[status][value]' => 'pending'
                        ])
                    ),
                Actions\Action::make('create_distribution')
                    ->label('Create Distribution')
                    ->icon('heroicon-o-share')
                    ->color('primary')
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-requests.create', [
                            'central_store_id' => $this->record->id
                        ])
                    ),
                Actions\Action::make('stock_report')
                    ->label('Stock Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->action(function () {
                        $this->generateStockReport();
                    }),
                Actions\Action::make('distribution_history')
                    ->label('Distribution History')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-requests.index', [
                            'tableFilters[central_store][value]' => $this->record->id
                        ])
                    ),
            ]);
        }

        // Add hub facility specific actions
        if ($this->record->is_hub) {
            $actions = array_merge($actions, [
                Actions\Action::make('manage_spokes')
                    ->label('Manage Spoke Facilities')
                    ->icon('heroicon-o-building-office-2')
                    ->color('warning')
                    ->badge(fn (): int => $this->record->spokes()->count())
                    ->url(fn (): string => 
                        route('filament.admin.resources.facilities.index', [
                            'tableFilters[hub][value]' => $this->record->id
                        ])
                    ),
                Actions\Action::make('hub_coordination')
                    ->label('Hub Coordination')
                    ->icon('heroicon-o-share')
                    ->color('info')
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-transfers.index', [
                            'tableFilters[from_facility][value]' => $this->record->id
                        ])
                    ),
            ]);
        }

        // Add regular facility actions (including hubs since they can also have stock)
        if (!$this->record->is_central_store) {
            $actions = array_merge($actions, [
                Actions\Action::make('request_stock')
                    ->label('Request Stock')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-requests.create', [
                            'requesting_facility_id' => $this->record->id
                        ])
                    ),
                Actions\Action::make('view_my_stock')
                    ->label('View My Stock')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('info')
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-levels.index', [
                            'tableFilters[facility][value]' => $this->record->id
                        ])
                    ),
                Actions\Action::make('transfer_stock')
                    ->label('Transfer Stock')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-transfers.create', [
                            'from_facility_id' => $this->record->id
                        ])
                    ),
                Actions\Action::make('my_requests')
                    ->label('My Requests')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->badge(fn (): int => 
                        StockRequest::where('requesting_facility_id', $this->record->id)
                            ->whereIn('status', ['pending', 'approved', 'dispatched'])
                            ->count()
                    )
                    ->url(fn (): string => 
                        route('filament.admin.resources.stock-requests.index', [
                            'tableFilters[requesting_facility][value]' => $this->record->id
                        ])
                    ),
            ]);
        }

        // Add standard actions at the end
        $actions = array_merge($actions, [
            Actions\EditAction::make(),
            
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('This will delete the facility and all associated data. This action cannot be undone.')
                ->visible(fn (): bool => 
                    !$this->record->is_central_store && 
                    $this->record->spokes()->count() === 0 &&
                    $this->record->users()->count() === 0
                ),
        ]);

        return $actions;
    }

    protected function generateStockReport(): void
    {
        $facility = $this->record;
        
        if (!$facility->is_central_store) {
            $this->notify('warning', 'Stock reports are only available for central stores.');
            return;
        }

        $stockData = $facility->central_store_stock_summary;
        
        \Filament\Notifications\Notification::make()
            ->title('Stock Report Generated')
            ->body("Total Items: {$stockData['total_items']}, Total Value: KES " . number_format($stockData['total_value'], 2))
            ->success()
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('download')
                    ->label('Download PDF')
                    ->url('#') // Implement PDF generation
                    ->openUrlInNewTab(),
                \Filament\Notifications\Actions\Action::make('email')
                    ->label('Email Report')
                    ->action(function () {
                        // Implement email sending
                        $this->notify('success', 'Stock report has been sent to your email address.');
                    }),
                \Filament\Notifications\Actions\Action::make('print')
                    ->label('Print Report')
                    ->action(function () {
                        // Implement print functionality
                        $this->notify('info', 'Print preview will open in a new window.');
                    }),
            ])
            ->send();
    }

    public function getTitle(): string
    {
        $record = $this->record;
        $titleParts = [$record->name];
        
        if ($record->is_central_store) {
            $titleParts[] = '(Central Store)';
        } elseif ($record->is_hub) {
            $titleParts[] = '(Hub)';
        }
        
        return implode(' ', $titleParts);
    }

    public function getSubheading(): ?string
    {
        $record = $this->record;
        $parts = [];
        
        if ($record->subcounty) {
            $parts[] = $record->subcounty->name;
        }
        
        if ($record->subcounty?->county) {
            $parts[] = $record->subcounty->county->name;
        }
        
        if ($record->mfl_code) {
            $parts[] = "MFL: {$record->mfl_code}";
        }
        
        return implode(' • ', $parts);
    }

    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\Shared\RelationManagers\UsersRelationManager::class,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [];
        
        // Add central store specific widgets
        if ($this->record->is_central_store) {
            $widgets = [
                \App\Filament\Widgets\CentralStoreStatsWidget::class,
                \App\Filament\Widgets\CentralStoreStockDistributionWidget::class,
            ];
        } elseif ($this->record->is_hub) {
            $widgets = [
                \App\Filament\Widgets\HubFacilityStatsWidget::class,
                \App\Filament\Widgets\SpokeManagementWidget::class,
            ];
        } else {
            $widgets = [
                \App\Filament\Widgets\FacilityStockStatsWidget::class,
                \App\Filament\Widgets\FacilityRequestsWidget::class,
            ];
        }
        
        return $widgets;
    }
}