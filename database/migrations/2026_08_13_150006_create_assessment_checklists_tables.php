<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_type_id')->nullable()->constrained('assessment_types')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_checklist_id')->constrained('assessment_checklists')->cascadeOnDelete();
            $table->string('group_label')->nullable();
            $table->string('label');
            $table->unsignedInteger('qty')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['assessment_checklist_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_checklist_items');
        Schema::dropIfExists('assessment_checklists');
    }
};
