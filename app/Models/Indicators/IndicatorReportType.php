<?php

namespace App\Models\Indicators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class IndicatorReportType extends Model { 

    protected $fillable = [
        'code',
        'name',
        'description',
        'color',
        'icon',
        'dhis2_dataset_id',
        'dhis2_org_unit_level',
        'is_active',
        'sort_order',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────────

    public function groups(): HasMany {
        return $this->hasMany(IndicatorGroup::class, 'report_type_id')
                        ->orderBy('sort_order');
    }

    public function frequencies(): BelongsToMany {
        return $this->belongsToMany(
                        IndicatorFrequency::class,
                        'indicator_report_type_frequencies',
                        'report_type_id',
                        'frequency_id'
                );
    }

    public function reportPeriods(): HasMany {
        return $this->hasMany(IndicatorReportPeriod::class, 'report_type_id');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────────────────

    public function scopeActive($query) {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    public function isDhis2Configured(): bool {
        return !empty($this->dhis2_dataset_id);
    }

    /**
     * Count of all indicators across all groups of this type.
     */
    public function getTotalIndicatorsCountAttribute(): int {
        return $this->groups()->withCount('indicators')->get()->sum('indicators_count');
    }
}
