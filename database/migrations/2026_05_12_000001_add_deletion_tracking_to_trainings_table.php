<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            if (!Schema::hasColumn('trainings', 'deleted_by')) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('deleted_at');
            }
            if (!Schema::hasColumn('trainings', 'deletion_reason')) {
                $table->text('deletion_reason')->nullable()->after('deleted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            if (Schema::hasColumn('trainings', 'deletion_reason')) {
                $table->dropColumn('deletion_reason');
            }
            if (Schema::hasColumn('trainings', 'deleted_by')) {
                $table->dropForeignIfExists(['deleted_by']);
                $table->dropColumn('deleted_by');
            }
        });
    }
};
