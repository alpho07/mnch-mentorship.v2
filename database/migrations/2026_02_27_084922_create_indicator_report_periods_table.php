<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('indicator_report_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('facility_id')
                    ->constrained('facilities')
                    ->cascadeOnDelete();
            $table->foreignId('report_type_id')
                    ->constrained('indicator_report_types')
                    ->cascadeOnDelete();
            $table->foreignId('frequency_id')
                    ->constrained('indicator_frequencies')
                    ->cascadeOnDelete();

            // Period definition
            $table->unsignedSmallInteger('period_year');     // 2025
            $table->unsignedTinyInteger('period_month')->nullable();   // 1–12 (monthly)
            $table->unsignedTinyInteger('period_quarter')->nullable(); // 1–4 (quarterly)
            // Computed DHIS2 period string (e.g., '202501', '2025Q1', '2025')
            $table->string('dhis2_period', 20)->nullable();

            // Workflow status
            $table->enum('status', [
                'draft',
                'submitted',
                'validated',
                'rejected',
                'pushed_to_dhis2',
            ])->default('draft');

            // Submission tracking
            $table->foreignId('submitted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            // Validation tracking
            $table->foreignId('validated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // DHIS2 push tracking
            $table->enum('dhis2_push_status', ['pending', 'success', 'failed'])->nullable();
            $table->timestamp('dhis2_push_at')->nullable();
            $table->json('dhis2_import_summary')->nullable(); // raw DHIS2 API response

            $table->text('notes')->nullable();
            $table->timestamps();

            // One report per facility/type/frequency/period
            $table->unique(
                    ['facility_id', 'report_type_id', 'frequency_id', 'period_year', 'period_month', 'period_quarter'],
                    'unique_indicator_period'
            );

            $table->index(['facility_id', 'status']);
           // $table->index(['report_type_id', 'period_year', 'period_month']);
            $table->index('status');
        });
    }

    public function down(): void {
        Schema::dropIfExists('indicator_report_periods');
    }
};
