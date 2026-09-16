<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_session_id',
        'inventory_item_id',
    ];

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }
    


     public function materials()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }


   
}

