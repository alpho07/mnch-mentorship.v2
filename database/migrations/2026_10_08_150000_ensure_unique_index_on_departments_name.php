<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fresh installs get departments.name UNIQUE from create_departments_table,
     * but the production table was created without it and accumulated duplicate
     * rows (cleaned up separately). Add the index only where it is missing.
     * Purely additive: no rows are touched, and it fails safely (nothing
     * changes) if duplicates exist.
     */
    public function up(): void
    {
        if (Schema::hasIndex('departments', ['name'], 'unique')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->unique('name', 'departments_name_unique');
        });
    }

    /**
     * Intentionally empty: on fresh installs the index belongs to the original
     * create migration, so rolling this one back must not drop it.
     */
    public function down(): void
    {
    }
};
