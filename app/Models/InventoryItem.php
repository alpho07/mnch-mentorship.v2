<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'barcode',
        'category_id',
        'supplier_id',
        'unit_of_measure',
        'unit_price',
        'minimum_stock_level',
        'maximum_stock_level',
        'reorder_point',
        'status', // NEW: Item status
        'condition', // NEW: Item condition
        'is_active',
        'requires_approval',
        'is_trackable',
        'expiry_tracking',
        'batch_tracking',
        'serial_tracking', // NEW: Serial number tracking
        'warranty_period', // NEW: Warranty in months
        'manufacturer', // NEW: Manufacturer name
        'model_number', // NEW: Model/part number
        'specifications', // NEW: Technical specifications
        'storage_requirements', // NEW: Storage conditions
        'disposal_method', // NEW: How to dispose when expired/damaged
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'minimum_stock_level' => 'integer',
        'maximum_stock_level' => 'integer',
        'reorder_point' => 'integer',
        'warranty_period' => 'integer',
        'is_active' => 'boolean',
        'requires_approval' => 'boolean',
        'is_trackable' => 'boolean',
        'expiry_tracking' => 'boolean',
        'batch_tracking' => 'boolean',
        'serial_tracking' => 'boolean',
        'specifications' => 'array',
        'storage_requirements' => 'array',
    ];

    // Item Status Constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_DISCONTINUED = 'discontinued';
    const STATUS_RECALLED = 'recalled';
    const STATUS_QUARANTINED = 'quarantined';
    const STATUS_RESTRICTED = 'restricted';

    // Item Condition Constants
    const CONDITION_NEW = 'new';
    const CONDITION_GOOD = 'good';
    const CONDITION_FAIR = 'fair';
    const CONDITION_POOR = 'poor';
    const CONDITION_DAMAGED = 'damaged';
    const CONDITION_EXPIRED = 'expired';
    const CONDITION_LOST = 'lost';
    const CONDITION_STOLEN = 'stolen';
    const CONDITION_DECOMMISSIONED = 'decommissioned';
    const CONDITION_DISPOSED = 'disposed';

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ItemStatusLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                    ->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE])
                    ->whereIn('condition', [
                        self::CONDITION_NEW, 
                        self::CONDITION_GOOD, 
                        self::CONDITION_FAIR
                    ]);
    }

    public function scopeUnavailable($query)
    {
        return $query->whereIn('condition', [
            self::CONDITION_DAMAGED,
            self::CONDITION_EXPIRED,
            self::CONDITION_LOST,
            self::CONDITION_STOLEN,
            self::CONDITION_DECOMMISSIONED,
            self::CONDITION_DISPOSED
        ]);
    }

    public function scopeLowStock($query)
    {
        return $query->whereHas('stockLevels', function ($q) {
            $q->whereColumn('current_stock', '<=', 'reorder_point');
        });
    }

    public function scopeOutOfStock($query)
    {
        return $query->whereHas('stockLevels', function ($q) {
            $q->where('current_stock', '<=', 0);
        });
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expiry_tracking', true)
                    ->whereHas('stockLevels', function ($q) use ($days) {
                        $q->where('expiry_date', '<=', now()->addDays($days))
                          ->where('expiry_date', '>', now());
                    });
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_tracking', true)
                    ->whereHas('stockLevels', function ($q) {
                        $q->where('expiry_date', '<', now());
                    });
    }

    // Computed Attributes
    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels()->sum('current_stock');
    }

    public function getCentralStoreStockAttribute(): int
    {
        return $this->stockLevels()
            ->whereHas('facility', fn($q) => $q->where('is_central_store', true))
            ->sum('current_stock');
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->stockLevels()
            ->sum('available_stock');
    }

    public function getTotalValueAttribute(): float
    {
        return $this->total_stock * $this->unit_price;
    }

    public function getStockStatusAttribute(): string
    {
        $totalStock = $this->total_stock;
        
        if ($totalStock <= 0) {
            return 'out_of_stock';
        } elseif ($totalStock <= $this->reorder_point) {
            return 'low_stock';
        } elseif ($this->maximum_stock_level && $totalStock >= $this->maximum_stock_level) {
            return 'overstock';
        }
        
        return 'in_stock';
    }

    public function getOverallConditionAttribute(): string
    {
        // Get the most common condition across all stock levels
        $conditions = $this->stockLevels()
            ->whereNotNull('condition')
            ->pluck('condition')
            ->countBy()
            ->sortDesc();

        return $conditions->keys()->first() ?? $this->condition;
    }

    public function getIsAvailableForUseAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE && 
               in_array($this->condition, [
                   self::CONDITION_NEW, 
                   self::CONDITION_GOOD, 
                   self::CONDITION_FAIR
               ]);
    }

    public function getWarrantyExpiryAttribute(): ?string
    {
        if (!$this->warranty_period) {
            return null;
        }

        return $this->created_at->addMonths($this->warranty_period)->format('Y-m-d');
    }

    // Methods
    public function updateStatus(string $newStatus, string $reason = null, int $userId = null): void
    {
        $oldStatus = $this->status;
        
        $this->update(['status' => $newStatus]);

        // Log status change
        $this->statusLogs()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => $userId ?? auth()->id(),
        ]);
    }

    public function updateCondition(string $newCondition, string $reason = null, int $userId = null): void
    {
        $oldCondition = $this->condition;
        
        $this->update(['condition' => $newCondition]);

        // Log condition change
        $this->statusLogs()->create([
            'old_condition' => $oldCondition,
            'new_condition' => $newCondition,
            'reason' => $reason,
            'changed_by' => $userId ?? auth()->id(),
        ]);

        // If item is damaged/lost/disposed, create transaction
        if (in_array($newCondition, [self::CONDITION_DAMAGED, self::CONDITION_LOST, self::CONDITION_DISPOSED])) {
            $this->handleUnavailableCondition($newCondition, $reason);
        }
    }

    private function handleUnavailableCondition(string $condition, string $reason = null): void
    {
        // Update all stock levels for this item
        $this->stockLevels()->each(function ($stockLevel) use ($condition, $reason) {
            if ($stockLevel->current_stock > 0) {
                // Create transaction for stock adjustment
                InventoryTransaction::create([
                    'inventory_item_id' => $this->id,
                    'facility_id' => $stockLevel->facility_id,
                    'transaction_type' => match($condition) {
                        self::CONDITION_DAMAGED => 'damaged',
                        self::CONDITION_LOST => 'lost',
                        self::CONDITION_STOLEN => 'stolen',
                        self::CONDITION_DISPOSED => 'disposal',
                        default => 'adjustment'
                    },
                    'quantity' => -$stockLevel->current_stock,
                    'previous_stock' => $stockLevel->current_stock,
                    'new_stock' => 0,
                    'reference_type' => 'condition_change',
                    'notes' => $reason ?? "Item condition changed to {$condition}",
                    'created_by' => auth()->id(),
                ]);

                // Zero out the stock
                $stockLevel->update([
                    'current_stock' => 0,
                    'available_stock' => 0,
                ]);
            }
        });
    }

    public function decommission(string $reason = null): void
    {
        $this->updateCondition(self::CONDITION_DECOMMISSIONED, $reason);
        $this->update(['is_active' => false]);
    }

    public function dispose(string $reason = null): void
    {
        $this->updateCondition(self::CONDITION_DISPOSED, $reason);
        $this->update(['is_active' => false]);
    }

    // Status and Condition options for forms
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_DISCONTINUED => 'Discontinued',
            self::STATUS_RECALLED => 'Recalled',
            self::STATUS_QUARANTINED => 'Quarantined',
            self::STATUS_RESTRICTED => 'Restricted',
        ];
    }

    public static function getConditionOptions(): array
    {
        return [
            self::CONDITION_NEW => 'New',
            self::CONDITION_GOOD => 'Good',
            self::CONDITION_FAIR => 'Fair',
            self::CONDITION_POOR => 'Poor',
            self::CONDITION_DAMAGED => 'Damaged',
            self::CONDITION_EXPIRED => 'Expired',
            self::CONDITION_LOST => 'Lost',
            self::CONDITION_STOLEN => 'Stolen',
            self::CONDITION_DECOMMISSIONED => 'Decommissioned',
            self::CONDITION_DISPOSED => 'Disposed',
        ];
    }
}