<?php

namespace App\Filament\Widgets;

use App\Models\Facility;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\StockRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HubFacilityStatsWidget extends BaseWidget
{
    public ?Facility $facility = null;

    public function mount(?Facility $facility = null): void
    {
        $this->facility = $facility ?? request()->route('record');

        if (is_string($this->facility)) {
            $this->facility = Facility::find($this->facility);
        }
    }

    protected function getStats(): array
    {
        if (!$this->facility || !$this->facility->is_hub) {
            return [];
        }

        $spokeIds = $this->facility->spokes()->pluck('id');

        return [
            Stat::make('Spoke Facilities',
                $this->facility->spokes()->count()
            )
                ->description('Connected facilities')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('warning')
                ->url(route('filament.admin.resources.facilities.index', [
                    'tableFilters[hub][value]' => $this->facility->id
                ])),

            Stat::make('Hub Stock Value',
                'KES ' . number_format(
                    StockLevel::where('facility_id', $this->facility->id)
                        ->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
                        ->selectRaw('SUM(stock_levels.current_stock * inventory_items.unit_price)')
                        ->value('SUM(stock_levels.current_stock * inventory_items.unit_price)') ?? 0,
                    2
                )
            )
                ->description('Hub inventory value')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Active Transfers',
                StockTransfer::where('from_facility_id', $this->facility->id)
                    ->orWhereIn('to_facility_id', $spokeIds)
                    ->whereIn('status', ['pending', 'approved', 'in_transit'])
                    ->count()
            )
                ->description('Ongoing transfers')
                ->descriptionIcon('heroicon-m-arrow-right-circle')
                ->color('info'),

            Stat::make('Spoke Requests',
                StockRequest::whereIn('requesting_facility_id', $spokeIds)
                    ->where('status', 'pending')
                    ->count()
            )
                ->description('Pending from spokes')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color('primary'),

            Stat::make('Coordination Events',
                StockTransfer::where('from_facility_id', $this->facility->id)
                    ->orWhereIn('from_facility_id', $spokeIds)
                    ->orWhereIn('to_facility_id', $spokeIds)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count()
            )
                ->description('Last 30 days')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('gray'),

            Stat::make('Coverage Area',
                $spokeIds->count() > 0 ?
                    $this->facility->subcounty->name . ' Network' : 'No Coverage'
            )
                ->description('Service area')
                ->descriptionIcon('heroicon-m-map')
                ->color('info'),
        ];
    }
}
