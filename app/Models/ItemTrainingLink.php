<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemTrainingLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'program_id',
        'module_id',
        'topic_id',
    ];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
