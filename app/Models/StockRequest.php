<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StockRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number',
        'requesting_facility_id',
        'central_store_id',
        'requested_by',
        'approved_by',
        'dispatched_by',
        'received_by',
        'status',
        'priority',
        'request_date',
        'approved_date',
        'dispatch_date',
        'received_date',
        'notes',
        'rejection_reason',
        'total_items',
        'total_value',
        'total_requested_value',
        'total_approved_value',
        'total_dispatched_value',
        'total_received_value',
        'estimated_arrival',
        'tracking_number',
        'transport_method',
        'requires_approval',
        'approval_level',
        'metadata',
        'archived_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'approved_date' => 'date',
        'dispatch_date' => 'date',
        'received_date' => 'date',
        'estimated_arrival' => 'datetime',
        'archived_at' => 'datetime',
        'total_items' => 'integer',
        'total_value' => 'decimal:2',
        'total_requested_value' => 'decimal:2',
        'total_approved_value' => 'decimal:2',
        'total_dispatched_value' => 'decimal:2',
        'total_received_value' => 'decimal:2',
        'requires_approval' => 'boolean',
        'metadata' => 'array',
    ];

    // Status Constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PARTIALLY_APPROVED = 'partially_approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_PARTIALLY_DISPATCHED = 'partially_dispatched';
    const STATUS_RECEIVED = 'received';
    const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    const STATUS_CANCELLED = 'cancelled';

    // Priority Constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Approval Level Constants
    const APPROVAL_LEVEL_FACILITY = 'facility';
    const APPROVAL_LEVEL_REGIONAL = 'regional';
    const APPROVAL_LEVEL_NATIONAL = 'national';

    // ===== RELATIONSHIPS =====

    public function requestingFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'requesting_facility_id');
    }

    public function centralStore(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'central_store_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    // ===== QUERY SCOPES =====

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', [
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_APPROVED
        ]);
    }

    public function scopeDispatched($query)
    {
        return $query->whereIn('status', [
            self::STATUS_DISPATCHED,
            self::STATUS_PARTIALLY_DISPATCHED
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [
            self::STATUS_RECEIVED,
            self::STATUS_PARTIALLY_RECEIVED
        ]);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('requesting_facility_id', $facilityId);
    }

    public function scopeByCentralStore($query, int $centralStoreId)
    {
        return $query->where('central_store_id', $centralStoreId);
    }

    public function scopeOverdue($query, int $days = 3)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('created_at', '<', now()->subDays($days));
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', self::PRIORITY_HIGH);
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    // ===== COMPUTED ATTRIBUTES =====

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getCanBeDispatchedAttribute(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_APPROVED
        ]);
    }

    public function getCanBeReceivedAttribute(): bool
    {
        return in_array($this->status, [
            self::STATUS_DISPATCHED,
            self::STATUS_PARTIALLY_DISPATCHED
        ]);
    }

    public function getTotalRequestedValueAttribute(): float
    {
        if ($this->relationLoaded('items')) {
            return $this->items->sum(fn($item) => $item->quantity_requested * $item->unit_price);
        }
        return $this->attributes['total_requested_value'] ?? 0;
    }

    public function getTotalApprovedValueAttribute(): float
    {
        if ($this->relationLoaded('items')) {
            return $this->items->sum(fn($item) => ($item->quantity_approved ?? 0) * $item->unit_price);
        }
        return $this->attributes['total_approved_value'] ?? 0;
    }

    public function getTotalDispatchedValueAttribute(): float
    {
        if ($this->relationLoaded('items')) {
            return $this->items->sum(fn($item) => ($item->quantity_dispatched ?? 0) * $item->unit_price);
        }
        return $this->attributes['total_dispatched_value'] ?? 0;
    }

    public function getTotalReceivedValueAttribute(): float
    {
        if ($this->relationLoaded('items')) {
            return $this->items->sum(fn($item) => ($item->quantity_received ?? 0) * $item->unit_price);
        }
        return $this->attributes['total_received_value'] ?? 0;
    }

    public function getDaysPendingAttribute(): int
    {
        return now()->diffInDays($this->created_at);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->days_pending > 3;
    }

    public function getIsUrgentAttribute(): bool
    {
        return $this->priority === self::PRIORITY_URGENT;
    }

    public function getIsHighPriorityAttribute(): bool
    {
        return in_array($this->priority, [self::PRIORITY_URGENT, self::PRIORITY_HIGH]);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED, self::STATUS_PARTIALLY_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_DISPATCHED, self::STATUS_PARTIALLY_DISPATCHED => 'primary',
            self::STATUS_RECEIVED, self::STATUS_PARTIALLY_RECEIVED => 'success',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'danger',
            self::PRIORITY_HIGH => 'warning',
            self::PRIORITY_MEDIUM => 'info',
            self::PRIORITY_LOW => 'gray',
            default => 'gray',
        };
    }

    public function getProgressPercentageAttribute(): int
    {
        return match ($this->status) {
            self::STATUS_PENDING => 25,
            self::STATUS_APPROVED, self::STATUS_PARTIALLY_APPROVED => 50,
            self::STATUS_DISPATCHED, self::STATUS_PARTIALLY_DISPATCHED => 75,
            self::STATUS_RECEIVED, self::STATUS_PARTIALLY_RECEIVED => 100,
            self::STATUS_REJECTED, self::STATUS_CANCELLED => 0,
            default => 0,
        };
    }

    public function getCanBeEditedAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_APPROVED
        ]);
    }

    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->status, [
            self::STATUS_RECEIVED,
            self::STATUS_PARTIALLY_RECEIVED
        ]);
    }

    public function getIsActiveAttribute(): bool
    {
        return !in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED
        ]) && is_null($this->archived_at);
    }

    // ===== STOCK AVAILABILITY METHODS =====

    /**
     * Check if request can be quickly approved (all items available)
     */
    public function canBeQuickApproved(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        foreach ($this->items as $item) {
            $availableStock = StockLevel::where('facility_id', $this->central_store_id)
                ->where('inventory_item_id', $item->inventory_item_id)
                ->sum('available_stock');

            if ($availableStock < $item->quantity_requested) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get stock availability for all items in this request
     */
    public function getStockAvailabilityAttribute(): array
    {
        return Cache::remember(
            "stock_request:{$this->id}:availability",
            300, // 5 minutes
            function () {
                $availability = [];

                foreach ($this->items as $item) {
                    $centralStock = StockLevel::where('facility_id', $this->central_store_id)
                        ->where('inventory_item_id', $item->inventory_item_id)
                        ->sum('available_stock');

                    $availability[] = [
                        'item_id' => $item->id,
                        'item_name' => $item->inventoryItem->name,
                        'item_sku' => $item->inventoryItem->sku,
                        'requested' => $item->quantity_requested,
                        'available' => $centralStock,
                        'can_fulfill' => $centralStock >= $item->quantity_requested,
                        'shortage' => max(0, $item->quantity_requested - $centralStock),
                        'unit_price' => $item->unit_price,
                        'total_value' => $item->quantity_requested * $item->unit_price,
                    ];
                }

                return $availability;
            }
        );
    }

    /**
     * Check detailed availability with batch and expiry info
     */
    public function checkDetailedAvailability(): array
    {
        $detailed = [];

        foreach ($this->items as $item) {
            $stockLevels = StockLevel::where('facility_id', $this->central_store_id)
                ->where('inventory_item_id', $item->inventory_item_id)
                ->where('available_stock', '>', 0)
                ->orderBy('expiry_date', 'asc')
                ->get();

            $totalAvailable = $stockLevels->sum('available_stock');
            $canFulfill = $totalAvailable >= $item->quantity_requested;

            $batchInfo = $stockLevels->map(function ($stockLevel) {
                return [
                    'stock_level_id' => $stockLevel->id,
                    'batch_number' => $stockLevel->batch_number,
                    'available_stock' => $stockLevel->available_stock,
                    'expiry_date' => $stockLevel->expiry_date,
                    'location' => $stockLevel->location,
                    'is_expired' => $stockLevel->is_expired,
                    'is_expiring_soon' => $stockLevel->is_expiring_soon,
                ];
            })->toArray();

            $detailed[] = [
                'item_id' => $item->id,
                'item_name' => $item->inventoryItem->name,
                'requested' => $item->quantity_requested,
                'total_available' => $totalAvailable,
                'can_fulfill' => $canFulfill,
                'shortage' => max(0, $item->quantity_requested - $totalAvailable),
                'batches' => $batchInfo,
                'recommended_batches' => $this->getRecommendedBatches($stockLevels, $item->quantity_requested),
            ];
        }

        return $detailed;
    }

    /**
     * Get recommended batches for fulfillment (FIFO - First In, First Out)
     */
    private function getRecommendedBatches($stockLevels, int $quantityNeeded): array
    {
        $recommended = [];
        $remaining = $quantityNeeded;

        foreach ($stockLevels as $stockLevel) {
            if ($remaining <= 0) break;

            $toTake = min($remaining, $stockLevel->available_stock);

            $recommended[] = [
                'stock_level_id' => $stockLevel->id,
                'batch_number' => $stockLevel->batch_number,
                'quantity_to_take' => $toTake,
                'expiry_date' => $stockLevel->expiry_date,
                'location' => $stockLevel->location,
            ];

            $remaining -= $toTake;
        }

        return $recommended;
    }

    // ===== APPROVAL METHODS =====

    /**
     * Quick approve all items at requested quantities
     */
    public function quickApprove(User $approver): void
    {
        DB::transaction(function () use ($approver) {
            // Validate that quick approval is possible
            if (!$this->canBeQuickApproved()) {
                throw new \Exception('Request cannot be quick approved - insufficient stock available');
            }

            // Approve all items at requested quantities
            foreach ($this->items as $item) {
                $item->update(['quantity_approved' => $item->quantity_requested]);
            }

            $this->update([
                'status' => self::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_date' => now(),
            ]);

            // Clear cache
            $this->clearStockAvailabilityCache();

            // Log the approval
            Log::info("Stock request {$this->request_number} quick approved by {$approver->full_name}");

            // Automatically dispatch if possible
            try {
                $this->dispatch($approver);
                Log::info("Stock request {$this->request_number} auto-dispatched successfully");
            } catch (\Exception $e) {
                Log::warning("Auto-dispatch failed for request {$this->request_number}: " . $e->getMessage());
                // Still send approval notification even if dispatch fails
                $this->sendApprovalNotification();
            }
        });
    }

    /**
     * Standard approval with custom quantities per item
     */
    public function approve(User $approver, array $itemApprovals = []): void
    {
        DB::transaction(function () use ($approver, $itemApprovals) {
            $allItemsFullyApproved = true;

            foreach ($this->items as $item) {
                $approvedQty = $itemApprovals[$item->id] ?? $item->quantity_requested;

                // Validate approved quantity
                $availableStock = StockLevel::where('facility_id', $this->central_store_id)
                    ->where('inventory_item_id', $item->inventory_item_id)
                    ->sum('available_stock');

                if ($approvedQty > $availableStock) {
                    throw new \Exception("Approved quantity ({$approvedQty}) exceeds available stock ({$availableStock}) for {$item->inventoryItem->name}");
                }

                $item->update(['quantity_approved' => $approvedQty]);

                if ($approvedQty < $item->quantity_requested) {
                    $allItemsFullyApproved = false;
                }
            }

            $status = $allItemsFullyApproved ? self::STATUS_APPROVED : self::STATUS_PARTIALLY_APPROVED;

            $this->update([
                'status' => $status,
                'approved_by' => $approver->id,
                'approved_date' => now(),
            ]);

            // Clear cache and recalculate totals
            $this->clearStockAvailabilityCache();
            $this->calculateTotals();

            Log::info("Stock request {$this->request_number} approved by {$approver->full_name} - Status: {$status}");

            $this->sendApprovalNotification();
        });
    }

    /**
     * Reject the request with reason
     */
    public function reject(User $approver, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'approved_date' => now(),
            'rejection_reason' => $reason,
        ]);

        // Clear cache
        $this->clearStockAvailabilityCache();

        Log::info("Stock request {$this->request_number} rejected by {$approver->full_name}: {$reason}");

        $this->sendRejectionNotification($reason);
    }

    // ===== DISPATCH METHODS =====

    /**
     * Dispatch approved items with stock deduction
     */
    public function dispatch(User $dispatcher): void
    {
        DB::transaction(function () use ($dispatcher) {
            if (!$this->can_be_dispatched) {
                throw new \Exception('Request cannot be dispatched - not in approved status');
            }

            $allItemsDispatched = true;
            $dispatchNotes = [];

            foreach ($this->items as $item) {
                if (($item->quantity_approved ?? 0) <= 0) {
                    continue; // Skip items with no approved quantity
                }

                // Get available stock levels ordered by expiry date (FIFO)
                $stockLevels = StockLevel::where('facility_id', $this->central_store_id)
                    ->where('inventory_item_id', $item->inventory_item_id)
                    ->where('available_stock', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $totalAvailable = $stockLevels->sum('available_stock');
                $approvedQty = $item->quantity_approved;

                if ($totalAvailable >= $approvedQty) {
                    // Full dispatch possible
                    $this->deductStockLevels($stockLevels, $approvedQty, $item);
                    $item->update(['quantity_dispatched' => $approvedQty]);
                } else if ($totalAvailable > 0) {
                    // Partial dispatch
                    $this->deductStockLevels($stockLevels, $totalAvailable, $item);
                    $item->update([
                        'quantity_dispatched' => $totalAvailable,
                        'balance_quantity' => $approvedQty - $totalAvailable
                    ]);
                    $dispatchNotes[] =
                        ($item->inventoryItem?->name ?? 'Unknown Item')
                        . ': Dispatched '
                        . ($totalAvailable ?? 0)
                        . ', Balance '
                        . ((($approvedQty ?? 0) - ($totalAvailable ?? 0)));
                    $allItemsDispatched = false;
                } else {
                    // No stock available
                    $item->update([
                        'quantity_dispatched' => 0,
                        'balance_quantity' => $approvedQty
                    ]);
                    $dispatchNotes[] = "{$item->inventoryItem->name}: No stock available";
                    $allItemsDispatched = false;
                }
            }

            $status = $allItemsDispatched ? self::STATUS_DISPATCHED : self::STATUS_PARTIALLY_DISPATCHED;
            $notes = empty($dispatchNotes) ? $this->notes : ($this->notes ?? '') . "\n\nDispatch Notes:\n" . implode("\n", $dispatchNotes);

            $this->update([
                'status' => $status,
                'dispatched_by' => $dispatcher->id,
                'dispatch_date' => now(),
                'notes' => $notes,
                'estimated_arrival' => now()->addDays(2), // Default 2 days delivery
            ]);

            // Recalculate totals and clear cache
            $this->calculateTotals();
            $this->clearStockAvailabilityCache();

            Log::info("Stock request {$this->request_number} dispatched by {$dispatcher->full_name} - Status: {$status}");

            // Send dispatch notification
            $this->sendDispatchNotification();
        });
    }

    /**
     * Deduct stock from multiple stock levels using FIFO
     */
    private function deductStockLevels($stockLevels, int $quantityToDeduct, $item): void
    {
        $remaining = $quantityToDeduct;

        foreach ($stockLevels as $stockLevel) {
            if ($remaining <= 0) break;

            $toDeduct = min($remaining, $stockLevel->available_stock);

            $stockLevel->adjustStock(
                -$toDeduct,
                "Dispatched to {$this->requestingFacility->name} - Request: {$this->request_number} - Item: {$item->inventoryItem->name}"
            );

            $remaining -= $toDeduct;
        }
    }

    // ===== RECEIVING METHODS =====

    /**
     * Receive dispatched items at requesting facility
     */
    public function receive(User $receiver, array $receivedQuantities): void
    {
        DB::transaction(function () use ($receiver, $receivedQuantities) {
            if (!$this->can_be_received) {
                throw new \Exception('Request cannot be received - not in dispatched status');
            }

            $allItemsReceived = true;

            foreach ($this->items as $item) {
                $receivedQty = $receivedQuantities[$item->id] ?? 0;
                $dispatchedQty = $item->quantity_dispatched ?? 0;

                // Validate received quantity
                if ($receivedQty > $dispatchedQty) {
                    throw new \Exception("Received quantity ({$receivedQty}) cannot exceed dispatched quantity ({$dispatchedQty}) for {$item->inventoryItem->name}");
                }

                $item->update(['quantity_received' => $receivedQty]);

                if ($receivedQty > 0) {
                    // Find or create stock level at receiving facility
                    $facilityStockLevel = StockLevel::firstOrCreate(
                        [
                            'facility_id' => $this->requesting_facility_id,
                            'inventory_item_id' => $item->inventory_item_id,
                            'batch_number' => $item->batch_number ?? null,
                        ],
                        [
                            'current_stock' => 0,
                            'reserved_stock' => 0,
                            'available_stock' => 0,
                            'condition' => 'new',
                            'expiry_date' => $item->expiry_date,
                            'last_updated_by' => $receiver->id,
                        ]
                    );

                    // Add to facility stock
                    $facilityStockLevel->adjustStock(
                        $receivedQty,
                        "Received from central store - Request: {$this->request_number}"
                    );
                }

                if ($receivedQty < $dispatchedQty) {
                    $allItemsReceived = false;
                }
            }

            $status = $allItemsReceived ? self::STATUS_RECEIVED : self::STATUS_PARTIALLY_RECEIVED;

            $this->update([
                'status' => $status,
                'received_by' => $receiver->id,
                'received_date' => now(),
            ]);

            // Recalculate totals
            $this->calculateTotals();

            Log::info("Stock request {$this->request_number} received by {$receiver->full_name} - Status: {$status}");

            $this->sendReceiptNotification();
        });
    }

    // ===== NOTIFICATION METHODS =====

    /**
     * Send approval notification to requesting facility
     */
    public function sendApprovalNotification(): void
    {
        $users = $this->getFacilityNotificationUsers();

        foreach ($users as $user) {
            $user->notify(new \App\Notifications\StockRequestApproved($this));
        }
    }

    /**
     * Send rejection notification with reason
     */
    public function sendRejectionNotification(string $reason): void
    {
        $users = $this->getFacilityNotificationUsers();

        foreach ($users as $user) {
            $user->notify(new \App\Notifications\StockRequestRejected($this, $reason));
        }
    }

    /**
     * Send dispatch notification
     */
    public function sendDispatchNotification(): void
    {
        $users = $this->getFacilityNotificationUsers();

        foreach ($users as $user) {
            $user->notify(new \App\Notifications\StockRequestDispatched($this));
        }
    }

    /**
     * Send receipt confirmation notification
     */
    public function sendReceiptNotification(): void
    {
        // Notify central store that items were received
        $centralStoreUsers = $this->centralStore->users()
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Central Store Manager', 'Store Manager']))
            ->get();

        foreach ($centralStoreUsers as $user) {
            $user->notify(new \App\Notifications\StockRequestReceived($this));
        }
    }

    /**
     * Send creation notification
     */
    public function sendCreationNotification(): void
    {
        // Notify central store managers
        $centralStoreManagers = $this->centralStore->users()
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Central Store Manager', 'Store Manager']))
            ->get();

        foreach ($centralStoreManagers as $manager) {
            $manager->notify(new \App\Notifications\NewStockRequestReceived($this));
        }
    }

    /**
     * Send overdue notification
     */
    public function sendOverdueNotification(): void
    {
        if ($this->status !== self::STATUS_PENDING || !$this->is_overdue) {
            return;
        }

        // Notify central store managers
        $centralStoreManagers = $this->centralStore->users()
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Central Store Manager', 'Store Manager']))
            ->get();

        foreach ($centralStoreManagers as $manager) {
            $manager->notify(new \App\Notifications\OverdueStockRequestAlert($this));
        }

        // Notify facility managers if very overdue (>7 days)
        if ($this->days_pending > 7) {
            $facilityManagers = $this->requestingFacility->users()
                ->whereHas('roles', fn($q) => $q->whereIn('name', ['Facility Manager']))
                ->get();

            foreach ($facilityManagers as $manager) {
                $manager->notify(new \App\Notifications\StockRequestVeryOverdue($this));
            }
        }
    }

    /**
     * Get users to notify at requesting facility
     */
    private function getFacilityNotificationUsers()
    {
        return $this->requestingFacility->users()
            ->whereHas('roles', fn($q) => $q->whereIn('name', [
                'Facility Manager',
                'Store Manager',
                'Store Keeper',
                'Facility In-Charge'
            ]))
            ->get();
    }

    // ===== AUTHORIZATION METHODS =====

    /**
     * Check if user can approve this request
     */
    public function canBeApprovedBy(User $user): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        // Check if user has permission to approve requests
        if (!$user->can('approve-stock-requests')) {
            return false;
        }

        // Check if user can manage the central store
        if (!$user->isAboveSite()) {
            return $user->scopedFacilityIds()->contains($this->central_store_id);
        }

        return true;
    }

    /**
     * Check if user can dispatch this request
     */
    public function canBeDispatchedBy(User $user): bool
    {
        if (!$this->can_be_dispatched) {
            return false;
        }

        return $user->can('dispatch-stock-requests') &&
            ($user->isAboveSite() || $user->scopedFacilityIds()->contains($this->central_store_id));
    }

    /**
     * Check if user can receive this request
     */
    public function canBeReceivedBy(User $user): bool
    {
        if (!$this->can_be_received) {
            return false;
        }

        return $user->can('receive-stock-requests') &&
            ($user->isAboveSite() || $user->scopedFacilityIds()->contains($this->requesting_facility_id));
    }

    // ===== UTILITY METHODS =====

    /**
     * Cancel the request
     */
    public function cancel(User $user, string $reason = null): void
    {
        if (!$this->can_be_cancelled) {
            throw new \Exception('Request cannot be cancelled at this stage');
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'notes' => ($this->notes ?? '') . "\n\nCancelled by {$user->full_name}: " . ($reason ?? 'No reason provided'),
        ]);

        $this->clearStockAvailabilityCache();
        Log::info("Stock request {$this->request_number} cancelled by {$user->full_name}");
    }

    /**
     * Archive the request
     */
    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
        $this->clearStockAvailabilityCache();
        Log::info("Stock request {$this->request_number} archived");
    }

    /**
     * Restore archived request
     */
    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
        Log::info("Stock request {$this->request_number} restored from archive");
    }

    /**
     * Calculate and update total values
     */
    public function calculateTotals(): void
    {
        $this->load('items');

        $totalItems = $this->items->count();
        $totalRequestedValue = $this->items->sum(fn($item) => $item->quantity_requested * $item->unit_price);
        $totalApprovedValue = $this->items->sum(fn($item) => ($item->quantity_approved ?? 0) * $item->unit_price);
        $totalDispatchedValue = $this->items->sum(fn($item) => ($item->quantity_dispatched ?? 0) * $item->unit_price);
        $totalReceivedValue = $this->items->sum(fn($item) => ($item->quantity_received ?? 0) * $item->unit_price);

        // Only update if values have changed to avoid infinite loops
        if (
            $this->total_items !== $totalItems ||
            $this->total_requested_value !== $totalRequestedValue ||
            $this->total_approved_value !== $totalApprovedValue ||
            $this->total_dispatched_value !== $totalDispatchedValue ||
            $this->total_received_value !== $totalReceivedValue
        ) {

            $this->updateQuietly([
                'total_items' => $totalItems,
                'total_requested_value' => $totalRequestedValue,
                'total_approved_value' => $totalApprovedValue,
                'total_dispatched_value' => $totalDispatchedValue,
                'total_received_value' => $totalReceivedValue,
                'total_value' => $totalRequestedValue, // Legacy field
            ]);
        }
    }

    /**
     * Clear stock availability cache
     */
    public function clearStockAvailabilityCache(): void
    {
        Cache::forget("stock_request:{$this->id}:availability");
    }

    // ===== VALIDATION METHODS =====

    /**
     * Validate request before submission
     */
    public function validateForSubmission(): array
    {
        $errors = [];

        if ($this->items->isEmpty()) {
            $errors[] = 'Request must have at least one item';
        }

        if (!$this->requesting_facility_id) {
            $errors[] = 'Requesting facility is required';
        }

        if (!$this->central_store_id) {
            $errors[] = 'Central store is required';
        }

        if ($this->requesting_facility_id === $this->central_store_id) {
            $errors[] = 'Requesting facility cannot be the same as central store';
        }

        foreach ($this->items as $item) {
            if ($item->quantity_requested <= 0) {
                $errors[] = "Invalid quantity for {$item->inventoryItem->name}";
            }
        }

        return $errors;
    }

    /**
     * Validate request before approval
     */
    public function validateForApproval(): array
    {
        $errors = [];

        if ($this->status !== self::STATUS_PENDING) {
            $errors[] = 'Only pending requests can be approved';
        }

        // Check if central store has any stock for requested items
        $hasAnyStock = false;
        foreach ($this->items as $item) {
            $stock = StockLevel::where('facility_id', $this->central_store_id)
                ->where('inventory_item_id', $item->inventory_item_id)
                ->sum('available_stock');

            if ($stock > 0) {
                $hasAnyStock = true;
                break;
            }
        }

        if (!$hasAnyStock) {
            $errors[] = 'Central store has no stock for any requested items';
        }

        return $errors;
    }

    // ===== STATIC HELPER METHODS =====

    /**
     * Get status options for forms
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PARTIALLY_APPROVED => 'Partially Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_DISPATCHED => 'Dispatched',
            self::STATUS_PARTIALLY_DISPATCHED => 'Partially Dispatched',
            self::STATUS_RECEIVED => 'Received',
            self::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get priority options for forms
     */
    public static function getPriorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }

    /**
     * Get approval level options for forms
     */
    public static function getApprovalLevelOptions(): array
    {
        return [
            self::APPROVAL_LEVEL_FACILITY => 'Facility Level',
            self::APPROVAL_LEVEL_REGIONAL => 'Regional Level',
            self::APPROVAL_LEVEL_NATIONAL => 'National Level',
        ];
    }

    /**
     * Get requests by status for dashboard
     */
    public static function getStatusCounts(array $facilityIds = []): array
    {
        $query = static::query()->active();

        if (!empty($facilityIds)) {
            $query->whereIn('central_store_id', $facilityIds);
        }

        return [
            'pending' => $query->clone()->where('status', self::STATUS_PENDING)->count(),
            'approved' => $query->clone()->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PARTIALLY_APPROVED])->count(),
            'dispatched' => $query->clone()->whereIn('status', [self::STATUS_DISPATCHED, self::STATUS_PARTIALLY_DISPATCHED])->count(),
            'received' => $query->clone()->whereIn('status', [self::STATUS_RECEIVED, self::STATUS_PARTIALLY_RECEIVED])->count(),
            'rejected' => $query->clone()->where('status', self::STATUS_REJECTED)->count(),
            'overdue' => $query->clone()->where('status', self::STATUS_PENDING)->where('created_at', '<', now()->subDays(3))->count(),
            'urgent' => $query->clone()->where('status', self::STATUS_PENDING)->where('priority', self::PRIORITY_URGENT)->count(),
        ];
    }

    /**
     * Get priority distribution
     */
    public static function getPriorityDistribution(array $facilityIds = []): array
    {
        $query = static::where('status', self::STATUS_PENDING)->active();

        if (!empty($facilityIds)) {
            $query->whereIn('central_store_id', $facilityIds);
        }

        return [
            'urgent' => $query->clone()->where('priority', self::PRIORITY_URGENT)->count(),
            'high' => $query->clone()->where('priority', self::PRIORITY_HIGH)->count(),
            'medium' => $query->clone()->where('priority', self::PRIORITY_MEDIUM)->count(),
            'low' => $query->clone()->where('priority', self::PRIORITY_LOW)->count(),
        ];
    }

    /**
     * Get requests requiring attention
     */
    public static function getRequestsRequiringAttention(array $facilityIds = []): array
    {
        $query = static::where('status', self::STATUS_PENDING)->active();

        if (!empty($facilityIds)) {
            $query->whereIn('central_store_id', $facilityIds);
        }

        return [
            'urgent_requests' => $query->clone()->where('priority', self::PRIORITY_URGENT)->get(),
            'overdue_requests' => $query->clone()->where('created_at', '<', now()->subDays(3))->get(),
            'high_value_requests' => $query->clone()->where('total_requested_value', '>', 100000)->get(), // KES 100,000+
            'old_requests' => $query->clone()->where('created_at', '<', now()->subWeek())->get(),
        ];
    }

    /**
     * Search requests by multiple criteria
     */
    public static function search(string $search): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->active()
            ->where(function ($query) use ($search) {
                $query->where('request_number', 'like', "%{$search}%")
                    ->orWhereHas('requestingFacility', fn($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('centralStore', fn($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('requestedBy', fn($q) => $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('items.inventoryItem', fn($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%"));
            });
    }

    /**
     * Bulk approve multiple requests
     */
    public static function bulkApprove(array $requestIds, User $approver): array
    {
        $results = [
            'approved' => [],
            'failed' => [],
            'errors' => []
        ];

        foreach ($requestIds as $requestId) {
            try {
                $request = static::findOrFail($requestId);

                if ($request->canBeQuickApproved()) {
                    $request->quickApprove($approver);
                    $results['approved'][] = $request->request_number;
                } else {
                    $results['failed'][] = [
                        'request_number' => $request->request_number,
                        'reason' => 'Insufficient stock for quick approval'
                    ];
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'request_id' => $requestId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk reject multiple requests
     */
    public static function bulkReject(array $requestIds, User $approver, string $reason): array
    {
        $results = [
            'rejected' => [],
            'errors' => []
        ];

        foreach ($requestIds as $requestId) {
            try {
                $request = static::findOrFail($requestId);
                $request->reject($approver, $reason);
                $results['rejected'][] = $request->request_number;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'request_id' => $requestId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Create stock request from array data
     */
    public static function createFromData(array $data): self
    {
        DB::beginTransaction();

        try {
            $request = static::create([
                'requesting_facility_id' => $data['requesting_facility_id'],
                'central_store_id' => $data['central_store_id'],
                'requested_by' => $data['requested_by'] ?? auth()->id(),
                'priority' => $data['priority'] ?? self::PRIORITY_MEDIUM,
                'request_date' => $data['request_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'requires_approval' => $data['requires_approval'] ?? true,
                'approval_level' => $data['approval_level'] ?? self::APPROVAL_LEVEL_FACILITY,
            ]);

            // Add items
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $request->items()->create([
                        'inventory_item_id' => $itemData['inventory_item_id'],
                        'quantity_requested' => $itemData['quantity_requested'],
                        'unit_price' => $itemData['unit_price'],
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }
            }

            $request->calculateTotals();

            DB::commit();
            return $request;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Duplicate existing request
     */
    public function duplicate(array $overrides = []): self
    {
        DB::beginTransaction();

        try {
            $newRequest = static::create(array_merge([
                'requesting_facility_id' => $this->requesting_facility_id,
                'central_store_id' => $this->central_store_id,
                'requested_by' => auth()->id(),
                'priority' => $this->priority,
                'request_date' => now(),
                'notes' => "Duplicated from {$this->request_number}",
                'requires_approval' => $this->requires_approval,
                'approval_level' => $this->approval_level,
            ], $overrides));

            // Duplicate items
            foreach ($this->items as $item) {
                $newRequest->items()->create([
                    'inventory_item_id' => $item->inventory_item_id,
                    'quantity_requested' => $item->quantity_requested,
                    'unit_price' => $item->unit_price,
                    'notes' => $item->notes,
                ]);
            }

            $newRequest->calculateTotals();

            DB::commit();
            return $newRequest;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Archive old completed requests
     */
    public static function archiveOldRequests(int $daysOld = 365): int
    {
        $cutoffDate = now()->subDays($daysOld);

        $archived = static::whereIn('status', [
            self::STATUS_RECEIVED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED
        ])
            ->where('updated_at', '<', $cutoffDate)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        Log::info("Archived {$archived} old stock requests");

        return $archived;
    }

    // ===== REPORTING METHODS =====

    /**
     * Get monthly request statistics
     */
    public static function getMonthlyStats(int $year, int $month): array
    {
        $startDate = now()->setYear($year)->setMonth($month)->startOfMonth();
        $endDate = now()->setYear($year)->setMonth($month)->endOfMonth();

        $requests = static::whereBetween('created_at', [$startDate, $endDate])->active()->get();

        return [
            'total_requests' => $requests->count(),
            'total_value' => $requests->sum('total_requested_value'),
            'approved_requests' => $requests->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PARTIALLY_APPROVED, self::STATUS_DISPATCHED, self::STATUS_RECEIVED])->count(),
            'rejected_requests' => $requests->where('status', self::STATUS_REJECTED)->count(),
            'pending_requests' => $requests->where('status', self::STATUS_PENDING)->count(),
            'average_processing_time' => $requests->where('approved_date')->avg(function ($request) {
                return $request->approved_date->diffInHours($request->created_at);
            }),
            'status_breakdown' => $requests->groupBy('status')->map->count(),
            'priority_breakdown' => $requests->groupBy('priority')->map->count(),
            'facility_breakdown' => $requests->groupBy('requestingFacility.name')->map->count()->sortDesc()->take(10),
        ];
    }

    /**
     * Get facility performance metrics
     */
    public static function getFacilityPerformance(int $facilityId, int $months = 6): array
    {
        $startDate = now()->subMonths($months);

        $requests = static::where('requesting_facility_id', $facilityId)
            ->where('created_at', '>=', $startDate)
            ->active()
            ->get();

        return [
            'total_requests' => $requests->count(),
            'total_value' => $requests->sum('total_requested_value'),
            'approval_rate' => $requests->count() > 0 ?
                ($requests->whereNotIn('status', [self::STATUS_PENDING, self::STATUS_REJECTED])->count() / $requests->count()) * 100 : 0,
            'average_request_value' => $requests->avg('total_requested_value'),
            'most_requested_items' => $requests->flatMap->items
                ->groupBy('inventory_item_id')
                ->map(function ($items) {
                    return [
                        'item_name' => $items->first()->inventoryItem->name,
                        'total_quantity' => $items->sum('quantity_requested'),
                        'total_requests' => $items->count(),
                    ];
                })
                ->sortByDesc('total_quantity')
                ->take(10)
                ->values(),
            'monthly_trend' => $requests->groupBy(function ($request) {
                return $request->created_at->format('Y-m');
            })->map->count(),
        ];
    }

    /**
     * Export requests to array for Excel/CSV
     */
    public function toExportArray(): array
    {
        return [
            'Request Number' => $this->request_number,
            'Requesting Facility' => $this->requestingFacility->name,
            'Central Store' => $this->centralStore->name,
            'Requested By' => $this->requestedBy->full_name,
            'Status' => ucfirst(str_replace('_', ' ', $this->status)),
            'Priority' => ucfirst($this->priority),
            'Request Date' => $this->request_date->format('Y-m-d'),
            'Approved Date' => $this->approved_date?->format('Y-m-d'),
            'Dispatch Date' => $this->dispatch_date?->format('Y-m-d'),
            'Received Date' => $this->received_date?->format('Y-m-d'),
            'Total Items' => $this->total_items,
            'Total Requested Value' => $this->total_requested_value,
            'Total Approved Value' => $this->total_approved_value,
            'Total Dispatched Value' => $this->total_dispatched_value,
            'Total Received Value' => $this->total_received_value,
            'Days Pending' => $this->days_pending,
            'Notes' => $this->notes,
            'Rejection Reason' => $this->rejection_reason,
        ];
    }

    /**
     * Convert to API response format
     */
    public function toApiResponse(): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'status' => $this->status,
            'priority' => $this->priority,
            'requesting_facility' => [
                'id' => $this->requestingFacility->id,
                'name' => $this->requestingFacility->name,
            ],
            'central_store' => [
                'id' => $this->centralStore->id,
                'name' => $this->centralStore->name,
            ],
            'requested_by' => [
                'id' => $this->requestedBy->id,
                'name' => $this->requestedBy->full_name,
            ],
            'dates' => [
                'requested' => $this->request_date?->toISOString(),
                'approved' => $this->approved_date?->toISOString(),
                'dispatched' => $this->dispatch_date?->toISOString(),
                'received' => $this->received_date?->toISOString(),
            ],
            'totals' => [
                'items' => $this->total_items,
                'requested_value' => $this->total_requested_value,
                'approved_value' => $this->total_approved_value,
                'dispatched_value' => $this->total_dispatched_value,
                'received_value' => $this->total_received_value,
            ],
            'workflow' => [
                'progress_percentage' => $this->progress_percentage,
                'can_be_approved' => $this->can_be_approved,
                'can_be_dispatched' => $this->can_be_dispatched,
                'can_be_received' => $this->can_be_received,
                'can_be_edited' => $this->can_be_edited,
                'can_be_cancelled' => $this->can_be_cancelled,
            ],
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'inventory_item' => [
                        'id' => $item->inventoryItem->id,
                        'name' => $item->inventoryItem->name,
                        'sku' => $item->inventoryItem->sku,
                    ],
                    'quantities' => [
                        'requested' => $item->quantity_requested,
                        'approved' => $item->quantity_approved,
                        'dispatched' => $item->quantity_dispatched,
                        'received' => $item->quantity_received,
                    ],
                    'unit_price' => $item->unit_price,
                    'notes' => $item->notes,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    // ===== MODEL EVENTS =====

    protected static function boot()
    {
        parent::boot();

        // Auto-generate request number
        static::creating(function ($model) {
            if (empty($model->request_number)) {
                $model->request_number = 'REQ-' . str_pad(static::count() + 1, 6, '0', STR_PAD_LEFT);
            }

            // Set default values
            $model->status = $model->status ?? self::STATUS_PENDING;
            $model->priority = $model->priority ?? self::PRIORITY_MEDIUM;
            $model->request_date = $model->request_date ?? now();
            $model->requires_approval = $model->requires_approval ?? true;
            $model->approval_level = $model->approval_level ?? self::APPROVAL_LEVEL_FACILITY;
        });

        // Send creation notification when request is created
        static::created(function ($request) {
            $request->sendCreationNotification();
        });

        // Calculate totals when request is saved
        static::saved(function ($model) {
            if ($model->relationLoaded('items')) {
                $model->calculateTotals();
            }
        });

        // Clear cache when request is updated
        static::updated(function ($request) {
            $request->clearStockAvailabilityCache();
        });

        // Log status changes
        static::updating(function ($model) {
            if ($model->isDirty('status')) {
                $oldStatus = $model->getOriginal('status');
                $newStatus = $model->status;

                Log::info("Stock request {$model->request_number} status changed from {$oldStatus} to {$newStatus}");
            }
        });

        // Clean up related data when request is deleted
        static::deleting(function ($request) {
            // Soft delete related items
            $request->items()->delete();

            // Clear cache
            $request->clearStockAvailabilityCache();

            Log::info("Stock request {$request->request_number} deleted");
        });

        // Handle restoration
        static::restored(function ($request) {
            // Restore related items
            $request->items()->restore();

            Log::info("Stock request {$request->request_number} restored");
        });
    }
}
