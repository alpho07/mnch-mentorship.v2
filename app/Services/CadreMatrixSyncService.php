<?php

namespace App\Services;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\Cadre;

/**
 * Bridges the admin-managed, dynamic Cadre list (add/remove any time via
 * CadreResource) into the static-by-design AssessmentQuestion engine.
 * Called on every relevant page load rather than only at seed time, so a
 * cadre added or deactivated after seeding is reflected immediately without
 * re-running any seeder.
 */
class CadreMatrixSyncService
{
    private const METRICS = [
        'ALLOCATED' => 'Allocated in Maternity (ANW/Labour Ward/PNW)',
        'TRAINED' => 'Trained on 5-day EmONC (from 2024 to date)',
        '24HR' => 'Present in a 24hr shift',
    ];

    public function syncMaternityHrQuestions(AssessmentSection $section): void
    {
        $this->sync(
            $section,
            'emonc',
            'EMONC_A_HR_CADRE',
            500,
            ['title' => 'Human Resources in Maternity Unit', 'rowLabelHeader' => 'Cadre']
        );
    }

    /**
     * Materializes 3 AssessmentQuestion rows (one per metric) for every
     * active cadre in $category, keyed by a stable per-cadre code so
     * re-syncing updates existing rows rather than duplicating them.
     *
     * $table, when given, encodes DynamicFormBuilder's 3-part table-row
     * `group` convention ("{title}|{rowLabelHeader}|{rowLabel}") so every
     * cadre's 3 questions render as one row in a single shared-header
     * table instead of N separate boxed groups. Without it, each cadre's
     * questions just share a plain `group = $cadre->name` (their own
     * small boxed group).
     *
     * Deactivates (never deletes) previously-materialized rows for cadres
     * that are no longer active or no longer in the category, preserving
     * any historical responses already recorded against them.
     */
    public function sync(AssessmentSection $section, string $category, string $codePrefix, int $orderBase = 0, ?array $table = null): void
    {
        $activeCadres = Cadre::category($category)->active()->ordered()->get();
        $activeCodes = [];
        $order = $orderBase;

        foreach ($activeCadres as $cadre) {
            $group = $table
                ? "{$table['title']}|{$table['rowLabelHeader']}|{$cadre->name}"
                : $cadre->name;

            foreach (self::METRICS as $suffix => $label) {
                $code = "{$codePrefix}{$cadre->id}_{$suffix}";
                $activeCodes[] = $code;
                $order++;

                AssessmentQuestion::updateOrCreate(
                    ['question_code' => $code],
                    [
                        'assessment_section_id' => $section->id,
                        'question_text' => $label,
                        'question_type' => 'number',
                        'group' => $group,
                        'is_required' => false,
                        'is_scored' => false,
                        'order' => $order,
                        'is_active' => true,
                    ]
                );
            }
        }

        AssessmentQuestion::where('assessment_section_id', $section->id)
            ->where('question_code', 'like', "{$codePrefix}%")
            ->whereNotIn('question_code', $activeCodes)
            ->update(['is_active' => false]);
    }
}
