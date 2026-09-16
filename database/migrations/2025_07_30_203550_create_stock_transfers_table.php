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
            Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('to_facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('dispatched_by')->nullable()->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'in_transit', 
                'delivered', 'cancelled', 'partially_received'
            ])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('transfer_date');
            $table->date('approved_date')->nullable();
            $table->date('dispatch_date')->nullable();
            $table->date('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->enum('approval_level', ['facility', 'regional', 'national'])->default('facility');
            $table->integer('total_items')->default(0);
            $table->decimal('total_value', 12, 2)->default(0);
            $table->string('tracking_number')->nullable();
            $table->string('transport_method')->nullable();
            $table->timestamp('estimated_arrival')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'from_facility_id']);
            $table->index(['status', 'to_facility_id']);
            $table->index(['transfer_date', 'status']);
            $table->index(['tracking_number']);
            $table->index(['priority', 'status']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
