<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         // Create pivot table for training-category relationships
        Schema::create('training_assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_category_id')->constrained('global_assessment_categories')->cascadeOnDelete();
            $table->decimal('pass_threshold', 5, 2)->default(70.00); // Training-specific threshold
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['training_id', 'assessment_category_id'],'training_id_assm');
            $table->index('training_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
      
        
        Schema::dropIfExists('training_assessment_categories');
   
    }
}; 