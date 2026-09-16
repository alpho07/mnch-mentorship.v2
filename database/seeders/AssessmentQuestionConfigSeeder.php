<?php

namespace Database\Seeders;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Configures assessment question settings:
 *  1. Infrastructure & Information Systems — sets requires_explanation_on = ["No","Partial"]
 *     for all yes_no / yes_no_partial questions so a comment textarea appears on negative answers.
 *  2. Information Systems — marks the mortality tracking question with type = mortality_three_month.
 *
 * Safe to run multiple times (uses updateOrCreate / update via query).
 *
 * Run with:
 *   php artisan db:seed --class=AssessmentQuestionConfigSeeder
 */
class AssessmentQuestionConfigSeeder extends Seeder
{
    /** Sections that need explanation fields on "No"/"Partial" answers */
    private const EXPLAIN_SECTIONS = ['infrastructure', 'information_systems'];

    /** Keywords used to identify the mortality question in Information Systems */
    private const MORTALITY_KEYWORDS = ['mortalit'];

    public function run(): void
    {
        $this->configureExplanations();
        $this->configureMortalityQuestion();
        $this->configureInfrastructureCommentQuestions(); 
    }

    /**
     * For all yes_no / yes_no_partial questions in the target sections,
     * set requires_explanation_on = ["No","Partial"] and ensure explanation_label is set.
     */
    private function configureExplanations(): void
    {
        $sectionIds = AssessmentSection::whereIn('code', self::EXPLAIN_SECTIONS)
            ->where('is_active', true)
            ->pluck('id');

        if ($sectionIds->isEmpty()) {
            $this->command->warn('No infrastructure or information_systems sections found.');
            return;
        }

        $updated = AssessmentQuestion::whereIn('assessment_section_id', $sectionIds)
            ->whereIn('question_type', ['yes_no', 'yes_no_partial'])
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($updated as $question) {
            $existing = $question->requires_explanation_on ?? [];

            // Skip if already configured correctly
            if (in_array('No', $existing) && in_array('Partial', $existing)) {
                continue;
            }

            $question->update([
                'requires_explanation_on' => ['No', 'Partial'],
                'explanation_label' => $question->explanation_label ?: 'Comments / Recommendations',
            ]);
            $count++;
        }

        $this->command->info("✓ {$count} questions configured with explanation-on-No/Partial.");
    }

    /**
     * Mark the mortality question in Information Systems with type = mortality_three_month.
     * Detected by question code or text containing a mortality keyword.
     */
    private function configureMortalityQuestion(): void
    {
        $section = AssessmentSection::where('code', 'information_systems')
            ->where('is_active', true)
            ->first();

        if (! $section) {
            $this->command->warn('information_systems section not found.');
            return;
        }

        $keywords = self::MORTALITY_KEYWORDS;

        $question = AssessmentQuestion::where('assessment_section_id', $section->id)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $kw) {
                    $query->orWhere('question_code', 'like', "%{$kw}%")
                          ->orWhere('question_text', 'like', "%{$kw}%");
                }
            })
            ->first();

        if (! $question) {
            $this->command->warn('Mortality question not found in information_systems — skipping type update.');
            return;
        }

        if ($question->question_type !== 'mortality_three_month') {
            $question->update(['question_type' => 'mortality_three_month']);
            $this->command->info("✓ Mortality question [{$question->question_code}] updated to type mortality_three_month.");
        } else {
            $this->command->info("  Mortality question already set to mortality_three_month.");
        }
    }

    /**
     * Specifically ensure ORT corner and Triage Area questions in Infrastructure
     * have requires_explanation_on configured (belt-and-suspenders for named questions).
     */
    private function configureInfrastructureCommentQuestions(): void
    {
        $section = AssessmentSection::where('code', 'infrastructure')
            ->where('is_active', true)
            ->first();

        if (! $section) {
            return;
        }

        // Match by question text keywords — adjust if your question texts differ
        $keywordGroups = [
            'oral rehydration'   => ['ort', 'oral rehydration', 'rehydration therapy'],
            'triage'             => ['triage'],
        ];

        foreach ($keywordGroups as $label => $keywords) {
            $question = AssessmentQuestion::where('assessment_section_id', $section->id)
                ->where(function ($query) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $query->orWhere('question_text', 'like', "%{$kw}%")
                              ->orWhere('question_code', 'like', "%{$kw}%");
                    }
                })
                ->whereIn('question_type', ['yes_no', 'yes_no_partial'])
                ->first();

            if ($question) {
                $existing = $question->requires_explanation_on ?? [];
                if (! in_array('No', $existing)) {
                    $question->update([
                        'requires_explanation_on' => ['No', 'Partial'],
                        'explanation_label' => $question->explanation_label ?: 'Comments / Recommendations',
                    ]);
                    $this->command->info("✓ [{$question->question_code}] ({$label}) configured for explanation on No/Partial.");
                }
            } else {
                $this->command->warn("  No matching question found for '{$label}' in infrastructure section.");
            }
        }
    }
}
