<?php

namespace App\Filament\Resources\StockRequestResource\Pages;

use App\Filament\Resources\StockRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockRequest extends CreateRecord
{
    protected static string $resource = StockRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = auth()->id();
        
        // Calculate totals
        $totalItems = 0;
        $totalValue = 0;
        
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $totalItems += $item['quantity_requested'];
                $totalValue += $item['quantity_requested'] * $item['unit_price'];
            }
        }
        
        $data['total_items'] = $totalItems;
        $data['total_value'] = $totalValue;
        
        return $data;
    }
}