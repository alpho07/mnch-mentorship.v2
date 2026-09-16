<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        // Add missing columns to trainings table
        if (Schema::hasTable('trainings')) {
            Schema::table('trainings', function (Blueprint $table) {
                if (!Schema::hasColumn('trainings', 'approved_training_area_id')) {
                    $col = $table->unsignedBigInteger('approved_training_area_id')->nullable();
                    // Avoid FK to non-existent table on SQLite
                    if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                        $table->foreign('approved_training_area_id')->references('id')->on('approved_training_areas')->nullOnDelete();
                    }
                }
                if (!Schema::hasColumn('trainings', 'lead_division_id')) {
                    $table->foreignId('lead_division_id')->nullable()->constrained('divisions')->nullOnDelete();
                }
                if (!Schema::hasColumn('trainings', 'location_type')) {
                    $table->enum('location_type', ['hospital', 'hotel', 'online'])->nullable();
                }
                if (!Schema::hasColumn('trainings', 'online_link')) {
                    $table->string('online_link')->nullable();
                }
            });
        }

        // Create training_counties pivot table
        if (!Schema::hasTable('training_counties')) {
            Schema::create('training_counties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
                $table->foreignId('county_id')->constrained('counties')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['training_id', 'county_id']);
            });
        }

        // Create training_partners pivot table
        if (!Schema::hasTable('training_partners')) {
            Schema::create('training_partners', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
                $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['training_id', 'partner_id']);
            });
        }

        // Create training_hospitals pivot table
        if (!Schema::hasTable('training_hospitals')) {
            Schema::create('training_hospitals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
                $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['training_id', 'facility_id']);
            });
        }

        // Create training_hotels table
        if (!Schema::hasTable('training_hotels')) {
            Schema::create('training_hotels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
                $table->string('hotel_name');
                $table->text('hotel_address')->nullable();
                $table->string('hotel_contact')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('training_hotels');
        Schema::dropIfExists('training_hospitals');
        Schema::dropIfExists('training_partners');
        Schema::dropIfExists('training_counties');

        if (Schema::hasTable('trainings')) {
            Schema::table('trainings', function (Blueprint $table) {
                if (Schema::hasColumn('trainings', 'online_link')) {
                    $table->dropColumn('online_link');
                }
                if (Schema::hasColumn('trainings', 'location_type')) {
                    $table->dropColumn('location_type');
                }
                if (Schema::hasColumn('trainings', 'lead_division_id')) {
                    $table->dropForeign(['lead_division_id']);
                    $table->dropColumn('lead_division_id');
                }
                if (Schema::hasColumn('trainings', 'approved_training_area_id')) {
                    $table->dropForeign(['approved_training_area_id']);
                    $table->dropColumn('approved_training_area_id');
                }
            });
        }
    }
};
