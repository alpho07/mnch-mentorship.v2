<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')
                  ->constrained('mentorship_classes')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('session_id')
                  ->nullable()
                  ->comment('Nullable: null = enrollment-level attendance, non-null = session-specific');
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('marked_by')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->timestamp('marked_at');
            $table->enum('source', ['auto', 'manual'])
                  ->default('manual')
                  ->comment('auto = via invite link enrollment, manual = mentor/co-mentor');
            $table->timestamps();

            // CRITICAL DOMAIN INVARIANT: One attendance record per user per session per class
            // For nullable session_id, MySQL unique index treats NULLs as distinct,
            // so we add a functional unique index via raw SQL below.
            $table->index(['class_id', 'user_id'], 'idx_class_user_attendance');
            $table->index(['session_id', 'user_id'], 'idx_session_user_attendance');
        });

        // Nullable-safe unique constraint: COALESCE session_id to 0 for uniqueness
        // This ensures UNIQUE(class_id, session_id, user_id) even when session_id is NULL
        //DB::statement('
         //   CREATE UNIQUE INDEX unique_attendance_per_session 
          //  ON class_attendances (class_id, COALESCE(session_id, 0), user_id)
        //');
    }

    public function down(): void
    {
        Schema::dropIfExists('class_attendances');
    }
};
