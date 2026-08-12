<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'version', 'is_active', 'is_public',
        'access_token', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'metadata' => 'array',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(SurveySection::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SurveyEvent::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
