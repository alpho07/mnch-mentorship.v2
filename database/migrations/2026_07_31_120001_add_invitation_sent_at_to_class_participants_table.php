<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ManageClassMentees's invitation-sending actions (existing, unmodified)
 * rely on class_participants.invitation_sent_at, which exists in
 * production but was never captured by a tracked migration. Adds it
 * idempotently so a fresh SQLite/RefreshDatabase schema matches reality.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('class_participants', 'invitation_sent_at')) {
                $table->timestamp('invitation_sent_at')->nullable()->after('enrolled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_participants', function (Blueprint $table) {
            if (Schema::hasColumn('class_participants', 'invitation_sent_at')) {
                $table->dropColumn('invitation_sent_at');
            }
        });
    }
};
