<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('assessment_sections', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // infrastructure, skills_lab, etc.
            $table->string('name');
            $table->text('description')->nullable();

            // Section Type: dynamic_questions | structured_data | commodity_matrix
            $table->enum('section_type', ['dynamic_questions', 'structured_data', 'commodity_matrix'])
                    ->default('dynamic_questions');

            $table->boolean('is_scored')->default(true);
            $table->integer('order')->default(0);
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index(['is_active', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('assessment_sections');
    }
};
