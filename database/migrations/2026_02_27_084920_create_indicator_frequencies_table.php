<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('indicator_frequencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();           // 'monthly', 'quarterly', 'annually'
            $table->string('name', 100);                    // 'Monthly'
            $table->string('dhis2_period_type', 50)->nullable(); // 'Monthly', 'Quarterly', 'Yearly'
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Pivot: report types ↔ frequencies
        Schema::create('indicator_report_type_frequencies', function (Blueprint $table) {
            $table->foreignId('report_type_id')
                    ->constrained('indicator_report_types')
                    ->cascadeOnDelete();
            $table->foreignId('frequency_id')
                    ->constrained('indicator_frequencies')
                    ->cascadeOnDelete();
            $table->primary(['report_type_id', 'frequency_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('indicator_report_type_frequencies');
        Schema::dropIfExists('indicator_frequencies');
    }
};
