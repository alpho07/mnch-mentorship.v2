<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * human_resource_responses.cadre_id was created with a foreign key
     * inferred from Laravel's naming convention (cadre_id -> cadres), but
     * the column has always actually referenced assessment_cadres (the
     * MainCadre model) — confirmed by real data containing cadre_id
     * values (15-22) that only exist in assessment_cadres, not cadres.
     * In production the constraint was never actually enforced (no FK
     * currently exists on this column at all), which is how the mismatch
     * went unnoticed; a fresh environment migrating from scratch would
     * otherwise have the wrong constraint silently block valid inserts.
     * Handles either starting state: constraint present pointing at
     * `cadres`, or absent entirely.
     */
    public function up(): void
    {
        $this->dropExistingForeignKeyIfPresent();

        // A handful of rows reference a cadre_id that no longer exists in
        // assessment_cadres (deleted at some point, with nothing to
        // cascade the deletion since no FK enforced it) — all carry zero
        // values in every count column, i.e. empty placeholders with no
        // real facility data, safe to remove before the constraint can
        // be added.
        DB::table('human_resource_responses')
            ->whereNotIn('cadre_id', function ($query) {
                $query->select('id')->from('assessment_cadres');
            })
            ->delete();

        Schema::table('human_resource_responses', function (Blueprint $table) {
            $table->foreign('cadre_id')->references('id')->on('assessment_cadres')->cascadeOnDelete();
        });
    }

    /**
     * Only drops the corrected constraint — does not restore the
     * original `cadres` FK. That constraint was never actually valid
     * against real data (cadre_id values like 2-14 only exist in
     * assessment_cadres), so recreating it here would simply fail the
     * same way the broken migration always would have on a fresh
     * database; the "original" state isn't one worth restoring.
     */
    public function down(): void
    {
        Schema::table('human_resource_responses', function (Blueprint $table) {
            $table->dropForeign(['cadre_id']);
        });
    }

    private function dropExistingForeignKeyIfPresent(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite has no reliable named-constraint introspection here;
            // dropForeign() succeeds when a fresh migrate created the
            // constraint (the common test case) and simply has nothing
            // to do if it's already absent.
            try {
                Schema::table('human_resource_responses', function (Blueprint $table) {
                    $table->dropForeign(['cadre_id']);
                });
            } catch (\Throwable $e) {
                // No such constraint — nothing to drop.
            }

            return;
        }

        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'human_resource_responses'
             AND COLUMN_NAME = 'cadre_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
        );

        foreach ($constraints as $constraint) {
            Schema::table('human_resource_responses', function (Blueprint $table) use ($constraint) {
                $table->dropForeign($constraint->CONSTRAINT_NAME);
            });
        }
    }
};
