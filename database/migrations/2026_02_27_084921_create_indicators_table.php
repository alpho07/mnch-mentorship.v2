<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                    ->constrained('indicator_groups')
                    ->cascadeOnDelete();

            // Self-referential: for sub-banded indicators (e.g., weight bands under a parent)
            $table->foreignId('parent_indicator_id')
                    ->nullable()
                    ->constrained('indicators')
                    ->nullOnDelete();

            $table->string('code', 30);                      // '1a', '2b', '7c', '7b_lt1000'
            $table->text('name');                            // Full indicator text
            $table->string('short_name', 255)->nullable();   // Abbreviated for tables/headers
            // Data collection type
            $table->enum('indicator_type', [
                'proportion', // has numerator + denominator → computed %
                'count', // single integer count
                'rate', // per 1000 or per 100,000
                'yes_no', // binary (e.g., audit meeting held)
            ])->default('proportion');

            // M&E category
            $table->enum('category', [
                'process',
                'output',
                'outcome',
                'satisfaction',
            ])->default('output');

            // Numerator / Denominator configuration
            $table->boolean('has_numerator')->default(true);
            $table->boolean('has_denominator')->default(true);
            $table->text('numerator_label')->nullable();     // What to enter in numerator
            $table->text('denominator_label')->nullable();   // What to enter in denominator
            // Source of data
            $table->string('source_document', 255)->nullable();   // Human readable
            $table->string('source_document_code', 50)->nullable(); // 'MOH_377', 'KMC_REG', 'NAR'
            // DHIS2 mappings (nullable — filled when DHIS2 integration is configured)
            $table->string('dhis2_numerator_uid', 100)->nullable();
            $table->string('dhis2_denominator_uid', 100)->nullable();
            $table->string('dhis2_indicator_uid', 100)->nullable();
            $table->string('dhis2_data_element_uid', 100)->nullable(); // for count/yes_no types
            // Validation constraints
            $table->unsignedInteger('min_value')->nullable();
            $table->unsignedInteger('max_value')->nullable();

            // UI helpers
            $table->text('display_hint')->nullable();        // helper text shown on data entry form
            $table->text('definition')->nullable();          // detailed definition for glossary tab

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group_id', 'sort_order']);
            $table->index('indicator_type');
            $table->index('parent_indicator_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('indicators');
    }
};
