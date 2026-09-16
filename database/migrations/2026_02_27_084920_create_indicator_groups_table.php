<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('indicator_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_type_id')
                    ->constrained('indicator_report_types')
                    ->cascadeOnDelete();
            $table->string('code', 50)->nullable();          // 'nb_module_1', 'paed_module_4'
            $table->string('name');                          // 'Module 1: IPC'
            $table->text('description')->nullable();         // module key topics
            $table->string('dhis2_section_id', 100)->nullable(); // DHIS2 section UID
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['report_type_id', 'sort_order']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('indicator_groups');
    }
};
