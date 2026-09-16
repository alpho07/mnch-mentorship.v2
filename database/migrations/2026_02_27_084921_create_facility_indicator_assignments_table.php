<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('facility_indicator_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')
                    ->unique()
                    ->constrained('facilities')
                    ->cascadeOnDelete();

            // Which report types this facility is configured to report
            $table->json('enabled_report_types')->nullable(); // array of report_type_ids
            // Once confirmed, the facility is locked to prevent accidental re-assignment
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

            // Override: super_admin can unlock and update
            $table->timestamp('last_updated_at')->nullable();
            $table->foreignId('last_updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Also add dhis2_org_unit_uid to facilities if not already present
        // Using a safe check pattern via raw statement — skip if column exists
        Schema::table('facilities', function (Blueprint $table) {
            if (!Schema::hasColumn('facilities', 'dhis2_org_unit_uid')) {
                $table->string('dhis2_org_unit_uid', 100)->nullable()->after('uid');
            }
        });
    }

    public function down(): void {
        Schema::dropIfExists('facility_indicator_assignments');

        if (Schema::hasColumn('facilities', 'dhis2_org_unit_uid')) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->dropColumn('dhis2_org_unit_uid');
            });
        }
    }
};
