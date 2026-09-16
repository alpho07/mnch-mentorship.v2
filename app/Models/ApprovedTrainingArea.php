<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovedTrainingArea extends Model { 

    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function trainings(): HasMany {
        return $this->hasMany(Training::class, 'approved_training_area_id');
    }

    // Query Scopes
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query) {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Computed Attributes
    public function getTrainingCountAttribute(): int {
        return $this->trainings()->count();
    }

    public function getActiveTrainingCountAttribute(): int {
        return $this->trainings()
                        ->where('start_date', '<=', now())
                        ->where('end_date', '>=', now())
                        ->count();
    }

    // Helper Methods
    public function activate(): bool {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool {
        return $this->update(['is_active' => false]);
    }

    public function canBeDeleted(): bool {
        return $this->trainings()->count() === 0;
    }
}
