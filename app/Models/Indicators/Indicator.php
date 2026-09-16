<?php

namespace App\Models\Indicators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicator extends Model { 

    protected $fillable = [
        'group_id',
        'parent_indicator_id',
        'code',
        'name',
        'short_name',
        'indicator_type',
        'category',
        'has_numerator',
        'has_denominator',
        'numerator_label',
        'denominator_label',
        'source_document',
        'source_document_code',
        'dhis2_numerator_uid',
        'dhis2_denominator_uid',
        'dhis2_indicator_uid',
        'dhis2_data_element_uid',
        'min_value',
        'max_value',
        'display_hint',
        'definition',
        'is_active',
        'sort_order',
    ];
    protected $casts = [
        'has_numerator' => 'boolean',
        'has_denominator' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'min_value' => 'integer',
        'max_value' => 'integer',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────────

    public function group(): BelongsTo {
        return $this->belongsTo(IndicatorGroup::class, 'group_id');
    }

    public function parent(): BelongsTo {
        return $this->belongsTo(Indicator::class, 'parent_indicator_id');
    }

    /**
     * Sub-band indicators (e.g., weight bands, gestational age bands).
     */
    public function children(): HasMany {
        return $this->hasMany(Indicator::class, 'parent_indicator_id')
                        ->orderBy('sort_order');
    }

    public function values(): HasMany {
        return $this->hasMany(IndicatorValue::class, 'indicator_id');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────────────────

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel($query) {
        return $query->whereNull('parent_indicator_id');
    }

    public function scopeOfType($query, string $type) {
        return $query->where('indicator_type', $type);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Type helpers
    // ──────────────────────────────────────────────────────────────────────────

    public function isProportion(): bool {
        return $this->indicator_type === 'proportion';
    }

    public function isCount(): bool {
        return $this->indicator_type === 'count';
    }

    public function isYesNo(): bool {
        return $this->indicator_type === 'yes_no';
    }

    public function isRate(): bool {
        return $this->indicator_type === 'rate';
    }

    public function hasBands(): bool {
        return $this->children()->exists();
    }

    public function isDhis2Ready(): bool {
        return match ($this->indicator_type) {
            'proportion' => !empty($this->dhis2_numerator_uid) && !empty($this->dhis2_denominator_uid),
            'count', 'yes_no' => !empty($this->dhis2_data_element_uid),
            default => false,
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Display
    // ──────────────────────────────────────────────────────────────────────────

    public function getDisplayNameAttribute(): string {
        return $this->short_name ?? $this->name;
    }

    public function getFullCodeAttribute(): string {
        return $this->group->code . '_' . $this->code;
    }
}
