<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns confirmed dead: not in Assessment::$fillable, superseded by the
     * generic assessment_question_responses.metadata JSON approach (see the
     * INFRA_NBU/INFRA_PAED special-cased fields in DynamicFormBuilder).
     * is_locked/locked_at/locked_by are NOT touched — actively used by
     * HasTeamAuthorization.
     */
    private const DEAD_COLUMNS = [
        'has_nbu',
        'nbu_nicu_beds',
        'nbu_general_cots',
        'nbu_kmc_beds',
        'nbu_comments',
        'has_paediatric',
        'paediatric_general_beds',
        'paediatric_picu_beds',
        'paediatric_comments',
    ];

    public function up(): void
    {
        // MySQL-only: verified working against the real database. On a
        // fresh SQLite test database, SQLite's DROP COLUMN (via Doctrine's
        // copy-and-recreate compatibility layer) triggers a full schema
        // re-validation that trips over an unrelated, pre-existing stale
        // index on training_participants — a latent issue in that table's
        // own migration, not something this cleanup step causes. None of
        // this feature's tests depend on these dead columns being absent.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('assessments', function (Blueprint $table) {
            $existing = array_filter(self::DEAD_COLUMNS, fn (string $column) => Schema::hasColumn('assessments', $column));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }

    /**
     * Reverse the migrations. Data loss on rollback is acceptable — these
     * columns are confirmed unused; re-added as nullable for schema parity.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('assessments', function (Blueprint $table) {
            $table->boolean('has_nbu')->nullable();
            $table->integer('nbu_nicu_beds')->default(0);
            $table->integer('nbu_general_cots')->default(0);
            $table->integer('nbu_kmc_beds')->default(0);
            $table->text('nbu_comments')->nullable();
            $table->boolean('has_paediatric')->nullable();
            $table->integer('paediatric_general_beds')->default(0);
            $table->integer('paediatric_picu_beds')->default(0);
            $table->text('paediatric_comments')->nullable();
        });
    }
};
