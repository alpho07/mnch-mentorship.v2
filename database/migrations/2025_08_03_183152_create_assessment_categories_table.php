<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight_percentage', 5, 2)->default(25.00);
            $table->decimal('pass_threshold', 5, 2)->default(70.00);
            $table->string('assessment_method')->nullable();
            $table->integer('order_sequence')->default(1);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['training_id', 'order_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_categories');
    }
};
