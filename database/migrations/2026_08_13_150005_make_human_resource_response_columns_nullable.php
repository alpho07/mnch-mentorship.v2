<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Previously default(0) + NOT NULL — a facility with a cadre that
        // genuinely doesn't get trained in a program (e.g. a maternity
        // theatre anaesthetist trained in "Type 1 Diabetes") needs a real
        // NULL to distinguish "not applicable" from "trained zero staff".
        Schema::table('human_resource_responses', function (Blueprint $table) {
            $table->integer('total_in_facility')->nullable()->default(null)->change();
            $table->integer('etat_plus')->nullable()->default(null)->change();
            $table->integer('comprehensive_newborn_care')->nullable()->default(null)->change();
            $table->integer('imnci')->nullable()->default(null)->change();
            $table->integer('type_1_diabetes')->nullable()->default(null)->change();
            $table->integer('essential_newborn_care')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('human_resource_responses', function (Blueprint $table) {
            $table->integer('total_in_facility')->nullable(false)->default(0)->change();
            $table->integer('etat_plus')->nullable(false)->default(0)->change();
            $table->integer('comprehensive_newborn_care')->nullable(false)->default(0)->change();
            $table->integer('imnci')->nullable(false)->default(0)->change();
            $table->integer('type_1_diabetes')->nullable(false)->default(0)->change();
            $table->integer('essential_newborn_care')->nullable(false)->default(0)->change();
        });
    }
};
