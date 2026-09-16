<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('indicator_report_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();            // 'newborn', 'paediatric'
            $table->string('name');                          // 'Newborn Indicators'
            $table->text('description')->nullable();
            $table->string('color', 20)->default('blue');   // tailwind color for UI
            $table->string('icon', 60)->nullable();          // heroicon name
            $table->string('dhis2_dataset_id', 100)->nullable();  // DHIS2 Dataset UID
            $table->string('dhis2_org_unit_level', 50)->default('facility');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('indicator_report_types');
    }
};
