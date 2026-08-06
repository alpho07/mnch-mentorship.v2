<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot table backing ReportTemplate::indicators(). Same story as
     * report_templates (see that migration's comment) — exists live,
     * no migration file anywhere. Schema copied exactly from `SHOW CREATE
     * TABLE report_template_indicators`: no FK constraints on
     * report_template_id/indicator_id in the real database (matched here
     * rather than inventing stricter constraints the live data was never
     * validated against).
     */
    public function up(): void
    {
        if (Schema::hasTable('report_template_indicators')) {
            return;
        }

        Schema::create('report_template_indicators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_template_id');
            $table->unsignedBigInteger('indicator_id');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_template_indicators');
    }
};
