<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * EmONC mentorships have never used start_date/end_date (the guided
     * wizard and chat assistant both hide these fields for EmONC programs
     * — see docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md §10) but the column
     * was left NOT NULL since the original create_trainings_table
     * migration, unlike mentorship_classes.start_date/end_date and
     * class_modules.start_date/end_date, which are already nullable for
     * the same reason.
     */
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
