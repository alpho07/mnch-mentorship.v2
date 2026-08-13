<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the 2026 revision of the Facility Readiness Assessment, sourced
 * from Assessments. v2.xlsx (root of repo). Every section/question/
 * commodity/checklist/cadre this creates is scoped to a brand-new
 * AssessmentType — nothing here touches the 2025 STANDARD_FACILITY_ASSESSMENT
 * type's data. See docs/superpowers/specs/2026-08-13-facility-assessment-2026-phase2-design.md
 * for the full content mapping this seeder implements.
 *
 * Run with:
 *   php artisan db:seed --class="Database\Seeders\FacilityAssessment2026\FacilityAssessment2026Seeder"
 */
class FacilityAssessment2026Seeder extends Seeder
{
    public function run(): void
    {
        $category = AssessmentTypeCategory::where('name', 'like', '%facility%')->first();

        $type = AssessmentType::firstOrCreate(
            ['code' => 'STANDARD_FACILITY_ASSESSMENT_2026'],
            [
                'name' => 'Standard Facility Readiness Assessment (2026)',
                'version' => '2026',
                'category_id' => $category?->id,
                'is_active' => true,
                'metadata' => ['parameters' => ['quality_of_care_timeline' => 'Neonates 7–28 days']],
            ]
        );

        // Existing rows (a re-run) won't have their metadata re-applied by
        // firstOrCreate — keep the parameter in sync explicitly.
        if (($type->metadata['parameters']['quality_of_care_timeline'] ?? null) !== 'Neonates 7–28 days') {
            $type->update(['metadata' => ['parameters' => ['quality_of_care_timeline' => 'Neonates 7–28 days']]]);
        }

        $this->call([
            ChecklistsSeeder::class,
            FacilityProfileSeeder::class,
            InfrastructureSeeder::class,
            BedCapacitySeeder::class,
            SkillsLabSeeder::class,
            HumanResourcesSeeder::class,
            HealthProductsSeeder::class,
            InformationSystemsSeeder::class,
            QualityOfCareSeeder::class,
            // IndicatorsSeeder::class, // Task 9
        ]);

        $this->command->info('✓ Facility Readiness Assessment 2026 seeded.');
    }
}
