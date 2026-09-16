<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_module_activity_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_module_id')->constrained('class_modules')->cascadeOnDelete();
            $table->foreignId('class_participant_id')->constrained('class_participants')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_module_id', 'class_participant_id', 'activity_id'], 'unique_activity_participant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_module_activity_participants');
    }
};
