<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_sections', function (Blueprint $table) {
            $table->json('display_conditions')->nullable()->after('is_active');
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->json('display_conditions')->nullable()->after('is_active');
        });
        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->json('display_conditions')->nullable()->after('description');
        });
        Schema::table('commodities', function (Blueprint $table) {
            $table->json('display_conditions')->nullable()->after('indent_level');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_sections', function (Blueprint $table) {
            $table->dropColumn('display_conditions');
        });
        Schema::table('assessment_departments', function (Blueprint $table) {
            $table->dropColumn('display_conditions');
        });
        Schema::table('commodity_categories', function (Blueprint $table) {
            $table->dropColumn('display_conditions');
        });
        Schema::table('commodities', function (Blueprint $table) {
            $table->dropColumn('display_conditions');
        });
    }
};
