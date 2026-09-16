<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('resource_program_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_module_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['resource_id', 'program_module_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('resource_program_modules');
    }
};
