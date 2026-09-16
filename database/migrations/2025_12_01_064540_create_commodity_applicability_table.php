<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('commodity_applicability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_department_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Pivot table: if commodity-department pair exists, it's applicable
            // If not, it's N/A
            $table->unique(['commodity_id', 'assessment_department_id'], 'commodity_department_unique');
            $table->index('commodity_id');
            $table->index('assessment_department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('commodity_applicability');
    }
};
