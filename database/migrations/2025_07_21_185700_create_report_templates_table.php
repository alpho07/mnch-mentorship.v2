<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * report_templates has always existed as a live table (referenced by
     * ReportTemplate, MonthlyReport, and FacilityReportTemplate models,
     * plus the monthly_reports FK) but had no migration file anywhere in
     * this repo — found while restoring monthly_reports (Phase 1 risk
     * 9.1a). Schema below is copied exactly from `SHOW CREATE TABLE
     * report_templates` on the real database, guarded by hasTable() so
     * this is a no-op there and only creates the table on a fresh
     * install / the sqlite test database.
     */
    public function up(): void
    {
        if (Schema::hasTable('report_templates')) {
            return;
        }

        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('report_type', ['newborn', 'pediatric', 'general']);
            $table->enum('frequency', ['monthly', 'quarterly', 'annually'])->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->json('dhis2_mapping')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
