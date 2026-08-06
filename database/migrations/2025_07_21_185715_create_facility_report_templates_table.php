<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot table backing ReportTemplate::facilities() / FacilityReportTemplate.
     * Same story as report_templates (see that migration's comment) — exists
     * live, no migration file anywhere. Schema copied exactly from `SHOW
     * CREATE TABLE facility_report_templates`, including its real FK
     * constraints (unlike report_template_indicators, this one has them
     * live).
     */
    public function up(): void
    {
        if (Schema::hasTable('facility_report_templates')) {
            return;
        }

        Schema::create('facility_report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_template_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'report_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_report_templates');
    }
};
