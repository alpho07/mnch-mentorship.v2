<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'inventory_item_id',
        'quantity_planned',
        'quantity_used',
        'unit_cost',
        'total_cost',
        'usage_notes',
        'returned_quantity',
    ];

    protected $casts = [
        'quantity_planned' => 'integer',
        'quantity_used' => 'integer',
        'returned_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // Relationships
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    // Computed Attributes
    public function getUsagePercentageAttribute(): float
    {
        if ($this->quantity_planned <= 0) return 0;
        return round(($this->quantity_used / $this->quantity_planned) * 100, 1);
    }

    public function getWastageQuantityAttribute(): int
    {
        return max(0, $this->quantity_planned - $this->quantity_used - ($this->returned_quantity ?? 0));
    }

    public function getActualCostAttribute(): float
    {
        return $this->quantity_used * $this->unit_cost;
    }

    public function getStatusAttribute(): string
    {
        $usagePercent = $this->usage_percentage;
        
        if ($usagePercent == 0) return 'Not Used';
        if ($usagePercent < 50) return 'Underutilized';
        if ($usagePercent <= 100) return 'As Planned';
        return 'Overused';
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'As Planned' => 'success',
            'Underutilized' => 'warning',
            'Overused' => 'danger',
            default => 'gray',
        };
    }
}