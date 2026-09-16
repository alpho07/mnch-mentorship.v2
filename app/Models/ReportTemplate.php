<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'report_type',
        'frequency',
        'is_active',
        'dhis2_mapping',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'dhis2_mapping' => 'array',
    ];

    public function indicators(): BelongsToMany
    {
        return $this->belongsToMany(Indicator::class, 'report_template_indicators')
            ->withPivot(['sort_order', 'is_required'])
            ->withTimestamps()
            ->orderBy('report_template_indicators.sort_order');
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class);
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_report_templates')
            ->withPivot(['start_date', 'end_date']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('report_type', $type);
    }
}

