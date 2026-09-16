<?php

namespace Database\Seeders;

use App\Models\Methodology;
use App\Models\ModuleSession;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\SessionMaterial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProgramModulesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedNewbornModules();
            $this->seedInfantChildModules();
        });

        $this->command->info('Program modules, sessions, and materials seeded successfully!');
    }

    private function curriculum(): array
    {
        $path = database_path('seeders/data/mentorship_curriculum_2025_10_13.php');

        if (!File::exists($path)) {
            return [];
        }

        return require $path;
    }

    private function seedNewbornModules(): void
    {
        $program = Program::firstOrCreate(
            ['name' => 'Newborn Care'],
            ['description' => 'Comprehensive newborn care training program']
        );

        $modules = $this->curriculum()['newborn'] ?? [];

        if ($modules === []) {
            $this->command->warn('Newborn curriculum file is missing or empty.');
            return;
        }

        foreach ($modules as $index => $moduleData) {
            $totalTime = collect($moduleData['sessions'])->sum('time_minutes');

            $module = ProgramModule::create([
                'program_id' => $program->id,
                'name' => $moduleData['module'],
                'order_sequence' => $index + 1,
                'total_time_minutes' => $totalTime,
                'is_active' => true,
            ]);

            $this->createSessions($module, $moduleData['sessions']);
        }

        $this->command->info('Seeded ' . $program->name . ' with ' . count($modules) . ' modules');
    }

    private function seedInfantChildModules(): void
    {
        $program = Program::firstOrCreate(
            ['name' => 'Infant and Child Care'],
            ['description' => 'Comprehensive infant and child care training program']
        );

        $modules = $this->curriculum()['infant_child'] ?? [];

        if ($modules === []) {
            $this->command->warn('Infant/Child curriculum file is missing or empty.');
            return;
        }

        foreach ($modules as $index => $moduleData) {
            $totalTime = collect($moduleData['sessions'])->sum('time_minutes');

            $module = ProgramModule::create([
                'program_id' => $program->id,
                'name' => $moduleData['module'],
                'order_sequence' => $index + 1,
                'total_time_minutes' => $totalTime,
                'is_active' => true,
            ]);

            $this->createSessions($module, $moduleData['sessions']);
        }

        $this->command->info('Seeded ' . $program->name . ' with ' . count($modules) . ' modules');
    }

    private function createSessions(ProgramModule $module, array $sessions): void
    {
        foreach ($sessions as $index => $sessionData) {
            $methodology = null;
            if (!empty($sessionData['methodology'])) {
                $methodology = Methodology::firstOrCreate(
                    ['name' => $sessionData['methodology']],
                    ['description' => $sessionData['methodology'], 'is_active' => true]
                );
            }

            $session = ModuleSession::create([
                'program_module_id' => $module->id,
                'name' => $sessionData['session'],
                'time_minutes' => $sessionData['time_minutes'],
                'methodology_id' => $methodology?->id,
                'order_sequence' => $index + 1,
                'is_active' => true,
            ]);

            if (!empty($sessionData['materials'])) {
                $this->createMaterials($session, $sessionData['materials']);
            }
        }
    }

    private function createMaterials(ModuleSession $session, array $materials): void
    {
        foreach ($materials as $material) {
            SessionMaterial::create([
                'module_session_id' => $session->id,
                'material_name' => $material,
                'quantity' => 1,
                'is_required' => true,
            ]);
        }
    }
}
