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
        Schema::table('assessment_sections', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_sections', 'assessment_type_id')) {
                $table->foreignId('assessment_type_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('assessment_types')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_type_id');
        });
    }
};
