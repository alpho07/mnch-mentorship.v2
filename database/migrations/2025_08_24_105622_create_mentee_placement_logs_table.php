<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentee_placement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'department' or 'cadre'
            $table->string('change_type', 20)->index();

            // Department change
            $table->foreignId('old_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('new_department_id')->nullable()->constrained('departments')->nullOnDelete();

            // Cadre change
            $table->foreignId('old_cadre_id')->nullable()->constrained('cadres')->nullOnDelete();
            $table->foreignId('new_cadre_id')->nullable()->constrained('cadres')->nullOnDelete();

            $table->date('effective_date')->index();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentee_placement_logs');
    }
};
