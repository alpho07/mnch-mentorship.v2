<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The previous migration (2026_07_17_000002) widened the `type` ENUM only on
 * MySQL — `DB::statement('ALTER TABLE ... MODIFY type ENUM(...)')` is
 * MySQL-only syntax, so on SQLite (used by the test suite — see
 * phpunit.xml's DB_CONNECTION=sqlite) the column kept its original 3-value
 * CHECK constraint from table creation (introduction, video, case_scenario).
 * Any RefreshDatabase test that seeds case_scenario_progression,
 * expected_learning_outcome, mentor_materials, or mentor_course_intro fails
 * on SQLite with a CHECK constraint violation. Converting to a plain string
 * column removes the enforced value list at the DB layer entirely (valid
 * values are still enforced by ProgramModuleContent/ContentsRelationManager
 * at the application layer), fixing this for both drivers going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_module_contents', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE program_module_contents MODIFY type ENUM(
                'introduction',
                'video',
                'case_scenario',
                'expected_learning_outcome',
                'case_scenario_progression',
                'mentor_materials',
                'mentor_course_intro'
            ) NOT NULL");
        }
    }
};
