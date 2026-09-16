<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'version',
        'is_active',
        'validity_period_days',
        'metadata',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(AssessmentSection::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
