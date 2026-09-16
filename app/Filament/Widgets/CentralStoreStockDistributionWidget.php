<?php

namespace App\Filament\Widgets;

use App\Models\Facility;
use App\Models\StockLevel;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CentralStoreStockDistributionWidget extends BaseWidget
{
    protected static ?string $heading = 'Current Stock Distribution';
    protected int|string|array $columnSpan = 'full';

    public ?Facility $facility = null;

    public function mount(?Facility $facility = null): void
    {
        $this->facility = $facility ?? request()->route('record');

        if (is_string($this->facility)) {
            $this->facility = Facility::find($this->facility);
        }
    }

    public function table(Table $table): Table
    {
        if (!$this->facility || !$this->facility->is_central_store) {
            return $table->query(StockLevel::whereRaw('1 = 0'));
        }

        return $table
            ->query(
                StockLevel::where('facility_id', $this->facility->id)
                    ->where('current_stock', '>', 0)
                    ->with(['inventoryItem.category'])
                    ->orderBy('current_stock', 'desc')
                    ->limit(10) // Show top 10 items by stock quantity
            )
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.category.name')
                    ->label('Category')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock')
                    ->badge()
                    ->color(fn ($record) => $record->is_low_stock ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Available')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('reserved_stock')
                    ->label('Reserved')
                    ->badge()
                    ->color('warning')
                    ->visible(fn ($record) => $record->reserved_stock > 0),
                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Value')
                    ->money('KES')
                    ->getStateUsing(fn ($record) => $record->current_stock * $record->inventoryItem->unit_price),
                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch')
                    ->placeholder('N/A')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->date()
                    ->color(fn ($record) => $record->is_expired ? 'danger' :
                            ($record->is_expiring_soon ? 'warning' : null))
                    ->placeholder('N/A')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('distribute')
                    ->label('Distribute')
                    ->icon('heroicon-o-share')
                    ->color('primary')
                    ->url(fn ($record) => route('filament.admin.resources.stock-requests.create', [
                        'central_store_id' => $this->facility->id,
                        'item_id' => $record->inventory_item_id,
                    ])),
                Tables\Actions\Action::make('adjust_stock')
                    ->label('Adjust')
                    ->icon('heroicon-o-plus-minus')
                    ->color('warning')
                    ->url(fn ($record) => route('filament.admin.resources.stock-levels.edit', [
                        'record' => $record->id,
                    ])),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('inventoryItem.category', 'name'),
                Tables\Filters\Filter::make('low_stock')
                    ->query(fn ($query) => $query->whereHas('inventoryItem', function ($q) {
                        $q->whereColumn('stock_levels.current_stock', '<=', 'inventory_items.reorder_point');
                    })),
                Tables\Filters\Filter::make('expiring_soon')
                    ->query(fn ($query) => $query->where('expiry_date', '<=', now()->addDays(30))
                                                  ->where('expiry_date', '>', now())),
            ])
            ->emptyStateHeading('No Stock Available')
            ->emptyStateDescription('This central store currently has no items in stock.')
            ->emptyStateActions([
                Tables\Actions\Action::make('add_stock')
                    ->label('Add Stock')
                    ->url(route('filament.admin.resources.stock-levels.create', [
                        'facility_id' => $this->facility->id
                    ]))
                    ->icon('heroicon-m-plus'),
            ]);
    }
}
