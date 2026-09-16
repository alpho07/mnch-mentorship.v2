<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->boolean('feedback_given')->default(false)->after('updated_by');
            $table->foreignId('feedback_given_by')->nullable()->constrained('users')->nullOnDelete()->after('feedback_given');
            $table->timestamp('feedback_given_at')->nullable()->after('feedback_given_by');
            $table->text('feedback_notes')->nullable()->after('feedback_given_at');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feedback_given_by');
            $table->dropColumn(['feedback_given', 'feedback_given_at', 'feedback_notes']);
        });
    }
};
