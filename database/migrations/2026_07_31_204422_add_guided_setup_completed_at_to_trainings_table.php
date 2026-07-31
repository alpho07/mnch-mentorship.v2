<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->timestamp('guided_setup_completed_at')->nullable()->after('identifier');
        });

        // Backfill existing rows so they aren't mistaken for abandoned guided
        // wizard drafts — only trainings created after this migration can be
        // genuinely "pending" (NULL).
        DB::table('trainings')->whereNull('guided_setup_completed_at')->update([
            'guided_setup_completed_at' => DB::raw('created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('guided_setup_completed_at');
        });
    }
};
