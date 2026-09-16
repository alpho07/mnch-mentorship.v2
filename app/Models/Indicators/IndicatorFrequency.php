<?php

namespace App\Models\Indicators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndicatorFrequency extends Model {

    protected $fillable = [
        'code',
        'name',
        'dhis2_period_type',
        'sort_order',
    ];
    protected $casts = [
        'sort_order' => 'integer',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────────

    public function reportTypes(): BelongsToMany {
        return $this->belongsToMany(
                        IndicatorReportType::class,
                        'indicator_report_type_frequencies',
                        'frequency_id',
                        'report_type_id'
                );
    }

    public function reportPeriods(): HasMany {
        return $this->hasMany(IndicatorReportPeriod::class, 'frequency_id');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DHIS2 Period Generation
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Generate a DHIS2 period string for the given year/month/quarter.
     * Monthly → '202501'
     * Quarterly → '2025Q1'
     * Annually → '2025'
     */
    public function buildDhis2Period(int $year, ?int $month = null, ?int $quarter = null): string {
        return match ($this->code) {
            'monthly' => sprintf('%d%02d', $year, $month),
            'quarterly' => sprintf('%dQ%d', $year, $quarter),
            'annually' => (string) $year,
            default => (string) $year,
        };
    }


    /**
     * Returns which period fields are needed for this frequency.
     */
    public function requiresMonth(): bool {
        return $this->code === 'monthly';
    }

    public function requiresQuarter(): bool {
        return $this->code === 'quarterly';
    }

    public function formatPeriodLabel(int $year, ?int $month, ?int $quarter): string {
        if ($this->requiresMonth() && $month) {
            return \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');
        }

        if ($this->requiresQuarter() && $quarter) {
            $labels = [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'];
            return ($labels[$quarter] ?? 'Q?') . ' ' . $year;
        }

        return (string) $year;
    }
}
