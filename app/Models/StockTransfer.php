<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_number',
        'from_facility_id',
        'to_facility_id',
        'initiated_by',
        'approved_by',
        'dispatched_by',
        'received_by',
        'status',
        'priority',
        'transfer_date',
        'approved_date',
        'dispatch_date',
        'received_date',
        'notes',
        'rejection_reason',
        'requires_approval',
        'approval_level',
        'total_items',
        'total_value',
        'tracking_number',
        'transport_method',
        'estimated_arrival',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'approved_date' => 'date',
        'dispatch_date' => 'date',
        'received_date' => 'date',
        'estimated_arrival' => 'datetime',
        'requires_approval' => 'boolean',
        'total_items' => 'integer',
        'total_value' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    const APPROVAL_LEVEL_FACILITY = 'facility';
    const APPROVAL_LEVEL_REGIONAL = 'regional';
    const APPROVAL_LEVEL_NATIONAL = 'national';

    // Relationships
    public function fromFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'from_facility_id');
    }

    public function toFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'to_facility_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TransferTrackingEvent::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', self::STATUS_IN_TRANSIT);
    }

    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where(function ($q) use ($facilityId) {
            $q->where('from_facility_id', $facilityId)
              ->orWhere('to_facility_id', $facilityId);
        });
    }

    public function scopeOutgoing($query, int $facilityId)
    {
        return $query->where('from_facility_id', $facilityId);
    }

    public function scopeIncoming($query, int $facilityId)
    {
        return $query->where('to_facility_id', $facilityId);
    }

    // Methods
    public function approve(User $approver): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_date' => now(),
        ]);

        $this->addTrackingEvent('approved', "Transfer approved by {$approver->full_name}");
    }

    public function reject(User $approver, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'approved_date' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->addTrackingEvent('rejected', "Transfer rejected: {$reason}");
    }

    public function dispatch(User $dispatcher): void
    {
        foreach ($this->items as $item) {
            $stockLevel = StockLevel::where('facility_id', $this->from_facility_id)
                ->where('inventory_item_id', $item->inventory_item_id)
                ->first();

            if ($stockLevel && $stockLevel->available_stock >= $item->quantity) {
                $stockLevel->adjustStock(-$item->quantity, "Transferred to {$this->toFacility->name}");
                $item->update(['quantity_dispatched' => $item->quantity]);
            } else {
                throw new \Exception("Insufficient stock for item: {$item->inventoryItem->name}");
            }
        }

        $this->update([
            'status' => self::STATUS_IN_TRANSIT,
            'dispatched_by' => $dispatcher->id,
            'dispatch_date' => now(),
        ]);

        $this->addTrackingEvent('dispatched', "Items dispatched by {$dispatcher->full_name}");
    }

    public function receive(User $receiver, array $receivedQuantities): void
    {
        $allItemsReceived = true;

        foreach ($this->items as $item) {
            $receivedQty = $receivedQuantities[$item->id] ?? 0;
            $item->update(['quantity_received' => $receivedQty]);

            if ($receivedQty > 0) {
                $stockLevel = StockLevel::firstOrCreate(
                    [
                        'facility_id' => $this->to_facility_id,
                        'inventory_item_id' => $item->inventory_item_id,
                    ],
                    ['current_stock' => 0, 'reserved_stock' => 0, 'available_stock' => 0]
                );

                $stockLevel->adjustStock($receivedQty, "Received from {$this->fromFacility->name}");
            }

            if ($receivedQty < $item->quantity) {
                $allItemsReceived = false;
            }
        }

        $status = $allItemsReceived ? self::STATUS_DELIVERED : self::STATUS_PARTIALLY_RECEIVED;

        $this->update([
            'status' => $status,
            'received_by' => $receiver->id,
            'received_date' => now(),
        ]);

        $this->addTrackingEvent('received', "Items received by {$receiver->full_name}");
    }

    public function addTrackingEvent(string $event, string $description, array $metadata = []): void
    {
        $this->trackingEvents()->create([
            'event_type' => $event,
            'description' => $description,
            'metadata' => json_encode($metadata),
            'latitude' => $metadata['latitude'] ?? null,
            'longitude' => $metadata['longitude'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    // Computed Attributes
    public function getCanBeApprovedAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->requires_approval;
    }

    public function getCanBeDispatchedAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED || 
               ($this->status === self::STATUS_PENDING && !$this->requires_approval);
    }

    public function getCanBeReceivedAttribute(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function getDistanceAttribute(): ?float
    {
        if ($this->fromFacility->lat && $this->fromFacility->long && 
            $this->toFacility->lat && $this->toFacility->long) {
            
            $earthRadius = 6371; // km
            
            $latFrom = deg2rad($this->fromFacility->lat);
            $lonFrom = deg2rad($this->fromFacility->long);
            $latTo = deg2rad($this->toFacility->lat);
            $lonTo = deg2rad($this->toFacility->long);
            
            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;
            
            $a = sin($latDelta / 2) * sin($latDelta / 2) +
                 cos($latFrom) * cos($latTo) *
                 sin($lonDelta / 2) * sin($lonDelta / 2);
                 
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            
            return $earthRadius * $c;
        }
        
        return null;
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->transfer_number)) {
                $model->transfer_number = 'TRF-' . str_pad(static::count() + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }
}