<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MainCadre extends Model {

    protected $table ='assessment_cadres';

    protected $fillable = [
        'assessment_type_id',
        'name',
        'code',
        'description',
        'order',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    protected static function boot() {
        parent::boot();

        // Auto-generate code from name
        static::creating(function ($cadre) {
            if (empty($cadre->code)) {
                $cadre->code = Str::slug($cadre->name, '_');
            }
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function assessmentType(): BelongsTo {
        return $this->belongsTo(AssessmentType::class);
    }

    public function humanResourceResponses(): HasMany {
        return $this->hasMany(HumanResourceResponse::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query) {
        return $query->orderBy('order');
    }
}
