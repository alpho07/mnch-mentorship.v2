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
    /**
     * The live `assessment_types` table doesn't match what its own creation
     * migration (2025_11_29_120453_create_assessment_types_table) defines —
     * it only has id/name/is_active/timestamps/deleted_at, with `name`
     * mistakenly typed as an int (can't hold a title string at all) and
     * missing code/description/version/validity_period_days/metadata
     * entirely, despite the model/resource already assuming they exist.
     * Table is empty (0 rows) in production, so repairing types in place is
     * safe — nothing to migrate/lose.
     */
    public function up(): void
    {
        if (Schema::hasColumn('assessment_types', 'name')) {
            Schema::table('assessment_types', function (Blueprint $table) {
                $table->string('name')->change();
            });
        }

        Schema::table('assessment_types', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_types', 'code')) {
                $table->string('code')->nullable()->after('name');
            }
            if (! Schema::hasColumn('assessment_types', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
            if (! Schema::hasColumn('assessment_types', 'version')) {
                $table->string('version')->default('1.0')->after('description');
            }
            if (! Schema::hasColumn('assessment_types', 'validity_period_days')) {
                $table->integer('validity_period_days')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('assessment_types', 'metadata')) {
                $table->json('metadata')->nullable()->after('validity_period_days');
            }
            if (! Schema::hasColumn('assessment_types', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // `code` needs to be unique once every row has one, but the column
        // was just added as nullable above — add the unique index in the
        // same migration since the table is empty (no existing duplicates
        // to violate it). MySQL-only: on a fresh SQLite test database, the
        // `code` column already came with a unique index from the original
        // creation migration (2025_11_29_120453) — the real MySQL database
        // is missing it as part of the same schema drift this migration
        // repairs.
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('assessment_types', function (Blueprint $table) {
                $table->unique('code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('assessment_types', function (Blueprint $table) {
                $table->dropUnique(['code']);
            });
        }

        Schema::table('assessment_types', function (Blueprint $table) {
            $table->dropColumn(['code', 'description', 'version', 'validity_period_days', 'metadata']);
        });
    }
};
