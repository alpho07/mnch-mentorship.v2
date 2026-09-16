<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->boolean('trained_before_mentorship')->nullable()->after('feedback_notes');
            $table->unsignedBigInteger('trained_marked_by')->nullable()->after('trained_before_mentorship');
            $table->timestamp('trained_marked_at')->nullable()->after('trained_marked_by');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['trained_before_mentorship', 'trained_marked_by', 'trained_marked_at']);
        });
    }
};
