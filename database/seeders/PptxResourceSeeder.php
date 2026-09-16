<?php

namespace Database\Seeders;

use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\ResourceType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds placeholder Resource records for all Newborn Care and Infant & Child Care PPTX slides.
 * No files are uploaded — status is 'draft' so the admin can attach files and publish later.
 *
 * Program module IDs are taken directly from the DB:
 *   Newborn Care (program 4)  : modules 4–15
 *   Infant & Child (program 5): modules 16–25
 */
class PptxResourceSeeder extends Seeder
{
    public function run(): void
    {
        $typeId     = ResourceType::where('slug', 'presentation-slides')->value('id');
        $authorId   = User::role('super_admin')->value('id') ?? 1;

        // Category IDs (from DB)
        $catNewborn     = ResourceCategory::where('name', 'Newborn Care')->value('id');
        $catENC         = ResourceCategory::where('name', 'Essential Newborn Care')->value('id');
        $catPreterm     = ResourceCategory::where('name', 'Preterm & Low Birth Weight')->value('id');
        $catSickNewborn = ResourceCategory::where('name', 'Sick Newborn Care')->value('id');
        $catNutrition   = ResourceCategory::where('name', 'Nutrition & Feeding')->value('id');
        $catInfection   = ResourceCategory::where('name', 'Infection Prevention')->value('id');
        $catEmergency   = ResourceCategory::where('name', 'Emergency Protocols')->value('id');
        $catPaediatric  = ResourceCategory::where('name', 'Paediatric Care')->value('id');
        $catDiabetes    = ResourceCategory::where('name', 'Diabetes Management')->value('id');
        $catTraining    = ResourceCategory::where('name', 'Healthcare Provider Training')->value('id');

        $resources = [

            // ─────────────────────────────────────────────────────────────────
            // NEWBORN CARE — Program 4
            // ─────────────────────────────────────────────────────────────────

            // Module 4 — IPC
            [
                'title'       => 'IPC',
                'category_id' => $catInfection,
                'module_ids'  => [4],
            ],

            // Module 5 — IFCDC
            [
                'title'       => 'IFCDC',
                'category_id' => $catTraining,
                'module_ids'  => [5],
            ],

            // Module 6 — Essential Newborn Care
            [
                'title'       => 'Essential Newborn Care',
                'category_id' => $catENC,
                'module_ids'  => [6],
            ],

            // Module 7 — Oxygen Therapy (2 slides: Pulse Oximetry + Oxygen Therapy)
            [
                'title'       => 'Pulse Oximetry',
                'category_id' => $catNewborn,
                'module_ids'  => [7],
            ],
            [
                'title'       => 'Oxygen Therapy',
                'category_id' => $catNewborn,
                'module_ids'  => [7],
            ],

            // Module 8 — Neonatal Thermoregulation (4 slides)
            [
                'title'       => 'Neonatal Thermoregulation',
                'category_id' => $catNewborn,
                'module_ids'  => [8],
            ],
            [
                'title'       => 'Use of a Plastic Wraps in Preterm Newborns',
                'category_id' => $catPreterm,
                'module_ids'  => [8],
            ],
            [
                'title'       => 'Use of a Radiant Warmer',
                'category_id' => $catPreterm,
                'module_ids'  => [8],
            ],
            [
                'title'       => 'Use of Incubators',
                'category_id' => $catPreterm,
                'module_ids'  => [8],
            ],

            // Module 9 — Newborn Resuscitation
            [
                'title'       => 'Neonatal Resuscitation',
                'category_id' => $catEmergency,
                'module_ids'  => [9],
            ],

            // Module 10 — Danger Signs & Neonatal Sepsis
            [
                'title'       => 'Danger Signs and Neonatal Sepsis',
                'category_id' => $catSickNewborn,
                'module_ids'  => [10],
            ],

            // Module 11 — Care for the Small and Sick Newborn (5 slides)
            [
                'title'       => 'Introduction to Care of Preterms',
                'category_id' => $catPreterm,
                'module_ids'  => [11],
            ],
            [
                'title'       => 'Ballard Score',
                'category_id' => $catPreterm,
                'module_ids'  => [11],
            ],
            [
                'title'       => 'Use of Continuous Positive Airway Pressure (CPAP)',
                'category_id' => $catPreterm,
                'module_ids'  => [11],
            ],
            [
                'title'       => 'Apnoea of Prematurity',
                'category_id' => $catPreterm,
                'module_ids'  => [11],
            ],
            [
                'title'       => 'Kangaroo Mother Care',
                'category_id' => $catPreterm,
                'module_ids'  => [11],
            ],

            // Module 12 — Neonatal Jaundice
            [
                'title'       => 'Neonatal Jaundice',
                'category_id' => $catNewborn,
                'module_ids'  => [12],
            ],

            // Module 13 — Neonatal Hypoglycaemia
            [
                'title'       => 'Neonatal Hypoglycaemia',
                'category_id' => $catNewborn,
                'module_ids'  => [13],
            ],

            // Module 14 — Neonatal Feeds and Fluids
            [
                'title'       => 'Feeds and Fluids',
                'category_id' => $catNutrition,
                'module_ids'  => [14],
            ],

            // Module 15 — Supportive System Topics
            [
                'title'       => 'Newborn Transport and Referral System',
                'category_id' => $catNewborn,
                'module_ids'  => [15],
            ],

            // ─────────────────────────────────────────────────────────────────
            // INFANT & CHILD CARE — Program 5
            // ─────────────────────────────────────────────────────────────────

            // Module 16 — Triage
            [
                'title'       => 'Triage',
                'category_id' => $catEmergency,
                'module_ids'  => [16],
            ],

            // Module 17 — Basic Life Support
            [
                'title'       => 'Basic Life Support',
                'category_id' => $catEmergency,
                'module_ids'  => [17],
            ],

            // Module 18 — Assessment & Management of Sick Infant/Child
            [
                'title'       => 'Assessment & Management of a Sick Infant-Child',
                'category_id' => $catPaediatric,
                'module_ids'  => [18],
            ],

            // Module 20 — Pneumonia / Respiratory (Oxygen + 3 respiratory distress slides)
            [
                'title'       => 'Oxygen Therapy (Infant & Child)',
                'category_id' => $catPaediatric,
                'module_ids'  => [20],
            ],
            [
                'title'       => 'Management of an Infant-Child with Respiratory Distress — Pneumonia',
                'category_id' => $catPaediatric,
                'module_ids'  => [20],
            ],
            [
                'title'       => 'Management of an Infant-Child with Respiratory Distress — Asthma',
                'category_id' => $catPaediatric,
                'module_ids'  => [20],
            ],
            [
                'title'       => 'Management of an Infant-Child with Respiratory Distress — Child with TB',
                'category_id' => $catPaediatric,
                'module_ids'  => [20],
            ],

            // Module 19 — Dehydration / Diarrhoea
            [
                'title'       => 'Fluid Therapy in Dehydration Due to Diarrhoea',
                'category_id' => $catPaediatric,
                'module_ids'  => [19],
            ],

            // Module 22 — SAM
            [
                'title'       => 'Severe Acute Malnutrition — Recognition and Early Treatment',
                'category_id' => $catNutrition,
                'module_ids'  => [22],
            ],

            // Module 23 — Altered Consciousness (Meningitis + Malaria)
            [
                'title'       => 'Meningitis and Malaria',
                'category_id' => $catPaediatric,
                'module_ids'  => [23],
            ],

            // Module 24 — Diabetes in Children
            [
                'title'       => 'Introduction to Diabetes',
                'category_id' => $catDiabetes,
                'module_ids'  => [24],
            ],
            [
                'title'       => 'Diabetic Ketoacidosis (DKA)',
                'category_id' => $catDiabetes,
                'module_ids'  => [24],
            ],

            // Module 25 — Documentation (general older child slides)
            [
                'title'       => 'Older Child Module Slides',
                'category_id' => $catTraining,
                'module_ids'  => [25],
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($resources as $data) {
            $slug = Str::slug($data['title']);
            $base = $slug;
            $i    = 2;
            while (Resource::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }

            $existing = Resource::where('title', $data['title'])->first();

            if ($existing) {
                // Just ensure pivot links are present
                $existing->programModules()->syncWithoutDetaching($data['module_ids']);
                $skipped++;
                continue;
            }

            $resource = Resource::create([
                'title'            => $data['title'],
                'slug'             => $slug,
                'excerpt'          => null,
                'content'          => '<p>Presentation slides — file to be uploaded.</p>',
                'category_id'      => $data['category_id'],
                'resource_type_id' => $typeId,
                'author_id'        => $authorId,
                'status'           => 'draft',
                'visibility'       => 'authenticated',
                'is_featured'      => false,
                'is_downloadable'  => true,
                'published_at'     => null,
            ]);

            $resource->programModules()->sync($data['module_ids']);
            $created++;
        }

        $this->command->info("PptxResourceSeeder: {$created} resources created, {$skipped} already existed (pivot links updated).");
    }
}
