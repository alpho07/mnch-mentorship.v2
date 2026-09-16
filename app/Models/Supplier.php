<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'supplier_code',
        'supplier_type',
        'status',
        'contact_person',
        'phone',
        'email',
        'website',
        'address',
        'city',
        'postal_code',
        'country',
        'tax_number',
        'registration_number',
        'payment_terms',
        'credit_limit',
        'notes',
        'is_preferred',
        'requires_po',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_preferred' => 'boolean',
        'requires_po' => 'boolean',
    ];

    // Status Constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_BLACKLISTED = 'blacklisted';

    // Type Constants
    const TYPE_MANUFACTURER = 'manufacturer';
    const TYPE_DISTRIBUTOR = 'distributor';
    const TYPE_WHOLESALER = 'wholesaler';
    const TYPE_RETAILER = 'retailer';
    const TYPE_GOVERNMENT = 'government';
    const TYPE_NGO = 'ngo';

    // Payment Terms Constants
    const PAYMENT_COD = 'cash_on_delivery';
    const PAYMENT_NET_7 = 'net_7';
    const PAYMENT_NET_15 = 'net_15';
    const PAYMENT_NET_30 = 'net_30';
    const PAYMENT_NET_60 = 'net_60';
    const PAYMENT_NET_90 = 'net_90';

    // Relationships
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    // Query Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('supplier_type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    public function scopeRequiresPO($query)
    {
        return $query->where('requires_po', true);
    }

    // Computed Attributes
    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getFullAddressAttribute(): string
    {
        $addressParts = array_filter([
            $this->address,
            $this->city,
            $this->postal_code,
            $this->country === 'KE' ? 'Kenya' : $this->country,
        ]);

        return implode(', ', $addressParts);
    }

    public function getTotalItemsSuppliedAttribute(): int
    {
        return $this->inventoryItems()->count();
    }

    public function getActiveItemsCountAttribute(): int
    {
        return $this->inventoryItems()
            ->where('is_active', true)
            ->where('status', 'active')
            ->count();
    }

    public function getTotalValueSuppliedAttribute(): float
    {
        return $this->inventoryItems()
            ->join('stock_levels', 'inventory_items.id', '=', 'stock_levels.inventory_item_id')
            ->selectRaw('SUM(stock_levels.current_stock * inventory_items.unit_price)')
            ->value('SUM(stock_levels.current_stock * inventory_items.unit_price)') ?? 0;
    }

    public function getAverageItemPriceAttribute(): float
    {
        return $this->inventoryItems()->avg('unit_price') ?? 0;
    }

    public function getContactInfoAttribute(): string
    {
        $contact = [];
        
        if ($this->contact_person) {
            $contact[] = $this->contact_person;
        }
        
        if ($this->phone) {
            $contact[] = $this->phone;
        }
        
        if ($this->email) {
            $contact[] = $this->email;
        }
        
        return implode(' | ', $contact);
    }

    public function getPaymentTermsDaysAttribute(): int
    {
        return match($this->payment_terms) {
            self::PAYMENT_COD => 0,
            self::PAYMENT_NET_7 => 7,
            self::PAYMENT_NET_15 => 15,
            self::PAYMENT_NET_30 => 30,
            self::PAYMENT_NET_60 => 60,
            self::PAYMENT_NET_90 => 90,
            default => 30,
        };
    }

    // Business Methods
    public function activate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    public function deactivate(): void
    {
        $this->update(['status' => self::STATUS_INACTIVE]);
    }

    public function suspend(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_SUSPENDED,
            'notes' => $this->notes . "\n\nSuspended: " . ($reason ?? 'No reason provided') . " - " . now()->format('Y-m-d H:i:s')
        ]);
    }

    public function blacklist(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_BLACKLISTED,
            'notes' => $this->notes . "\n\nBlacklisted: " . ($reason ?? 'No reason provided') . " - " . now()->format('Y-m-d H:i:s')
        ]);
    }

    public function makePreferred(): void
    {
        $this->update(['is_preferred' => true]);
    }

    public function removePreferred(): void
    {
        $this->update(['is_preferred' => false]);
    }

    public function canSupply(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasReachedCreditLimit(float $amount): bool
    {
        if ($this->credit_limit <= 0) {
            return false; // No credit limit set
        }
        
        // You can implement outstanding amount calculation here
        // For now, we'll just check against the credit limit
        return $amount > $this->credit_limit;
    }

    // Static Methods for Form Options
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_BLACKLISTED => 'Blacklisted',
        ];
    }

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_MANUFACTURER => 'Manufacturer',
            self::TYPE_DISTRIBUTOR => 'Distributor',
            self::TYPE_WHOLESALER => 'Wholesaler',
            self::TYPE_RETAILER => 'Retailer',
            self::TYPE_GOVERNMENT => 'Government Agency',
            self::TYPE_NGO => 'NGO/Non-Profit',
        ];
    }

    public static function getPaymentTermsOptions(): array
    {
        return [
            self::PAYMENT_COD => 'Cash on Delivery',
            self::PAYMENT_NET_7 => 'Net 7 Days',
            self::PAYMENT_NET_15 => 'Net 15 Days',
            self::PAYMENT_NET_30 => 'Net 30 Days',
            self::PAYMENT_NET_60 => 'Net 60 Days',
            self::PAYMENT_NET_90 => 'Net 90 Days',
        ];
    }

    public static function getCountryOptions(): array
    {
        return [
            'KE' => 'Kenya',
            'UG' => 'Uganda',
            'TZ' => 'Tanzania',
            'RW' => 'Rwanda',
            'ET' => 'Ethiopia',
            'SS' => 'South Sudan',
            'Other' => 'Other',
        ];
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();
        
        // Auto-generate supplier code when creating
        static::creating(function ($model) {
            if (empty($model->supplier_code)) {
                $model->supplier_code = 'SUP-' . str_pad(static::count() + 1, 6, '0', STR_PAD_LEFT);
            }
        });

        // Log when supplier status changes
        static::updating(function ($model) {
            if ($model->isDirty('status')) {
                $oldStatus = $model->getOriginal('status');
                $newStatus = $model->status;
                
                // You can log this change or trigger events here
                \Log::info("Supplier {$model->supplier_code} status changed from {$oldStatus} to {$newStatus}");
            }
        });
    }
}