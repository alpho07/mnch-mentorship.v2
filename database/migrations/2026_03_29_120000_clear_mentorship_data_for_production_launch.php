<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove all facility mentorship transactional data so production can start clean.
     *
     * This intentionally preserves reference/master data such as users, facilities,
     * programs, modules, departments, cadres, and assessment catalogs.
     */
    public function up(): void
    {
        if (! Schema::hasTable('trainings')) {
            return;
        }

        DB::transaction(function (): void {
            $mentorshipIds = DB::table('trainings')
                ->where('type', 'facility_mentorship')
                ->where('id', '!=', 67)
                ->pluck('id');

            if ($mentorshipIds->isEmpty()) {
                return;
            }

            $classIds = Schema::hasTable('mentorship_classes')
                ? DB::table('mentorship_classes')
                    ->whereIn('training_id', $mentorshipIds)
                    ->pluck('id')
                : collect();

            $classModuleIds = Schema::hasTable('class_modules')
                ? DB::table('class_modules')
                    ->whereIn('mentorship_class_id', $classIds)
                    ->pluck('id')
                : collect();

            $classParticipantIds = Schema::hasTable('class_participants')
                ? DB::table('class_participants')
                    ->whereIn('mentorship_class_id', $classIds)
                    ->pluck('id')
                : collect();

            $moduleAssessmentIds = Schema::hasTable('module_assessments')
                ? DB::table('module_assessments')
                    ->whereIn('class_module_id', $classModuleIds)
                    ->pluck('id')
                : collect();

            $trainingParticipantIds = Schema::hasTable('training_participants')
                ? DB::table('training_participants')
                    ->whereIn('training_id', $mentorshipIds)
                    ->pluck('id')
                : collect();

            if (Schema::hasTable('module_assessment_results') && $moduleAssessmentIds->isNotEmpty()) {
                DB::table('module_assessment_results')
                    ->whereIn('module_assessment_id', $moduleAssessmentIds)
                    ->delete();
            }

            if (Schema::hasTable('mentee_module_progress') && $classParticipantIds->isNotEmpty()) {
                DB::table('mentee_module_progress')
                    ->whereIn('class_participant_id', $classParticipantIds)
                    ->delete();
            }

            if (Schema::hasTable('module_assessments') && $classModuleIds->isNotEmpty()) {
                DB::table('module_assessments')
                    ->whereIn('class_module_id', $classModuleIds)
                    ->delete();
            }

            if (Schema::hasTable('class_attendances') && $classIds->isNotEmpty()) {
                DB::table('class_attendances')
                    ->whereIn('class_id', $classIds)
                    ->delete();
            }

            if (Schema::hasTable('session_attendances') && $classParticipantIds->isNotEmpty()) {
                DB::table('session_attendances')
                    ->whereIn('class_participant_id', $classParticipantIds)
                    ->delete();
            }

            if (Schema::hasTable('class_sessions') && $classModuleIds->isNotEmpty()) {
                DB::table('class_sessions')
                    ->whereIn('class_module_id', $classModuleIds)
                    ->delete();
            }

            if (Schema::hasTable('class_modules') && $classIds->isNotEmpty()) {
                DB::table('class_modules')
                    ->whereIn('mentorship_class_id', $classIds)
                    ->delete();
            }

            if (Schema::hasTable('class_participants') && $classIds->isNotEmpty()) {
                DB::table('class_participants')
                    ->whereIn('mentorship_class_id', $classIds)
                    ->delete();
            }

            if (Schema::hasTable('mentorship_co_mentors')) {
                DB::table('mentorship_co_mentors')
                    ->whereIn('training_id', $mentorshipIds)
                    ->delete();
            }

            if (Schema::hasTable('mentorship_module_usages')) {
                DB::table('mentorship_module_usages')
                    ->whereIn('mentorship_id', $mentorshipIds)
                    ->delete();
            }

            if (Schema::hasTable('mentorship_classes')) {
                DB::table('mentorship_classes')
                    ->whereIn('training_id', $mentorshipIds)
                    ->delete();
            }

            if (Schema::hasTable('mentee_assessment_results') && $trainingParticipantIds->isNotEmpty()) {
                DB::table('mentee_assessment_results')
                    ->whereIn('participant_id', $trainingParticipantIds)
                    ->delete();
            }

            if (Schema::hasTable('participant_objective_results') && $trainingParticipantIds->isNotEmpty()) {
                DB::table('participant_objective_results')
                    ->whereIn('participant_id', $trainingParticipantIds)
                    ->delete();
            }

            if (Schema::hasTable('participant_status_logs') && $trainingParticipantIds->isNotEmpty()) {
                DB::table('participant_status_logs')
                    ->whereIn('training_participant_id', $trainingParticipantIds)
                    ->orWhereIn('mentorship_participant_id', $trainingParticipantIds)
                    ->delete();
            }

            if (Schema::hasTable('session_attendances') && $trainingParticipantIds->isNotEmpty()) {
                DB::table('session_attendances')
                    ->whereIn('training_participant_id', $trainingParticipantIds)
                    ->delete();
            }

            if (Schema::hasTable('training_sessions')) {
                DB::table('training_sessions')
                    ->whereIn('training_id', $mentorshipIds)
                    ->delete();
            }

            if (Schema::hasTable('training_materials')) {
                DB::table('training_materials')
                    ->whereIn('training_id', $mentorshipIds)
                    ->delete();
            }

            if (Schema::hasTable('training_assessment_categories')) {
                DB::table('training_assessment_categories')
                    ->whereIn('training_id', $mentorshipIds)
                    ->delete();
            }

            foreach ([
                'training_departments',
                'training_programs',
                'training_modules',
                'training_methodologies',
                'training_target_facilities',
                'training_locations',
                'training_counties',
                'training_partners',
                'training_hospitals',
                'training_hotels',
            ] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)
                        ->whereIn('training_id', $mentorshipIds)
                        ->delete();
                }
            }

            if (Schema::hasTable('training_participants')) {
                DB::table('training_participants')
                    ->whereIn('training_id', $mentorshipIds)
                    ->delete();
            }

            DB::table('trainings')
                ->whereIn('id', $mentorshipIds)
                ->delete();
        });
    }

    /**
     * This cleanup is intentionally irreversible.
     */
    public function down(): void
    {
        //
    }
};
