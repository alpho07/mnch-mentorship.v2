<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a question be marked "does not apply to this facility" rather than
 * left blank or answered 0 — the same distinction
 * assessment_commodity_responses already draws with its own
 * not_applicable column, and the one the indicators table's third column
 * writes to. A blank answer means "not yet collected"; 0 means "collected,
 * and the count was zero"; not_applicable means neither will ever be true
 * here, and the report prints N/A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_question_responses', function (Blueprint $table) {
            $table->boolean('not_applicable')->default(false)->after('response_value');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_question_responses', function (Blueprint $table) {
            $table->dropColumn('not_applicable');
        });
    }
};
