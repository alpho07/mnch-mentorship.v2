<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HumanResourceResponse extends Model {

    protected $fillable = [
        'assessment_id',
        'cadre_id',
        'total_in_facility',
        'etat_plus',
        'comprehensive_newborn_care',
        'imnci',
        'type_1_diabetes',
        'essential_newborn_care',
    ];
    protected $casts = [
        'total_in_facility' => 'integer',
        'etat_plus' => 'integer',
        'comprehensive_newborn_care' => 'integer',
        'imnci' => 'integer',
        'type_1_diabetes' => 'integer',
        'essential_newborn_care' => 'integer',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class);
    }

    public function cadre(): BelongsTo {
        return $this->belongsTo(MainCadre::class);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get total trained count
     */
    public function getTotalTrainedAttribute(): int {
        return $this->etat_plus + $this->comprehensive_newborn_care + $this->imnci + $this->type_1_diabetes + $this->essential_newborn_care;
    }

    /**
     * Get training percentage
     */
    public function getTrainingPercentageAttribute(): float {
        if ($this->total_in_facility === 0) {
            return 0;
        }

        // Each person can have multiple trainings, so we calculate based on total possible
        $totalPossible = $this->total_in_facility * 5; // 5 training types

        return round(($this->total_trained / $totalPossible) * 100, 2);
    }
}
