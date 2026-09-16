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
        Schema::table('program_modules', function (Blueprint $table) {
            if (! Schema::hasColumn('program_modules', 'start_date')) {
                $table->date('start_date')->nullable()->after('order_sequence');
            }

            if (! Schema::hasColumn('program_modules', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_modules', function (Blueprint $table) {
            if (Schema::hasColumn('program_modules', 'start_date')) {
                $table->dropColumn('start_date');
            }

            if (Schema::hasColumn('program_modules', 'end_date')) {
                $table->dropColumn('end_date');
            }
        });
    }
};
