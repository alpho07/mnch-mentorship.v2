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
        Schema::table('assessment_types', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_types', 'period_start')) {
                $table->date('period_start')->nullable()->after('validity_period_days');
            }
            if (! Schema::hasColumn('assessment_types', 'period_end')) {
                $table->date('period_end')->nullable()->after('period_start');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_types', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
