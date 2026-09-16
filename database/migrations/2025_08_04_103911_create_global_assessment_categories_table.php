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
        // First, create global assessment_categories table (remove training_id)
        Schema::create('global_assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->decimal('weight_percentage', 5, 2)->default(25.00);
            $table->string('assessment_method')->default('Practical Demonstration');
            $table->integer('order_sequence')->default(1);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index('order_sequence');
            $table->index('is_required');
        });


       
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void    {
    
        Schema::dropIfExists('global_assessment_categories');
    }
}; 