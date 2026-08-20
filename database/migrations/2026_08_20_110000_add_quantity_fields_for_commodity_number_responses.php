<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            // Marks commodities whose label promises a follow-up number
            // when the answer is Yes (e.g. "Functional Infusion Pumps. If
            // yes indicate number") — previously nothing captured that
            // number anywhere, form or database, despite the label.
            $table->boolean('requires_quantity')->default(false)->after('indent_level');
        });

        Schema::table('assessment_commodity_responses', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->after('available');
        });
    }

    public function down(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            $table->dropColumn('requires_quantity');
        });

        Schema::table('assessment_commodity_responses', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
