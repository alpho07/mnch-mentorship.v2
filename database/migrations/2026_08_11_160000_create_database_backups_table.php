<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('disk')->default('backups');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->enum('type', ['manual', 'scheduled', 'pre_restore_safety']);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');
    }
};
