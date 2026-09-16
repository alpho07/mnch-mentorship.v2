<?php

namespace App\Filament\Widgets;

use App\Models\InventoryItem;
use App\Models\Facility;
use App\Models\StockLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Component;

class InventoryOverviewWidget extends Widget implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $view = 'filament.widgets.quick-stock-entry';
    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Quick Stock Entry')
                    ->description('Quickly add stock to existing inventory items')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Select::make('facility_id')
                                    ->label('Facility')
                                    ->options(Facility::pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->options(InventoryItem::active()->get()->mapWithKeys(function ($item) {
                                        return [$item->id => "{$item->sku} - {$item->name}"];
                                    }))
                                    ->searchable()
                                    ->required(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity to Add')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                                Forms\Components\TextInput::make('batch_number')
                                    ->label('Batch Number')
                                    ->placeholder('Optional'),
                            ]),
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('expiry_date')
                                    ->label('Expiry Date')
                                    ->placeholder('If applicable'),
                                Forms\Components\TextInput::make('location')
                                    ->label('Storage Location')
                                    ->placeholder('e.g., Shelf A1'),
                                Forms\Components\Select::make('transaction_type')
                                    ->label('Transaction Type')
                                    ->options([
                                        'stock_in' => 'Stock Received',
                                        'adjustment' => 'Stock Adjustment',
                                        'return' => 'Stock Return',
                                    ])
                                    ->default('stock_in')
                                    ->required(),
                            ]),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->placeholder('Reason for stock addition'),
                    ]),
            ])
            ->statePath('data');
    }

    public function addStock(): void
    {
        $data = $this->form->getState();

        try {
            // Find or create stock level
            $stockLevel = StockLevel::firstOrCreate(
                [
                    'facility_id' => $data['facility_id'],
                    'inventory_item_id' => $data['inventory_item_id'],
                    'batch_number' => $data['batch_number'] ?? null,
                ],
                [
                    'current_stock' => 0,
                    'reserved_stock' => 0,
                    'available_stock' => 0,
                    'location' => $data['location'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null,
                    'condition' => 'new',
                    'last_updated_by' => auth()->id(),
                ]
            );

            // Add the stock
            $stockLevel->adjustStock($data['quantity'], $data['notes'] ?? 'Quick stock entry');

            // Update location and expiry if provided
            $stockLevel->update([
                'location' => $data['location'] ?? $stockLevel->location,
                'expiry_date' => $data['expiry_date'] ?? $stockLevel->expiry_date,
                'last_updated_by' => auth()->id(),
            ]);

            Notification::make()
                ->title('Stock Added Successfully')
                ->success()
                ->body("Added {$data['quantity']} units to {$stockLevel->inventoryItem->name}")
                ->send();

            // Reset form
            $this->form->fill();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Adding Stock')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    public static function canView(): bool
    {
        return auth()->user()->can('create', StockLevel::class);
    }
}

// Create the widget view file
// resources/views/filament/widgets/quick-stock-entry.blade.php