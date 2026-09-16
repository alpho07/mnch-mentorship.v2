<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentorship_module_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentorship_id')
                  ->comment('References trainings.id (the mentorship)')
                  ->constrained('trainings')
                  ->cascadeOnDelete();
            $table->foreignId('module_id')
                  ->constrained('program_modules')
                  ->cascadeOnDelete();
            $table->foreignId('first_class_id')
                  ->comment('The class where this module was first assigned')
                  ->constrained('mentorship_classes')
                  ->cascadeOnDelete();
            $table->timestamps();

            // CRITICAL DOMAIN INVARIANT: A module can only be taught ONCE per mentorship
            $table->unique(['mentorship_id', 'module_id'], 'unique_module_per_mentorship');

            // Performance index for filtering available modules
            $table->index('mentorship_id', 'idx_mentorship_usages');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_module_usages');
    }
};
