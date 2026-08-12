<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            // RolePermissionSeeder::class,
            // NationalMentorsSeeder::class, // Run after roles are created
            ProgramModulesSeeder::class,   // Programs + modules required for mentorship creation
            EmoncProgramSeeder::class,         // Maternal Health (EmONC) program, modules, and tracks
            AphModuleContentSeeder::class,     // APH quiz (15 Qs pre/post test) + EmONC video links
            EmoncAphIntroContentSeeder::class, // APH intro/outcome/objectives/workplan (skipped by the batch seeders)
            ModuleRubricSeeder::class,         // Practical rubrics for Modules 4, 5 and all PPH tracks
            EmoncBatchAContentSeeder::class,   // Modules 2,3,4,6 — content + quiz
            EmoncBatchBContentSeeder::class,   // Modules 7,8,9 — content + quiz
            EmoncBatchCContentSeeder::class,   // Modules 10,11,12,13 — content + quiz
            EmoncPphModuleContentSeeder::class, // Module 5 (PPH) parent — content + quiz
            EmoncPphTracksBatch1Seeder::class,  // PPH Tracks 1-6 — content + quiz
            EmoncPphTracksBatch2Seeder::class,  // PPH Tracks 7-11 — content + quiz
            EmoncRubricItemsSeeder::class,      // Competency assessment checklist items (mentor manual)
            EmoncModuleActivitiesSeeder::class, // Corrects module/track activity mix per EmONC Modules Summary.docx
            PptxResourceSeeder::class,         // Newborn Care + Infant & Child PPTX slide placeholders
            // Assessment configuration seeders — safe to re-run at any time:
            // AssessmentQuestionConfigSeeder::class,  // Sets explanation fields + mortality question type
            // AmbuBagCommoditySeeder::class,           // Splits generic Ambu Bag into 250ml/500ml/1500ml
        ]);
    }
}
