<?php

// Enhanced Suppliers Migration
// 2024_01_01_000002_create_suppliers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('supplier_code')->unique();
            $table->enum('supplier_type', [
                'manufacturer', 'distributor', 'wholesaler', 
                'retailer', 'government', 'ngo'
            ])->default('distributor');
            $table->enum('status', [
                'active', 'inactive', 'suspended', 'blacklisted'
            ])->default('active');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 5)->default('KE');
            $table->string('tax_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->enum('payment_terms', [
                'cash_on_delivery', 'net_7', 'net_15', 
                'net_30', 'net_60', 'net_90'
            ])->default('net_30');
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('requires_po')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'supplier_type']);
            $table->index(['name']);
            $table->index(['is_preferred']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};