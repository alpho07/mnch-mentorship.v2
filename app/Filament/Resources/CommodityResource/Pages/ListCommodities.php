<?php

namespace App\Filament\Resources\CommodityResource\Pages;

use App\Filament\Resources\CommodityResource;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCommodities extends ListRecords {

    protected static string $resource = CommodityResource::class;

    protected function getHeaderActions(): array {
        return [
                    Actions\CreateAction::make()
                    ->label('Add Commodity')
                    ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array {
        $tabs = [
            'all' => Tab::make('All')
                    ->badge(Commodity::count()),
            'active' => Tab::make('Active')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('is_active', true))
                    ->badge(Commodity::where('is_active', true)->count())
                    ->badgeColor('success'),
            'inactive' => Tab::make('Inactive')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('is_active', false))
                    ->badge(Commodity::where('is_active', false)->count())
                    ->badgeColor('warning'),
            'unassigned' => Tab::make('No Departments')
                    ->modifyQueryUsing(fn(Builder $query) => $query->whereDoesntHave('applicableDepartments'))
                    ->badge(Commodity::whereDoesntHave('applicableDepartments')->count())
                    ->badgeColor('danger'),
        ];

        // One tab per category
        $categories = CommodityCategory::orderBy('order')
                ->withCount('commodities')
                ->get();

        foreach ($categories as $cat) {
            $tabs['cat_' . $cat->id] = Tab::make($cat->name)
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('commodity_category_id', $cat->id))
                    ->badge($cat->commodities_count)
                    ->badgeColor('primary');
        }

        return $tabs;
    }
}
