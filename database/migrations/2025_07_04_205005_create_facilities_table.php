<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('subcounty_id');
            $table->unsignedBigInteger('facility_type_id');
            $table->boolean('is_hub')->default(false);
            $table->string('mfl_code')->nullable();
            $table->string('lat')->nullable();
            $table->string('long')->nullable();
            $table->unsignedBigInteger('hub_id')->nullable(); // If spoke, FK to hub facility
            $table->boolean('is_central_store')->default(false);
            $table->string('storage_capacity')->nullable();
            $table->json('operating_hours')->nullable();
            $table->text('storage_conditions')->nullable();
            $table->text('distribution_notes')->nullable();
            $table->timestamps();

            $table->foreign('subcounty_id')->references('id')->on('subcounties')->cascadeOnDelete();
            $table->foreign('facility_type_id')->references('id')->on('facility_types')->restrictOnDelete();
            $table->foreign('hub_id')->references('id')->on('facilities')->nullOnDelete();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
