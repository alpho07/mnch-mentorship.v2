<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: nullable `program_area` tags used to scope program-area
 * admins (newborn_child / emonc). NULL on users = not area-scoped;
 * NULL on content = shared / unassigned.
 */
return new class extends Migration
{
    private const TABLES = ['users', 'programs', 'trainings', 'assessment_types', 'indicator_report_types'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'program_area')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('program_area', 32)->nullable()->index());
            }
        }

        DB::table('programs')->where('name', 'like', '%EmONC%')->update(['program_area' => 'emonc']);
        DB::table('programs')->whereIn('name', ['Newborn Care', 'Infant and Child Care'])->update(['program_area' => 'newborn_child']);

        // Mentorships inherit from their program.
        foreach (['emonc', 'newborn_child'] as $area) {
            DB::table('trainings')
                ->whereIn('program_id', DB::table('programs')->where('program_area', $area)->select('id'))
                ->update(['program_area' => $area]);
        }

        // Existing MOH (global) trainings are all newborn/paediatric topics (legacy programs 1 & 2).
        DB::table('trainings')
            ->where('type', 'global_training')
            ->whereNull('program_area')
            ->whereIn('id', DB::table('training_programs')->whereIn('program_id', [1, 2])->select('training_id'))
            ->update(['program_area' => 'newborn_child']);

        DB::table('indicator_report_types')->whereIn('code', ['newborn', 'paediatric'])->update(['program_area' => 'newborn_child']);
    }

    public function down(): void
    {
        // Intentionally non-destructive in production.
    }
};
