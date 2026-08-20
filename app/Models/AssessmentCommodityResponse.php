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
        'not_applicable',
        'notes',
        'score',
        'quantity',
    ];
    protected $casts = [
        'available' => 'boolean',
        'not_applicable' => 'boolean',
        'score' => 'decimal:2',
        'quantity' => 'integer',
    ];

    protected static function boot() {
        parent::boot();

        // Auto-calculate score before saving. Not-applicable responses keep
        // a score of 0 for storage purposes (the `score` column is not
        // nullable) — the actual exclusion from scoring denominators is
        // driven by `not_applicable`, not by this field, in
        // CommodityScoringService.
        static::saving(function ($response) {
            $response->score = ($response->not_applicable || ! $response->available) ? 0 : 1;
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
