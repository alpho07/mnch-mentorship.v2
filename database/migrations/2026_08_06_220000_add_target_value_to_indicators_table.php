<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * indicators.target_value exists live in the real database (int
     * unsigned, nullable) and is read by IndicatorsProgressDashboard
     * (app/Filament/Pages/Indicators/), but had no migration anywhere —
     * found while investigating a 500 on admin/indicators/progress-dashboard
     * during route smoke testing. Same "live but unmigrated" pattern as
     * monthly_reports/report_templates earlier this session. Guarded so
     * it's a no-op against the real database and only adds the column on
     * a fresh install / the sqlite test database.
     */
    public function up(): void
    {
        if (Schema::hasColumn('indicators', 'target_value')) {
            return;
        }

        Schema::table('indicators', function (Blueprint $table) {
            $table->unsignedInteger('target_value')->nullable()->after('max_value');
        });
    }

    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->dropColumn('target_value');
        });
    }
};
