<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * assessment_types.id was created as a plain signed INT instead of
     * Laravel's standard bigint-unsigned auto-increment primary key —
     * incompatible with foreignId()'s FK type elsewhere in the app. Table
     * is empty (0 rows) in the real (MySQL) database, so widening the type
     * in place is safe. Raw SQL (not the schema builder) because Doctrine
     * DBAL can be unreliable changing an auto_increment primary key
     * column's type. MySQL-only: a fresh SQLite test database never has
     * this historical mismatch — Schema::create() there already produces
     * the correct type — and SQLite doesn't support MODIFY COLUMN syntax.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('assessment_types', 'id')) {
            DB::statement('ALTER TABLE `assessment_types` MODIFY `id` BIGINT UNSIGNED AUTO_INCREMENT');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `assessment_types` MODIFY `id` INT AUTO_INCREMENT');
        }
    }
};
