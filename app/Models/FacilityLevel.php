<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityLevel extends Model {

    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'level_number',
        'description',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'level_number' => 'integer',
    ];

    public function facilities(): HasMany {
        return $this->hasMany(Facility::class);
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeOrderByLevel($query) {
        return $query->orderBy('level_number');
    }
}
