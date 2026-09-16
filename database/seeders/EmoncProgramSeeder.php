<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Program;
use App\Models\ProgramModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmoncProgramSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedActivities();

            $program = Program::firstOrCreate(
                ['name' => 'Maternal Health (EmONC)'],
                ['description' => 'Emergency Obstetric and Newborn Care mentorship and training package']
            );

            $modules = [
                ['name' => 'Ante Partum Hemorrhage', 'has_tracks' => false],
                ['name' => 'Labour Care Guide (LCG)', 'has_tracks' => false],
                ['name' => 'Prolonged & Obstructed Labour', 'has_tracks' => false],
                ['name' => 'Active Management of the Third Stage of Labor (AMSTL)', 'has_tracks' => false],
                ['name' => 'Management of Postpartum Hemorrhage (PPH)', 'has_tracks' => true],
                ['name' => 'Management of Cord Prolapse', 'has_tracks' => false],
                ['name' => 'Vaginal Breech Delivery', 'has_tracks' => false],
                ['name' => 'Shoulder Dystocia Delivery', 'has_tracks' => false],
                ['name' => 'Vaginal Vacuum Assisted Delivery', 'has_tracks' => false],
                ['name' => 'Management of Maternal Shock', 'has_tracks' => false],
                ['name' => 'Maternal Resuscitation', 'has_tracks' => false],
                ['name' => 'Management of Pre-Eclampsia/Eclampsia', 'has_tracks' => false],
                ['name' => 'Immediate Neonatal Resuscitation', 'has_tracks' => false],
            ];

            $defaultActivityIds = Activity::whereIn('name', ['CME', 'Hands-on Demo', 'Drill'])
                ->pluck('id')
                ->toArray();

            foreach ($modules as $index => $moduleData) {
                $baseName = $moduleData['name'];
                $prefixedName = 'Module '.($index + 1).': '.$baseName;

                // Try to update an existing record with the old (non-prefixed) name first.
                $module = ProgramModule::where('program_id', $program->id)
                    ->where('name', $baseName)
                    ->whereNull('parent_id')
                    ->first();

                if ($module) {
                    $module->update(['name' => $prefixedName]);
                } else {
                    $module = ProgramModule::firstOrCreate(
                        [
                            'program_id' => $program->id,
                            'name' => $prefixedName,
                        ],
                        [
                            'order_sequence' => $index + 1,
                            'is_active' => true,
                        ]
                    );
                }

                $module->activities()->syncWithoutDetaching($defaultActivityIds);

                if ($moduleData['has_tracks']) {
                    $this->createPphTracks($module, $defaultActivityIds);
                }
            }
        });

        $this->command->info('Maternal Health (EmONC) program, modules, tracks, and activities seeded successfully.');
    }

    private function seedActivities(): void
    {
        $activities = [
            ['name' => 'CME', 'description' => 'Continuing Medical Education session'],
            ['name' => 'Hands-on Demo', 'description' => 'Practical hands-on demonstration'],
            ['name' => 'Drill', 'description' => 'Scenario-based skill drill'],
        ];

        foreach ($activities as $activity) {
            Activity::firstOrCreate(
                ['name' => $activity['name']],
                [
                    'description' => $activity['description'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function createPphTracks(ProgramModule $parentModule, array $activityIds): void
    {
        $tracks = [
            'Bimanual compression of the uterus',
            'Compression of abdominal aorta',
            'Removal of retained placenta',
            'Uterine inversion',
            'The placement of the intrauterine balloon tamponade (condom tamponade)',
            'The placement of the intrauterine balloon tamponade (free flow system)',
            'Repair of perineal tear',
            'Repair of cervical tear',
            'The placement of the B-Lynch suture',
            'Placement of non-pneumatic anti-shock garment (NASG)',
            'Post-partum hemorrhage simulation',
        ];

        foreach ($tracks as $index => $trackName) {
            $prefixedName = 'Track '.($index + 1).': '.$trackName;

            // Try to update an existing record with the old (non-prefixed) name first.
            $track = ProgramModule::where('program_id', $parentModule->program_id)
                ->where('parent_id', $parentModule->id)
                ->where('name', $trackName)
                ->first();

            if ($track) {
                $track->update(['name' => $prefixedName]);
            } else {
                $track = ProgramModule::firstOrCreate(
                    [
                        'program_id' => $parentModule->program_id,
                        'parent_id' => $parentModule->id,
                        'name' => $prefixedName,
                    ],
                    [
                        'order_sequence' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }

            $track->activities()->syncWithoutDetaching($activityIds);
        }
    }
}
