<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds 'revoked' to co-mentor status enum and adds invitation_token column.
     */
    public function up(): void
    {
        // Add 'revoked' to the status enum (MySQL only — SQLite does not support MODIFY COLUMN)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `mentorship_co_mentors` MODIFY COLUMN `status` ENUM('pending','accepted','declined','removed','revoked') NOT NULL DEFAULT 'pending'");
        }

        // Add invitation_token column if not exists
        if (!Schema::hasColumn('mentorship_co_mentors', 'invitation_token')) {
            Schema::table('mentorship_co_mentors', function (Blueprint $table) {
                $table->string('invitation_token', 64)->nullable()->unique()->after('permissions');
                $table->index('invitation_token', 'co_mentors_invitation_token_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert 'revoked' records to 'removed' before changing enum
        DB::table('mentorship_co_mentors')
            ->where('status', 'revoked')
            ->update(['status' => 'removed']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `mentorship_co_mentors` MODIFY COLUMN `status` ENUM('pending','accepted','declined','removed') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasColumn('mentorship_co_mentors', 'invitation_token')) {
            Schema::table('mentorship_co_mentors', function (Blueprint $table) {
                $table->dropIndex('co_mentors_invitation_token_index');
                $table->dropColumn('invitation_token');
            });
        }
    }
};
