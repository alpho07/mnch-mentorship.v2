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
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('current_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->integer('available_stock')->default(0);
            $table->string('location')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->enum('condition', [
                'new',
                'good',
                'fair',
                'poor',
                'damaged',
                'expired',
                'lost',
                'stolen',
                'decommissioned',
                'disposed'
            ])->default('new');

            $table->string('serial_number')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('last_updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['facility_id', 'inventory_item_id', 'batch_number'], 'unique_facility_item_batch');
            $table->index(['facility_id', 'current_stock']);
            $table->index(['inventory_item_id', 'current_stock']);
            $table->index(['expiry_date']);
            $table->index(['available_stock']);
            $table->index(['condition']);
            $table->index(['serial_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
