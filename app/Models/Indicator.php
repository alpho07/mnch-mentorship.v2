<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'numerator_description',
        'denominator_description',
        'calculation_type',
        'source_document',
        'target_value',
        'is_active',
        'dhis2_mapping',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'is_active' => 'boolean',
        'dhis2_mapping' => 'array',
    ];

    public function reportTemplates(): BelongsToMany
    {
        return $this->belongsToMany(ReportTemplate::class, 'report_template_indicators')
            ->withPivot(['sort_order', 'is_required']);
    }

    public function values(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function calculateValue(?int $numerator, ?int $denominator): ?float
    {
        if (is_null($numerator)) {
            return null;
        }

        return match ($this->calculation_type) {
            'count' => $numerator,
            'percentage' => $denominator > 0 ? ($numerator / $denominator) * 100 : 0,
            'rate' => $denominator > 0 ? ($numerator / $denominator) * 1000 : 0,
            'ratio' => $denominator > 0 ? $numerator / $denominator : 0,
            default => $numerator,
        };
    }
}