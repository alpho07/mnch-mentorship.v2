<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleSession extends Model 
{
    use HasFactory;

    protected $fillable = [
        'program_module_id',
        'name',
        'time_minutes',
        'methodology_id',
        'order_sequence',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'time_minutes' => 'integer',
        'order_sequence' => 'integer',
    ];

    // Relationships
    public function programModule(): BelongsTo
    {
        return $this->belongsTo(ProgramModule::class);
    }

    public function methodology(): BelongsTo
    {
        return $this->belongsTo(Methodology::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(SessionMaterial::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    // Computed Attributes
    public function getDurationAttribute(): string
    {
        if ($this->time_minutes < 60) {
            return "{$this->time_minutes}m";
        }
        
        $hours = floor($this->time_minutes / 60);
        $minutes = $this->time_minutes % 60;
        
        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }

    public function getMaterialsCountAttribute(): int
    {
        return $this->materials()->count();
    }
}