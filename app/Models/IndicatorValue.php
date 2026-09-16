<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'monthly_report_id',
        'indicator_id',
        'numerator',
        'denominator',
        'calculated_value',
        'comments',
    ];

    protected $casts = [
        'calculated_value' => 'decimal:4',
    ];

    public function monthlyReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function (IndicatorValue $value) {
            if ($value->numerator !== null) {
                $value->calculated_value = $value->indicator->calculateValue(
                    $value->numerator,
                    $value->denominator
                );
            }
        });
    }

    public function getFormattedValueAttribute(): string
    {
        if (is_null($this->calculated_value)) {
            return '-';
        }

        return match ($this->indicator->calculation_type) {
            'percentage' => number_format($this->calculated_value, 1) . '%',
            'rate' => number_format($this->calculated_value, 1) . ' per 1000',
            'ratio' => number_format($this->calculated_value, 2) . ':1',
            default => number_format($this->calculated_value, 0),
        };
    }
}
