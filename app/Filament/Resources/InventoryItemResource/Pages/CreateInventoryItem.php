<?php
namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\StockLevel;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $formData = $this->form->getState();

        // Create initial stock levels if provided
        if (isset($formData['initial_stock_levels']) && is_array($formData['initial_stock_levels'])) {
            foreach ($formData['initial_stock_levels'] as $stockData) {
                if ($stockData['facility_id'] && $stockData['current_stock'] > 0) {
                    $stockLevel = StockLevel::create([
                        'facility_id' => $stockData['facility_id'],
                        'inventory_item_id' => $record->id,
                        'current_stock' => $stockData['current_stock'],
                        'reserved_stock' => 0,
                        'available_stock' => $stockData['current_stock'],
                        'location' => $stockData['location'] ?? null,
                        'batch_number' => $stockData['batch_number'] ?? null,
                        'expiry_date' => $stockData['expiry_date'] ?? null,
                        'condition' => $record->condition,
                        'last_updated_by' => auth()->id(),
                    ]);

                    // Create initial stock transaction
                    \App\Models\InventoryTransaction::create([
                        'inventory_item_id' => $record->id,
                        'facility_id' => $stockData['facility_id'],
                        'transaction_type' => 'stock_in',
                        'quantity' => $stockData['current_stock'],
                        'previous_stock' => 0,
                        'new_stock' => $stockData['current_stock'],
                        'reference_type' => 'initial_stock',
                        'batch_number' => $stockData['batch_number'] ?? null,
                        'expiry_date' => $stockData['expiry_date'] ?? null,
                        'unit_price' => $record->unit_price,
                        'notes' => $stockData['notes'] ?? 'Initial stock setup',
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove initial_stock_levels from the main data as it's not part of inventory_items table
        unset($data['initial_stock_levels']);
        
        return $data;
    }
}