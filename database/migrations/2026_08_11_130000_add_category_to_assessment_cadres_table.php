<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_cadres', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_cadres', 'category')) {
                $table->string('category')->nullable()->after('code')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_cadres', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
