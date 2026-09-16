<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_attendances', function (Blueprint $table) {
            $table->unsignedBigInteger('class_module_id')
                  ->nullable()
                  ->after('session_id')
                  ->comment('Which module this attendance record applies to (for exemption detection)');

            $table->index('class_module_id', 'idx_class_attendance_module');
        });
    }

    public function down(): void
    {
        Schema::table('class_attendances', function (Blueprint $table) {
            $table->dropIndex('idx_class_attendance_module');
            $table->dropColumn('class_module_id');
        });
    }
};
