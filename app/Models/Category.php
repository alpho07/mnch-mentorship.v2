<?php
// Command: This model likely already exists, but here's the enhanced version

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;
    protected $table ='inventory_categories';

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'code',
        'color',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'category_id');
    }

    // Query Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    // Computed Attributes
    public function getFullNameAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->name . ' > ' . $this->name;
        }

        return $this->name;
    }

    public function getItemCountAttribute(): int
    {
        return $this->inventoryItems()->count();
    }

    public function getTotalValueAttribute(): float
    {
        return $this->inventoryItems()
            ->join('stock_levels', 'inventory_items.id', '=', 'stock_levels.inventory_item_id')
            ->selectRaw('SUM(stock_levels.current_stock * inventory_items.unit_price)')
            ->value('SUM(stock_levels.current_stock * inventory_items.unit_price)') ?? 0;
    }
}
