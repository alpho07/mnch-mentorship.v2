<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Indicators\FacilityIndicatorAssignment;

class Facility extends Model {

    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'name',
        'uid',
        'mfl_code',
        'subcounty_id',
        'ward',
        'facility_level_id',
        'facility_type_id',
        'facility_ownership_id',
        'latitude',
        'longitude',
        'physical_address',
        'postal_address',
        'telephone',
        'email',
        'incharge_name',
        'incharge_designation',
        'incharge_contact',
        'is_active',
        'notes',
        'is_hub',
        'hub_id',
        'is_central_store',
        'storage_capacity',
        'operating_hours',
    ];
    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
        'is_hub' => 'boolean',
        'is_central_store' => 'boolean',
        'operating_hours' => 'array',
    ];
    protected $with = ['subcounty', 'facilityType', 'facilityLevel', 'facilityOwnership'];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Location & Classification Relationships
     */
    public function subcounty(): BelongsTo {
        return $this->belongsTo(Subcounty::class);
    }

    public function facilityType(): BelongsTo {
        return $this->belongsTo(FacilityType::class);
    }

    public function facilityLevel(): BelongsTo {
        return $this->belongsTo(FacilityLevel::class);
    }

    public function facilityOwnership(): BelongsTo {
        return $this->belongsTo(FacilityOwnership::class);
    }

    /**
     * Hub & Spoke Relationships (Training/Mentorship)
     */
    public function hub(): BelongsTo {
        return $this->belongsTo(Facility::class, 'hub_id');
    }

    public function spokes(): HasMany {
        return $this->hasMany(Facility::class, 'hub_id');
    }

    /**
     * User Relationships
     */
    public function users(): HasMany {
        return $this->hasMany(User::class);
    }

    public function scopedUsers(): BelongsToMany {
        return $this->belongsToMany(User::class, 'facility_user');
    }

    /**
     * Training & Assessment Relationships
     */
    public function trainings(): HasMany {
        return $this->hasMany(Training::class);
    }

    public function assessments(): HasMany {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Inventory Management Relationships
     */
    public function stockLevels(): HasMany {
        return $this->hasMany(StockLevel::class);
    }

    public function stockRequests(): HasMany {
        return $this->hasMany(StockRequest::class, 'requesting_facility_id');
    }

    public function centralStoreRequests(): HasMany {
        return $this->hasMany(StockRequest::class, 'central_store_id');
    }

    public function outgoingTransfers(): HasMany {
        return $this->hasMany(StockTransfer::class, 'from_facility_id');
    }

    public function incomingTransfers(): HasMany {
        return $this->hasMany(StockTransfer::class, 'to_facility_id');
    }

    public function inventoryTransactions(): HasMany {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Reporting Relationships
     */
    public function reportTemplates(): BelongsToMany {
        return $this->belongsToMany(ReportTemplate::class, 'facility_report_templates')
                        ->withPivot(['start_date', 'end_date'])
                        ->withTimestamps();
    }

    public function monthlyReports(): HasMany {
        return $this->hasMany(MonthlyReport::class);
    }

    // ============================================
    // QUERY SCOPES
    // ============================================

    /**
     * Filter by subcounty
     */
    public function scopeBySubcounty($query, int $subcountyId) {
        return $query->where('subcounty_id', $subcountyId);
    }

    /**
     * Filter by facility level
     */
    public function scopeByLevel($query, int $levelId) {
        return $query->where('facility_level_id', $levelId);
    }

    /**
     * Filter by ownership type
     */
    public function scopeByOwnership($query, int $ownershipId) {
        return $query->where('facility_ownership_id', $ownershipId);
    }

    /**
     * Filter by facility type
     */
    public function scopeByType($query, int $facilityTypeId) {
        return $query->where('facility_type_id', $facilityTypeId);
    }

    /**
     * Only active facilities
     */
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    /**
     * Only hub facilities
     */
    public function scopeHubs($query) {
        return $query->where('is_hub', true);
    }

    /**
     * Only spoke facilities
     */
    public function scopeSpokes($query) {
        return $query->where('is_hub', false)->whereNotNull('hub_id');
    }

    /**
     * Standalone facilities (not hub or spoke)
     */
    public function scopeStandalone($query) {
        return $query->where('is_hub', false)->whereNull('hub_id');
    }

    /**
     * Only central store facilities
     */
    public function scopeCentralStores($query) {
        return $query->where('is_central_store', true);
    }

    /**
     * Facilities within a radius (km) from given coordinates
     */
    public function scopeWithinRadius($query, float $lat, float $lng, float $radius) {
        return $query->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->selectRaw("*, (
                        6371 * acos(
                            cos(radians(?)) *
                            cos(radians(latitude)) *
                            cos(radians(longitude) - radians(?)) +
                            sin(radians(?)) *
                            sin(radians(latitude))
                        )
                    ) AS distance", [$lat, $lng, $lat])
                        ->having('distance', '<', $radius)
                        ->orderBy('distance');
    }

    /**
     * Facilities with GPS coordinates
     */
    public function scopeWithCoordinates($query) {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    /**
     * Facilities by county
     */
    public function scopeByCounty($query, int $countyId) {
        return $query->whereHas('subcounty', function ($q) use ($countyId) {
                    $q->where('county_id', $countyId);
                });
    }

    // ============================================
    // COMPUTED ATTRIBUTES
    // ============================================

    /**
     * Get county name through subcounty relationship
     */
    public function getCountyAttribute(): ?string {
        return $this->subcounty?->county?->name;
    }

    /**
     * Get coordinates as array
     */
    public function getCoordinatesAttribute(): ?array {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ];
        }

        return null;
    }

    /**
     * Get facility in-charge details
     */
    public function getInchargeDetailsAttribute(): array {
        return [
            'name' => $this->incharge_name,
            'designation' => $this->incharge_designation,
            'contact' => $this->incharge_contact,
        ];
    }

    /**
     * Get full address formatted
     */
    public function getFullAddressAttribute(): string {
        $parts = array_filter([
            $this->physical_address,
            $this->ward,
            $this->subcounty?->name,
            $this->county,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get number of spoke facilities (if hub)
     */
    public function getSpokeCountAttribute(): int {
        return $this->spokes()->count();
    }

    /**
     * Get number of trainings conducted
     */
    public function getTrainingCountAttribute(): int {
        return $this->trainings()->count();
    }

    /**
     * Get number of assessments conducted
     */
    public function getAssessmentCountAttribute(): int {
        return $this->assessments()->count();
    }

    /**
     * Get active report templates
     */
    public function getActiveReportTemplatesAttribute() {
        return $this->reportTemplates()
                        ->wherePivot('start_date', '<=', now())
                        ->where(function ($query) {
                            $query->wherePivot('end_date', '>=', now())
                                    ->orWherePivot('end_date', null);
                        })
                        ->where('is_active', true)
                        ->get();
    }

    // ============================================
    // INVENTORY MANAGEMENT COMPUTED ATTRIBUTES
    // ============================================

    /**
     * Get total stock value at facility
     */
    public function getTotalStockValueAttribute(): float {
        return $this->stockLevels()
                        ->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
                        ->selectRaw('SUM(stock_levels.current_stock * inventory_items.unit_price) as total')
                        ->value('total') ?? 0;
    }

    /**
     * Get count of low stock items
     */
    public function getLowStockItemsCountAttribute(): int {
        return $this->stockLevels()
                        ->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
                        ->whereColumn('stock_levels.current_stock', '<=', 'inventory_items.reorder_point')
                        ->count();
    }

    /**
     * Get count of out of stock items
     */
    public function getOutOfStockItemsCountAttribute(): int {
        return $this->stockLevels()
                        ->where('current_stock', '<=', 0)
                        ->count();
    }

    /**
     * Get central store stock summary (if central store)
     */
    public function getCentralStoreStockSummaryAttribute(): array {
        if (!$this->is_central_store) {
            return [];
        }

        $stockLevels = $this->stockLevels()->with('inventoryItem')->get();

        return [
            'total_items' => $stockLevels->count(),
            'total_quantity' => $stockLevels->sum('current_stock'),
            'total_value' => $stockLevels->sum('stock_value'),
            'available_quantity' => $stockLevels->sum('available_stock'),
            'reserved_quantity' => $stockLevels->sum('reserved_stock'),
            'low_stock_items' => $stockLevels->filter(fn($sl) => $sl->is_low_stock)->count(),
            'out_of_stock_items' => $stockLevels->where('current_stock', '<=', 0)->count(),
        ];
    }

    // ============================================
    // HELPER METHODS - BASIC CHECKS
    // ============================================

    /**
     * Check if facility has GPS coordinates
     */
    public function hasCoordinates(): bool {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Check if facility has in-charge information
     */
    public function hasIncharge(): bool {
        return !is_null($this->incharge_name) && !is_null($this->incharge_contact);
    }

    /**
     * Check if facility is ready for assessment
     */
    public function isReadyForAssessment(): bool {
        return $this->is_active && !is_null($this->mfl_code) && $this->hasIncharge();
    }

    /**
     * Check if this is a central store
     */
    public function isCentralStore(): bool {
        return $this->is_central_store;
    }

    /**
     * Check if this is a hub facility
     */
    public function isHub(): bool {
        return $this->is_hub;
    }

    /**
     * Check if this is a spoke facility
     */
    public function isSpoke(): bool {
        return !$this->is_hub && !is_null($this->hub_id);
    }

    // ============================================
    // INVENTORY MANAGEMENT METHODS
    // ============================================

    /**
     * Get stock level for a specific inventory item
     */
    public function getStockLevel(int $inventoryItemId): ?StockLevel {
        return $this->stockLevels()
                        ->where('inventory_item_id', $inventoryItemId)
                        ->first();
    }

    /**
     * Check if facility has sufficient stock
     */
    public function hasStock(int $inventoryItemId, int $quantity = 1): bool {
        $stockLevel = $this->getStockLevel($inventoryItemId);
        return $stockLevel && $stockLevel->available_stock >= $quantity;
    }

    /**
     * Get nearby facilities within radius
     */
    public function getNearbyFacilities(float $radius = 50): \Illuminate\Database\Eloquent\Collection {
        if (!$this->hasCoordinates()) {
            return collect();
        }

        return static::withinRadius($this->latitude, $this->longitude, $radius)
                        ->where('id', '!=', $this->id)
                        ->get();
    }

    /**
     * Check if this facility can receive transfers from another facility
     */
    public function canReceiveTransfersFrom(Facility $fromFacility): bool {
        // Same subcounty transfers are always allowed
        if ($this->subcounty_id === $fromFacility->subcounty_id) {
            return true;
        }

        // Hub to spoke transfers
        if ($fromFacility->is_hub && $this->hub_id === $fromFacility->id) {
            return true;
        }

        // Central store to any facility
        if ($fromFacility->is_central_store) {
            return true;
        }

        return false;
    }

    /**
     * Get the central store responsible for this facility
     */
    public function getCentralStoreForFacility(): ?Facility {
        if ($this->is_central_store) {
            return $this;
        }

        // First try to find central store in same subcounty
        $centralStore = Facility::centralStores()
                ->where('subcounty_id', $this->subcounty_id)
                ->first();

        // If not found, try same county
        if (!$centralStore) {
            $centralStore = Facility::centralStores()
                    ->whereHas('subcounty', fn($q) => $q->where('county_id', $this->subcounty->county_id))
                    ->first();
        }

        return $centralStore;
    }

    /**
     * Get facilities that this central store serves
     */
    public function getDistributionFacilities(): \Illuminate\Database\Eloquent\Collection {
        if (!$this->is_central_store) {
            return collect();
        }

        return Facility::where('is_central_store', false)
                        ->where(function ($query) {
                            $query->where('subcounty_id', $this->subcounty_id)
                                    ->orWhereHas('subcounty', fn($q) => $q->where('county_id', $this->subcounty->county_id));
                        })
                        ->get();
    }

    /**
     * Get total stock grouped by category (for central stores)
     */
    public function getTotalStockAtCentralStore(): array {
        if (!$this->is_central_store) {
            return [];
        }

        return $this->stockLevels()
                        ->with('inventoryItem.category')
                        ->get()
                        ->groupBy('inventoryItem.category.name')
                        ->map(function ($items) {
                            return [
                                'total_items' => $items->count(),
                                'total_quantity' => $items->sum('current_stock'),
                                'total_value' => $items->sum('stock_value'),
                                'available_quantity' => $items->sum('available_stock'),
                            ];
                        })
                        ->toArray();
    }

    /**
     * Get pending distribution requests (for central stores)
     */
    public function getPendingDistributions(): \Illuminate\Database\Eloquent\Collection {
        if (!$this->is_central_store) {
            return collect();
        }

        return StockRequest::where('central_store_id', $this->id)
                        ->whereIn('status', ['approved', 'partially_approved'])
                        ->with(['requestingFacility', 'items.inventoryItem'])
                        ->get();
    }

    // ============================================
    // HUB & SPOKE METHODS
    // ============================================

    /**
     * Get all spoke facilities under this hub
     */
    public function getSpokeFacilities(): \Illuminate\Database\Eloquent\Collection {
        if (!$this->is_hub) {
            return collect();
        }

        return $this->spokes()->with(['facilityType', 'facilityLevel'])->get();
    }

    /**
     * Get training statistics for hub
     */
    public function getHubTrainingStatistics(): array {
        if (!$this->is_hub) {
            return [];
        }

        $spokeFacilities = $this->spokes()->pluck('id');

        return [
            'hub_trainings' => $this->trainings()->count(),
            'spoke_trainings' => Training::whereIn('facility_id', $spokeFacilities)->count(),
            'total_trainings' => $this->trainings()->count() + Training::whereIn('facility_id', $spokeFacilities)->count(),
            'spoke_count' => $this->spokes()->count(),
        ];
    }

    // ============================================
    // ASSESSMENT METHODS
    // ============================================

    /**
     * Get latest assessment
     */
    public function getLatestAssessment(): ?Assessment {
        return $this->assessments()
                        ->latest('assessment_date')
                        ->first();
    }

    /**
     * Get assessments by type
     */
    public function getAssessmentsByType(string $type): \Illuminate\Database\Eloquent\Collection {
        return $this->assessments()
                        ->whereHas('assessmentType', fn($q) => $q->where('code', $type))
                        ->with(['assessmentType', 'assessor'])
                        ->orderBy('assessment_date', 'desc')
                        ->get();
    }

    /**
     * Check if facility has completed a specific assessment type
     */
    public function hasCompletedAssessment(string $assessmentTypeCode): bool {
        return $this->assessments()
                        ->whereHas('assessmentType', fn($q) => $q->where('code', $assessmentTypeCode))
                        ->where('status', 'completed')
                        ->exists();
    }

    /**
     * Get assessment completion rate
     */
    public function getAssessmentCompletionRate(): float {
        $total = $this->assessments()->count();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->assessments()->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }

    // ============================================
    // REPORTING METHODS
    // ============================================

    /**
     * Get facility statistics summary
     */
    public function getStatisticsSummary(): array {
        return [
            'basic_info' => [
                'name' => $this->name,
                'mfl_code' => $this->mfl_code,
                'level' => $this->facilityLevel->name,
                'type' => $this->facilityType->name,
                'ownership' => $this->facilityOwnership->name,
                'county' => $this->county,
                'subcounty' => $this->subcounty->name,
            ],
            'trainings' => [
                'total' => $this->trainings()->count(),
                'completed' => $this->trainings()->where('status', 'completed')->count(),
                'ongoing' => $this->trainings()->where('status', 'ongoing')->count(),
            ],
            'assessments' => [
                'total' => $this->assessments()->count(),
                'completed' => $this->assessments()->where('status', 'completed')->count(),
                'pending' => $this->assessments()->where('status', 'pending')->count(),
            ],
            'inventory' => [
                'total_value' => $this->total_stock_value,
                'low_stock_items' => $this->low_stock_items_count,
                'out_of_stock_items' => $this->out_of_stock_items_count,
            ],
            'hub_spoke' => [
                'is_hub' => $this->is_hub,
                'spoke_count' => $this->is_hub ? $this->spoke_count : 0,
                'parent_hub' => $this->isSpoke() ? $this->hub->name : null,
            ],
        ];
    }

    // ============================================
    // VALIDATION METHODS
    // ============================================

    /**
     * Validate facility data completeness
     */
    public function getDataCompletenessScore(): array {
        $requiredFields = [
            'name' => !empty($this->name),
            'mfl_code' => !empty($this->mfl_code),
            'subcounty_id' => !empty($this->subcounty_id),
            'facility_level_id' => !empty($this->facility_level_id),
            'facility_type_id' => !empty($this->facility_type_id),
            'facility_ownership_id' => !empty($this->facility_ownership_id),
            'coordinates' => $this->hasCoordinates(),
            'physical_address' => !empty($this->physical_address),
            'telephone' => !empty($this->telephone),
            'email' => !empty($this->email),
            'incharge_name' => !empty($this->incharge_name),
            'incharge_contact' => !empty($this->incharge_contact),
        ];

        $completed = count(array_filter($requiredFields));
        $total = count($requiredFields);
        $percentage = round(($completed / $total) * 100, 2);

        return [
            'completed_fields' => $completed,
            'total_fields' => $total,
            'percentage' => $percentage,
            'missing_fields' => array_keys(array_filter($requiredFields, fn($v) => !$v)),
        ];
    }

    /**
     * The indicator reporting configuration for this facility.
     */
    public function indicatorAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(FacilityIndicatorAssignment::class, 'facility_id');
    }

    /**
     * Check whether this facility is configured and ready for indicator reporting.
     */
    public function isReadyForIndicatorReporting(): bool {
        return $this->indicatorAssignment?->is_locked && !empty($this->indicatorAssignment?->enabled_report_types);
    }
}
