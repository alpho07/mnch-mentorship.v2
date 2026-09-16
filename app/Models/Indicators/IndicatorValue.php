<?php

namespace App\Models\Indicators;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorValue extends Model {

    protected $fillable = [
        'period_id',
        'indicator_id',
        'numerator_value',
        'denominator_value',
        'computed_percentage',
        'count_value',
        'yes_no_value',
        'comment',
        'created_by',
        'updated_by',
    ];
    protected $casts = [
        'numerator_value' => 'integer',
        'denominator_value' => 'integer',
        'computed_percentage' => 'float',
        'count_value' => 'integer',
        'yes_no_value' => 'boolean',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────────

    public function period(): BelongsTo {
        return $this->belongsTo(IndicatorReportPeriod::class, 'period_id');
    }

    public function indicator(): BelongsTo {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function createdByUser(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Computed percentage
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Recompute the stored percentage from numerator / denominator.
     * Call after setting numerator_value or denominator_value.
     */
    public function recomputePercentage(): void {
        if (
                $this->indicator->isProportion() && is_numeric($this->numerator_value) && is_numeric($this->denominator_value) && $this->denominator_value > 0
        ) {
            $this->computed_percentage = round(
                    ($this->numerator_value / $this->denominator_value) * 100,
                    4
            );
        } else {
            $this->computed_percentage = null;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Validation
    // ──────────────────────────────────────────────────────────────────────────

    public function isNumeratorExceedsDenominator(): bool {
        return $this->indicator->isProportion() && is_numeric($this->numerator_value) && is_numeric($this->denominator_value) && $this->numerator_value > $this->denominator_value;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DHIS2 payload fragment
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build the dataValues array entries for DHIS2 API push.
     * Returns an array of [dataElement => UID, value => X] pairs.
     */
    public function toDhis2DataValues(): array {
        $indicator = $this->indicator;
        $entries = [];

        if ($indicator->isProportion()) {
            if ($indicator->dhis2_numerator_uid && $this->numerator_value !== null) {
                $entries[] = [
                    'dataElement' => $indicator->dhis2_numerator_uid,
                    'value' => $this->numerator_value,
                ];
            }
            if ($indicator->dhis2_denominator_uid && $this->denominator_value !== null) {
                $entries[] = [
                    'dataElement' => $indicator->dhis2_denominator_uid,
                    'value' => $this->denominator_value,
                ];
            }
        } elseif ($indicator->isCount()) {
            if ($indicator->dhis2_data_element_uid && $this->count_value !== null) {
                $entries[] = [
                    'dataElement' => $indicator->dhis2_data_element_uid,
                    'value' => $this->count_value,
                ];
            }
        } elseif ($indicator->isYesNo()) {
            if ($indicator->dhis2_data_element_uid && $this->yes_no_value !== null) {
                $entries[] = [
                    'dataElement' => $indicator->dhis2_data_element_uid,
                    'value' => $this->yes_no_value ? 'true' : 'false',
                ];
            }
        }

        return $entries;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Boot — auto-set auditing fields
    // ──────────────────────────────────────────────────────────────────────────

    protected static function booted(): void {
        static::creating(function (self $value) {
            $value->created_by = $value->created_by ?? auth()->id();
            $value->updated_by = $value->updated_by ?? auth()->id();
        });

        static::updating(function (self $value) {
            $value->updated_by = auth()->id();
        });

        // Auto-recompute percentage on save
        static::saving(function (self $value) {
            $value->recomputePercentage();
        });
    }
}
