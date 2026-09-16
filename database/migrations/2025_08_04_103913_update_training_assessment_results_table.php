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
        // Update mentee_assessment_results table for Pass/Fail
        Schema::table('mentee_assessment_results', function (Blueprint $table) {
            // Add result column and remove score if it exists
            if (!Schema::hasColumn('mentee_assessment_results', 'result')) {
                $table->enum('result', ['pass', 'fail'])->after('assessment_category_id');
            }
            
            // Keep score for backward compatibility, but make it nullable
            if (Schema::hasColumn('mentee_assessment_results', 'score')) {
                $table->decimal('score', 5, 2)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentee_assessment_results', function (Blueprint $table) {
            if (Schema::hasColumn('mentee_assessment_results', 'result')) {
                $table->dropColumn('result');
            }
        });        
     
    }
}; 