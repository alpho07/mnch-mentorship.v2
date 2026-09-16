<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Add columns that exist on production MySQL but were missing from migrations.
     */
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            if (!Schema::hasColumn('trainings', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'identifier')) {
                $table->string('identifier')->nullable()->unique();
            }
            if (!Schema::hasColumn('trainings', 'status')) {
                $table->string('status')->nullable()->default('draft');
            }
            if (!Schema::hasColumn('trainings', 'lead_type')) {
                $table->string('lead_type')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'lead_county_id')) {
                $table->unsignedBigInteger('lead_county_id')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'lead_partner_id')) {
                $table->unsignedBigInteger('lead_partner_id')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'county_id')) {
                $table->unsignedBigInteger('county_id')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'max_participants')) {
                $table->unsignedInteger('max_participants')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'registration_deadline')) {
                $table->dateTime('registration_deadline')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'target_audience')) {
                $table->text('target_audience')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'completion_criteria')) {
                $table->json('completion_criteria')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'materials_needed')) {
                $table->json('materials_needed')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'learning_outcomes')) {
                $table->json('learning_outcomes')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'prerequisites')) {
                $table->json('prerequisites')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'training_approaches')) {
                $table->json('training_approaches')->nullable();
            }
            if (!Schema::hasColumn('trainings', 'assess_participants')) {
                $table->boolean('assess_participants')->default(false);
            }
            if (!Schema::hasColumn('trainings', 'provide_materials')) {
                $table->boolean('provide_materials')->default(false);
            }
            if (!Schema::hasColumn('trainings', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $cols = [
                'description', 'identifier', 'status', 'lead_type',
                'lead_county_id', 'lead_partner_id', 'county_id',
                'max_participants', 'registration_deadline', 'target_audience',
                'completion_criteria', 'materials_needed', 'learning_outcomes',
                'prerequisites', 'training_approaches', 'assess_participants',
                'provide_materials',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('trainings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
