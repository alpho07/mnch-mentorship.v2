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
        'category_id',
        'description',
        'version',
        'is_active',
        'validity_period_days',
        'metadata',
        'template_parameters',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'template_parameters' => 'array',
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

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AssessmentTypeCategory::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function interpolate(?string $text): ?string
    {
        if ($text === null || ! str_contains($text, '{{')) {
            return $text;
        }

        $parameters = $this->template_parameters ?? [];

        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $matches) use ($parameters) {
            return $parameters[$matches[1]] ?? $matches[0];
        }, $text);
    }
}
