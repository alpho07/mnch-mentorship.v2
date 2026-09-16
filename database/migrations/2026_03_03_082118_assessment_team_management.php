<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        // ── 1. Add lock columns to assessments ──────────────────────────────
        Schema::table('assessments', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('updated_by');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete()->after('locked_at');
        });

        // ── 2. Assessment team pivot ─────────────────────────────────────────
        Schema::create('assessment_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['team_lead', 'member'])->default('member');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            $table->unique(['assessment_id', 'user_id']);
            $table->index(['assessment_id', 'role']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('assessment_team');

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['is_locked', 'locked_at']);
        });
    }
}; 
