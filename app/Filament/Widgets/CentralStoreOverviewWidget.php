<?php

// Central Store Dashboard Widget
namespace App\Filament\Widgets;

use App\Models\Facility;
use App\Models\StockLevel;
use App\Models\StockRequest;
use App\Models\InventoryItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CentralStoreOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $centralStores = Facility::centralStores()->get();
        $userFacility = auth()->user()->facility;
        
        // If user is at a central store, show that store's stats
        // Otherwise show system-wide central store stats
        $targetStores = $userFacility && $userFacility->is_central_store 
            ? collect([$userFacility]) 
            : $centralStores;

        $centralStoreIds = $targetStores->pluck('id');

        return [
            Stat::make('Central Store Stock Value', 
                'KES ' . number_format(
                    StockLevel::whereIn('facility_id', $centralStoreIds)
                        ->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
                        ->selectRaw('SUM(stock_levels.current_stock * inventory_items.unit_price)')
                        ->value('SUM(stock_levels.current_stock * inventory_items.unit_price)') ?? 0,
                    2
                )
            )
                ->description('Total value at central stores')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Items in Central Stores', 
                StockLevel::whereIn('facility_id', $centralStoreIds)
                    ->where('current_stock', '>', 0)
                    ->distinct('inventory_item_id')
                    ->count()
            )
                ->description('Unique items in stock')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Pending Facility Requests', 
                StockRequest::whereIn('central_store_id', $centralStoreIds)
                    ->where('status', 'pending')
                    ->count()
            )
                ->description('Awaiting approval from central stores')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Ready to Dispatch', 
                StockRequest::whereIn('central_store_id', $centralStoreIds)
                    ->whereIn('status', ['approved', 'partially_approved'])
                    ->count()
            )
                ->description('Approved requests ready for dispatch')
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary'),

            Stat::make('Low Stock at Central Store', 
                StockLevel::whereIn('facility_id', $centralStoreIds)
                    ->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
                    ->whereColumn('stock_levels.current_stock', '<=', 'inventory_items.reorder_point')
                    ->count()
            )
                ->description('Items below reorder point')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Items Out of Stock', 
                InventoryItem::whereDoesntHave('stockLevels', function ($query) use ($centralStoreIds) {
                    $query->whereIn('facility_id', $centralStoreIds)
                          ->where('current_stock', '>', 0);
                })->count()
            )
                ->description('Items with zero central store stock')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}

