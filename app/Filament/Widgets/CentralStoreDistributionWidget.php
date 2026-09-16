<?php
// Central Store Stock Distribution Widget
namespace App\Filament\Widgets;

use App\Models\StockLevel;
use App\Models\Facility;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CentralStoreDistributionWidget extends BaseWidget
{
    protected static ?string $heading = 'Central Store Stock Distribution';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $centralStoreIds = Facility::centralStores()->pluck('id');

        return $table
            ->query(
                StockLevel::whereIn('facility_id', $centralStoreIds)
                    ->where('current_stock', '>', 0)
                    ->with(['facility', 'inventoryItem.category'])
                    ->orderBy('current_stock', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Central Store')
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
                    ->label('Total Stock')
                    ->badge()
                    ->color(fn ($record) => $record->is_low_stock ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Available')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('reserved_stock')
                    ->label('Reserved')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Value')
                    ->money('KES'),
                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->date()
                    ->color(fn ($record) => $record->is_expired ? 'danger' : 
                            ($record->is_expiring_soon ? 'warning' : null))
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('distribute')
                    ->label('Create Distribution')
                    ->icon('heroicon-o-share')
                    ->color('primary')
                    ->url(fn ($record) => route('filament.admin.resources.stock-requests.create', [
                        'central_store_id' => $record->facility_id,
                        'item_id' => $record->inventory_item_id,
                    ])),
                Tables\Actions\Action::make('transfer')
                    ->label('Transfer Stock')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.stock-transfers.create', [
                        'from_facility_id' => $record->facility_id,
                        'item_id' => $record->inventory_item_id,
                    ])),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('facility')
                    ->label('Central Store')
                    ->options(Facility::centralStores()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('inventoryItem.category', 'name'),
                Tables\Filters\Filter::make('low_stock')
                    ->query(fn ($query) => $query->whereHas('inventoryItem', function ($q) {
                        $q->whereColumn('stock_levels.current_stock', '<=', 'inventory_items.reorder_point');
                    })),
                Tables\Filters\Filter::make('expiring_soon')
                    ->query(fn ($query) => $query->where('expiry_date', '<=', now()->addDays(30))
                                                  ->where('expiry_date', '>', now())),
            ]);
    }
}

