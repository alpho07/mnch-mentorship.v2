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
        Schema::create('module_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_module_id')->constrained('program_modules')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('case_scenario')->nullable();
            $table->unsignedSmallInteger('total_marks');
            $table->unsignedSmallInteger('pass_marks');
            $table->decimal('pass_percentage', 5, 2)->default(85.00);
            $table->json('equipment_supplies')->nullable();
            $table->json('debrief_questions')->nullable();
            $table->unsignedSmallInteger('order_sequence')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['program_module_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_rubrics');
    }
};
