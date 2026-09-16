<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_participants', function (Blueprint $table) {
            $table->timestamp('mentor_approved_at')->nullable()->after('completed_at');
            $table->foreignId('mentor_approved_by')->nullable()->after('mentor_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('head_drmh_approved_at')->nullable()->after('mentor_approved_by');
            $table->foreignId('head_drmh_approved_by')->nullable()->after('head_drmh_approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mentor_approved_by');
            $table->dropConstrainedForeignId('head_drmh_approved_by');
            $table->dropColumn('mentor_approved_at');
            $table->dropColumn('head_drmh_approved_at');
        });
    }
};
