<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessment_commodity_responses', function (Blueprint $table) {
            $table->boolean('not_applicable')->default(false)->after('available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_commodity_responses', function (Blueprint $table) {
            $table->dropColumn('not_applicable');
        });
    }
};
