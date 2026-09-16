<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentee_module_progress', function (Blueprint $table) {
            $table->foreignId('pre_test_attempt_id')->nullable()->after('assessment_status')->constrained('quiz_attempts')->nullOnDelete();
            $table->foreignId('post_test_attempt_id')->nullable()->after('pre_test_attempt_id')->constrained('quiz_attempts')->nullOnDelete();
            $table->string('hands_on_video_url')->nullable()->after('post_test_attempt_id');
            $table->string('hands_on_video_path')->nullable()->after('hands_on_video_url');
        });
    }

    public function down(): void
    {
        Schema::table('mentee_module_progress', function (Blueprint $table) {
            $table->dropForeign(['pre_test_attempt_id']);
            $table->dropForeign(['post_test_attempt_id']);
            $table->dropColumn(['pre_test_attempt_id', 'post_test_attempt_id', 'hands_on_video_url', 'hands_on_video_path']);
        });
    }
};
