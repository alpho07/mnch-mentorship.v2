<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('commodity_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // AIRWAY, BREATHING, CIRCULATION, etc.
            $table->string('slug')->unique(); // airway, breathing, circulation, etc.
            $table->integer('order')->default(0);
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('commodity_categories');
    }
};
