<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'location_id',
        'item_batch_id',
        'type',
        'quantity',
        'user_id',
        'remarks',
        'transaction_date',
        'latitude',
        'longitude',
    ];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function batch()
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
