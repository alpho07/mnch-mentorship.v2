<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            $table->string('group_label')->nullable()->after('name');
            $table->unsignedTinyInteger('indent_level')->default(0)->after('group_label');
        });
    }

    public function down(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            $table->dropColumn(['group_label', 'indent_level']);
        });
    }
};
