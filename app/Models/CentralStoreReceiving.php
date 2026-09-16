<?php
// New Model: CentralStoreReceiving for tracking incoming stock to central stores
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CentralStoreReceiving extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receiving_number',
        'central_store_id',
        'supplier_id',
        'purchase_order_number',
        'delivery_note_number',
        'received_by',
        'received_date',
        'status',
        'total_items',
        'total_value',
        'notes',
        'quality_check_passed',
        'quality_notes',
    ];

    protected $casts = [
        'received_date' => 'date',
        'total_items' => 'integer',
        'total_value' => 'decimal:2',
        'quality_check_passed' => 'boolean',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_RECEIVED = 'received';
    const STATUS_QUALITY_CHECK = 'quality_check';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // Relationships
    public function centralStore(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'central_store_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CentralStoreReceivingItem::class);
    }

    // Methods
    public function approve(): void
    {
        foreach ($this->items as $item) {
            if ($item->quantity_accepted > 0) {
                // Add to central store stock
                $stockLevel = StockLevel::firstOrCreate(
                    [
                        'facility_id' => $this->central_store_id,
                        'inventory_item_id' => $item->inventory_item_id,
                        'batch_number' => $item->batch_number,
                    ],
                    [
                        'current_stock' => 0,
                        'reserved_stock' => 0,
                        'available_stock' => 0,
                        'condition' => 'new',
                        'last_updated_by' => auth()->id(),
                    ]
                );

                $stockLevel->adjustStock(
                    $item->quantity_accepted, 
                    "Received from supplier {$this->supplier->name} - Receiving: {$this->receiving_number}"
                );
            }
        }

        $this->update(['status' => self::STATUS_APPROVED]);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->receiving_number)) {
                $model->receiving_number = 'RCV-' . str_pad(static::count() + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }
}