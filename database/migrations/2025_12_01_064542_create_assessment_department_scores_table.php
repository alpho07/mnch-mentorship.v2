<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('assessment_department_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commodity_category_id')->nullable()->constrained()->cascadeOnDelete();

            // Scoring per Department × Category
            $table->integer('available_count')->default(0);
            $table->integer('total_applicable')->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->enum('grade', ['green', 'yellow', 'red'])->nullable();

            $table->timestamps();

            $table->index('assessment_id');
            $table->index('assessment_department_id');
            $table->index('commodity_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('assessment_department_scores');
    }
};
