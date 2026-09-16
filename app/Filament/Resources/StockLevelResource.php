<?php

// Create StockLevelResource for managing stock quantities

namespace App\Filament\Resources;

use App\Filament\Resources\StockLevelResource\Pages;
use App\Models\StockLevel;
use App\Models\Facility;
use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class StockLevelResource extends Resource {

    protected static ?string $model = StockLevel::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?string $navigationLabel = 'Stock Levels';
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_stock::level');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Stock Location')
                            ->schema([
                                Forms\Components\Select::make('facility_id')
                                ->label('Facility')
                                ->relationship('facility', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                                Forms\Components\Select::make('inventory_item_id')
                                ->label('Inventory Item')
                                ->relationship('inventoryItem', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->getOptionLabelFromRecordUsing(fn($record) => "{$record->sku} - {$record->name}"),
                                Forms\Components\TextInput::make('location')
                                ->label('Storage Location')
                                ->placeholder('e.g., Shelf A1, Room 101, Warehouse Section B')
                                ->maxLength(255),
                            ])->columns(2),
                            Forms\Components\Section::make('Stock Quantities')
                            ->schema([
                                Forms\Components\TextInput::make('current_stock')
                                ->label('Current Stock')
                                ->numeric()
                                ->required()
                                ->default(0)
                                ->minValue(0)
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $reserved = $get('reserved_stock') ?? 0;
                                    $set('available_stock', max(0, $state - $reserved));
                                }),
                                Forms\Components\TextInput::make('reserved_stock')
                                ->label('Reserved Stock')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $current = $get('current_stock') ?? 0;
                                    $set('available_stock', max(0, $current - $state));
                                }),
                                Forms\Components\TextInput::make('available_stock')
                                ->label('Available Stock')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(true),
                            ])->columns(3),
                            Forms\Components\Section::make('Batch & Expiry Information')
                            ->schema([
                                Forms\Components\TextInput::make('batch_number')
                                ->label('Batch Number')
                                ->maxLength(255),
                                Forms\Components\DatePicker::make('expiry_date')
                                ->label('Expiry Date'),
                                Forms\Components\TextInput::make('serial_number')
                                ->label('Serial Number')
                                ->maxLength(255),
                                Forms\Components\Select::make('condition')
                                ->options(InventoryItem::getConditionOptions())
                                ->default('new')
                                ->required(),
                            ])->columns(2),
                            Forms\Components\Section::make('Additional Information')
                            ->schema([
                                Forms\Components\KeyValue::make('metadata')
                                ->label('Additional Data')
                                ->keyLabel('Property')
                                ->valueLabel('Value'),
                                Forms\Components\Textarea::make('notes')
                                ->label('Notes')
                                ->rows(3),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('facility.name')
                            ->label('Facility')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('inventoryItem.sku')
                            ->label('SKU')
                            ->searchable(),
                            Tables\Columns\TextColumn::make('inventoryItem.name')
                            ->label('Item')
                            ->searchable()
                            ->limit(30),
                            Tables\Columns\TextColumn::make('current_stock')
                            ->label('Current')
                            ->badge()
                            ->color(fn($record) => $record->current_stock <= 0 ? 'danger' :
                                    ($record->current_stock <= $record->inventoryItem->reorder_point ? 'warning' : 'success')),
                            Tables\Columns\TextColumn::make('reserved_stock')
                            ->label('Reserved')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('available_stock')
                            ->label('Available')
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\TextColumn::make('condition')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'new' => 'success',
                                        'good' => 'success',
                                        'fair' => 'info',
                                        'poor' => 'warning',
                                        'damaged' => 'danger',
                                        'expired' => 'danger',
                                        default => 'gray',
                                    }),
                            Tables\Columns\TextColumn::make('batch_number')
                            ->label('Batch')
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('expiry_date')
                            ->label('Expiry')
                            ->date()
                            ->color(fn($record) => $record->is_expired ? 'danger' :
                                    ($record->is_expiring_soon ? 'warning' : null))
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('location')
                            ->label('Location')
                            ->limit(20)
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('facility')
                            ->relationship('facility', 'name'),
                            Tables\Filters\SelectFilter::make('inventory_item')
                            ->relationship('inventoryItem', 'name'),
                            Tables\Filters\SelectFilter::make('condition')
                            ->options(InventoryItem::getConditionOptions()),
                            Tables\Filters\Filter::make('low_stock')
                            ->label('Low Stock')
                            ->query(fn(Builder $query): Builder =>
                                    $query->whereHas('inventoryItem', function ($q) {
                                        $q->whereColumn('stock_levels.current_stock', '<=', 'inventory_items.reorder_point');
                                    })
                            ),
                            Tables\Filters\Filter::make('out_of_stock')
                            ->label('Out of Stock')
                            ->query(fn(Builder $query): Builder => $query->where('current_stock', '<=', 0)),
                            Tables\Filters\Filter::make('expired')
                            ->label('Expired')
                            ->query(fn(Builder $query): Builder => $query->where('expiry_date', '<', now())),
                            Tables\Filters\Filter::make('expiring_soon')
                            ->label('Expiring Soon')
                            ->query(fn(Builder $query): Builder =>
                                    $query->where('expiry_date', '<=', now()->addDays(30))
                                    ->where('expiry_date', '>', now())
                            ),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\Action::make('adjust_stock')
                            ->label('Adjust Stock')
                            //->icon('heroicon-o-adjustments')
                            ->color('warning')
                            ->form([
                                Forms\Components\Select::make('adjustment_type')
                                ->label('Adjustment Type')
                                ->options([
                                    'increase' => 'Increase Stock',
                                    'decrease' => 'Decrease Stock',
                                    'set' => 'Set Exact Amount',
                                ])
                                ->required()
                                ->reactive(),
                                Forms\Components\TextInput::make('quantity')
                                ->label(fn(callable $get) => match ($get('adjustment_type')) {
                                            'increase' => 'Quantity to Add',
                                            'decrease' => 'Quantity to Remove',
                                            'set' => 'New Stock Level',
                                            default => 'Quantity'
                                        })
                                ->numeric()
                                ->required()
                                ->minValue(0),
                                Forms\Components\Textarea::make('reason')
                                ->label('Reason for Adjustment')
                                ->required()
                                ->rows(3),
                            ])
                            ->action(function (StockLevel $record, array $data): void {
                                $oldStock = $record->current_stock;

                                $newStock = match ($data['adjustment_type']) {
                                    'increase' => $oldStock + $data['quantity'],
                                    'decrease' => max(0, $oldStock - $data['quantity']),
                                    'set' => $data['quantity'],
                                };

                                $adjustment = $newStock - $oldStock;
                                $record->adjustStock($adjustment, $data['reason']);
                            }),
                            Tables\Actions\Action::make('transfer_stock')
                            ->label('Transfer Stock')
                            ->icon('heroicon-o-arrow-right-circle')
                            ->color('info')
                            ->url(fn($record) => route('filament.admin.resources.stock-requests.create', [
                                        'from_facility_id' => $record->facility_id,
                                        'item_id' => $record->inventory_item_id,
                                    ])),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                                Tables\Actions\BulkAction::make('bulk_adjust')
                                ->label('Bulk Stock Adjustment')
                                //->icon('heroicon-o-adjustments')
                                ->form([
                                    Forms\Components\Select::make('adjustment_type')
                                    ->options([
                                        'increase' => 'Increase Stock',
                                        'decrease' => 'Decrease Stock',
                                    ])
                                    ->required(),
                                    Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->required(),
                                    Forms\Components\Textarea::make('reason')
                                    ->required(),
                                ])
                                ->action(function ($records, array $data): void {
                                    foreach ($records as $record) {
                                        $adjustment = $data['adjustment_type'] === 'increase' ? $data['quantity'] : -$data['quantity'];
                                        $record->adjustStock($adjustment, $data['reason']);
                                    }
                                }),
                            ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Infolists\Components\Section::make('Stock Details')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                ->schema([
                                    Infolists\Components\TextEntry::make('current_stock')
                                    ->badge()
                                    ->color('success'),
                                    Infolists\Components\TextEntry::make('reserved_stock')
                                    ->badge()
                                    ->color('info'),
                                    Infolists\Components\TextEntry::make('available_stock')
                                    ->badge()
                                    ->color('primary'),
                                ]),
                                Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('facility.name'),
                                    Infolists\Components\TextEntry::make('inventoryItem.name'),
                                    Infolists\Components\TextEntry::make('location'),
                                    Infolists\Components\TextEntry::make('condition')
                                    ->badge(),
                                ]),
                            ]),
                            Infolists\Components\Section::make('Batch Information')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                ->schema([
                                    Infolists\Components\TextEntry::make('batch_number'),
                                    Infolists\Components\TextEntry::make('serial_number'),
                                    Infolists\Components\TextEntry::make('expiry_date')
                                    ->date(),
                                ]),
                            ]),
        ]);
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListStockLevels::route('/'),
            'create' => Pages\CreateStockLevel::route('/create'),
            //'view' => Pages\ViewStockLevel::route('/{record}'),
            'edit' => Pages\EditStockLevel::route('/{record}/edit'),
        ];
    }
}
