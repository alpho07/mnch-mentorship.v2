<?php

namespace App\Models\Indicators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndicatorGroup extends Model { 

    protected $fillable = [
        'report_type_id',
        'code',
        'name',
        'description',
        'dhis2_section_id',
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

    public function reportType(): BelongsTo {
        return $this->belongsTo(IndicatorReportType::class, 'report_type_id');
    }

    public function indicators(): HasMany {
        return $this->hasMany(Indicator::class, 'group_id')
                        ->whereNull('parent_indicator_id') // top-level only by default
                        ->orderBy('sort_order');
    }

    public function allIndicators(): HasMany {
        return $this->hasMany(Indicator::class, 'group_id')
                        ->orderBy('sort_order');
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

    public function getCompletionRateAttribute(): ?float {
        // Computed externally via service — placeholder
        return null;
    }
}
