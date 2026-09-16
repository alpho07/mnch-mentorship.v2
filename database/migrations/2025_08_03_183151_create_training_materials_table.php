<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->onDelete('cascade');
            $table->foreignId('inventory_item_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_planned')->default(0);
            $table->integer('quantity_used')->default(0);
            $table->integer('returned_quantity')->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->text('usage_notes')->nullable();
            $table->timestamps();

            $table->index(['training_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_materials');
    }
};