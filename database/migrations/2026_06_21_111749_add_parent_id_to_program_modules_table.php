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
        Schema::table('program_modules', function (Blueprint $table) {
            if (! Schema::hasColumn('program_modules', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('program_id')
                    ->constrained('program_modules')
                    ->nullOnDelete();
                $table->index('parent_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_modules', function (Blueprint $table) {
            if (Schema::hasColumn('program_modules', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
            }
        });
    }
};
