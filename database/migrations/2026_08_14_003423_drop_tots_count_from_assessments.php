<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reverses 2026_08_13_160000_add_tots_count_to_assessments.php — ToTs is
 * now its own cadre-style row in the Human Resources matrix (per-training-
 * area counts via HumanResourceResponse, gated with na_training_columns
 * including 'total_in_facility'), not a single flat count on the
 * assessment record. No live data used this column (0 rows had it set).
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only: verified working against the real database. On a
        // fresh SQLite test database, SQLite's DROP COLUMN (via Doctrine's
        // copy-and-recreate compatibility layer) triggers a full schema
        // re-validation that trips over an unrelated, pre-existing stale
        // index on training_participants — same latent issue worked around
        // in 2026_08_01_161245_drop_dead_nbu_paediatric_columns_from_assessments_table.php.
        // No test depends on tots_count being absent.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('assessments', function (Blueprint $table) {
            if (Schema::hasColumn('assessments', 'tots_count')) {
                $table->dropColumn('tots_count');
            }
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedInteger('tots_count')->nullable()->after('excluded_cadre_ids');
        });
    }
};
