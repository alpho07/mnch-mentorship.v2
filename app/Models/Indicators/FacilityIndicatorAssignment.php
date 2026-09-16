<?php

namespace App\Models\Indicators;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityIndicatorAssignment extends Model { 

    protected $fillable = [
        'facility_id',
        'enabled_report_types',
        'is_locked',
        'locked_at',
        'locked_by',
        'last_updated_at',
        'last_updated_by',
        'notes',
    ];
    protected $casts = [
        'enabled_report_types' => 'array',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────────

    public function facility(): BelongsTo {
        return $this->belongsTo(Facility::class);
    }

    public function lockedByUser(): BelongsTo {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function lastUpdatedByUser(): BelongsTo {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    public function hasReportType(int $reportTypeId): bool {
        return in_array($reportTypeId, $this->enabled_report_types ?? []);
    }

    public function enabledReportTypeModels() {
        return IndicatorReportType::whereIn('id', $this->enabled_report_types ?? [])->get();
    }

    public function lock(int $userId): void {
        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $userId,
        ]);
    }

    public function unlock(int $userId): void {
        $this->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
            'last_updated_at' => now(),
            'last_updated_by' => $userId,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Static helpers
    // ──────────────────────────────────────────────────────────────────────────

    public static function forFacility(int $facilityId): ?self {
        return static::where('facility_id', $facilityId)->first();
    }

    public static function findOrCreateForFacility(int $facilityId): self {
        return static::firstOrCreate(['facility_id' => $facilityId]);
    }
}
