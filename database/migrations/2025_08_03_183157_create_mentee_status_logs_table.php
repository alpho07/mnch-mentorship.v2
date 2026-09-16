<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('mentee_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->date('effective_date');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('facility_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

             $table->index(['user_id', 'effective_date']);
            $table->index(['new_status', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentee_status_logs');
    }
};