<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('human_resource_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cadre_id')->constrained()->cascadeOnDelete();

            // Training Counts (14 cadres × 6 training types)
            $table->integer('total_in_facility')->default(0);
            $table->integer('etat_plus')->default(0);
            $table->integer('comprehensive_newborn_care')->default(0);
            $table->integer('imnci')->default(0);
            $table->integer('type_1_diabetes')->default(0);
            $table->integer('essential_newborn_care')->default(0);

            $table->timestamps();

            $table->unique(['assessment_id', 'cadre_id']);
            $table->index('assessment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('human_resource_responses');
    }
};
