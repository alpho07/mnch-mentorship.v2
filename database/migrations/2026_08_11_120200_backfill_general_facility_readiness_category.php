<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STANDARD_TYPE_CODE = 'STANDARD_FACILITY_ASSESSMENT';

    private const CATEGORY_NAME = 'General Facility Readiness';

    /**
     * Every AssessmentType needs a category going forward. The pre-existing
     * "Standard Facility Assessment" type (itself backfilled by
     * 2026_08_01_161245_backfill_standard_facility_assessment_type) predates
     * categorization entirely, so it's assigned a catch-all category here.
     * Idempotent — safe to run more than once.
     */
    public function up(): void
    {
        $categoryId = DB::table('assessment_type_categories')->where('name', self::CATEGORY_NAME)->value('id');

        if (! $categoryId) {
            $categoryId = DB::table('assessment_type_categories')->insertGetId([
                'name' => self::CATEGORY_NAME,
                'description' => 'Catch-all category for facility assessment templates that predate categorization.',
                'order' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('assessment_types')
            ->where('code', self::STANDARD_TYPE_CODE)
            ->whereNull('category_id')
            ->update(['category_id' => $categoryId]);
    }

    public function down(): void
    {
        DB::table('assessment_types')
            ->where('code', self::STANDARD_TYPE_CODE)
            ->update(['category_id' => null]);
    }
};
