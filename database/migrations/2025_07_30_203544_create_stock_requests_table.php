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
       Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('requesting_facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('central_store_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('dispatched_by')->nullable()->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->enum('status', [
                'pending', 'approved', 'partially_approved', 'rejected',
                'dispatched', 'partially_dispatched', 'received', 
                'partially_received', 'cancelled'
            ])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('request_date');
            $table->date('approved_date')->nullable();
            $table->date('dispatch_date')->nullable();
            $table->date('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->integer('total_items')->default(0);
            $table->decimal('total_value', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index(['requesting_facility_id', 'status']);
            $table->index(['central_store_id', 'status']);
            $table->index(['request_date', 'status']);
            $table->index(['requested_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_requests');
    }
};
