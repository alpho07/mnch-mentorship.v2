<?php
// Facility Stock Overview Widget (for non-central stores)
namespace App\Filament\Widgets;

use App\Models\StockLevel;
use App\Models\Facility;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FacilityStockOverviewWidget extends BaseWidget
{
    protected static ?string $heading = 'Facility Stock Overview';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $userFacility = auth()->user()->facility;
        $facilityIds = $userFacility && !$userFacility->is_central_store 
            ? [$userFacility->id] 
            : Facility::where('is_central_store', false)->pluck('id');

        return $table
            ->query(
                StockLevel::whereIn('facility_id', $facilityIds)
                    ->where('current_stock', '>', 0)
                    ->with(['facility', 'inventoryItem.category'])
                    ->orderBy('current_stock', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->searchable(),
                Tables\Columns\TextColumn::make('inventoryItem.category.name')
                    ->label('Category')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock')
                    ->badge()
                    ->color(fn ($record) => $record->is_low_stock ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('inventoryItem.reorder_point')
                    ->label('Reorder Point'),
                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Value')
                    ->money('KES'),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->date()
                    ->color(fn ($record) => $record->is_expired ? 'danger' : 
                            ($record->is_expiring_soon ? 'warning' : null))
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('request_more')
                    ->label('Request More')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->visible(fn ($record) => $record->is_low_stock)
                    ->url(fn ($record) => route('filament.admin.resources.stock-requests.create', [
                        'requesting_facility_id' => $record->facility_id,
                        'item_id' => $record->inventory_item_id,
                    ])),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('facility')
                    ->options(Facility::where('is_central_store', false)->pluck('name', 'id')),
                Tables\Filters\Filter::make('low_stock')
                    ->query(fn ($query) => $query->whereHas('inventoryItem', function ($q) {
                        $q->whereColumn('stock_levels.current_stock', '<=', 'inventory_items.reorder_point');
                    })),
            ]);
    }
}