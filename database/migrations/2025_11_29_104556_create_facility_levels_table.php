<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('facility_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Level 2, Level 3, Level 4, Level 5, Level 6
            $table->string('code')->unique(); // L2, L3, L4, L5, L6
            $table->integer('level_number'); // 2, 3, 4, 5, 6
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_levels');
    }
};