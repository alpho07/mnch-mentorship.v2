<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_types', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_types', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('code')
                    ->constrained('assessment_type_categories')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
