<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recreates the `partners` table, which existed in the dev/production
     * database but had no corresponding migration in the codebase (its
     * original migration appears to have been lost along with the other
     * deleted assessment/report migrations — see CLAUDE.md). Without it, a
     * fresh `migrate:fresh` breaks the training_partners FK created in
     * 2025_12_10_094741_add_hotel_and_modify_training_table.php.
     */
    public function up(): void
    {
        if (! Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('type', ['ngo', 'private', 'international', 'faith_based', 'academic', 'development', 'other'])->default('other');
                $table->string('contact_person')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 20)->nullable();
                $table->text('address')->nullable();
                $table->string('website')->nullable();
                $table->string('registration_number', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['name', 'is_active']);
                $table->index('type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
