<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->foreignId('assessment_type_id')->nullable()->after('id')->constrained('assessment_types')->nullOnDelete();
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->foreignId('assessment_type_id')->nullable()->after('id')->constrained('assessment_types')->nullOnDelete();
        });
        Schema::table('assessment_cadres', function (Blueprint $table) {
            $table->foreignId('assessment_type_id')->nullable()->after('id')->constrained('assessment_types')->nullOnDelete();
        });

        // Backfill: every existing row in these three tables today belongs
        // to the one live "Standard Facility Assessment" template — the
        // only assessment type that currently consumes commodities,
        // departments, or this cadre list at all.
        $standardTypeId = DB::table('assessment_types')->where('code', 'STANDARD_FACILITY_ASSESSMENT')->value('id');

        if ($standardTypeId) {
            DB::table('commodity_categories')->whereNull('assessment_type_id')->update(['assessment_type_id' => $standardTypeId]);
            DB::table('assessment_departments')->whereNull('assessment_type_id')->update(['assessment_type_id' => $standardTypeId]);
            DB::table('assessment_cadres')->whereNull('assessment_type_id')->update(['assessment_type_id' => $standardTypeId]);
        }

        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->dropUnique('commodity_categories_slug_unique');
            $table->unique(['assessment_type_id', 'slug']);
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->dropUnique('assessment_departments_slug_unique');
            $table->unique(['assessment_type_id', 'slug']);
        });
        Schema::table('assessment_cadres', function (Blueprint $table) {
            $table->dropUnique('assessment_cadres_code_unique');
            $table->unique(['assessment_type_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->dropUnique(['assessment_type_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('assessment_type_id');
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->dropUnique(['assessment_type_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('assessment_type_id');
        });
        Schema::table('assessment_cadres', function (Blueprint $table) {
            $table->dropUnique(['assessment_type_id', 'code']);
            $table->unique('code');
            $table->dropConstrainedForeignId('assessment_type_id');
        });
    }
};
