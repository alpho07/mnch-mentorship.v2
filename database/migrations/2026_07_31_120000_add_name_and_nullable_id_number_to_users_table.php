<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The base users migration (2025_07_04_205113_create_users_table) is
 * significantly out of sync with the real database: `name` exists there
 * (User::$fillable already declares it and it's used throughout the app)
 * and first_name/last_name/email/id_number/phone/status/password are all
 * nullable in production, but none of this is reflected in this migration
 * set. This brings a fresh SQLite/RefreshDatabase schema in line with
 * production reality (verified via `SHOW COLUMNS FROM users` against the
 * real database).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable()->after('last_name');
            }
        });

        $nullableColumns = ['first_name', 'last_name', 'email', 'id_number', 'phone', 'status', 'password'];

        if (DB::getDriverName() === 'mysql') {
            foreach ($nullableColumns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    DB::statement("ALTER TABLE users MODIFY {$column} VARCHAR(255) NULL");
                }
            }
        } else {
            Schema::table('users', function (Blueprint $table) use ($nullableColumns) {
                foreach ($nullableColumns as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->string($column)->nullable()->change();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
