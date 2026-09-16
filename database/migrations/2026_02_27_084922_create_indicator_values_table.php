<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('indicator_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('period_id')
                    ->constrained('indicator_report_periods')
                    ->cascadeOnDelete();
            $table->foreignId('indicator_id')
                    ->constrained('indicators')
                    ->cascadeOnDelete();

            // Value storage — only the relevant field(s) are populated per indicator_type
            $table->unsignedInteger('numerator_value')->nullable();   // proportion
            $table->unsignedInteger('denominator_value')->nullable(); // proportion
            $table->decimal('computed_percentage', 8, 4)->nullable(); // auto-computed N/D*100
            $table->unsignedInteger('count_value')->nullable();       // count type
            $table->boolean('yes_no_value')->nullable();               // yes_no type
            // Optional per-indicator comment / data quality note
            $table->text('comment')->nullable();

            // Audit trail
            $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

            $table->timestamps();

            // One value per indicator per period
            $table->unique(['period_id', 'indicator_id']);
            $table->index('indicator_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('indicator_values');
    }
};
