<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'batch_no',
        'expiry_date',
        'initial_quantity',
    ];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class);
    }

    public function itemTransactions()
    {
        return $this->hasMany(ItemTransaction::class);
    }
}
