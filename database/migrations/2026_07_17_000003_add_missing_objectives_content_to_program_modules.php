<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The base migration (2025_12_10_185911_add_mentorshhip_system_table) only creates
 * program_modules inside `if (!Schema::hasTable('program_modules'))`, so on any DB where
 * the table already existed pre-migration, `objectives`/`content` were never added even
 * though the ProgramModule model has always declared them fillable/cast. Add them here
 * idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_modules', function (Blueprint $table) {
            if (! Schema::hasColumn('program_modules', 'objectives')) {
                $table->json('objectives')->nullable();
            }
            if (! Schema::hasColumn('program_modules', 'content')) {
                $table->json('content')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('program_modules', function (Blueprint $table) {
            if (Schema::hasColumn('program_modules', 'objectives')) {
                $table->dropColumn('objectives');
            }
            if (Schema::hasColumn('program_modules', 'content')) {
                $table->dropColumn('content');
            }
        });
    }
};
