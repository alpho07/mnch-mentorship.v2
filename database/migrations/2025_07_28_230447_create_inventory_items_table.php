<?php

// database/migrations/2024_01_01_000003_create_inventory_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->unique()->nullable();

            // Classification
            $table->foreignId('category_id')->constrained('inventory_categories')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();

            // Pricing & Units
            $table->string('unit_of_measure');
            $table->decimal('unit_price', 10, 2)->default(0);

            // Status & Condition Management
            $table->enum('status', [
                'active',
                'inactive',
                'discontinued',
                'recalled',
                'quarantined',
                'restricted'
            ])->default('active');

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

            // Stock Management
            $table->integer('minimum_stock_level')->default(0);
            $table->integer('maximum_stock_level')->nullable();
            $table->integer('reorder_point')->default(0);

            // Product Details
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->integer('warranty_period')->nullable(); // in months
            $table->json('specifications')->nullable(); // Technical specs
            $table->json('storage_requirements')->nullable(); // Storage conditions
            $table->text('disposal_method')->nullable(); // Disposal instructions

            // Tracking Settings
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_trackable')->default(false);
            $table->boolean('expiry_tracking')->default(false);
            $table->boolean('batch_tracking')->default(false);
            $table->boolean('serial_tracking')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['is_active', 'category_id']);
            $table->index(['sku', 'barcode']);
            $table->index(['status', 'condition']);
            $table->index(['reorder_point']);
            $table->index(['supplier_id']);
            $table->index(['manufacturer']);
            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
