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
        Schema::create('rubric_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_rubric_id')->constrained('module_rubrics')->cascadeOnDelete();
            $table->foreignId('class_module_id')->nullable()->constrained('class_modules')->nullOnDelete();
            $table->foreignId('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('score');
            $table->boolean('passed');
            $table->text('notes')->nullable();
            $table->timestamp('assessed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rubric_assessments');
    }
};
