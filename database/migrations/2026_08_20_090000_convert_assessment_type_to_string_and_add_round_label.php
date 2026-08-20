<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The legacy assessment_type enum column is left untouched —
        // converting it (even via add-copy-drop-rename) requires a
        // dropColumn() call, which fails on this project's SQLite test
        // database due to a pre-existing stale index on an unrelated
        // table (training_participants) that SQLite's full-schema
        // revalidation trips over on ANY dropColumn, anywhere in the
        // schema (see 2026_08_14_003423_drop_tots_count_from_assessments.php
        // for the same issue previously hit). Purely additive instead.
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('round', 20)->nullable()->after('assessment_type');
            $table->string('round_label')->nullable()->after('round');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['round', 'round_label']);
        });
    }
};
