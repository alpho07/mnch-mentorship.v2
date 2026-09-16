<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentee_module_progress', function (Blueprint $table) {
            $table->string('video_review_status')->default('pending')->after('hands_on_video_path');
            $table->timestamp('video_reviewed_at')->nullable()->after('video_review_status');
            $table->foreignId('video_reviewed_by')->nullable()->after('video_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('video_review_notes')->nullable()->after('video_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('mentee_module_progress', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_reviewed_by');
            $table->dropColumn('video_review_notes');
            $table->dropColumn('video_reviewed_at');
            $table->dropColumn('video_review_status');
        });
    }
};
