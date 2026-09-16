<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->string('old_condition')->nullable();
            $table->string('new_condition')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['changed_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_status_logs');
    }
};