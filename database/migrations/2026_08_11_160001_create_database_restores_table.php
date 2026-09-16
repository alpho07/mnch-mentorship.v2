<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('database_restores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_backup_id')->constrained('database_backups')->cascadeOnDelete();
            $table->foreignId('safety_backup_id')->nullable()->constrained('database_backups')->nullOnDelete();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->foreignId('restored_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_restores');
    }
};
