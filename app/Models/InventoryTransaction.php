<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'facility_id',
        'transaction_type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reference_type',
        'reference_id',
        'batch_number',
        'expiry_date',
        'unit_price',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
        'unit_price' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    const TYPE_STOCK_IN = 'stock_in';
    const TYPE_STOCK_OUT = 'stock_out';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';
    const TYPE_REQUEST_OUT = 'request_out';
    const TYPE_REQUEST_IN = 'request_in';

    // Relationships
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeStockIn($query)
    {
        return $query->whereIn('transaction_type', [
            self::TYPE_STOCK_IN, 
            self::TYPE_TRANSFER_IN, 
            self::TYPE_REQUEST_IN
        ]);
    }

    public function scopeStockOut($query)
    {
        return $query->whereIn('transaction_type', [
            self::TYPE_STOCK_OUT, 
            self::TYPE_TRANSFER_OUT, 
            self::TYPE_REQUEST_OUT
        ]);
    }

    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByItem($query, int $itemId)
    {
        return $query->where('inventory_item_id', $itemId);
    }
}