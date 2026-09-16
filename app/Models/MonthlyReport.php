<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'report_template_id',
        'created_by',
        'approved_by',
        'reporting_period',
        'status',
        'comments',
        'submitted_at',
        'approved_at',
        'dhis2_sync_status',
    ];

    protected $casts = [
        'reporting_period' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'dhis2_sync_status' => 'array',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function indicatorValues(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }

    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByPeriod($query, string $period)
    {
        return $query->where('reporting_period', $period);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function canSubmit(): bool
    {
        return $this->status === 'draft';
    }

    public function canApprove(): bool
    {
        return $this->status === 'submitted';
    }

    public function getCompletionPercentageAttribute(): float
    {
        $totalIndicators = $this->reportTemplate->indicators()->count();
        if ($totalIndicators === 0) {
            return 100;
        }

        $completedIndicators = $this->indicatorValues()
            ->whereNotNull('numerator')
            ->count();

        return ($completedIndicators / $totalIndicators) * 100;
    }
}