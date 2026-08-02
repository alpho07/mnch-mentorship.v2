<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable — no time limit unless an admin explicitly sets one on a
     * quiz (team decision 2026-07-24: timing is wanted, but the exact
     * duration per quiz is still being confirmed, so this defaults to
     * "off" rather than forcing a number on every existing quiz).
     */
    public function up(): void
    {
        Schema::table('program_module_quizzes', function (Blueprint $table) {
            $table->unsignedSmallInteger('time_limit_minutes')->nullable()->after('pass_mark_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('program_module_quizzes', function (Blueprint $table) {
            $table->dropColumn('time_limit_minutes');
        });
    }
};
