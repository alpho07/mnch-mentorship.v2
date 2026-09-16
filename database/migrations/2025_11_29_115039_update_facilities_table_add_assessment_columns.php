<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up() {
        Schema::table('facilities', function (Blueprint $table) {
            // Add UID if it doesn't exist
            if (!Schema::hasColumn('facilities', 'uid')) {
                $table->string('uid')->unique()->nullable()->after('name');
            }

            // Add Ward (Row 8)
            if (!Schema::hasColumn('facilities', 'ward')) {
                $table->string('ward')->nullable()->after('subcounty_id');
            }

            // Add Facility Level (Row 9) - Foreign Key
            if (!Schema::hasColumn('facilities', 'facility_level_id')) {
                $table->foreignId('facility_level_id')
                        ->nullable()
                        ->after('ward')
                        ->constrained('facility_levels')
                        ->restrictOnDelete();
            }

            // Add Facility Ownership (Row 11) - Foreign Key
            if (!Schema::hasColumn('facilities', 'facility_ownership_id')) {
                $table->foreignId('facility_ownership_id')
                        ->nullable()
                        ->after('facility_type_id')
                        ->constrained('facility_ownerships')
                        ->restrictOnDelete();
            }


            // Add Physical Address (Row 14)
            if (!Schema::hasColumn('facilities', 'physical_address')) {
                $table->text('physical_address')->nullable()->after('facility_ownership_id');
            }

            // Add Postal Address (Row 15)
            if (!Schema::hasColumn('facilities', 'postal_address')) {
                $table->string('postal_address')->nullable()->after('physical_address');
            }

            // Add Telephone (Row 16)
            if (!Schema::hasColumn('facilities', 'telephone')) {
                $table->string('telephone')->nullable()->after('postal_address');
            }

            // Add Email (Row 17)
            if (!Schema::hasColumn('facilities', 'email')) {
                $table->string('email')->nullable()->after('telephone');
            }

            // Add In-charge Name (Row 18)
            if (!Schema::hasColumn('facilities', 'incharge_name')) {
                $table->string('incharge_name')->nullable()->after('email');
            }

            // Add In-charge Designation (Row 19)
            if (!Schema::hasColumn('facilities', 'incharge_designation')) {
                $table->string('incharge_designation')->nullable()->after('incharge_name');
            }

            // Add In-charge Contact (Row 20)
            if (!Schema::hasColumn('facilities', 'incharge_contact')) {
                $table->string('incharge_contact')->nullable()->after('incharge_designation');
            }

            // Add Notes field
            if (!Schema::hasColumn('facilities', 'notes')) {
                $table->text('notes')->nullable()->after('operating_hours');
            }

            // Add soft deletes if not exists
            if (!Schema::hasColumn('facilities', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }

            // Add indexes for better query performance
            //$table->index('mfl_code');
            //$table->index('facility_level_id');
            //$table->index('facility_ownership_id');
            //$table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('facilities', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['mfl_code']);
            $table->dropIndex(['facility_level_id']);
            $table->dropIndex(['facility_ownership_id']);
            $table->dropIndex(['latitude', 'longitude']);

            // Drop new columns
            if (Schema::hasColumn('facilities', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('facilities', 'notes')) {
                $table->dropColumn('notes');
            }

            if (Schema::hasColumn('facilities', 'incharge_contact')) {
                $table->dropColumn('incharge_contact');
            }

            if (Schema::hasColumn('facilities', 'incharge_designation')) {
                $table->dropColumn('incharge_designation');
            }

            if (Schema::hasColumn('facilities', 'incharge_name')) {
                $table->dropColumn('incharge_name');
            }

            if (Schema::hasColumn('facilities', 'email')) {
                $table->dropColumn('email');
            }

            if (Schema::hasColumn('facilities', 'telephone')) {
                $table->dropColumn('telephone');
            }

            if (Schema::hasColumn('facilities', 'postal_address')) {
                $table->dropColumn('postal_address');
            }

            if (Schema::hasColumn('facilities', 'physical_address')) {
                $table->dropColumn('physical_address');
            }

            if (Schema::hasColumn('facilities', 'facility_ownership_id')) {
                $table->dropForeign(['facility_ownership_id']);
                $table->dropColumn('facility_ownership_id');
            }

            if (Schema::hasColumn('facilities', 'facility_level_id')) {
                $table->dropForeign(['facility_level_id']);
                $table->dropColumn('facility_level_id');
            }

            if (Schema::hasColumn('facilities', 'ward')) {
                $table->dropColumn('ward');
            }

            if (Schema::hasColumn('facilities', 'uid')) {
                $table->dropColumn('uid');
            }

            // Rename back latitude/longitude to lat/long if needed
            if (Schema::hasColumn('facilities', 'latitude')) {
                $table->renameColumn('latitude', 'lat');
            }

            if (Schema::hasColumn('facilities', 'longitude')) {
                $table->renameColumn('longitude', 'long');
            }
        });
    }
};
