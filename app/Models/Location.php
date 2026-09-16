<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'address',
        'latitude',
        'longitude',
    ];

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class, 'current_location_id');
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
