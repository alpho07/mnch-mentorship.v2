<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_request_id',
        'inventory_item_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_dispatched',
        'quantity_received',
        'balance_quantity',
        'unit_price',
        'notes',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_approved' => 'integer',
        'quantity_dispatched' => 'integer',
        'quantity_received' => 'integer',
        'balance_quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    // Relationships
    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    // Computed Attributes
    public function getTotalRequestedValueAttribute(): float
    {
        return $this->quantity_requested * $this->unit_price;
    }

    public function getTotalApprovedValueAttribute(): float
    {
        return $this->quantity_approved * $this->unit_price;
    }

    public function getIsFullyApprovedAttribute(): bool
    {
        return $this->quantity_approved >= $this->quantity_requested;
    }

    public function getIsFullyDispatchedAttribute(): bool
    {
        return $this->quantity_dispatched >= $this->quantity_approved;
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->quantity_received >= $this->quantity_dispatched;
    }
}
