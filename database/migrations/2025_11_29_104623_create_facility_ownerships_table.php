<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('facility_ownerships', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Public, Private, Faith-Based, NGO
            $table->string('code')->unique(); // PUB, PRV, FBO, NGO
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_ownerships');
    }
};