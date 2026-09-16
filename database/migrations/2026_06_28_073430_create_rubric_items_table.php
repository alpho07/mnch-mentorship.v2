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
        Schema::create('rubric_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_rubric_id')->constrained('module_rubrics')->cascadeOnDelete();
            $table->text('description');
            $table->text('guidance')->nullable();
            $table->unsignedSmallInteger('order_sequence');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rubric_items');
    }
};
