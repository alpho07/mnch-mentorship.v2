<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentSection extends Model
{
    /**
     * The three section "kinds" a template can mix and match. Kept as the
     * pre-existing DB enum values (not renamed) since the mobile API's
     * schema endpoint emits `section_type` verbatim to client apps.
     */
    public const KIND_QUESTION_GROUP = 'dynamic_questions';

    public const KIND_HUMAN_RESOURCES = 'structured_data';

    public const KIND_COMMODITY_MATRIX = 'commodity_matrix';

    /**
     * facility_profile/bed_capacity are also typed structured_data but are
     * informational-only (0 questions, already excluded from the mobile app
     * and reports) — not a real "kind" to dispatch a page for.
     */
    public const INFORMATIONAL_CODES = ['facility_profile', 'bed_capacity'];

    protected $fillable = [
        'assessment_type_id',
        'name',
        'code',
        'description',
        'section_type',
        'is_scored',
        'icon',
        'color',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_scored' => 'boolean',
    ];

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    /**
     * Questions in this section
     */
    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class, 'assessment_section_id');
    }

    /**
     * Section scores
     */
    public function sectionScores(): HasMany
    {
        return $this->hasMany(AssessmentSectionScore::class, 'assessment_section_id');
    }

    /**
     * Scope to get only active sections
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only scored sections
     */
    public function scopeScored($query)
    {
        return $query->where('is_scored', true);
    }

    /**
     * Scope to order by order column
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Resolves the effective dispatch kind for this section — distinguishes
     * the real "human_resources" section from the informational
     * facility_profile/bed_capacity rows that share the same DB enum value.
     */
    public function resolvedKind(): string
    {
        if (in_array($this->code, self::INFORMATIONAL_CODES, true)) {
            return 'informational';
        }

        return match ($this->section_type) {
            self::KIND_HUMAN_RESOURCES => 'human_resources',
            self::KIND_COMMODITY_MATRIX => 'commodity_matrix',
            default => 'question_group',
        };
    }
}
