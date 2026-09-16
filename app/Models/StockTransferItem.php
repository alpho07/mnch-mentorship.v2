<?php

// StockTransferItem Model
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'inventory_item_id',
        'quantity',
        'quantity_dispatched',
        'quantity_received',
        'unit_price',
        'batch_number',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_dispatched' => 'integer',
        'quantity_received' => 'integer',
        'unit_price' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    // Relationships
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    // Computed Attributes
    public function getTotalValueAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->quantity_received >= $this->quantity_dispatched;
    }
}

// InventoryTransaction Model for audit trail
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

// TransferTrackingEvent Model for geo-tracking
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferTrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'event_type',
        'description',
        'latitude',
        'longitude',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'metadata' => 'array',
    ];

    const EVENT_CREATED = 'created';
    const EVENT_APPROVED = 'approved';
    const EVENT_REJECTED = 'rejected';
    const EVENT_DISPATCHED = 'dispatched';
    const EVENT_IN_TRANSIT = 'in_transit';
    const EVENT_LOCATION_UPDATE = 'location_update';
    const EVENT_DELIVERED = 'delivered';
    const EVENT_RECEIVED = 'received';

    // Relationships
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Computed Attributes
    public function getLocationAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude,
            ];
        }
        return null;
    }

    // Scopes
    public function scopeWithLocation($query)
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}