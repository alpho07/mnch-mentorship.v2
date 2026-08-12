<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_event_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_section_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['survey_event_id', 'survey_section_id'], 'survey_event_section_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_event_sections');
    }
};
