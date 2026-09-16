<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryCategory;
use App\Models\Supplier;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class InventoryItemResource extends Resource {

    protected static ?string $model = InventoryItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_inventory::item');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Basic Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                ->rows(3),
                                Forms\Components\TextInput::make('sku')
                                ->label('SKU')
                                ->unique(ignoreRecord: true)
                                ->required(),
                                Forms\Components\TextInput::make('barcode')
                                ->unique(ignoreRecord: true),
                            ])->columns(2),
                            Forms\Components\Section::make('Classification & Status')
                            ->schema([
                                Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                                Forms\Components\Select::make('supplier_id')
                                ->label('Supplier')
                                ->relationship('supplier', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->getOptionLabelFromRecordUsing(fn($record) => "{$record->supplier_code} - {$record->name}"),
                                Forms\Components\Select::make('status')
                                ->options(InventoryItem::getStatusOptions())
                                ->default('active')
                                ->required(),
                                Forms\Components\Select::make('condition')
                                ->options(InventoryItem::getConditionOptions())
                                ->default('new')
                                ->required(),
                            ])->columns(2),
                            Forms\Components\Section::make('Pricing & Units')
                            ->schema([
                                Forms\Components\TextInput::make('unit_of_measure')
                                ->required()
                                ->placeholder('e.g., pieces, boxes, kg'),
                                Forms\Components\TextInput::make('unit_price')
                                ->numeric()
                                ->prefix('KES')
                                ->step(0.01)
                                ->required(),
                            ])->columns(2),
                            Forms\Components\Section::make('Stock Management')
                            ->schema([
                                Forms\Components\TextInput::make('minimum_stock_level')
                                ->numeric()
                                ->default(0),
                                Forms\Components\TextInput::make('maximum_stock_level')
                                ->numeric(),
                                Forms\Components\TextInput::make('reorder_point')
                                ->numeric()
                                ->default(0),
                            ])->columns(3),
                            // NEW: Initial Stock Section
                            Forms\Components\Section::make('Initial Stock Setup')
                            ->description('Set up initial stock levels for facilities')
                            ->schema([
                                Forms\Components\Repeater::make('initial_stock_levels')
                                ->label('Initial Stock by Facility')
                                ->schema([
                                    Forms\Components\Select::make('facility_id')
                                    ->label('Facility')
                                    ->options(Facility::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->distinct(),
                                    Forms\Components\TextInput::make('current_stock')
                                    ->label('Initial Quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0),
                                    Forms\Components\TextInput::make('location')
                                    ->label('Storage Location')
                                    ->placeholder('e.g., Shelf A1, Room 101'),
                                    Forms\Components\TextInput::make('batch_number')
                                    ->label('Batch Number')
                                    ->placeholder('Optional'),
                                    Forms\Components\DatePicker::make('expiry_date')
                                    ->label('Expiry Date')
                                    ->placeholder('If applicable'),
                                    Forms\Components\Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(2)
                                    ->placeholder('Any additional notes'),
                                ])
                                ->columns(3)
                                ->defaultItems(1)
                                ->collapsible()
                                ->addActionLabel('Add Another Facility'),
                            ])
                            ->visible(fn($operation) => $operation === 'create'),
                            Forms\Components\Section::make('Product Details')
                            ->schema([
                                Forms\Components\TextInput::make('manufacturer')
                                ->maxLength(255),
                                Forms\Components\TextInput::make('model_number')
                                ->label('Model/Part Number')
                                ->maxLength(255),
                                Forms\Components\TextInput::make('warranty_period')
                                ->label('Warranty Period (Months)')
                                ->numeric()
                                ->minValue(0),
                            ])->columns(3),
                            Forms\Components\Section::make('Tracking Settings')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                ->default(true),
                                Forms\Components\Toggle::make('requires_approval')
                                ->helperText('Requires approval for requests/transfers'),
                                Forms\Components\Toggle::make('is_trackable')
                                ->helperText('Enable location tracking'),
                                Forms\Components\Toggle::make('expiry_tracking')
                                ->helperText('Track expiry dates'),
                                Forms\Components\Toggle::make('batch_tracking')
                                ->helperText('Track batch numbers'),
                                Forms\Components\Toggle::make('serial_tracking')
                                ->helperText('Track serial numbers'),
                            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('sku')
                            ->label('SKU')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('name')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('category.name')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('unit_of_measure')
                            ->label('UOM'),
                            Tables\Columns\TextColumn::make('unit_price')
                            ->money('KES')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('total_stock')
                            ->badge()
                            ->color(fn($record) => match ($record->stock_status) {
                                        'out_of_stock' => 'danger',
                                        'low_stock' => 'warning',
                                        'overstock' => 'info',
                                        default => 'success'
                                    }),
                            Tables\Columns\TextColumn::make('stock_status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'out_of_stock' => 'danger',
                                        'low_stock' => 'warning',
                                        'overstock' => 'info',
                                        'in_stock' => 'success',
                                    }),
                            Tables\Columns\IconColumn::make('is_active')
                            ->boolean(),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('category')
                            ->relationship('category', 'name'),
                            Tables\Filters\SelectFilter::make('supplier')
                            ->relationship('supplier', 'name'),
                            Tables\Filters\Filter::make('low_stock')
                            ->query(fn(Builder $query): Builder => $query->lowStock()),
                            Tables\Filters\Filter::make('out_of_stock')
                            ->query(fn(Builder $query): Builder => $query->outOfStock()),
                            Tables\Filters\TernaryFilter::make('is_active'),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Infolists\Components\Section::make('Item Details')
                            ->schema([
                                Infolists\Components\TextEntry::make('name'),
                                Infolists\Components\TextEntry::make('sku'),
                                Infolists\Components\TextEntry::make('barcode'),
                                Infolists\Components\TextEntry::make('description'),
                            ])->columns(2),
                            Infolists\Components\Section::make('Stock Information')
                            ->schema([
                                Infolists\Components\TextEntry::make('total_stock')
                                ->badge()
                                ->color('success'),
                                Infolists\Components\TextEntry::make('central_store_stock')
                                ->label('Central Store Stock'),
                                Infolists\Components\TextEntry::make('available_stock')
                                ->label('Available Stock'),
                                Infolists\Components\TextEntry::make('total_value')
                                ->money('KES'),
                            ])->columns(2),
        ]);
    }

    public static function getRelations(): array {
        return [
                //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            //'view' => Pages\ViewInventoryItem::route('/{record}'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
