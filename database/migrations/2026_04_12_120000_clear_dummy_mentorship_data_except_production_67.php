<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRODUCTION_MENTORSHIP_ID = 67;

    /**
     * Remove dummy facility mentorship records while preserving mentorship 67
     * and all of its classes, modules, mentees, attendance, and related trail data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('trainings')) {
            return;
        }

        DB::transaction(function (): void {
            $mentorshipIds = DB::table('trainings')
                ->where('type', 'facility_mentorship')
                ->where('id', '!=', self::PRODUCTION_MENTORSHIP_ID)
                ->pluck('id');

            if ($mentorshipIds->isEmpty()) {
                return;
            }

            $classIds = $this->pluckIds('mentorship_classes', 'training_id', $mentorshipIds);
            $classModuleIds = $this->pluckIds('class_modules', 'mentorship_class_id', $classIds);
            $classSessionIds = $this->pluckIds('class_sessions', 'class_module_id', $classModuleIds);
            $classParticipantIds = $this->pluckIds('class_participants', 'mentorship_class_id', $classIds);
            $moduleAssessmentIds = $this->pluckIds('module_assessments', 'class_module_id', $classModuleIds);
            $menteeProgressIds = $this->pluckIds('mentee_module_progress', 'class_module_id', $classModuleIds);
            $trainingParticipantIds = $this->pluckIds('training_participants', 'training_id', $mentorshipIds);
            $classAttendanceIds = $this->pluckIds('class_attendances', 'class_id', $classIds);
            $coMentorIds = $this->pluckIds('mentorship_co_mentors', 'training_id', $mentorshipIds);
            $moduleUsageIds = $this->pluckIds('mentorship_module_usages', 'mentorship_id', $mentorshipIds);
            $moduleAssessmentResultIds = $this->pluckIds('module_assessment_results', 'module_assessment_id', $moduleAssessmentIds);

            $this->deleteActivityTrails([
                \App\Models\Training::class => $mentorshipIds,
                \App\Models\MentorshipClass::class => $classIds,
                \App\Models\ClassModule::class => $classModuleIds,
                \App\Models\ClassSession::class => $classSessionIds,
                \App\Models\ClassParticipant::class => $classParticipantIds,
                \App\Models\ClassAttendance::class => $classAttendanceIds,
                \App\Models\MenteeModuleProgress::class => $menteeProgressIds,
                \App\Models\TrainingParticipant::class => $trainingParticipantIds,
                \App\Models\MentorshipCoMentor::class => $coMentorIds,
                \App\Models\MentorshipModuleUsage::class => $moduleUsageIds,
                \App\Models\ModuleAssessment::class => $moduleAssessmentIds,
                \App\Models\ModuleAssessmentResult::class => $moduleAssessmentResultIds,
            ]);

            $this->deleteWhereIn('module_assessment_results', 'module_assessment_id', $moduleAssessmentIds);
            $this->deleteWhereIn('module_assessment_results', 'class_participant_id', $classParticipantIds);
            $this->deleteWhereIn('module_assessment_results', 'mentee_progress_id', $menteeProgressIds);

            $this->deleteWhereIn('mentee_module_progress', 'class_participant_id', $classParticipantIds);
            $this->deleteWhereIn('mentee_module_progress', 'class_module_id', $classModuleIds);

            $this->deleteWhereIn('module_assessments', 'class_module_id', $classModuleIds);

            $this->deleteWhereIn('class_attendances', 'class_id', $classIds);
            $this->deleteWhereIn('class_attendances', 'class_module_id', $classModuleIds);

            $this->deleteWhereIn('class_session_attendance', 'class_session_id', $classSessionIds);
            $this->deleteWhereIn('class_session_attendance', 'class_participant_id', $classParticipantIds);
            $this->deleteWhereIn('session_attendance', 'session_id', $classSessionIds);
            $this->deleteWhereIn('session_attendance', 'class_participant_id', $classParticipantIds);
            $this->deleteWhereIn('session_attendance', 'training_participant_id', $trainingParticipantIds);

            $this->deleteWhereIn('class_sessions', 'class_module_id', $classModuleIds);
            $this->deleteWhereIn('class_modules', 'mentorship_class_id', $classIds);
            $this->deleteWhereIn('class_participants', 'mentorship_class_id', $classIds);

            $this->deleteWhereIn('mentorship_co_mentors', 'training_id', $mentorshipIds);
            $this->deleteWhereIn('mentorship_module_usages', 'mentorship_id', $mentorshipIds);
            $this->deleteWhereIn('mentorship_module_usages', 'first_class_id', $classIds);
            $this->deleteWhereIn('mentorship_classes', 'training_id', $mentorshipIds);

            $this->deleteWhereIn('mentee_assessment_results', 'participant_id', $trainingParticipantIds);
            $this->deleteWhereIn('participant_objective_results', 'participant_id', $trainingParticipantIds);
            $this->deleteWhereIn('participant_status_logs', 'training_participant_id', $trainingParticipantIds);
            $this->deleteWhereIn('participant_status_logs', 'mentorship_participant_id', $trainingParticipantIds);

            $this->deleteWhereIn('training_sessions', 'training_id', $mentorshipIds);
            $this->deleteWhereIn('training_materials', 'training_id', $mentorshipIds);
            $this->deleteWhereIn('training_assessment_categories', 'training_id', $mentorshipIds);

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
                $this->deleteWhereIn($table, 'training_id', $mentorshipIds);
            }

            $this->deleteWhereIn('training_participants', 'training_id', $mentorshipIds);

            DB::table('trainings')
                ->whereIn('id', $mentorshipIds)
                ->where('type', 'facility_mentorship')
                ->where('id', '!=', self::PRODUCTION_MENTORSHIP_ID)
                ->delete();
        });
    }

    /**
     * This data cleanup is intentionally irreversible.
     */
    public function down(): void
    {
        //
    }

    private function pluckIds(string $table, string $column, Collection $ids): Collection
    {
        if ($ids->isEmpty() || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return collect();
        }

        return DB::table($table)
            ->whereIn($column, $ids)
            ->pluck('id');
    }

    private function deleteWhereIn(string $table, string $column, Collection $ids): void
    {
        if ($ids->isEmpty() || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->whereIn($column, $ids)
            ->delete();
    }

    /**
     * Remove activity-log trail rows for deleted mentorship records only.
     *
     * @param  array<class-string, \Illuminate\Support\Collection<int, int>>  $subjects
     */
    private function deleteActivityTrails(array $subjects): void
    {
        $table = config('activitylog.table_name', 'activity_log');

        if (
            ! Schema::hasTable($table) ||
            ! Schema::hasColumn($table, 'subject_type') ||
            ! Schema::hasColumn($table, 'subject_id')
        ) {
            return;
        }

        foreach ($subjects as $subjectType => $ids) {
            if ($ids->isEmpty()) {
                continue;
            }

            DB::table($table)
                ->where('subject_type', $subjectType)
                ->whereIn('subject_id', $ids)
                ->delete();
        }
    }
};
