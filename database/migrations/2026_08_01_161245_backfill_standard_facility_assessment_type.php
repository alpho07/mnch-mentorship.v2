<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STANDARD_TYPE_CODE = 'STANDARD_FACILITY_ASSESSMENT';

    /**
     * Run the migrations.
     *
     * Templatizes the existing global sections/questions as a real
     * AssessmentType so historical assessments and sections have
     * something to point at. Idempotent — safe to run more than once.
     */
    public function up(): void
    {
        $typeId = DB::table('assessment_types')->where('code', self::STANDARD_TYPE_CODE)->value('id');

        if (! $typeId) {
            $typeId = DB::table('assessment_types')->insertGetId([
                'name' => 'Standard Facility Assessment',
                'code' => self::STANDARD_TYPE_CODE,
                'description' => 'The original facility assessment structure (Infrastructure, Skills Lab, Human Resources, Health Products, Information Systems, Quality of Care), templatized from the pre-existing global sections and questions.',
                'version' => '1.0',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('assessment_sections')
            ->whereNull('assessment_type_id')
            ->update(['assessment_type_id' => $typeId]);

        DB::table('assessments')
            ->whereNull('assessment_type_id')
            ->update(['assessment_type_id' => $typeId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $typeId = DB::table('assessment_types')->where('code', self::STANDARD_TYPE_CODE)->value('id');

        if (! $typeId) {
            return;
        }

        DB::table('assessment_sections')->where('assessment_type_id', $typeId)->update(['assessment_type_id' => null]);
        DB::table('assessments')->where('assessment_type_id', $typeId)->update(['assessment_type_id' => null]);
        DB::table('assessment_types')->where('id', $typeId)->delete();
    }
};
