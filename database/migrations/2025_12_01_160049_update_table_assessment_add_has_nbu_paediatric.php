<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('assessments', function (Blueprint $table) {
            $table->boolean('has_nbu')->nullable()->after('facility_id');
            $table->boolean('has_paediatric')->nullable()->after('has_nbu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['has_nbu', 'has_paediatric']);
        });
    }
};
