<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentCommodityResponse extends Model {

    protected $fillable = [
        'assessment_id',
        'commodity_id',
        'assessment_department_id',
        'available',
        'notes',
        'score',
    ];
    protected $casts = [
        'available' => 'boolean',
        'score' => 'decimal:2',
    ];

    protected static function boot() {
        parent::boot();

        // Auto-calculate score before saving
        static::saving(function ($response) {
            $response->score = $response->available ? 1 : 0;
        });

        // Trigger scoring recalculation after save
        static::saved(function ($response) {
            // Recalculate department scores
            app(\App\Services\CommodityScoringService::class)
                    ->recalculateDepartmentScore(
                            $response->assessment_id,
                            $response->assessment_department_id
                    );
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class);
    }

    public function commodity(): BelongsTo {
        return $this->belongsTo(Commodity::class);
    }

    public function department(): BelongsTo {
        return $this->belongsTo(AssessmentDepartment::class, 'assessment_department_id');
    }
}
