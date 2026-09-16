<?php

namespace App\Filament\Widgets;

use App\Models\Facility;
use App\Models\StockLevel;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FacilityStockStatsWidget extends BaseWidget
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
        if (!$this->facility) {
            return [];
        }

        $stockLevels = StockLevel::where('facility_id', $this->facility->id);

        return [
            Stat::make('Current Stock Items',
                $stockLevels->where('current_stock', '>', 0)->count()
            )
                ->description('Items in stock')
                ->descriptionIcon('heroicon-m-cube')
                ->color('success')
                ->url(route('filament.admin.resources.stock-levels.index', [
                    'tableFilters[facility][value]' => $this->facility->id
                ])),

            Stat::make('Stock Value',
                'KES ' . number_format(
                    $stockLevels->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
                        ->selectRaw('SUM(stock_levels.current_stock * inventory_items.unit_price)')
                        ->value('SUM(stock_levels.current_stock * inventory_items.unit_price)') ?? 0,
                    2
                )
            )
                ->description('Total inventory value')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Low Stock Items',
                $stockLevels->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
                    ->whereColumn('stock_levels.current_stock', '<=', 'inventory_items.reorder_point')
                    ->count()
            )
                ->description('Items below reorder point')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make('Out of Stock',
                $stockLevels->where('current_stock', '<=', 0)->count()
            )
                ->description('Items with zero stock')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Pending Requests',
                StockRequest::where('requesting_facility_id', $this->facility->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->count()
            )
                ->description('Active stock requests')
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary')
                ->url(route('filament.admin.resources.stock-requests.index', [
                    'tableFilters[requesting_facility][value]' => $this->facility->id
                ])),

            Stat::make('Recent Transfers',
                StockTransfer::where(function($query) {
                        $query->where('from_facility_id', $this->facility->id)
                              ->orWhere('to_facility_id', $this->facility->id);
                    })
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count()
            )
                ->description('Last 30 days')
                ->descriptionIcon('heroicon-m-arrow-right-circle')
                ->color('success'),
        ];
    }
}
