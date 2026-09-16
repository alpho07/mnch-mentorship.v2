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
        Schema::table('programs', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('description');
            // Roles that can still see/use the program when is_active = false.
            // super_admin always bypasses this check implicitly.
            $table->json('visible_to_roles')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'visible_to_roles']);
        });
    }
};
