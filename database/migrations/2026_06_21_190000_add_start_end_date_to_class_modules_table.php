<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_modules', function (Blueprint $table) {
            if (! Schema::hasColumn('class_modules', 'start_date')) {
                $table->date('start_date')->nullable()->after('order_sequence');
            }
            if (! Schema::hasColumn('class_modules', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_modules', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
