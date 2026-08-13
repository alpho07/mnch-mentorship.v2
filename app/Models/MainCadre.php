<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MainCadre extends Model {

    protected $table ='assessment_cadres';

    /**
     * The five fixed training-program columns, in the same order
     * EditHumanResources renders them — used to validate na_training_columns
     * values and by the export/PDF services to know which key means what.
     */
    public const TRAINING_COLUMNS = ['etat_plus', 'comprehensive_newborn_care', 'imnci', 'type_1_diabetes', 'essential_newborn_care'];

    protected $fillable = [
        'assessment_type_id',
        'name',
        'code',
        'description',
        'order',
        'is_active',
        'na_training_columns',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'na_training_columns' => 'array',
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

    public function isColumnNotApplicable(string $column): bool {
        return in_array($column, $this->na_training_columns ?? [], true);
    }
}
