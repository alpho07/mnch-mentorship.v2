<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

        Schema::table('program_module_contents', function (Blueprint $table) {
            $table->string('manual_reference_url')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('program_module_contents', function (Blueprint $table) {
            $table->dropColumn('manual_reference_url');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE program_module_contents MODIFY type ENUM(
                'introduction',
                'video',
                'case_scenario'
            ) NOT NULL");
        }
    }
};
