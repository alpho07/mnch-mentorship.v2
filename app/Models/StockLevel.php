<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'inventory_item_id',
        'current_stock',
        'reserved_stock',
        'available_stock',
        'location',
        'batch_number',
        'expiry_date',
        'last_updated_by',
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'reserved_stock' => 'integer',
        'available_stock' => 'integer',
        'expiry_date' => 'date',
    ];

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    // Scopes
    public function scopeCentralStore($query)
    {
        return $query->whereHas('facility', fn($q) => $q->where('is_central_store', true));
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
                    ->where('expiry_date', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    // Methods
    public function updateAvailableStock(): void
    {
        $this->available_stock = $this->current_stock - $this->reserved_stock;
        $this->save();
    }

    public function reserveStock(int $quantity): bool
    {
        if ($this->available_stock >= $quantity) {
            $this->reserved_stock += $quantity;
            $this->updateAvailableStock();
            return true;
        }
        return false;
    }

    public function releaseReservedStock(int $quantity): void
    {
        $this->reserved_stock = max(0, $this->reserved_stock - $quantity);
        $this->updateAvailableStock();
    }

    public function adjustStock(int $quantity, string $reason = null): void
    {
        $oldStock = $this->current_stock;
        $this->current_stock = max(0, $this->current_stock + $quantity);
        $this->updateAvailableStock();

        // Log the transaction
        InventoryTransaction::create([
            'inventory_item_id' => $this->inventory_item_id,
            'facility_id' => $this->facility_id,
            'transaction_type' => $quantity > 0 ? 'stock_in' : 'stock_out',
            'quantity' => abs($quantity),
            'previous_stock' => $oldStock,
            'new_stock' => $this->current_stock,
            'reference_type' => 'adjustment',
            'notes' => $reason,
            'created_by' => auth()->id(),
        ]);
    }

    // Computed Attributes
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date < now();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date && 
               $this->expiry_date <= now()->addDays(30) && 
               $this->expiry_date > now();
    }

    public function getDaysToExpiryAttribute(): ?int
    {
        return $this->expiry_date ? now()->diffInDays($this->expiry_date, false) : null;
    }
}