<?php

namespace App\Models\Indicators;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndicatorReportPeriod extends Model {

    protected $fillable = [
        'facility_id',
        'report_type_id',
        'frequency_id',
        'period_year',
        'period_month',
        'period_quarter',
        'dhis2_period',
        'status',
        'submitted_by',
        'submitted_at',
        'validated_by',
        'validated_at',
        'rejection_reason',
        'dhis2_push_status',
        'dhis2_push_at',
        'dhis2_import_summary',
        'notes',
    ];
    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'period_quarter' => 'integer',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'dhis2_push_at' => 'datetime',
        'dhis2_import_summary' => 'array',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_VALIDATED = 'validated';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PUSHED_DHIS2 = 'pushed_to_dhis2';

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────────

    public function facility(): BelongsTo {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function reportType(): BelongsTo {
        return $this->belongsTo(IndicatorReportType::class, 'report_type_id');
    }

    public function frequency(): BelongsTo {
        return $this->belongsTo(IndicatorFrequency::class, 'frequency_id');
    }

    public function values(): HasMany {
        return $this->hasMany(IndicatorValue::class, 'period_id');
    }

    public function submittedByUser(): BelongsTo {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function validatedByUser(): BelongsTo {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────────────────

    public function scopeForFacility($query, int $facilityId) {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByStatus($query, string $status) {
        return $query->where('status', $status);
    }

    public function scopeDraft($query) {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSubmitted($query) {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeValidated($query) {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    public function scopeRejected($query) {
        return $query->where('status', self::STATUS_REJECTED);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Status helpers
    // ──────────────────────────────────────────────────────────────────────────

    public function isDraft(): bool {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isValidated(): bool {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isRejected(): bool {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isPushed(): bool {
        return $this->status === self::STATUS_PUSHED_DHIS2;
    }

    public function isEditable(): bool {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }

    public function canBeSubmitted(): bool {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeValidated(): bool {
        return $this->status === self::STATUS_SUBMITTED;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Computed attributes
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Human-readable period label.
     */
    public function getPeriodLabelAttribute(): string {
        $freq = $this->frequency;
        if (!$freq)
            return (string) $this->period_year;
        return $freq->formatPeriodLabel($this->period_year, $this->period_month, $this->period_quarter);
    }

    /**
     * Completion stats: how many indicators have been filled.
     */
    public function getCompletionStatsAttribute(): array {
        $totalIndicators = $this->reportType->groups()
                ->with('allIndicators')
                ->get()
                ->flatMap(fn($g) => $g->allIndicators)
                ->where('is_active', true)
                ->count();

        $filled = $this->values()->count();

        return [
            'total' => $totalIndicators,
            'filled' => $filled,
            'percentage' => $totalIndicators > 0 ? round(($filled / $totalIndicators) * 100, 1) : 0,
        ];
    }

    /**
     * Get or build the DHIS2 period string.
     */
    public function computeDhis2Period(): string {
        return $this->frequency->buildDhis2Period(
                        $this->period_year,
                        $this->period_month,
                        $this->period_quarter
                );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Status badge helper for Filament
    // ──────────────────────────────────────────────────────────────────────────

    public function getStatusColor(): string {
        return match ($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_SUBMITTED => 'warning',
            self::STATUS_VALIDATED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_PUSHED_DHIS2 => 'info',
            default => 'gray',
        };
    }

    public function getStatusIcon(): string {
        return match ($this->status) {
            self::STATUS_DRAFT => 'heroicon-o-pencil',
            self::STATUS_SUBMITTED => 'heroicon-o-clock',
            self::STATUS_VALIDATED => 'heroicon-o-check-circle',
            self::STATUS_REJECTED => 'heroicon-o-x-circle',
            self::STATUS_PUSHED_DHIS2 => 'heroicon-o-arrow-up-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }
}
