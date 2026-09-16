<?php

namespace App\Filament\Widgets;

use App\Models\Facility;
use App\Models\StockLevel;
use App\Models\StockRequest;
use App\Models\InventoryItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CentralStoreStatsWidget extends BaseWidget
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
        if (!$this->facility || !$this->facility->is_central_store) {
            return [];
        }

        $stockLevels = StockLevel::where('facility_id', $this->facility->id)->with('inventoryItem');

        return [
            Stat::make('Total Stock Value',
                'KES ' . number_format(
                    $stockLevels->get()->sum(fn($sl) => $sl->current_stock * $sl->inventoryItem->unit_price),
                    2
                )
            )
                ->description('Total inventory value')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]), // Sample trending data

            Stat::make('Items in Stock',
                $stockLevels->where('current_stock', '>', 0)->distinct('inventory_item_id')->count()
            )
                ->description('Unique items available')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Pending Requests',
                StockRequest::where('central_store_id', $this->facility->id)
                    ->where('status', 'pending')
                    ->count()
            )
                ->description('Awaiting approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Ready to Dispatch',
                StockRequest::where('central_store_id', $this->facility->id)
                    ->whereIn('status', ['approved', 'partially_approved'])
                    ->count()
            )
                ->description('Approved for dispatch')
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary'),

            Stat::make('Low Stock Items',
                $stockLevels->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
                    ->whereColumn('stock_levels.current_stock', '<=', 'inventory_items.reorder_point')
                    ->count()
            )
                ->description('Below reorder point')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Facilities Served',
                $this->facility->getDistributionFacilities()->count()
            )
                ->description('Distribution coverage')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('success'),
        ];
    }
}
