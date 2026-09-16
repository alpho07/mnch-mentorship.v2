<?php

use App\Models\Program;
use App\Models\ProgramModule;
use Illuminate\Database\Migrations\Migration;

/**
 * One-time data fix aligning EmONC module 2/3 names and module 10/11 order with the
 * mentor manual (latest revision). Matches by exact current name, so it's a safe no-op
 * if EmoncProgramSeeder was never run or names have already been updated.
 */
return new class extends Migration
{
    public function up(): void
    {
        $program = Program::where('name', 'Maternal Health (EmONC)')->first();

        if (! $program) {
            return;
        }

        $rename = [
            'Module 2: Partograph Use and Interpretation' => 'Module 2: Labour Care Guide (LCG)',
            'Module 3: Obstructed Labour' => 'Module 3: Prolonged & Obstructed Labour',
        ];

        foreach ($rename as $oldName => $newName) {
            ProgramModule::where('program_id', $program->id)
                ->whereNull('parent_id')
                ->where('name', $oldName)
                ->update(['name' => $newName]);
        }

        $moduleResuscitation = ProgramModule::where('program_id', $program->id)
            ->whereNull('parent_id')
            ->where('name', 'Module 10: Maternal Resuscitation')
            ->first();

        $moduleShock = ProgramModule::where('program_id', $program->id)
            ->whereNull('parent_id')
            ->where('name', 'Module 11: Management of Maternal Shock')
            ->first();

        if ($moduleResuscitation && $moduleShock) {
            $moduleResuscitation->update(['name' => 'Module 11: Maternal Resuscitation', 'order_sequence' => 11]);
            $moduleShock->update(['name' => 'Module 10: Management of Maternal Shock', 'order_sequence' => 10]);
        }
    }

    public function down(): void
    {
        $program = Program::where('name', 'Maternal Health (EmONC)')->first();

        if (! $program) {
            return;
        }

        $rename = [
            'Module 2: Labour Care Guide (LCG)' => 'Module 2: Partograph Use and Interpretation',
            'Module 3: Prolonged & Obstructed Labour' => 'Module 3: Obstructed Labour',
        ];

        foreach ($rename as $oldName => $newName) {
            ProgramModule::where('program_id', $program->id)
                ->whereNull('parent_id')
                ->where('name', $oldName)
                ->update(['name' => $newName]);
        }

        $moduleResuscitation = ProgramModule::where('program_id', $program->id)
            ->whereNull('parent_id')
            ->where('name', 'Module 11: Maternal Resuscitation')
            ->first();

        $moduleShock = ProgramModule::where('program_id', $program->id)
            ->whereNull('parent_id')
            ->where('name', 'Module 10: Management of Maternal Shock')
            ->first();

        if ($moduleResuscitation && $moduleShock) {
            $moduleResuscitation->update(['name' => 'Module 10: Maternal Resuscitation', 'order_sequence' => 10]);
            $moduleShock->update(['name' => 'Module 11: Management of Maternal Shock', 'order_sequence' => 11]);
        }
    }
};
